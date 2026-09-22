<?php

namespace Tests\Feature\Events;

use App\Events\Support\EventNotUnderWay;
use App\Events\Support\Rehearsal;
use App\Events\Support\ResultFiling;
use App\Events\Models\ClubEvent;
use Tests\TestCase;

/**
 * The rule that says whether a result may be filed yet — and that every sport
 * obeys the SAME one.
 *
 * Enrolment, the draw, elimination formats, round robin and the money are
 * common to every martial art; the scoreboard's design, its operation, the
 * rules and the points and fouls are not. "Has this competition begun?" is in
 * the first group, so there is one implementation and three callers.
 *
 * Before 2026-09-22 there were three different answers: jiu-jitsu started the
 * day by itself, and karate and taekwondo had no check at all.
 */
class ResultFilingTest extends TestCase
{
    /**
     * @param  \Carbon\Carbon|null  $startedAt  set AFTER create(): started_at is
     *                                          not mass-assignable, so passing it
     *                                          to create() is silently dropped.
     */
    private function event(array $attrs = [], $startedAt = null): ClubEvent
    {
        $owner = $this->createUser();

        $event = ClubEvent::create(array_merge([
            'tenant_id' => $this->createClub($owner)->id,
            'created_by' => $owner->id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ], $attrs));

        if ($startedAt !== null) {
            $event->started_at = $startedAt;
            $event->save();
        }

        return $event;
    }

    public function test_a_result_is_refused_before_the_day_has_started(): void
    {
        $event = $this->event();

        $this->assertFalse(app(ResultFiling::class)->permitted($event));

        $this->expectException(EventNotUnderWay::class);
        app(ResultFiling::class)->refuseUnlessUnderWay($event);
    }

    public function test_the_refusal_is_its_own_type_so_a_console_can_offer_a_way_forward(): void
    {
        $event = $this->event();

        try {
            app(ResultFiling::class)->refuseUnlessUnderWay($event);
            $this->fail('filing was not refused');
        } catch (EventNotUnderWay $e) {
            // The distinction the console depends on: every OTHER refusal means
            // "do something different", this one means "do the same thing after
            // one more tap". Refusing with no way forward stopped a
            // championship on 2026-09-18.
            $this->assertInstanceOf(\RuntimeException::class, $e);
            $this->assertNotSame('', $e->getMessage(), 'the official is told nothing');
        }
    }

    public function test_a_started_day_permits_filing(): void
    {
        $event = $this->event([], now());

        $this->assertTrue(app(ResultFiling::class)->permitted($event));
    }

    public function test_a_rehearsal_permits_filing_without_starting_the_day(): void
    {
        $event = $this->event();
        $owner = $event->creator ?? $this->createUser();

        app(Rehearsal::class)->begin($event->fresh(), $owner);

        $this->assertTrue(app(ResultFiling::class)->permitted($event->fresh()));
        $this->assertNull($event->fresh()->started_at, 'a rehearsal started the real competition');
    }

    public function test_starting_the_day_records_who_and_flags_the_skipped_checks(): void
    {
        $event = $this->event();
        $official = $this->createUser();

        app(ResultFiling::class)->startDay($event, $official);

        $event = $event->fresh();
        $this->assertNotNull($event->started_at);
        // The official at the table, not the organiser who never pressed it.
        $this->assertSame($official->id, $event->started_by);
        // Started from the mat, so the pre-flight checklist was by definition
        // not walked. Flagged rather than hidden — the event console and the
        // MCP readiness tool both read this.
        $this->assertTrue((bool) $event->start_overridden);
        $this->assertTrue(app(ResultFiling::class)->permitted($event));
    }

    public function test_starting_an_already_started_day_changes_nothing(): void
    {
        $when = now()->subHour();
        $event = $this->event([], $when);

        app(ResultFiling::class)->startDay($event, $this->createUser());

        $this->assertSame(
            $when->toDateTimeString(),
            $event->fresh()->started_at->toDateTimeString(),
            'a second start moved the clock'
        );
    }

    /**
     * The drift guard.
     *
     * Every sport that runs a mat must ask the SHARED rule. This is the check
     * that would have caught karate and taekwondo having no check at all —
     * which is not a thing anybody notices, because nothing fails.
     */
    public function test_every_sport_asks_the_shared_rule(): void
    {
        $sports = glob(base_path('app/Scoreboard/Sports/*/Mat/Scoring.php'));

        $this->assertNotEmpty($sports, 'no scoring classes were found at all');

        foreach ($sports as $file) {
            $sport = basename(dirname(dirname($file)));
            $code = file_get_contents($file);

            $this->assertStringContainsString(
                'ResultFiling',
                $code,
                "{$sport} does not consult App\\Events\\Support\\ResultFiling, so a result can be "
                ."filed there against a competition nobody has started. The rule is common; only "
                ."the command NAMES are this sport's own."
            );

            $this->assertStringContainsString(
                'start_day',
                $code,
                "{$sport} has no start_day command, so an official refused at its mat has no way "
                .'forward and must go and find the organiser. That is what stopped a championship '
                .'on 2026-09-18.'
            );
        }
    }
}
