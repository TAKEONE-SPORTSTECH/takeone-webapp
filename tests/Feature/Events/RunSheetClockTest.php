<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventRunSlot;
use App\Events\Support\RunSheet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The running order's "now" is read on the HALL's clock, not the server's.
 *
 * Regression for 18 Sep 2026: the app runs in UTC, the sheet's times are
 * Manama wall-clock, and the "now" marker sat three hours behind the hall.
 */
class RunSheetClockTest extends TestCase
{
    private function eventWithSheet(string $timezone): ClubEvent
    {
        $club = $this->createClub($this->createUser(), ['country' => 'BH', 'timezone' => $timezone]);
        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'title' => 'Day', 'event_type' => 'championship', 'sport' => 'taekwondo',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => '2026-09-18', 'start_time' => '10:00:00',
        ]);
        foreach ([['Morning', 10 * 60], ['Afternoon', 13 * 60 + 10]] as $i => [$label, $at]) {
            EventRunSlot::create([
                'uuid' => (string) Str::uuid(), 'event_id' => $event->id, 'day' => 1, 'position' => $i,
                'kind' => EventRunSlot::KIND_CUSTOM, 'label' => $label, 'duration_minutes' => 30, 'pinned_at' => $at,
            ]);
        }

        return $event;
    }

    public function test_now_follows_the_hosts_timezone_not_utc(): void
    {
        // 10:15 UTC is 13:15 in Manama: the afternoon part is on, the morning is done.
        $this->travelTo(Carbon::parse('2026-09-18 10:15:00', 'UTC'));

        $rows = app(RunSheet::class)->rows($this->eventWithSheet('Asia/Bahrain'));

        $this->assertSame(['done', 'now'], array_column($rows, 'status'));
    }

    public function test_the_day_boundary_is_the_halls_midnight(): void
    {
        // 22:30 UTC on the 17th is already 01:30 on the 18th in Manama — the day has begun.
        $this->travelTo(Carbon::parse('2026-09-17 22:30:00', 'UTC'));

        $rows = app(RunSheet::class)->rows($this->eventWithSheet('Asia/Bahrain'));

        $this->assertSame(['upcoming', 'upcoming'], array_column($rows, 'status'));
        $this->assertSame('Asia/Bahrain', RunSheet::timezone(ClubEvent::latest('id')->first()));
    }

    public function test_an_unknown_timezone_falls_back_to_the_platforms_home(): void
    {
        $this->travelTo(Carbon::parse('2026-09-18 10:15:00', 'UTC'));

        $event = $this->eventWithSheet('Not/AZone');

        $this->assertSame(config('events.entry_timezone'), RunSheet::timezone($event));
    }
}
