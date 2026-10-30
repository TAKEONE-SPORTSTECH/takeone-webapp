<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventMatchEvent;
use App\Events\Models\EventRunSlot;
use App\Events\Support\RunSheet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Once the mats have run bouts, the running order reads THEM, not the plan
 * (RunSheet::withActuals — owner, 2026-10-25: "make it realistic, it's
 * impossible to be way beyond the expected when the actual is way less").
 *
 * What this protects: before anything is fought the plan stands untouched; a
 * division that has run shows its real times; the one on the mat ends after
 * what is left of it at the day's measured pace, never before now; and what
 * follows flows from there instead of from the plan's 8-minute guesses.
 */
class RunSheetActualsTest extends TestCase
{
    private ClubEvent $event;
    /** @var array<string, EventCategory> */
    private array $div = [];

    protected function setUp(): void
    {
        parent::setUp();
        $club = $this->createClub($this->createUser(), ['country' => 'BH', 'timezone' => 'Asia/Bahrain']);
        $this->event = ClubEvent::create([
            'tenant_id' => $club->id, 'title' => 'League', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => '2026-10-17', 'start_time' => '08:00:00', 'minutes_per_match' => 8,
        ]);
        foreach (['one' => 4, 'two' => 2] as $key => $bouts) {
            $this->div[$key] = EventCategory::create(['event_id' => $this->event->id, 'name' => "Division $key", 'sort_order' => 1]);
            for ($i = 0; $i < $bouts; $i++) {
                EventMatch::create(['event_id' => $this->event->id, 'category_id' => $this->div[$key]->id,
                    'round' => $i === $bouts - 1 ? 'Final' : 'Semifinal', 'slot' => $i, 'match_no' => $i + 1,
                    'court' => 'Mat 1', 'status' => 'upcoming', 'a_name' => "A$i", 'b_name' => "B$i"]);
            }
            EventRunSlot::create(['uuid' => (string) Str::uuid(), 'event_id' => $this->event->id, 'day' => 1,
                'position' => $key === 'one' ? 1 : 2, 'kind' => EventRunSlot::KIND_DIVISION, 'category_id' => $this->div[$key]->id]);
        }
    }

    /** A bout fought between two local (Manama) clock times, and decided. */
    private function fought(EventMatch $m, string $from, string $to): void
    {
        foreach ([['load', $from], ['start', $from], ['finish', $to]] as $k => [$cmd, $at]) {
            EventMatchEvent::create(['event_id' => $this->event->id, 'match_id' => $m->id, 'court' => 'Mat 1', 'sport' => 'karate',
                'command' => $cmd, 'payload' => [], 'occurred_at' => Carbon::parse("2026-10-17 $at", 'Asia/Bahrain')->utc(), 'sequence' => $m->id * 10 + $k]);
        }
        $m->update(['winner' => 'a', 'status' => 'done']);
    }

    private function rows(string $nowLocal): array
    {
        return app(RunSheet::class)->rows($this->event->fresh(), 1, Carbon::parse("2026-10-17 $nowLocal", 'Asia/Bahrain'));
    }

    private function clock(int $m): string
    {
        return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }

    public function test_before_anything_is_fought_the_plan_stands(): void
    {
        $rows = $this->rows('07:00');

        $this->assertSame('08:00', $this->clock($rows[0]['starts_at']));
        $this->assertSame(4 * 8 + RunSheet::TURNAROUND, $rows[0]['duration']);
        $this->assertSame(['upcoming', 'upcoming'], array_column($rows, 'status'));
    }

    public function test_the_day_reads_the_mats_once_bouts_have_run(): void
    {
        $bouts = EventMatch::where('category_id', $this->div['one']->id)->orderBy('slot')->get();
        // Three bouts of three minutes, two minutes apart: the day's pace is 5 minutes a bout.
        $this->fought($bouts[0], '09:50', '09:53');
        $this->fought($bouts[1], '09:55', '09:58');
        $this->fought($bouts[2], '10:00', '10:03');

        $rows = $this->rows('10:10');

        // On the mat: its real start, and its last bout after now at the day's pace.
        $this->assertSame(['09:50', '10:15', 'now'], [$this->clock($rows[0]['starts_at']), $this->clock($rows[0]['ends_at']), $rows[0]['status']]);
        // Next: from there, two bouts at five minutes — not the plan's 08:00 + 8-minute bouts.
        $this->assertSame(['10:15', '10:25', 'upcoming'], [$this->clock($rows[1]['starts_at']), $this->clock($rows[1]['ends_at']), $rows[1]['status']]);
    }

    public function test_a_division_that_has_run_shows_when_it_really_ran(): void
    {
        $bouts = EventMatch::where('category_id', $this->div['one']->id)->orderBy('slot')->get();
        $this->fought($bouts[0], '09:50', '09:53');
        $this->fought($bouts[1], '09:55', '09:58');
        $this->fought($bouts[2], '10:00', '10:03');
        $this->fought($bouts[3], '10:05', '10:09');

        $rows = $this->rows('18:00');

        $this->assertSame(['09:50', '10:09', 'done'], [$this->clock($rows[0]['starts_at']), $this->clock($rows[0]['ends_at']), $rows[0]['status']]);
        // Never fought, the day long over: it follows the last real bell, not 08:00.
        $this->assertGreaterThanOrEqual(10 * 60 + 9, $rows[1]['starts_at']);
    }
}
