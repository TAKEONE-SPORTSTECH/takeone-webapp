<?php

namespace App\Events\Championship;

use App\Http\Controllers\Controller;
use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventPerformance;
use App\Events\Support\EventAccess;
use App\Members\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The sport championship's own doors — drawing divisions and recording what
 * happened. JSON only, organiser only (EventAccess::canManage), every object
 * resolved INSIDE its event so an id from another event is simply not found.
 * Each write returns the division as it now stands (No-Reload rule).
 */
class ChampionshipController extends Controller
{
    public function __construct(private Runner $runner) {}

    public function data(ClubEvent $event): JsonResponse
    {
        $me = $this->guard($event);

        return response()->json(['success' => true] + app(EventTypeRegistry::class)->for($event)->workspaceData($event, $me));
    }

    public function build(ClubEvent $event, int $division): JsonResponse
    {
        $this->guard($event);

        return $this->ok(__('event-sport_championship::messages.drawn'), $this->runner->build($event, $this->division($event, $division)));
    }

    public function knockout(ClubEvent $event, int $division): JsonResponse
    {
        $this->guard($event);

        return $this->ok(__('event-sport_championship::messages.knockout_drawn'), $this->runner->toKnockout($event, $this->division($event, $division)));
    }

    public function nextRound(ClubEvent $event, int $division): JsonResponse
    {
        $this->guard($event);

        return $this->ok(__('event-sport_championship::messages.round_paired'), $this->runner->nextSwissRound($event, $this->division($event, $division)));
    }

    public function final(ClubEvent $event, int $division): JsonResponse
    {
        $this->guard($event);

        return $this->ok(__('event-sport_championship::messages.final_drawn'), $this->runner->toFinal($event, $this->division($event, $division)));
    }

    public function contest(Request $request, ClubEvent $event, int $contest): JsonResponse
    {
        $this->guard($event);

        $payload = $request->validate([
            'sets' => ['nullable', 'array', 'max:9'],
            'sets.*' => ['array', 'size:2'],
            'sets.*.*' => ['integer', 'min:0', 'max:99'],
            'a' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'b' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'penalties' => ['nullable', 'array', 'size:2'],
            'penalties.*' => ['integer', 'min:0', 'max:99'],
            'extra_time' => ['nullable', 'boolean'],
            'decided_by' => ['nullable', 'in:a,b'],
            'walkover' => ['nullable', 'in:a,b'],
            'retired' => ['nullable', 'in:a,b'],
        ]);

        $row = EventMatch::where('event_id', $event->id)->findOrFail($contest);

        return $this->ok(__('event-sport_championship::messages.result_saved'), $this->runner->record($event, $row, $payload));
    }

    public function performance(Request $request, ClubEvent $event, int $performance): JsonResponse
    {
        $this->guard($event);

        $payload = $request->validate([
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'attempts' => ['nullable', 'array', 'max:12'],
            'attempts.*' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'difficulty' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'status' => ['nullable', 'in:ok,dns,dnf,dq'],
        ]);

        $row = EventPerformance::where('event_id', $event->id)->findOrFail($performance);

        return $this->ok(__('event-sport_championship::messages.result_saved'), $this->runner->recordPerformance($event, $row, $payload));
    }

    private function ok(string $message, array $division): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'division' => $division]);
    }

    private function division(ClubEvent $event, int $id): EventCategory
    {
        return EventCategory::where('event_id', $event->id)->findOrFail($id);
    }

    private function guard(ClubEvent $event): User
    {
        $me = Auth::user();

        abort_unless($me instanceof User && app(EventAccess::class)->canManage($event, $me), 403);
        abort_unless(app(EventTypeRegistry::class)->for($event) instanceof Championship, 404);

        return $me;
    }
}
