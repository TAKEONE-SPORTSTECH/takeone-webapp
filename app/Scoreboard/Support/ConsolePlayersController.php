<?php

namespace App\Scoreboard\Support;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use App\Scoreboard\Fleet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The console Players tab's doors, for every sport with a hall fleet
 * (App\Scoreboard\Fleet) — ConsolePlayers does the work.
 *
 * Two front doors, one body, exactly as each console has:
 *  · the PAIRED TABLE, by its device token — acting as the organiser who
 *    paired it, re-checked on every request (the screen must be claimed, a
 *    control, on a mat of an event of THIS sport, and its organiser must still
 *    be able to score);
 *  · the SIGNED-IN organiser's console, by event uuid.
 * Through either, the Players tab is for ORGANISERS (canManage) — an
 * appointed official who may score is not thereby allowed to rewrite the
 * entry list. Every failure of the token answers alike (404), so a console a
 * stranger is looking at says nothing about which tokens are real.
 *
 * The sport comes from the route (`->defaults('sport', …)`), never the request.
 */
class ConsolePlayersController extends Controller
{
    public function __construct(private ConsolePlayers $players) {}

    /* ── The paired table ─────────────────────────────────────────────── */

    public function tokenList(Request $request, string $token): JsonResponse
    {
        [$event] = $this->viaToken($request, $token);

        return response()->json($this->players->list($event));
    }

    public function tokenShow(Request $request, string $token, int $registration): JsonResponse
    {
        [$event, $actor] = $this->viaToken($request, $token);

        return $this->players->show($event, $registration, $actor);
    }

    public function tokenUpdate(Request $request, string $token, int $registration): JsonResponse
    {
        [$event, $actor] = $this->viaToken($request, $token);

        return $this->players->save($request, $event, $registration, $actor);
    }

    public function tokenVsFrame(Request $request, string $token, int $registration): JsonResponse
    {
        [$event, $actor] = $this->viaToken($request, $token);

        return $this->players->saveVsFrame($request, $event, $registration, $actor);
    }

    public function tokenVsPreview(Request $request, string $token)
    {
        [$event] = $this->viaToken($request, $token);

        return $this->players->vsPreview($request, $event);
    }

    public function tokenGeoCountry(Request $request, string $token): JsonResponse
    {
        $this->viaToken($request, $token);

        return $this->geoCountry($request);
    }

    /* ── The signed-in organiser ──────────────────────────────────────── */

    public function list(Request $request, ClubEvent $event): JsonResponse
    {
        $this->signedIn($request, $event);

        return response()->json($this->players->list($event));
    }

    public function show(Request $request, ClubEvent $event, int $registration): JsonResponse
    {
        return $this->players->show($event, $registration, $this->signedIn($request, $event));
    }

    public function update(Request $request, ClubEvent $event, int $registration): JsonResponse
    {
        return $this->players->save($request, $event, $registration, $this->signedIn($request, $event));
    }

    public function vsFrame(Request $request, ClubEvent $event, int $registration): JsonResponse
    {
        return $this->players->saveVsFrame($request, $event, $registration, $this->signedIn($request, $event));
    }

    public function vsPreview(Request $request, ClubEvent $event)
    {
        $this->signedIn($request, $event);

        return $this->players->vsPreview($request, $event);
    }

    public function geoCountryForEvent(Request $request, ClubEvent $event): JsonResponse
    {
        $this->signedIn($request, $event);

        return $this->geoCountry($request);
    }

    /* ── Who is asking ────────────────────────────────────────────────── */

    /**
     * A paired scoring table, standing in for the organiser who paired it.
     *
     * @return array{0: ClubEvent, 1: User}
     */
    private function viaToken(Request $request, string $token): array
    {
        $sport = (string) $request->route('sport');
        $model = Fleet::deviceFor($sport);
        $device = $model ? $model::resolve($token) : null;

        abort_unless(
            $device
            && $device->isClaimed()
            && $device->event
            && $device->surface === 'control'
            && $device->court
            && $device->event->sport === $sport,
            404
        );

        $by = $device->created_by ? User::find($device->created_by) : null;

        abort_unless($by && app(EventAccess::class)->canScore($device->event, $by), 403);
        abort_unless(app(EventAccess::class)->canManage($device->event, $by), 403);

        $device->touchSeen();

        return [$device->event, $by];
    }

    /** The organiser's own console, signed in. */
    private function signedIn(Request $request, ClubEvent $event): User
    {
        $user = Auth::user();

        abort_unless($event->sport === (string) $request->route('sport'), 404);
        abort_unless($user && app(EventAccess::class)->canManage($event, $user), 403);

        return $user;
    }

    /** Where this device is, as a country — seeds a blank nationality. */
    private function geoCountry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json([
            'success' => true,
            'country' => \App\Support\GeoCountry::forCoordinates((float) $data['lat'], (float) $data['lng']),
        ]);
    }
}
