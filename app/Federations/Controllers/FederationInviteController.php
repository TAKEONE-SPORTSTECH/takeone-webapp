<?php

namespace App\Federations\Controllers;

use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationDirectory;
use App\Federations\Services\FederationInvitation;
use App\Federations\Services\FederationRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
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
        $data = $request->validate(['contact' => ['required', 'string', 'max:191']]);
        $this->invitation->sendCode($e, $data['contact']);

        // The same answer whether or not the contact belongs to a club.
        return response()->json(['success' => true]);
    }

    public function verify(Request $request, string $event): JsonResponse
    {
        $e = $this->event($event);
        $data = $request->validate(['contact' => ['required', 'string', 'max:191'], 'code' => ['required', 'string', 'max:12']]);

        return $this->invitation->verify($e, $data['contact'], $data['code'])
            ? response()->json(['success' => true, 'store' => $this->invitation->store($e)])
            : response()->json(['success' => false], 422);
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
            ? response()->json(['success' => true, 'ref' => $r['ref'], 'balance' => $r['balance'], 'store' => $r['store']])
            : response()->json(['success' => false, 'message' => $r['message'] ?? 'This entry could not be saved.'], 422);
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
