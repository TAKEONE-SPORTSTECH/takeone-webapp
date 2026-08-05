<?php

namespace Tests\Feature\Events;

use App\Events\EventTypeRegistry;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\HealthRecord;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserRelationship;
use Tests\TestCase;

/**
 * "When am I on, and where?"
 *
 * The bouts-ahead count is the honest number — it falls as the mat is SCORED,
 * not as the clock runs — so it stays true when the day runs late. Everything
 * else on the screen, and both calls to the mat, derive from it.
 */
class NextBoutTest extends TestCase
{
    private Tenant $club;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coach = $this->createUser(['full_name' => 'Coach Kim']);
        $this->club = $this->createClub($this->coach, ['country' => 'BH', 'currency' => 'BHD']);
        $this->coach->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    private function event(array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $this->club->id,
            'created_by' => $this->coach->id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'minutes_per_match' => 6,
            'status' => 'active',
            'is_archived' => false,
        ], $attrs));
    }

    private function athlete(ClubEvent $event, EventCategory $cat, string $name, ?string $birthdate = null): ClubEventRegistration
    {
        $user = $this->createUser([
            'full_name' => $name,
            'gender' => 'Male',
            'birthdate' => $birthdate ?? now()->subYears(25)->toDateString(),
        ]);
        $user->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
        HealthRecord::create(['user_id' => $user->id, 'weight' => 57, 'recorded_at' => now()]);

        return ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $user->id, 'category_id' => $cat->id,
            'role' => 'participant', 'status' => 'joined', 'paid' => true,
            'weight' => 57, 'registered_at' => now(),
        ]);
    }

    private function bout(ClubEvent $e, EventCategory $c, int $slot, string $mat, int $no, ?ClubEventRegistration $a = null, ?ClubEventRegistration $b = null, string $round = 'Round of 16'): EventMatch
    {
        return EventMatch::create([
            'event_id' => $e->id, 'category_id' => $c->id,
            'round' => $round, 'phase' => 'preliminary', 'slot' => $slot,
            'court' => $mat, 'match_no' => $no,
            'a_name' => $a?->user?->full_name, 'a_competitor_id' => $a?->id,
            'b_name' => $b?->user?->full_name, 'b_competitor_id' => $b?->id,
            'status' => 'upcoming',
        ]);
    }

    private function package()
    {
        return app(EventTypeRegistry::class)->get('taekwondo_tournament');
    }

    private function scenario(): array
    {
        $event = $this->event();
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $mine = $this->athlete($event, $cat, 'Ali');
        $opp = $this->athlete($event, $cat, 'Bader');

        // Three bouts ahead of mine on Mat 1, plus a decoy on Mat 2.
        foreach ([1, 2, 3] as $n) {
            $this->bout($event, $cat, $n, 'Mat 1', $n, $this->athlete($event, $cat, "Filler {$n}"), $this->athlete($event, $cat, "Rival {$n}"));
        }
        $this->bout($event, $cat, 9, 'Mat 2', 1, $this->athlete($event, $cat, 'Elsewhere'), $this->athlete($event, $cat, 'Other'));

        $ours = $this->bout($event, $cat, 4, 'Mat 1', 4, $mine, $opp);

        return [$event, $cat, $mine, $opp, $ours];
    }

    /* ---------------- The countdown ---------------- */

    public function test_it_counts_only_undecided_bouts_ahead_on_the_same_mat(): void
    {
        [$event, , $mine, , $ours] = $this->scenario();

        $next = $this->package()->nextUp($event, $mine->user);

        $this->assertSame($ours->id, $next['match_id']);
        $this->assertSame(3, $next['bouts_ahead'], 'the bout on the other mat does not count');
        $this->assertSame('Mat 1', $next['court']);
        $this->assertSame('1-04', $next['code'], 'mat + bout, as the callers announce it');
        $this->assertSame('Bader', $next['opponent']);
        $this->assertSame('red', $next['corner']);
        $this->assertFalse($next['is_next']);
    }

    public function test_the_estimate_comes_from_the_events_own_per_bout_allowance(): void
    {
        [$event, , $mine] = $this->scenario();

        // 3 bouts ahead × 6 minutes each.
        $this->assertSame(18, $this->package()->nextUp($event, $mine->user)['eta_minutes']);
    }

    public function test_the_countdown_falls_as_the_mat_is_scored_not_as_the_clock_runs(): void
    {
        [$event, $cat, $mine] = $this->scenario();
        $package = $this->package();

        $this->assertSame(3, $package->nextUp($event, $mine->user)['bouts_ahead']);

        $first = $cat->matches()->where('court', 'Mat 1')->where('match_no', 1)->first();
        $package->recordOutcome($event, $first->id, ['winner' => 'a']);

        $this->assertSame(2, $package->nextUp($event, $mine->user)['bouts_ahead'], 'a decided bout leaves the queue');
    }

    public function test_the_athlete_is_flagged_as_next_when_the_mat_reaches_them(): void
    {
        [$event, $cat, $mine] = $this->scenario();
        $package = $this->package();

        foreach ([1, 2, 3] as $no) {
            $bout = $cat->matches()->where('court', 'Mat 1')->where('match_no', $no)->first();
            $package->recordOutcome($event, $bout->id, ['winner' => 'a']);
        }

        $next = $package->nextUp($event, $mine->user);
        $this->assertSame(0, $next['bouts_ahead']);
        $this->assertTrue($next['is_next']);
    }

    public function test_an_athlete_with_nothing_left_gets_nothing(): void
    {
        $event = $this->event();
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);
        $a = $this->athlete($event, $cat, 'Ali');
        $b = $this->athlete($event, $cat, 'Bader');
        $bout = $this->bout($event, $cat, 0, 'Mat 1', 1, $a, $b, 'Final');

        $this->package()->recordOutcome($event, $bout->id, ['winner' => 'a']);

        $this->assertNull($this->package()->nextUp($event, $a->user->fresh()));
    }

    public function test_an_opponent_still_to_be_decided_reads_as_unknown(): void
    {
        $event = $this->event();
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);
        $mine = $this->athlete($event, $cat, 'Ali');

        $this->bout($event, $cat, 0, 'Mat 1', 1, $mine, null, 'Semifinal');

        $this->assertNull($this->package()->nextUp($event, $mine->user)['opponent']);
    }

    /* ---------------- Calls to the mat ---------------- */

    public function test_the_call_room_summons_fires_when_the_athlete_comes_within_range(): void
    {
        [$event, $cat, $mine] = $this->scenario();
        $package = $this->package();

        // Nothing is pushed until the mat actually moves.
        $this->assertSame(0, UserNotification::where('user_id', $mine->user_id)->count());

        // One bout scored → 2 ahead, which is call-room range (threshold 2).
        $package->recordOutcome($event, $cat->matches()->where('match_no', 1)->where('court', 'Mat 1')->first()->id, ['winner' => 'a']);

        $this->assertSame(1, UserNotification::where('user_id', $mine->user_id)->where('icon', 'bi-megaphone-fill')->count(),
            'inside 2 bouts the athlete is summoned to the call room');
        $this->assertSame(1, UserNotification::where('user_id', $mine->user_id)->where('icon', 'bi-stopwatch')->count(),
            'and the warm-up warning lands with it, since 2 is also inside 6');
    }

    public function test_an_athlete_is_never_called_twice_for_the_same_bout(): void
    {
        [$event, $cat, $mine] = $this->scenario();
        $package = $this->package();

        // Score, then re-score the same bout repeatedly — mats get corrected.
        $first = $cat->matches()->where('court', 'Mat 1')->where('match_no', 1)->first();
        foreach (range(1, 3) as $_) {
            $package->recordOutcome($event, $first->id, ['winner' => 'a']);
        }

        $this->assertSame(1, UserNotification::where('user_id', $mine->user_id)->where('icon', 'bi-stopwatch')->count());
    }

    public function test_the_result_push_tells_the_athlete_what_is_next(): void
    {
        [$event, $cat, $mine, $opp, $ours] = $this->scenario();

        // Give them a later bout so there IS a next.
        $this->bout($event, $cat, 20, 'Mat 1', 20, $mine, null, 'Semifinal');

        $this->package()->recordOutcome($event, $ours->id, ['winner' => 'a']);

        $note = UserNotification::where('user_id', $mine->user_id)
            ->whereIn('icon', ['bi-trophy', 'bi-flag'])->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('Semifinal', $note->body);
    }

    public function test_a_guardian_is_called_alongside_a_minor(): void
    {
        $event = $this->event();
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Cadet Men -45 kg', 'sort_order' => 1]);

        $minor = $this->athlete($event, $cat, 'Young Ali', now()->subYears(13)->toDateString());
        $rival = $this->athlete($event, $cat, 'Young Bader', now()->subYears(13)->toDateString());

        $guardian = $this->createUser(['full_name' => 'Parent']);
        UserRelationship::create([
            'guardian_user_id' => $guardian->id,
            'dependent_user_id' => $minor->user_id,
            'relationship_type' => 'child',
        ]);

        $filler = $this->bout($event, $cat, 0, 'Mat 1', 1,
            $this->athlete($event, $cat, 'F1'), $this->athlete($event, $cat, 'F2'));
        $this->bout($event, $cat, 1, 'Mat 1', 2, $minor, $rival);

        $this->package()->recordOutcome($event, $filler->id, ['winner' => 'a']);

        $this->assertSame(1, UserNotification::where('user_id', $guardian->id)->where('icon', 'bi-megaphone-fill')->count(),
            'at 13 it is the parent outside the field of play who needs to hear it');
    }

    public function test_an_adult_athletes_relatives_are_not_called(): void
    {
        [$event, $cat, $mine] = $this->scenario();

        $relative = $this->createUser();
        UserRelationship::create([
            'guardian_user_id' => $relative->id,
            'dependent_user_id' => $mine->user_id,
            'relationship_type' => 'parent',
        ]);

        $first = $cat->matches()->where('court', 'Mat 1')->where('match_no', 1)->first();
        $this->package()->recordOutcome($event, $first->id, ['winner' => 'a']);

        $this->assertSame(0, UserNotification::where('user_id', $relative->id)->count());
    }

    /* ---------------- The screens ---------------- */

    public function test_the_athlete_screen_renders_and_serves_json(): void
    {
        [$event, , $mine] = $this->scenario();

        $this->actingAs($mine->user)->get("/me/events/{$event->uuid}/next-up")->assertOk();

        $this->actingAs($mine->user)
            ->getJson("/me/events/{$event->uuid}/next-up")
            ->assertOk()
            ->assertJsonPath('mine.bouts_ahead', 3)
            ->assertJsonPath('mine.court', 'Mat 1');
    }

    public function test_a_coach_sees_the_squad_soonest_first(): void
    {
        [$event, $cat, $mine] = $this->scenario();

        // A squad-mate further down the same mat.
        $later = $this->athlete($event, $cat, 'Zed');
        $this->bout($event, $cat, 30, 'Mat 1', 30, $later, null, 'Semifinal');

        $squad = $this->actingAs($this->coach)
            ->getJson("/me/events/{$event->uuid}/next-up")
            ->assertOk()
            ->json('squad');

        $names = collect($squad)->pluck('name')->all();
        $this->assertLessThan(
            array_search('Zed', $names, true),
            array_search('Ali', $names, true),
            'the athlete due sooner is listed first',
        );
    }

    public function test_an_ordinary_athlete_sees_no_squad(): void
    {
        [$event, , $mine] = $this->scenario();

        $this->actingAs($mine->user)
            ->getJson("/me/events/{$event->uuid}/next-up")
            ->assertOk()
            ->assertJsonPath('squad', []);
    }

    /* ---------------- The venue board ---------------- */

    public function test_the_mat_board_shows_the_live_bout_and_the_queue_behind_it(): void
    {
        [$event, , , , $ours] = $this->scenario();

        $board = $this->actingAs($this->coach)
            ->getJson("/me/events/{$event->uuid}/board?mat=".urlencode('Mat 1'))
            ->assertOk()
            ->json('mats');

        $this->assertCount(1, $board, 'only the requested mat');
        $this->assertSame('Mat 1', $board[0]['court']);
        $this->assertSame('1-01', $board[0]['now']['code'], 'the lowest undecided bout is what is on now');
        $this->assertSame('Filler 1', $board[0]['now']['red']);
        $this->assertNotEmpty($board[0]['on_deck']);
    }

    public function test_the_board_carries_no_personal_data(): void
    {
        [$event] = $this->scenario();

        $board = $this->actingAs($this->coach)
            ->getJson("/me/events/{$event->uuid}/board")->json('mats');

        // A hall screen shows names and bout numbers — never ids, weights,
        // payment state or anything else about a person.
        foreach (array_merge([$board[0]['now']], $board[0]['on_deck']) as $bout) {
            $this->assertSame(
                ['code', 'bout_no', 'round', 'division', 'red', 'blue', 'status'],
                array_keys($bout),
            );
        }
    }

    public function test_the_whole_venue_board_lists_every_mat_in_play(): void
    {
        [$event] = $this->scenario();

        $board = $this->actingAs($this->coach)
            ->getJson("/me/events/{$event->uuid}/board")->json('mats');

        $this->assertSame(['Mat 1', 'Mat 2'], collect($board)->pluck('court')->all());
    }

    public function test_the_board_advances_as_bouts_are_decided(): void
    {
        [$event, $cat] = $this->scenario();

        $first = $cat->matches()->where('court', 'Mat 1')->where('match_no', 1)->first();
        $this->package()->recordOutcome($event, $first->id, ['winner' => 'a']);

        $board = $this->actingAs($this->coach)
            ->getJson("/me/events/{$event->uuid}/board?mat=".urlencode('Mat 1'))->json('mats');

        $this->assertSame('1-02', $board[0]['now']['code'], 'a decided bout leaves the board');
    }

    public function test_the_board_page_renders(): void
    {
        [$event] = $this->scenario();

        $this->actingAs($this->coach)->get("/me/events/{$event->uuid}/board")->assertOk();
    }

    /* ---------------- Push priority ---------------- */

    public function test_only_the_call_room_summons_is_marked_urgent(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        [$event, $cat] = $this->scenario();
        $first = $cat->matches()->where('court', 'Mat 1')->where('match_no', 1)->first();
        $this->package()->recordOutcome($event, $first->id, ['winner' => 'a']);

        $urgent = [];
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendPushNotification::class,
            function ($job) use (&$urgent) {
                $urgent[] = [$job->body, (bool) ($job->options['urgent'] ?? false)];

                return true;
            });

        $summons = collect($urgent)->filter(fn ($r) => str_contains($r[0], 'call room'));
        $warmups = collect($urgent)->filter(fn ($r) => str_contains($r[0], 'Warm up'));

        $this->assertNotEmpty($summons);
        $this->assertTrue($summons->every(fn ($r) => $r[1] === true), 'the summons must ring through an idle phone');
        $this->assertTrue($warmups->every(fn ($r) => $r[1] === false), 'the warm-up nudge must not');
    }
}
