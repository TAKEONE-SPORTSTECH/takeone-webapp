<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Support\DeskSignInCode;
use App\Events\Support\KnownPerson;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The organiser's half of the entry desk's sign-in code: issue one for an
 * athlete on their card. The athlete's half is PublicEntryController@redeemDeskCode.
 * Every rule about who may issue it, and for whom, lives in DeskSignInCode.
 */
class EntrySignInCodeController extends Controller
{
    public function store(Request $request, ClubEvent $event, int $entry, DeskSignInCode $codes): JsonResponse
    {
        abort_unless(KnownPerson::enabled(), 404);

        $registration = ClubEventRegistration::where('event_id', $event->id)->whereKey($entry)->first();
        abort_unless($registration && $codes->mayIssue($event, $request->user()), 404);

        $result = $codes->issue($event, $registration, $request->user());

        return response()->json(['success' => $result['ok']] + $result, $result['ok'] ? 200 : 422);
    }
}
