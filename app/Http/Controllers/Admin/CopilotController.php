<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiManager;
use App\Services\ClubCreationService;
use App\Services\Copilot\CopilotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * HTTP surface for the Copilot ("Coach") assistant.
 *
 * The route group already enforces auth + `role:super-admin`, so both endpoints
 * act as a super-admin (who can already create any club). The Ollama server is
 * reached only via CopilotService (server-side); the browser never sees it.
 */
class CopilotController extends Controller
{
    /** Speech → text: transcribe a recorded clip so the user can talk to Coach. */
    public function stt(Request $request, AiManager $ai)
    {
        $request->validate(['audio' => ['required', 'file', 'max:20480']]); // 20 MB

        $file = $request->file('audio');
        $mime = $file->getMimeType() ?: 'audio/webm';

        // Only accept real audio (MediaRecorder emits audio/webm|ogg|mp4; Chrome
        // may label webm as video/webm). Deny anything else.
        $allowed = ['audio/webm', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/mpeg', 'audio/x-m4a', 'audio/aac', 'audio/flac', 'video/webm'];
        if (! in_array($mime, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported audio format.'], 422);
        }
        if ($mime === 'video/webm') {
            $mime = 'audio/webm';
        }

        try {
            $text = $ai->stt()->transcribe(base64_encode(file_get_contents($file->getRealPath())), $mime);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'text' => $text]);
    }

    /** Text → speech: return spoken audio (WAV) of Coach's reply. */
    public function tts(Request $request, AiManager $ai)
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:5000']]);

        try {
            $audio = $ai->tts()->synthesize($data['text']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response($audio['data'], 200)
            ->header('Content-Type', $audio['mime'])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * One assistant turn. Returns the reply and, when the model has gathered
     * enough, a signed draft `proposal` + `token` for the confirm step.
     */
    public function message(Request $request, CopilotService $copilot)
    {
        $validated = $request->validate([
            'context' => 'nullable|string|in:create_club',
            'messages' => 'array',
            'messages.*.role' => 'required|string|in:user,assistant',
            'messages.*.content' => 'required|string',
        ]);

        if (! config('copilot.enabled', true)) {
            return response()->json(['reply' => 'The assistant is currently turned off.']);
        }

        try {
            $result = $copilot->reply(
                $request->user(),
                $validated['context'] ?? 'create_club',
                $validated['messages'] ?? [],
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'reply' => "I couldn't reach the AI service just now. Please try again in a moment.",
            ]);
        }

        return response()->json($result);
    }

    /**
     * Commit a confirmed draft. The proposal is decrypted (tamper-evident),
     * re-validated, given a fresh unique slug, and the owner is FORCED to the
     * acting super-admin — the client cannot smuggle in a different owner.
     */
    public function apply(Request $request, ClubCreationService $clubs)
    {
        $request->validate(['token' => 'required|string']);

        try {
            $proposal = json_decode(Crypt::decryptString($request->string('token')), true);
        } catch (\Throwable) {
            return response()->json(['success' => false, 'message' => 'This proposal is no longer valid. Please ask Coach to draft it again.'], 422);
        }

        if (! is_array($proposal)) {
            return response()->json(['success' => false, 'message' => 'Invalid proposal.'], 422);
        }

        $validator = Validator::make($proposal, [
            'club_name' => 'required|string|max:255',
            'country' => 'nullable|string|max:2',
            'currency' => 'nullable|string|max:3',
            'description' => 'nullable|string|max:1000',
            'slogan' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'registration_requirements' => 'nullable|string|max:20000',
            'registration_terms' => 'nullable|string|max:20000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'The proposal could not be validated.'], 422);
        }

        // Tenant mutators run HtmlSanitizer::clean() on the registration_* HTML on save.
        $data = $validator->validated();
        // Keep the slug the user reviewed if it's still free; otherwise regenerate.
        $reviewedSlug = is_string($proposal['slug'] ?? null) ? $proposal['slug'] : null;
        $data['slug'] = ($reviewedSlug && ! \App\Models\Tenant::where('slug', $reviewedSlug)->exists())
            ? $reviewedSlug
            : $this->uniqueSlug($data['club_name']);
        $data['country'] = strtoupper($data['country'] ?? 'BH');
        $data['currency'] = strtoupper($data['currency'] ?? 'BHD');
        $data['status'] = 'active';
        $data['public_profile_enabled'] = true;
        // Never trust a client/model-supplied owner — force the acting user.
        $data['owner_user_id'] = $request->user()->id;

        try {
            $club = $clubs->create($data);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to create the club. Please try again.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Club created.',
            'club' => [
                'id' => $club->id,
                'slug' => $club->slug,
                'club_name' => $club->club_name,
                'dashboard_url' => route('admin.club.dashboard', $club->slug),
            ],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'club-'.Str::lower(Str::random(6));
        }
        $slug = $base;
        $i = 2;

        while (\App\Models\Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
