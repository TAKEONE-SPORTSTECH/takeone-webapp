<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Support\EntryWindow;
use App\Events\Support\EventFee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A competition follows ITS OWN timezone, strictly (2026-09-29).
 *
 * Every date/time on an event is the hall's wall clock. The app runs in UTC,
 * so before this every clock rule on a Manama event fired three hours late.
 */
class EventTimezoneTest extends TestCase
{
    private function club(string $tz = 'Asia/Bahrain'): Tenant
    {
        return $this->createClub($this->createUser(), ['country' => 'BH', 'timezone' => $tz]);
    }

    private function event(Tenant $club, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id, 'title' => 'Cup', 'event_type' => 'championship', 'sport' => 'taekwondo',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => '2026-09-18', 'start_time' => '10:00:00',
        ], $attrs));
    }

    public function test_the_zone_is_the_events_own_then_the_hosts_then_the_platforms(): void
    {
        $club = $this->club('Asia/Dubai');

        $this->assertSame('Asia/Dubai', $this->event($club)->zone());
        $this->assertSame('Europe/London', $this->event($club, ['timezone' => 'Europe/London'])->zone());
        $this->assertSame('Asia/Dubai', $this->event($club, ['timezone' => 'Not/AZone'])->zone());
        $this->assertSame(config('events.entry_timezone'), $this->event($this->club('garbage'))->zone());
    }

    public function test_a_same_day_event_ends_at_its_end_time_in_the_hall(): void
    {
        $event = $this->event($this->club(), ['end_time' => '21:00:00']);

        $this->travelTo(Carbon::parse('2026-09-18 17:30:00', 'UTC'));   // 20:30 Manama
        $this->assertFalse($event->hasEnded());

        $this->travelTo(Carbon::parse('2026-09-18 18:30:00', 'UTC'));   // 21:30 Manama — UTC alone said 18:30 < 21:00
        $this->assertTrue($event->hasEnded());
    }

    public function test_it_is_overdue_from_its_start_time_in_the_hall(): void
    {
        $event = $this->event($this->club());

        $this->travelTo(Carbon::parse('2026-09-18 07:30:00', 'UTC'));   // 10:30 Manama
        $this->assertTrue($event->isOverdueToStart());
        $this->assertSame('2026-09-18T10:00:00+03:00', $event->scheduledStart()->toIso8601String());
    }

    public function test_an_event_chosen_zone_overrides_the_club_everywhere(): void
    {
        // A Bahrain club running a competition in London.
        $event = $this->event($this->club(), ['timezone' => 'Europe/London', 'enrollment_ends_at' => '2026-09-10']);

        $this->assertSame('2026-09-10T23:59:59+01:00', EntryWindow::closesAt($event)->toIso8601String());

        $this->travelTo(Carbon::parse('2026-09-10 22:30:00', 'UTC'));   // 23:30 London, 01:30 Manama
        $this->assertFalse(EntryWindow::hasClosed($event));
    }

    public function test_the_late_fee_starts_on_the_halls_clock(): void
    {
        $event = $this->event($this->club(), ['late_fee_amount' => 5, 'late_fee_from' => '2026-09-12 00:00:00']);

        $this->assertTrue(EventFee::lateFeeApplies($event, 'participant', Carbon::parse('2026-09-11 21:30:00', 'UTC')));  // 00:30 Manama
        $this->assertFalse(EventFee::lateFeeApplies($event, 'participant', Carbon::parse('2026-09-11 20:30:00', 'UTC'))); // 23:30 Manama
    }

    public function test_a_start_day_draw_opens_at_the_halls_midnight(): void
    {
        $event = $this->event($this->club(), ['draw_reveal' => ClubEvent::DRAW_START_DAY]);

        $this->travelTo(Carbon::parse('2026-09-17 20:30:00', 'UTC'));   // 23:30 Manama the night before
        $this->assertFalse($event->drawRevealed());

        $this->travelTo(Carbon::parse('2026-09-17 21:30:00', 'UTC'));   // 00:30 Manama on the day
        $this->assertTrue($event->drawRevealed());
    }

    public function test_the_organiser_can_choose_the_zone_and_a_bad_one_is_refused(): void
    {
        Queue::fake();
        $club = $this->club();
        $owner = \App\Members\Models\User::find($club->owner_user_id);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $payload = [
            'tenant_id' => $club->id, 'title' => 'Away Open', 'event_type' => 'race', 'scope' => 'internal',
            'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00',
            'participant_free' => true, 'spectator_enabled' => false,
        ];

        $this->actingAs($owner->fresh())->postJson('/me/events', $payload + ['timezone' => 'Nowhere/Land'])
            ->assertStatus(422)->assertJsonValidationErrors('timezone');

        $this->actingAs($owner->fresh())->postJson('/me/events', $payload + ['timezone' => 'Asia/Riyadh'])->assertOk();
        $this->assertSame('Asia/Riyadh', ClubEvent::where('title', 'Away Open')->value('timezone'));
    }
}
