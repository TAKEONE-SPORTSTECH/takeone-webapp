<?php

namespace Tests\Feature;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Media\BoutStats;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * One bout's statistics (App\Media\BoutStats) and the stat sheet PDF door
 * (BoutVideoController::statsPdf): the numbers read back from the mat's log,
 * and who may download them — exactly whoever may watch the bout.
 */
class BoutStatsTest extends TestCase
{
    /** @return array{0: ClubEvent, 1: \App\Members\Models\User, 2: EventMatch} */
    private function bout(bool $logged = true): array
    {
        $organiser = $this->createUser(['full_name' => 'Sensei Ali']);
        $club = $this->createClub($organiser, ['country' => 'BH']);
        $organiser->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $organiser->id, 'title' => 'Autumn Cup',
            'event_type' => 'championship', 'sport' => 'karate', 'scope' => 'internal',
            'date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00', 'status' => 'active', 'is_archived' => false,
        ]);
        $category = EventCategory::create(['event_id' => $event->id, 'name' => 'Cadets Kumite Male -52 kg', 'sort_order' => 1]);

        $regs = [];
        foreach (['Red Fighter', 'Blue Fighter'] as $name) {
            $athlete = $this->createUser(['full_name' => $name, 'gender' => 'Male']);
            $regs[] = ClubEventRegistration::create([
                'event_id' => $event->id, 'user_id' => $athlete->id, 'category_id' => $category->id,
                'role' => 'participant', 'paid' => true,
            ]);
        }

        $match = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $category->id, 'round' => 'Final', 'phase' => 'finals',
            'slot' => 0, 'match_no' => 7, 'a_name' => 'Red Fighter', 'b_name' => 'Blue Fighter',
            'a_competitor_id' => $regs[0]->id, 'b_competitor_id' => $regs[1]->id,
            'a_corner' => 'red', 'b_corner' => 'blue', 'court' => 'Mat 1', 'status' => 'done', 'winner' => 'a',
            'a_score' => 4, 'b_score' => 3,
        ]);

        if ($logged) {
            // A 90-second bout: blue scores first, red comes back and wins 4–3
            // with a point 10 s from the end; a false start was reset away.
            $rows = [
                ['load', null, null, 90, []],
                ['start', null, null, 90, []],
                ['point', 'b', 3, 80, ['side' => 'ao', 'n' => 3]],  // abandoned attempt
                ['reset', null, null, 90, []],
                ['start', null, null, 90, []],
                ['point', 'b', 3, 75, ['side' => 'ao', 'n' => 3]],  // 0:15 · 0–3
                ['pause', null, null, 70, []],
                ['penalty', 'b', null, 70, ['side' => 'ao', 'level' => 1]],
                ['start', null, null, 70, []],
                ['point', 'a', 1, 40, ['side' => 'aka', 'n' => 1]], // 0:50 · 1–3
                ['point', 'a', 3, 10, ['side' => 'aka', 'n' => 3]], // 1:20 · 4–3
                ['pause', null, null, 0, []],
                ['finish', null, null, 0, ['winner' => 'aka']],
            ];
            $t = now()->startOfMinute();
            foreach ($rows as $i => [$cmd, $side, $pts, $left, $payload]) {
                DB::table('event_match_events')->insert([
                    'event_id' => $event->id, 'match_id' => $match->id, 'court' => 'Mat 1', 'sport' => 'karate',
                    'command' => $cmd, 'side' => $side, 'points' => $pts, 'score_a' => 0, 'score_b' => 0,
                    'clock_remaining' => $left, 'clock_duration' => 90,
                    'payload' => json_encode(['command' => $cmd] + $payload),
                    'occurred_at' => $t->copy()->addSeconds($i * 5)->format('Y-m-d H:i:s.v'),
                    'sequence' => $i + 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return [$event, $organiser->fresh(), $match->fresh()];
    }

    private function door(ClubEvent $event, EventMatch $match): string
    {
        return route('me.events.bout.stats.pdf', ['event' => $event->uuid, 'matchNo' => $match->match_no]);
    }

    private function outsider(): \App\Members\Models\User
    {
        $outsider = $this->createUser();
        $club = $this->createClub($outsider, ['country' => 'BH']);
        $outsider->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $outsider->fresh();
    }

    public function test_the_stats_are_read_from_the_last_attempt_of_the_log(): void
    {
        [, , $match] = $this->bout();

        $s = app(BoutStats::class)->for($match->load('event'));

        $this->assertSame(['red' => 4, 'blue' => 3], $s['score']);
        $this->assertSame('red', $s['winner']);
        $this->assertSame(['corner' => 'blue', 'at' => 15.0, 'n' => 3, 'won' => false], $s['first']);
        $this->assertSame(1, $s['lead_changes']);
        $this->assertTrue($s['comeback']);
        $this->assertEquals(80.0, $s['decided_at']);
        $this->assertEquals(10.0, $s['decided_left']);
        $this->assertSame(1, $s['blue']['penalties']);
        $this->assertSame([1 => 1, 3 => 1], $s['red']['by_value']);
        $this->assertSame(3, $s['blue']['biggest_lead']);
        $this->assertSame(4, $s['red']['longest_run']);
        $this->assertSame(1, $s['stoppages']);
        $this->assertSame(10, $s['red']['lead_seconds']);
        $this->assertCount(4, $s['events']);    // three points and a penalty; the reset attempt is gone
    }

    public function test_a_bout_with_nothing_logged_has_no_stats_and_no_sheet(): void
    {
        [$event, $organiser, $match] = $this->bout(logged: false);

        $this->assertNull(app(BoutStats::class)->for($match));

        $this->actingAs($organiser)->getJson($this->door($event, $match))->assertNotFound();
    }

    public function test_whoever_may_watch_downloads_the_sheet(): void
    {
        if (! \App\Events\Support\BracketPdf::available()) {
            $this->markTestSkipped('No PDF engine on this machine.');
        }

        [$event, $organiser, $match] = $this->bout();

        $res = $this->actingAs($organiser)->get($this->door($event, $match))->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_someone_who_may_not_watch_the_bout_gets_nothing(): void
    {
        [$event, , $match] = $this->bout();

        $this->actingAs($this->outsider())->getJson($this->door($event, $match))->assertNotFound();
        $this->getJson('/me/events/'.$event->uuid.'/bout/999/stats.pdf')->assertNotFound();
    }

    public function test_a_guest_gets_nothing(): void
    {
        [$event, , $match] = $this->bout();

        $this->getJson($this->door($event, $match))->assertUnauthorized();
    }

    public function test_both_bout_pages_offer_the_sheet_only_when_there_is_one(): void
    {
        if (! \App\Events\Support\BracketPdf::available()) {
            $this->markTestSkipped('No PDF engine on this machine.');
        }

        $phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148';
        $desk = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36';

        [$event, $organiser, $match] = $this->bout();
        [$quietEvent, $quietOrganiser, $quiet] = $this->bout(logged: false);

        foreach ([$phone, $desk] as $agent) {
            $page = route('me.events.bout.video', ['event' => $event->uuid, 'matchNo' => $match->match_no]);
            $on = $this->actingAs($organiser)->withHeader('User-Agent', $agent)->get($page)->assertOk()->getContent();
            $this->assertStringContainsString(e($this->door($event, $match)), $on);

            $page = route('me.events.bout.video', ['event' => $quietEvent->uuid, 'matchNo' => $quiet->match_no]);
            $off = $this->actingAs($quietOrganiser)->withHeader('User-Agent', $agent)->get($page)->assertOk()->getContent();
            $this->assertStringNotContainsString('stats.pdf', $off);
        }
    }
}
