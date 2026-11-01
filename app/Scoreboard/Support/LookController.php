<?php

namespace App\Scoreboard\Support;

use App\Events\Models\ClubEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The two PLATFORM-WIDE looks the shared Settings pop-up edits — the cropper's
 * photo guide (PhotoGuide) and the VS screen's layout (VsLayout) — through one
 * sport-neutral door, so every console's Settings has the same tabs.
 *
 * Super-admin only: these change every console and every VS screen on the
 * platform. The event in the address is only the console it was set from, and
 * must be a sport with a hall fleet. Karate's own doors (karate-scoreboard.
 * photo-guide / vs-layout) stay as they were.
 */
class LookController extends Controller
{
    public function photoGuide(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        $data = $request->validate([
            'reset' => ['sometimes', 'boolean'],
            'guide' => ['required_without:reset', 'array'],
            'guide.*' => ['numeric', 'between:0,1'],
        ]);

        $guide = ! empty($data['reset']) ? PhotoGuide::reset() : PhotoGuide::save($data['guide']);

        \Illuminate\Support\Facades\Log::info('scoreboard.photo_guide.saved', ['user' => Auth::id(), 'reset' => ! empty($data['reset']), 'from' => $event->sport]);
        $this->broadcast();

        return response()->json(['success' => true, 'message' => __('scoreboard::karate_messages.pg_saved'), 'guide' => $guide]);
    }

    public function vsLayout(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        $data = $request->validate([
            'layout' => ['required', 'array'],
            'layout.info_bottom' => ['required', 'numeric', 'between:0,700'],
            'layout.info_scale' => ['required', 'numeric', 'between:0.5,1.3'],
            'layout.show_stats' => ['sometimes', 'boolean'],
        ]);

        $layout = VsLayout::save($data['layout']);
        \Illuminate\Support\Facades\Log::info('scoreboard.vs_layout.saved', ['user' => Auth::id(), 'from' => $event->sport]);
        $this->broadcast();

        return response()->json(['success' => true, 'message' => __('scoreboard::karate_messages.vs_saved'), 'layout' => $layout]);
    }

    private function guard(ClubEvent $event): void
    {
        abort_unless(\App\Scoreboard\Fleet::deviceFor($event->sport) !== null, 404);
        abort_unless(Auth::user()?->isSuperAdmin(), 403);
    }

    /**
     * The Karate screens take the new look live, as they did from Karate's own
     * door; every other screen draws it on its next load (it is read at render).
     */
    private function broadcast(): void
    {
        $message = ['action' => 'look', 'guide' => PhotoGuide::get(), 'vs' => VsLayout::get()];

        $ids = \App\Scoreboard\Sports\Karate\HallScreen\CourtDisplayDevice::whereNull('revoked_at')
            ->whereNotNull('court')->orderByDesc('event_id')->distinct()->limit(50)->pluck('event_id');

        ClubEvent::whereIn('id', $ids)->where('sport', 'karate')->get()
            ->each(fn (ClubEvent $e) => rescue(fn () => \App\Scoreboard\Sports\Karate\HallScreen\ScreenChannel::notifyCourt($e, null, $message), null, false));
    }
}
