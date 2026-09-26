<?php

namespace App\Federations\Controllers;

use App\Federations\Models\Federation;
use App\Federations\Services\FederationBilling;
use App\Federations\Services\FederationDirectory;
use App\Federations\Services\FederationDesk;
use App\Federations\Services\FederationDraws;
use App\Federations\Services\FederationMessages;
use App\Federations\Services\FederationStrings;
use App\Federations\Services\FederationEvents;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationEventDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The dashboard's writes. The designed page saves whole lists to what it
 * thinks is its own storage; takeone-bridge.js forwards those saves here, and
 * FederationDirectory decides what of each actually happens. Every answer
 * carries the server's copy, which the page redraws from.
 */
class FederationApiController extends Controller
{
    public function __construct(private FederationDirectory $directory) {}

    public function orgs(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['orgs' => ['present', 'array', 'max:500']]);

        return response()->json([
            'success' => true,
            'orgs' => $this->directory->syncOrgs($request->user(), $access, $data['orgs']),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['users' => ['present', 'array', 'max:500']]);
        $result = $this->directory->syncUsers($request->user(), $access, $data['users']);

        return response()->json(['success' => true, 'users' => $result['users'], 'message' => $result['message']]);
    }

    public function region(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'region' => ['required', 'array'],
            'region.country' => ['nullable', 'string', 'max:3'],
            'region.tz' => ['nullable', 'string', 'max:64'],
            'region.cur' => ['nullable', 'string', 'size:3'],
        ]);

        return response()->json([
            'success' => true,
            'region' => $this->directory->syncRegion($access, $data['region']),
        ]);
    }

    public function billing(Request $request, FederationBilling $billing): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'account' => ['required', 'uuid'],
            'billing' => ['required', 'array'],
            'billing.cycle' => ['nullable', 'in:monthly,annual'],
            'billing.invoices' => ['nullable', 'array', 'max:500'],
        ]);

        return response()->json([
            'success' => true,
            'billing' => $billing->sync($request->user(), $access, $data['account'], (array) $request->input('billing')),
        ]);
    }

    public function event(Request $request, FederationEvents $events): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'event' => ['required', 'array'],
            'event.id' => ['required', 'uuid'],
            'orgId' => ['nullable', 'uuid'],
        ]);
        // validate() keeps only the nested keys that carry a rule; the event
        // is a whole form, so the service reads — and cleans — all of it.
        $saved = $events->save($request->user(), $access, (array) $request->input('event'), $data['orgId'] ?? null);

        return $saved
            ? response()->json(['success' => true, 'event' => $saved])
            : response()->json(['success' => false, 'message' => 'This event could not be saved.'], 422);
    }

    public function eventDelete(Request $request, FederationEvents $events): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['event' => ['required', 'uuid']]);

        return $events->delete($request->user(), $access, $data['event'])
            ? response()->json(['success' => true, 'message' => 'Event deleted.', 'event' => ['id' => $data['event']]])
            : response()->json(['success' => false, 'message' => 'This event could not be deleted.'], 422);
    }

    public function upload(Request $request, FederationEvents $events): JsonResponse
    {
        $access = $this->access($request);
        $request->validate(['file' => ['required', 'file', 'max:10240']]);
        $token = $events->stage($request->user(), $access, $request->file('file'));

        return $token
            ? response()->json(['success' => true, 'token' => $token])
            : response()->json(['success' => false, 'message' => 'Only PDF, Word, Excel and image files can be attached.'], 422);
    }

    public function entryConfirm(Request $request, FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['entry' => ['required', 'uuid']]);
        $ok = $desk->confirmEntryPayment($request->user(), $access, $data['entry']);

        return response()->json(['success' => $ok, 'entriesByEvent' => $desk->store($access)['entriesByEvent']], $ok ? 200 : 422);
    }

    public function transferReply(Request $request, FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['transfer' => ['required', 'uuid'], 'released' => ['required', 'boolean']]);
        $ok = $desk->transferReply($access, $data['transfer'], (bool) $data['released']);

        return response()->json(['success' => $ok] + ($ok ? ['transfers' => $desk->store($access)['transfers']] : []), $ok ? 200 : 422);
    }

    public function transferDecide(Request $request, FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['transfer' => ['required', 'uuid'], 'approve' => ['required', 'boolean']]);
        $ok = $desk->decideTransfer($request->user(), $access, $data['transfer'], (bool) $data['approve']);
        $store = $ok ? $desk->store($access) : [];

        return response()->json(['success' => $ok] + ($ok ? ['transfers' => $store['transfers'], 'athletes' => $store['athletes'], 'clubs' => $store['clubs']] : []), $ok ? 200 : 422);
    }

    public function regsApprove(Request $request, FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['entry' => ['required', 'uuid'], 'athlete' => ['required', 'string', 'max:16']]);
        $ok = $desk->approveReg($access, $data['entry'], $data['athlete']);

        return response()->json(['success' => $ok, 'regs' => $desk->regs($access)], $ok ? 200 : 422);
    }

    public function regsWithdraw(Request $request, FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['athletes' => ['required', 'array', 'max:1000']]);
        $ok = $desk->withdrawRegs($access, $data['athletes']);

        return response()->json(['success' => $ok, 'regs' => $desk->regs($access)], $ok ? 200 : 403);
    }

    /** The chat dock: every thread in scope (read), or write to one club (send). */
    public function messages(Request $request, FederationMessages $messages): JsonResponse
    {
        $access = $this->access($request);
        if ($request->isMethod('get')) {
            return response()->json(['success' => true, 'messages' => $messages->threads($access), 'clubs' => $messages->clubMap($access)]);
        }
        $data = $request->validate([
            'club' => ['required', 'string', 'regex:/^fc-[0-9a-f-]{36}$/'],
            'text' => ['required', 'string', 'max:5000'],
        ]);
        $threads = $messages->send($request->user(), $access, $data['club'], trim($data['text']));

        return $threads
            ? response()->json(['success' => true, 'messages' => $threads])
            : response()->json(['success' => false, 'message' => 'This club cannot receive messages.', 'messages' => $messages->threads($access)], 422);
    }

    /** Settings → Translations: the whole strings table, as the page saves it. */
    public function strings(Request $request, FederationStrings $strings): JsonResponse
    {
        $access = $this->access($request);
        $request->validate(['strings' => ['present', 'array', 'max:'.FederationStrings::MAX_ROWS]]);

        return response()->json(['success' => true, 'strings' => $strings->sync($request->user(), $access, (array) $request->input('strings'))]);
    }

    /** Record or clear one bout on the Draw tab. Answers with the division as kept. */
    public function draw(Request $request, FederationDraws $draws): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'event' => ['required', 'uuid'],
            'division' => ['required', 'string', 'max:191'],
            'match' => ['required', 'string', 'regex:/^\d{1,2}-\d{1,3}$/'],
            'winner' => ['nullable', 'string', 'max:16'],
            'by' => ['nullable', 'required_with:winner', 'in:'.implode(',', FederationDraws::METHODS)],
            'slots' => ['present', 'array', 'max:512'],
            'slots.*' => ['nullable', 'string', 'max:16'],
        ]);
        $out = $draws->record($request->user(), $access, $data['event'], $data['division'], $data['match'],
            $data['winner'] ?? null, isset($data['winner']) ? $data['by'] : null, array_values($data['slots']));

        return response()->json(['success' => $out['ok'], 'division' => $out['division']], $out['ok'] ? 200 : 422);
    }

    /** An event's logo or artwork — public: it is the poster clubs are invited with. */
    public function eventMedia(FederationEvent $event, string $kind): Response
    {
        abort_unless(config('federations.enabled'), 404);
        $path = match ($kind) {
            'logo' => $event->logo,
            'art-p' => $event->art_portrait,
            'art-l' => $event->art_landscape,
            default => null,
        };
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The QR code for an event's club invitation — public, like the link it
     * encodes (the invitation page is what a club opens without signing in).
     *
     * The address inside it is built HERE from the event's own uuid, never
     * taken from the request: this can only ever draw a TakeOne invitation,
     * so it is not an open QR service anyone could point anywhere. The per-club
     * personal links carry a secret token and are deliberately not offered.
     */
    public function inviteQr(Request $request, FederationEvent $event, string $format): Response
    {
        abort_unless(config('federations.enabled'), 404);
        $url = route('federations.app.invitation').'?event='.rawurlencode($event->uuid);

        $headers = ['Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="invitation-qr-'.(Str::slug((string) $event->name) ?: 'event').'.'.$format.'"';
        }

        if ($format === 'png') {
            $png = (new \BaconQrCode\Writer(new \BaconQrCode\Renderer\GDLibRenderer(1024, 4)))->writeString($url);

            return response($png, 200, ['Content-Type' => 'image/png'] + $headers);
        }

        return response(\App\Support\Qr::svg($url, 512, 2), 200, ['Content-Type' => 'image/svg+xml; charset=utf-8'] + $headers);
    }

    /**
     * A document the organiser attached for clubs to read. Public by design —
     * the invitation page lists it before any club has signed in — reached
     * only by its own uuid, never by a path.
     */
    public function eventDocument(string $document): Response
    {
        abort_unless(config('federations.enabled'), 404);
        $doc = FederationEventDocument::where('uuid', $document)->first();
        abort_unless($doc && $doc->event && Storage::disk('local')->exists($doc->path), 404);

        $ext = pathinfo($doc->path, PATHINFO_EXTENSION);
        $name = (Str::slug($doc->title) ?: 'document').'.'.$ext;

        return Storage::disk('local')->download($doc->path, $name, [
            'Content-Type' => $doc->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * A federation's logo. Public on purpose: it is the body's own branding,
     * printed on the invitation clubs open without signing in.
     */
    public function logo(Federation $federation): Response
    {
        abort_unless(config('federations.enabled') && $federation->isApproved() && $federation->logo, 404);
        abort_unless(Storage::disk('public')->exists($federation->logo), 404);

        return Storage::disk('public')->response($federation->logo, null, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{root: Federation, manager: bool, sports: array, role: string} */
    /** The Statistics page's figures — read-only, the viewer's own scope (FederationStatistics). */
    public function statistics(Request $request, \App\Federations\Services\FederationStatistics $statistics): JsonResponse
    {
        $access = $this->access($request);

        return response()->json(['success' => true, 'stats' => $statistics->payload($access)], 200, ['Cache-Control' => 'no-store, private']);
    }

    /** Clubs page → "Add club": existing platform clubs, by club name or owner name, phone or email. */
    public function clubSearch(Request $request, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($picker->mayAdd($access), 403);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);

        return response()->json(['success' => true, 'clubs' => $picker->search($access, $data['q'])], 200, ['Cache-Control' => 'no-store, private']);
    }

    /** Affiliate an existing club with the viewer's federation; answers with the refreshed club list. */
    public function clubAdd(Request $request, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['club' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9\-]+$/']]);
        $ok = $picker->add($request->user(), $access, $data['club']);

        return $ok
            ? response()->json(['success' => true, 'message' => 'Club added.'] + $picker->clubs($access))
            : response()->json(['success' => false, 'message' => 'That club could not be added.'], 422);
    }

    /** The club list again — what a `clubs` refresh signal re-fetches. */
    public function clubList(Request $request, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        return response()->json(['success' => true] + $picker->clubs($this->access($request)), 200, ['Cache-Control' => 'no-store, private']);
    }

    private function access(Request $request): array
    {
        abort_unless(config('federations.enabled'), 404);
        $access = $this->directory->access($request->user());
        abort_unless($access, 404);

        return $access;
    }
}
