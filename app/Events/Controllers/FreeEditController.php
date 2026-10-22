<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventMatch;
use App\Events\Support\EventAccess;
use App\Events\Support\Tournament\FreeEdit;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The draw desk's "Free edit" tool (App\Events\Support\Tournament\FreeEdit):
 * write one bout exactly as it happened, even after the event. Organiser only,
 * behind EVENT_FREE_EDIT — off, or not the organiser, and the door is not there.
 */
class FreeEditController extends Controller
{
    public function show(Request $request, ClubEvent $event, int $match, FreeEdit $edit, EventAccess $access): JsonResponse
    {
        $bout = $this->bout($request, $event, $match, $access);

        return response()->json(['success' => true] + $edit->describe($event, $bout),
            200, ['Cache-Control' => 'no-store, private']);
    }

    public function save(Request $request, ClubEvent $event, int $match, FreeEdit $edit, EventAccess $access): JsonResponse
    {
        $bout = $this->bout($request, $event, $match, $access);

        $data = $request->validate([
            'a' => ['present', 'nullable', 'integer', 'min:1'],
            'b' => ['present', 'nullable', 'integer', 'min:1'],
            'winner' => ['nullable', Rule::in(['a', 'b'])],
            'a_score' => ['nullable', 'string', 'max:10'],
            'b_score' => ['nullable', 'string', 'max:10'],
            'advance' => ['nullable', 'boolean'],
        ]);

        try {
            $out = $edit->save($event, $bout, $data, $request->user());
        } catch (\Throwable $e) {
            // One transaction: a failure here changed nothing.
            report($e);
            $out = ['ok' => false, 'message' => __('events::events.action_failed')];
        }

        return response()->json(['success' => $out['ok'], 'message' => $out['message']], $out['ok'] ? 200 : 422);
    }

    private function bout(Request $request, ClubEvent $event, int $match, EventAccess $access): EventMatch
    {
        abort_unless(FreeEdit::enabled() && $request->user() && $access->canManage($event, $request->user()), 404);

        return EventMatch::where('event_id', $event->id)->where('id', $match)->firstOrFail();
    }
}
