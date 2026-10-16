<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use App\Events\Support\UnplacedEntrants;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /me/events/{uuid}/divisions/unplaced — the entrants in no division, each
 * with the divisions they fit, best first (App\Events\Support\UnplacedEntrants).
 *
 * The same people who may put somebody in a group (canArrange — the organiser
 * and the jury), because this list exists to be acted on through that very
 * endpoint (me.events.divisions.members). Reads only.
 */
class DivisionPlacementController extends Controller
{
    public function unplaced(Request $request, ClubEvent $event, UnplacedEntrants $unplaced, EventAccess $access): JsonResponse
    {
        abort_unless($access->canArrange($event, $request->user()), 403);

        return response()->json(['success' => true, 'people' => $unplaced->for($event)], 200, ['Cache-Control' => 'no-store, private']);
    }
}
