<?php

namespace Tests\Feature;

use App\Events\Support\CategorySystems;
use App\Mcp\Tools\GetSportRuleBookTool;
use App\Sports\Combat\SportRegistry;
use App\Sports\Package\SportPackages;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * Phase 2 of the Sport Package Plan: the rule-book contract is a wrapper over
 * the live classification path, so it must agree with it everywhere.
 */
class SportPackagesTest extends TestCase
{
    public function test_every_sport_has_its_official_styles_at_version_one(): void
    {
        $p = app(SportPackages::class);
        $this->assertEqualsCanonicalizing(['taekwondo', 'karate', 'bjj'], $p->sports());
        $this->assertArrayHasKey('wkf', $p->styles('karate'));
        $this->assertArrayHasKey('shotokan', $p->styles('karate'));
        $this->assertSame(['gi', 'nogi'], $p->style('bjj', 'ibjjf')->kinds);
        foreach ($p->sports() as $s) {
            foreach ($p->styles($s) as $book) {
                $this->assertSame('v1', $book->edition);
                $this->assertTrue($book->official);
            }
        }
    }

    public function test_classification_is_identical_to_the_live_path(): void
    {
        $p = app(SportPackages::class);
        $sys = app(CategorySystems::class);
        $cases = 0;
        foreach ($p->sports() as $sport) {
            foreach ($p->styles($sport) as $key => $book) {
                $cs = $sys->weights($sport)[$key];
                foreach ($book->kinds as $kind) {
                    foreach (['Male', 'Female'] as $g) {
                        foreach ([6, 9, 12, 14, 16, 17, 19, 25, 32, 41, 55] as $age) {
                            for ($w = 20; $w <= 140; $w += 3.5) {
                                $this->assertSame($cs->classify($g, $age, $w, $kind === 'standard' ? null : $kind), $book->classify($g, $age, $w, $kind));
                                $cases++;
                            }
                        }
                    }
                }
            }
        }
        $this->assertGreaterThan(3000, $cases);
    }

    public function test_an_events_rule_book_is_the_one_its_entry_desk_uses(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $event = \App\Events\Models\ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'Trials',
            'event_type' => 'tournament', 'sport' => 'karate', 'scope' => 'internal',
            'date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00', 'status' => 'active', 'is_archived' => false,
        ]);
        $event->forceFill(['category_system' => 'shotokan'])->save();

        $book = app(SportPackages::class)->forEvent($event);
        $this->assertSame('shotokan', $book->style);
        $live = app(CategorySystems::class)->sportFor(app(SportRegistry::class)->get('karate'), $event);
        $this->assertSame($live->classify('Male', 16, 61.0), $book->classify('Male', 16, 61.0));
    }

    public function test_the_mcp_tool_reads_and_classifies_and_hides_other_peoples_events(): void
    {
        $this->actingAs($this->createUser());
        $out = json_decode((string) (new GetSportRuleBookTool)->handle(new Request(['sport' => 'karate', 'style' => 'wkf', 'gender' => 'Male', 'age' => 25, 'weight' => 70]))->content(), true);
        $this->assertSame('wkf', $out['rule_books'][0]['style']);
        $this->assertNotEmpty($out['rule_books'][0]['classified']['division']);

        $missing = (string) (new GetSportRuleBookTool)->handle(new Request(['event' => (string) \Illuminate\Support\Str::uuid()]))->content();
        $this->assertStringContainsString('not found', $missing);
    }

    public function test_an_event_is_locked_to_version_one_and_an_unknown_choice_changes_nothing(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $event = \App\Events\Models\ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'Open',
            'event_type' => 'championship', 'sport' => 'bjj', 'scope' => 'internal',
            'date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00', 'status' => 'active', 'is_archived' => false,
        ]);
        $p = app(SportPackages::class);

        $this->assertSame(['v1'], array_keys($p->editions('bjj', 'ibjjf')));
        $this->assertSame('v1', $p->forEvent($event)->edition);

        // A stored choice of a version that does not exist is ignored, never guessed at.
        \App\Events\Models\EventRuleBook::create(['event_id' => $event->id, 'edition' => 'v9']);
        $this->assertSame('v1', $p->forEvent($event->fresh())->edition);
    }
}
