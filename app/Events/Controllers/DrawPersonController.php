<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\DrawPersonFinder;
use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The draw menu's person search: which divisions an entrant of THIS event is
 * in, found by name, phone, email or CPR (owner, 2026-10-18).
 *
 * Organiser only — the same rule as the entry search — because phone, email
 * and CPR are private keys. They are matched on and never returned: the
 * answer is each match's name, club and division ids (DrawPersonFinder).
 * Throttled at the route: it takes a search term.
 */
class DrawPersonController extends Controller
{
    public function find(Request $request, ClubEvent $event, DrawPersonFinder $finder, EventAccess $access): JsonResponse
    {
        $me = $request->user();
        abort_unless($me && $access->canManage($event, $me), 403);

        $data = $request->validate(['q' => ['required', 'string', 'max:80']]);

        return response()->json(['success' => true, 'people' => $finder->find($event, $data['q'])]);
    }
}
