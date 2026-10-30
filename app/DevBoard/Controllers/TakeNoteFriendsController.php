<?php

namespace App\DevBoard\Controllers;

use App\DevBoard\Mail\TakeNoteInvite;
use App\DevBoard\Middleware\TakeNoteLocale;
use App\DevBoard\Models\BoardMember;
use App\DevBoard\Models\Notebook;
use App\DevBoard\Support\Notebooks;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Friends: the owner invites people by email, and each one gets a notebook of
 * their own — empty, private, theirs. Nobody invited ever sees the owner's
 * notes, and the owner never sees theirs (Support\Notebooks scopes every
 * query). Only the main notebook's people may invite; everyone else gets the
 * same 404 as a stranger.
 *
 * An answer never says whether an email already has a TAKEONE account: an
 * invitation is "waiting" until a verified account with that email opens
 * TakeNote, whoever it is. The invited address gets an email (Mail\TakeNoteInvite).
 *
 * Also here: the language TakeNote is read in on this device.
 */
class TakeNoteFriendsController extends Controller
{
    private const MAX_FRIENDS = 100;

    public function index(Request $request): JsonResponse
    {
        abort_unless(Notebooks::canInvite($request->user()), 404);

        return response()->json(['success' => true, 'friends' => $this->list()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Notebooks::canInvite($request->user()), 404);
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:190']]);
        $email = Notebooks::email($data['email']);

        // someone who already uses the owner's notebook keeps it — an own notebook would take its place
        $account = User::whereRaw('lower(email) = ?', [$email])->first();
        if ($email === Notebooks::email((string) $request->user()->email)
            || ($account && ($account->isSuperAdmin() || BoardMember::where('user_id', $account->id)->exists() || Notebooks::main()?->owner_id === $account->id))) {
            return response()->json(['success' => false, 'message' => __('devboard::messages.fr_has_access')], 422);
        }

        $book = Notebook::where('is_main', false)
            ->where(fn ($q) => $q->where('email', $email)->when($account, fn ($q) => $q->orWhere('owner_id', $account->id)))
            ->first();
        if ($book && ! $book->revoked_at) {
            return response()->json(['success' => false, 'message' => __('devboard::messages.fr_already')], 422);
        }
        // the notebook waiting for them, the email and the notice: one place, shared with folder sharing
        if (! \App\DevBoard\Support\FriendInvites::open($email, $request->user(), $account)) {
            return response()->json(['success' => false, 'message' => __('devboard::messages.fr_too_many')], 422);
        }

        return response()->json(['success' => true, 'message' => __('devboard::messages.fr_sent'), 'friends' => $this->list()]);
    }

    /**
     * An invite LINK: no email needed — whoever opens it first gets a notebook of their own and goes
     * straight in (TakeNoteJoinController). The link is shown once; only its SHA-256 is kept.
     */
    public function link(Request $request): JsonResponse
    {
        abort_unless(Notebooks::canInvite($request->user()), 404);
        $data = $request->validate(['label' => ['nullable', 'string', 'max:80']]);
        if (Notebook::where('is_main', false)->count() >= self::MAX_FRIENDS) {
            return response()->json(['success' => false, 'message' => __('devboard::messages.fr_too_many')], 422);
        }

        $token = Str::random(40);
        $book = Notebook::create(['invited_by' => $request->user()->id, 'label' => trim((string) ($data['label'] ?? '')) ?: null]);
        $book->forceFill(['invite_hash' => Notebooks::linkHash($token)])->save();

        return response()->json([
            'success' => true,
            'message' => __('devboard::messages.lk_made'),
            'link' => route('takenote.join', $token),
            'friends' => $this->list(),
        ]);
    }

    /** Take a friend's TakeNote away. Their notes are kept — inviting them again gives it back. */
    public function destroy(Request $request, Notebook $notebook): JsonResponse
    {
        abort_unless(Notebooks::canInvite($request->user()), 404);
        abort_if($notebook->is_main, 404);
        $notebook->forceFill(['revoked_at' => now()])->save();

        return response()->json(['success' => true, 'message' => __('devboard::messages.fr_removed'), 'friends' => $this->list()]);
    }

    /** The language TakeNote is read in on this device (a year-long cookie; the page reloads in it). */
    public function locale(Request $request): JsonResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(config('devboard.locales', [])))]]);

        return response()->json(['success' => true])
            ->cookie(TakeNoteLocale::COOKIE, $data['locale'], 60 * 24 * 365);
    }

    private function list(): array
    {
        return Notebook::with('owner:id,full_name')->where('is_main', false)->whereNull('revoked_at')->latest()->get()
            ->map(fn (Notebook $b) => [
                'uuid' => $b->uuid,
                'email' => $b->email,
                'label' => $b->label,
                'link' => ! $b->email, // invited by a link rather than an email
                'name' => $b->owner ? $this->first($b->owner) : null,
                'joined' => (bool) $b->owner_id,
                'since' => ($b->joined_at ?? $b->created_at)?->toIso8601String(),
            ])->values()->all();
    }

    private function first(User $u): string
    {
        return trim(explode(' ', trim((string) $u->full_name))[0] ?? '') ?: (string) $u->full_name;
    }
}
