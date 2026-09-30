<?php

namespace App\Federations\Controllers;

use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationDirectory;
use App\Federations\Services\FederationInvitation;
use App\Federations\Services\FederationRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The club invitation's own doors. Public (a club opens the link without a
 * platform account), so every write is bound to the club this SESSION proved
 * — never to an id the page sends — and every door is throttled.
 */
class FederationInviteController extends Controller
{
    public function __construct(private FederationInvitation $invitation) {}

    private function event(string $event): FederationEvent
    {
        abort_unless(config('federations.enabled'), 404);
        $e = $this->invitation->event($event);
        abort_unless($e, 404);

        return $e;
    }

    public function code(Request $request, string $event): JsonResponse
    {
        $e = $this->event($event);
        // Either a contact typed by hand, or a club picked from "Find your club".
        $data = $request->validate([
            'contact' => ['required_without:club', 'nullable', 'string', 'max:191'],
            'club' => ['required_without:contact', 'nullable', 'string', 'regex:/^(fc|fa)-[0-9a-f-]{36}$/'],
        ]);
        if (! empty($data['club'])) {
            $this->invitation->sendCodeToClub($e, $data['club']);
        } else {
            $this->invitation->sendCode($e, $data['contact']);
        }

        // The same answer whether or not the contact belongs to a club.
        return response()->json(['success' => true]);
    }

    public function verify(Request $request, string $event): JsonResponse
    {
        $e = $this->event($event);
        $data = $request->validate([
            'contact' => ['required_without:club', 'nullable', 'string', 'max:191'],
            'club' => ['required_without:contact', 'nullable', 'string', 'regex:/^(fc|fa)-[0-9a-f-]{36}$/'],
            'code' => ['required', 'string', 'max:12'],
        ]);

        $r = ! empty($data['club'])
            ? $this->invitation->verify($e, $data['club'], $data['code'], true)
            : $this->invitation->verify($e, $data['contact'], $data['code']);
        if (! $r['ok']) {
            return response()->json(['success' => false], 422);
        }
        // One owner, several clubs: they choose (choose()) before anything is shown.
        if (! empty($r['choices'])) {
            return response()->json(['success' => true, 'choices' => $r['choices']]);
        }

        return response()->json(['success' => true, 'store' => $this->invitation->store($e)]);
    }

    /** After a code that covered several clubs: the one the owner picked. */
    public function choose(Request $request, string $event): JsonResponse
    {
        $e = $this->event($event);
        $data = $request->validate(['club' => ['required', 'string', 'regex:/^(fc|fa)-[0-9a-f-]{36}$/']]);

        return $this->invitation->choose($e, $data['club'])
            ? response()->json(['success' => true, 'store' => $this->invitation->store($e)])
            : response()->json(['success' => false], 422);
    }

    /** "Find your club" as the coach types a name. */
    public function clubs(Request $request, string $event): JsonResponse
    {
        $e = $this->event($event);
        $q = (string) $request->query('q', '');
        abort_if(mb_strlen($q) > 80, 422);

        return response()->json(['success' => true, 'clubs' => $this->invitation->searchClubs($e, $q)]);
    }

    /** A "new club" that may already be registered: the match, or nothing. */
    public function checkClub(Request $request, string $event): JsonResponse
    {
        $e = $this->event($event);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json(['success' => true, 'match' => $this->invitation->checkNewClub($e, $data)]);
    }

    /**
     * The one-tap link from the code email. Always lands on the invitation —
     * signed in when the link was good, at the start when it was not.
     */
    public function magic(string $event, string $token): RedirectResponse
    {
        $e = $this->event($event);
        if (preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            $this->invitation->useMagicLink($e, $token);
        }

        return redirect()->to(route('federations.app.invitation').'?event='.urlencode($e->uuid));
    }

    public function lookup(Request $request, string $event): JsonResponse
    {
        $e = $this->event($event);
        $q = (string) $request->query('q', '');
        abort_if(mb_strlen($q) > 60, 422);

        return response()->json(['success' => true] + $this->invitation->lookup($e, $q));
    }

    public function submit(Request $request, string $event): JsonResponse
    {
        $e = $this->event($event);
        $request->validate([
            'clubMode' => ['required', 'in:new,existing'],
            'entries' => ['present', 'array', 'max:300'],
            'transferReq' => ['present', 'array', 'max:50'],
        ]);
        $r = $this->invitation->submit($e, $request->all());

        return $r['ok']
            ? response()->json(['success' => true, 'ref' => $r['ref'], 'balance' => $r['balance'], 'added' => $r['added'] ?? null, 'skipped' => $r['skipped'] ?? [], 'store' => $r['store']])
            : response()->json(array_filter(['success' => false, 'message' => $r['message'] ?? 'This entry could not be saved.', 'code' => $r['code'] ?? null, 'match' => $r['match'] ?? null], fn ($v) => $v !== null), ($r['code'] ?? null) === 'club_exists' ? 409 : 422);
    }

    public function withdraw(Request $request, string $event): JsonResponse
    {
        $e = $this->event($event);
        $data = $request->validate(['athleteId' => ['required', 'string', 'max:16']]);
        $r = $this->invitation->withdraw($e, $data['athleteId']);

        return $r['ok'] ? response()->json(['success' => true, 'store' => $r['store']]) : response()->json(['success' => false], 422);
    }

    /** A club application's logo — the club's own branding. */
    public function clubLogo(string $application): Response
    {
        abort_unless(config('federations.enabled'), 404);
        $app = FederationClubApplication::where('uuid', $application)->where('status', '!=', FederationClubApplication::STATUS_REJECTED)->first();
        abort_unless($app && $app->logo && Storage::disk('public')->exists($app->logo), 404);

        return Storage::disk('public')->response($app->logo, null, ['Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * An official's photo. Private: only the club itself (this session proved
     * it, for any event of the tree) or the federation's staff.
     */
    public function official(Request $request, FederationRegistry $registry, FederationDirectory $directory, string $clubKey, string $slot): Response
    {
        abort_unless(config('federations.enabled') && in_array($slot, ['mgr', 'ref1', 'ref2'], true), 404);

        $staffIds = null;
        if ($request->user() && ($access = $directory->access($request->user()))) {
            $staffIds = collect($directory->subtree($access['root']))->pluck('id');
        }
        // Session::put('fedinv.<event>') nests under `fedinv` — one proved club per event.
        $allowedKeys = collect((array) $request->session()->get('fedinv', []))->values();

        $row = null;
        if ($staffIds) {
            $row = $registry->clubFromKey($clubKey, $staffIds);
        }
        if (! $row && $allowedKeys->contains($clubKey)) {
            $row = $registry->clubFromKey($clubKey, \App\Federations\Models\Federation::pluck('id'));
        }
        $path = $row ? (($row->officials ?? [])[$slot]['photo'] ?? null) : null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }
}
