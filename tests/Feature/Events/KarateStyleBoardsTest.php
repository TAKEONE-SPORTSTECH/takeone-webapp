<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Scoreboard\Sports\Karate\Mat\Scoring;
use App\Scoreboard\Sports\Karate\Styles\KarateStyles;
use Tests\TestCase;

/**
 * Karate style scoreboards (App\Scoreboard\Sports\Karate\Styles\KarateStyles).
 *
 * What this protects: with the switch off, or for an event with no style,
 * every Karate mat runs the one shared board exactly as before; with it on, a
 * styled event gets its style's own console, wall board and engine — and the
 * shared plumbing (state, scoring commands) still answers the same way.
 */
class KarateStyleBoardsTest extends TestCase
{
    private const MAT = 'Mat 1';

    /** @return array{0: \App\Members\Models\User, 1: ClubEvent} */
    private function scenario(?string $belts = null): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Style Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);
        if ($belts) {
            $event->forceFill(['belt_system' => $belts])->save();
        }

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men', 'sort_order' => 1]);
        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id, 'round' => 'Final', 'phase' => 'finals',
            'slot' => 0, 'a_name' => 'Ali', 'b_name' => 'Bader', 'court' => self::MAT, 'status' => 'upcoming',
        ]);

        return [$owner, $event->fresh()];
    }

    public function test_with_the_switch_off_every_event_gets_the_shared_board(): void
    {
        config(['features.karate_style_boards' => false]);
        [$owner, $event] = $this->scenario('kyokushin');

        $this->assertNull(KarateStyles::of($event));
        $this->assertSame('scoreboard::karate.scoreboard.control', KarateStyles::view($event, 'control'));
        $this->assertSame(Scoring::class, get_class(KarateStyles::scoring($event)));

        $this->actingAs($owner)->get("/karate/control/{$event->uuid}?mat=".urlencode(self::MAT))
            ->assertOk()->assertViewIs('scoreboard::karate.scoreboard.control');
    }

    public function test_an_event_with_no_style_keeps_the_shared_board_when_switched_on(): void
    {
        config(['features.karate_style_boards' => true]);
        [, $event] = $this->scenario();

        $this->assertNull(KarateStyles::of($event));
        $this->assertSame('scoreboard::karate.scoreboard.mat', KarateStyles::view($event, 'mat'));
    }

    public function test_a_kyokushin_event_gets_its_own_console_board_and_engine(): void
    {
        config(['features.karate_style_boards' => true]);
        [$owner, $event] = $this->scenario('kyokushin');

        $this->assertSame('kyokushin', KarateStyles::of($event));
        $this->assertSame('scoreboard::karate.styles.kyokushin.mat', KarateStyles::view($event, 'mat'));
        $this->assertInstanceOf(\App\Scoreboard\Sports\Karate\Styles\Kyokushin\Scoring::class, KarateStyles::scoring($event));

        $this->actingAs($owner)->get("/karate/control/{$event->uuid}?mat=".urlencode(self::MAT))
            ->assertOk()->assertViewIs('scoreboard::karate.styles.kyokushin.control');

        // The style's engine runs the same commands the shared one does.
        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}", ['mat' => self::MAT, 'command' => 'rules', 'gap' => 6])
            ->assertOk();
    }

    public function test_every_style_has_its_screens(): void
    {
        foreach (array_keys(KarateStyles::STYLES) as $style) {
            foreach (['control', 'mat', 'winner-celebration'] as $screen) {
                $this->assertTrue(view()->exists("scoreboard::karate.styles.{$style}.{$screen}"), "{$style}/{$screen} is missing");
            }
        }
    }

    public function test_the_organisers_console_has_the_draw_button(): void
    {
        [$owner, $event] = $this->scenario('kyokushin');

        $this->actingAs($owner)->get("/karate/control/{$event->uuid}?mat=".urlencode(self::MAT))
            ->assertOk()
            ->assertSee(route('me.events.bracket', $event->uuid), false)
            ->assertSee(__('events::events.tablet_draw_button'));
    }

    /** A Kyokushin mat with a bout loaded, switch on. */
    private function liveKyokushinMat(): array
    {
        config(['features.karate_style_boards' => true]);
        [$owner, $event] = $this->scenario('kyokushin');
        $match = EventMatch::where('event_id', $event->id)->first();
        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}", ['mat' => self::MAT, 'command' => 'load', 'match_id' => $match->id])->assertOk();

        return [$owner, $event];
    }

    private function cmd(ClubEvent $event, array $payload)
    {
        return $this->postJson("/karate/control/{$event->uuid}", ['mat' => self::MAT] + $payload);
    }

    public function test_an_organiser_designs_the_mats_scoring_buttons(): void
    {
        [, $event] = $this->liveKyokushinMat();

        $state = $this->cmd($event, ['command' => 'buttons', 'buttons' => [
            ['label' => 'Waza-ari', 'value' => 1, 'effect' => 'win_after', 'times' => 2],
            ['label' => 'Ippon', 'value' => 2, 'effect' => 'win'],
            ['label' => 'Good technique', 'value' => 0, 'effect' => 'add'],
        ]])->assertOk()->json('state');

        $this->assertCount(3, $state['buttons']);
        $this->assertSame('Waza-ari', $state['buttons'][0]['label']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{1,12}$/', $state['buttons'][0]['id']);

        // Saved for the event: a cold mat comes back with them.
        \App\Scoreboard\Sports\Karate\Mat\MatState::forget($event, self::MAT);
        $this->assertSame('Ippon', \App\Scoreboard\Sports\Karate\Mat\MatState::load($event->fresh(), self::MAT)->buttons[1]['label']);

        // And back to the style's own.
        $this->assertNull($this->cmd($event, ['command' => 'buttons', 'buttons_default' => true])->json('state.buttons'));
    }

    public function test_presses_score_correct_and_win(): void
    {
        [, $event] = $this->liveKyokushinMat();
        $ids = collect($this->cmd($event, ['command' => 'buttons', 'buttons' => [
            ['label' => 'Waza-ari', 'value' => 1, 'effect' => 'win_after', 'times' => 2],
            ['label' => 'Ippon', 'value' => 2, 'effect' => 'win'],
        ]])->json('state.buttons'))->pluck('id');

        $s = $this->cmd($event, ['command' => 'button', 'side' => 'aka', 'button' => $ids[0]])->assertOk()->json('state');
        $this->assertSame(1, $s['akaScore']);
        $this->assertFalse($s['finished']);
        $this->assertSame('Waza-ari', $s['lastEvent']['label']);

        // Correct takes the last press back, points and count.
        $s = $this->cmd($event, ['command' => 'button_undo', 'side' => 'aka'])->json('state');
        $this->assertSame(0, $s['akaScore']);

        // Two waza-ari make ippon: the second press ends it for the table to confirm.
        $this->cmd($event, ['command' => 'button', 'side' => 'aka', 'button' => $ids[0]]);
        $s = $this->cmd($event, ['command' => 'button', 'side' => 'aka', 'button' => $ids[0]])->json('state');
        $this->assertTrue($s['finished']);
        $this->assertTrue($s['awaitingDecision']);
        $this->assertSame('aka', $s['winner']);
        $this->assertSame('Waza-ari × 2', $s['winNote']);

        // A press on a finished bout changes nothing.
        $s = $this->cmd($event, ['command' => 'button', 'side' => 'ao', 'button' => $ids[1]])->json('state');
        $this->assertSame(0, $s['aoScore']);
    }

    public function test_a_win_button_ends_the_bout_at_once(): void
    {
        [, $event] = $this->liveKyokushinMat();
        $id = $this->cmd($event, ['command' => 'buttons', 'buttons' => [['label' => 'Ippon', 'value' => 2, 'effect' => 'win']]])->json('state.buttons.0.id');

        $s = $this->cmd($event, ['command' => 'button', 'side' => 'ao', 'button' => $id])->json('state');
        $this->assertSame('ao', $s['winner']);
        $this->assertSame(2, $s['aoScore']);
    }

    public function test_button_lists_are_held_to_their_limits(): void
    {
        [, $event] = $this->liveKyokushinMat();

        $this->cmd($event, ['command' => 'buttons', 'buttons' => [['label' => 'X', 'value' => 99, 'effect' => 'add']]])->assertStatus(422);
        $this->cmd($event, ['command' => 'buttons', 'buttons' => [['label' => 'X', 'value' => 1, 'effect' => 'explode']]])->assertStatus(422);
        $this->cmd($event, ['command' => 'buttons', 'buttons' => array_fill(0, 9, ['label' => 'X', 'value' => 1, 'effect' => 'add'])])->assertStatus(422);
    }

    public function test_a_mat_without_a_style_refuses_the_button_commands(): void
    {
        config(['features.karate_style_boards' => true]);
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}", ['mat' => self::MAT, 'command' => 'buttons', 'buttons_default' => true])
            ->assertStatus(422);
    }

    public function test_kyokushin_scores_waza_ari_and_ippon_out_of_the_box(): void
    {
        [, $event] = $this->liveKyokushinMat();

        $s = $this->cmd($event, ['command' => 'button', 'side' => 'ao', 'button' => 'wazaari'])->assertOk()->json('state');
        // Waza-ari is a half point.
        $this->assertEquals(0.5, $s['aoScore']);
        $this->assertFalse($s['finished']);

        // The second waza-ari is awasete ippon.
        $s = $this->cmd($event, ['command' => 'button', 'side' => 'ao', 'button' => 'wazaari'])->json('state');
        $this->assertSame('ao', $s['winner']);
        $this->assertTrue($s['awaitingDecision']);
    }

    public function test_kyokushin_has_no_point_gap_and_disqualifies_at_shikkaku(): void
    {
        [, $event] = $this->liveKyokushinMat();
        $this->cmd($event, ['command' => 'buttons', 'buttons' => [['label' => 'Point', 'value' => 10, 'effect' => 'add']]]);
        $id = $this->cmd($event, ['command' => 'button', 'side' => 'aka', 'button' => 'x'])->json('state.buttons.0.id');

        $s = $this->cmd($event, ['command' => 'button', 'side' => 'aka', 'button' => $id])->json('state');
        $this->assertSame(10, $s['akaScore']);
        $this->assertFalse($s['finished'], 'a ten-point lead ends nothing in Kyokushin');

        $s = $this->cmd($event, ['command' => 'penalty', 'side' => 'aka', 'level' => 3])->json('state');
        $this->assertFalse($s['finished']);
        $s = $this->cmd($event, ['command' => 'penalty', 'side' => 'aka', 'level' => 4])->json('state');
        $this->assertSame('ao', $s['winner']);
        $this->assertSame('shikkaku', $s['winReason']);
    }

    public function test_hantei_majority_wins_and_a_draw_goes_to_encho_then_weight(): void
    {
        [, $event] = $this->liveKyokushinMat();

        // 2–2–1 is no majority: a draw, waiting for an extension.
        $s = $this->cmd($event, ['command' => 'hantei', 'flags_aka' => 2, 'flags_ao' => 2, 'flags_draw' => 1])->assertOk()->json('state');
        $this->assertNull($s['winner']);
        $this->assertTrue($s['finished']);
        $this->assertNull($s['tally']['hantei']['winner']);

        $s = $this->cmd($event, ['command' => 'encho'])->json('state');
        $this->assertSame(1, $s['tally']['encho']);
        $this->assertFalse($s['finished']);
        $this->assertEquals(120, $s['remaining']);
        $this->assertArrayNotHasKey('hantei', $s['tally']);

        // Too many flags is refused.
        $this->cmd($event, ['command' => 'hantei', 'flags_aka' => 4, 'flags_ao' => 2])->assertStatus(422);

        // 3–1–1 wins.
        $s = $this->cmd($event, ['command' => 'hantei', 'flags_aka' => 3, 'flags_ao' => 1, 'flags_draw' => 1])->json('state');
        $this->assertSame('aka', $s['winner']);
        $this->assertSame('Hantei 3–1', $s['winNote']);

        // Three extensions, then no more.
        $this->cmd($event, ['command' => 'encho']);
        $this->cmd($event, ['command' => 'encho']);
        $this->cmd($event, ['command' => 'encho'])->assertStatus(422);

        $s = $this->cmd($event, ['command' => 'weight', 'winner' => 'ao'])->json('state');
        $this->assertSame('ao', $s['winner']);
        $this->assertSame('Weight decision', $s['winNote']);
    }

    public function test_the_shared_board_keeps_its_own_ladder_and_gap(): void
    {
        // A WKF event: hansoku at the fifth rung, and the commands Kyokushin adds are refused.
        config(['features.karate_style_boards' => true]);
        [$owner, $event] = $this->scenario();
        $match = EventMatch::where('event_id', $event->id)->first();
        $this->actingAs($owner);
        $this->cmd($event, ['command' => 'load', 'match_id' => $match->id]);

        $s = $this->cmd($event, ['command' => 'penalty', 'side' => 'aka', 'level' => 4])->json('state');
        $this->assertFalse($s['finished']);
        $s = $this->cmd($event, ['command' => 'penalty', 'side' => 'aka', 'level' => 5])->json('state');
        $this->assertSame('hansoku', $s['winReason']);

        $this->cmd($event, ['command' => 'hantei', 'flags_aka' => 3])->assertStatus(422);
    }

    public function test_buttons_take_half_points_and_nothing_finer(): void
    {
        [, $event] = $this->liveKyokushinMat();

        $id = $this->cmd($event, ['command' => 'buttons', 'buttons' => [['label' => 'Half', 'value' => 0.5, 'effect' => 'add']]])
            ->assertOk()->json('state.buttons.0.id');
        $this->cmd($event, ['command' => 'button', 'side' => 'aka', 'button' => $id]);
        $s = $this->cmd($event, ['command' => 'button', 'side' => 'aka', 'button' => $id])->json('state');

        // Two halves are a whole point, stored as a whole number again.
        $this->assertSame(1, $s['akaScore']);

        $this->cmd($event, ['command' => 'buttons', 'buttons' => [['label' => 'Odd', 'value' => 0.3, 'effect' => 'add']]])->assertStatus(422);
    }

    public function test_a_whole_score_keeps_its_shape(): void
    {
        $state = \App\Scoreboard\Sports\Karate\Mat\MatState::fromArray(['akaScore' => 3, 'aoScore' => 1.5]);

        $this->assertSame(3, $state->akaScore);
        $this->assertSame(1.5, $state->aoScore);
    }

    public function test_an_organiser_designs_the_penalty_ladder_with_score_effects(): void
    {
        [, $event] = $this->liveKyokushinMat();

        $state = $this->cmd($event, ['command' => 'penalties', 'penalties' => [
            ['label' => 'Chui', 'effect' => 'none'],
            ['label' => 'Genten', 'effect' => 'deduct', 'value' => 0.5],
            ['label' => 'Chui 3', 'effect' => 'award', 'value' => 0.5],
            ['label' => 'Shikkaku', 'effect' => 'none', 'ends' => true],
        ]])->assertOk()->json('state');
        $this->assertCount(4, $state['penalties']);

        // Up to Genten: a half point off Aka.
        $s = $this->cmd($event, ['command' => 'penalty', 'side' => 'aka', 'level' => 2])->json('state');
        $this->assertSame(2, $s['akaPen']);
        $this->assertEquals(-0.5, $s['akaScore']);

        // Chui 3: a half point to Ao.
        $s = $this->cmd($event, ['command' => 'penalty', 'side' => 'aka', 'level' => 3])->json('state');
        $this->assertEquals(0.5, $s['aoScore']);

        // Taken back off entirely: no trace on either score.
        $s = $this->cmd($event, ['command' => 'penalty', 'side' => 'aka', 'level' => 0])->json('state');
        $this->assertSame(0, $s['akaScore']);
        $this->assertSame(0, $s['aoScore']);
        $this->assertFalse($s['finished']);

        // Straight to Shikkaku: Ao wins.
        $s = $this->cmd($event, ['command' => 'penalty', 'side' => 'aka', 'level' => 4])->json('state');
        $this->assertSame('ao', $s['winner']);
        $this->assertSame('shikkaku', $s['winReason']);

        // Saved for the event, and back to Kyokushin's own on request.
        \App\Scoreboard\Sports\Karate\Mat\MatState::forget($event, self::MAT);
        $this->assertSame('Genten', \App\Scoreboard\Sports\Karate\Mat\MatState::load($event->fresh(), self::MAT)->penalties[1]['label']);
        $this->assertNull($this->cmd($event, ['command' => 'penalties', 'penalties_default' => true])->json('state.penalties'));
    }

    public function test_penalty_ladders_are_held_to_their_limits(): void
    {
        [, $event] = $this->liveKyokushinMat();

        $this->cmd($event, ['command' => 'penalties', 'penalties' => [['label' => 'X', 'effect' => 'explode']]])->assertStatus(422);
        $this->cmd($event, ['command' => 'penalties', 'penalties' => [['label' => 'X', 'effect' => 'deduct', 'value' => 0.3]]])->assertStatus(422);
        $this->cmd($event, ['command' => 'penalties', 'penalties' => array_fill(0, 9, ['label' => 'X', 'effect' => 'none'])])->assertStatus(422);
    }
}
