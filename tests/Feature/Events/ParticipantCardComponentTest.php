<?php

namespace Tests\Feature\Events;

use App\Sports\Combat\BeltIcon;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * <x-participant-card> is STANDALONE (CLAUDE.md → Components Are Standalone).
 *
 * The entry list's own tests prove the card works where it is used today. These
 * prove the harder thing: that it works where it is NOT used yet — rendered on
 * its own, with nothing around it, no page script, no partial included first
 * and no init to call. That is the difference between a component and a
 * fragment somebody extracted.
 *
 * The contract's own reuse test, asked here as assertions:
 *   · can it be used on another page without rewriting internals?
 *   · does it depend on hidden external script or page-only structure?
 *   · are its inputs and outputs clear?
 *   · can it initialise itself safely?
 */
class ParticipantCardComponentTest extends TestCase
{
    /** @param array<string, mixed> $person */
    private function render(array $person, array $props = []): string
    {
        $attrs = '';

        foreach ($props as $key => $value) {
            $attrs .= is_bool($value)
                ? ' :'.$key.'="'.($value ? 'true' : 'false').'"'
                : ' :'.$key.'="$'.$key.'"';
        }

        /* ⚠️ `@once` is remembered by the view factory for its lifetime, not
           per render — so a second Blade::render() in the same test would draw
           a card with no stylesheet and no runtime, and every singleton
           assertion would pass or fail for the wrong reason. Each render here
           gets a clean factory, which is what a fresh page load is. */
        view()->flushState();

        /* Rendered with NOTHING around it but the two stacks every layout on
           this platform provides (`styles` and `scripts` — the one host
           requirement the component documents). No wrapper, no page script, no
           partial included first. If the card needs a host to have done
           anything else, this is where that shows. */
        return Blade::render(
            '<x-participant-card :person="$person"'.$attrs.' />@stack(\'styles\')@stack(\'scripts\')',
            ['person' => $person] + $props,
        );
    }

    private function person(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mohammed Ali Saloom',
            'uuid' => '2eefc405-360a-469b-966e-f7eda4d188c9',
            'gender' => 'Male',
            'age' => 34,
            'life_stage' => 'Young Adult',
            'country' => 'BH',
            'club' => 'Victorian Academy Jiu-Jitsu',
            'club_logo' => null,
            'photo' => null,
            'activity' => 'Gi + No Gi',
            'belt_icon' => BeltIcon::for('white'),
            'division_rows' => [['id' => 7, 'name' => 'Group C (-60) Adult [Gi]']],
        ], $overrides);
    }

    public function test_a_name_is_the_only_thing_it_needs(): void
    {
        $html = $this->render(['name' => 'Just A Name']);

        $this->assertStringContainsString('class="pcard"', $html);
        $this->assertStringContainsString('Just A Name', $html);

        // Every optional row simply does not draw.
        $this->assertStringNotContainsString('class="chip"', $html);
        $this->assertStringNotContainsString('class="status"', $html);
        $this->assertStringNotContainsString('class="divs"', $html);
        $this->assertStringNotContainsString('class="fmt"', $html);
    }

    public function test_it_brings_its_own_stylesheet_and_runtime(): void
    {
        $html = $this->render($this->person());

        // The stylesheet: a page that has never heard of this card still draws
        // it correctly.
        $this->assertStringContainsString('.pcard .gbar', $html);
        $this->assertStringContainsString('.lightbox.open', $html);

        // The behaviour: the photo lightbox and the tap handler travel with it.
        $this->assertStringContainsString('id="lightbox"', $html);
        $this->assertStringContainsString('window.openLightbox', $html);
        $this->assertStringContainsString('window.onCardTap', $html);
    }

    /*
     * The "one stylesheet however many cards" half of this lives in
     * ParticipantCardTest, against a real page with several entrants on it.
     * Asserting it here meant rendering a loop through Blade::render(), where
     * the stack a component pushes to is flushed between iterations by the view
     * factory's own bookkeeping — a fact about the test harness, not about the
     * component, and a test that fails for that reason teaches the next person
     * the wrong lesson.
     */

    /**
     * The organiser's sheet is part of the card, and only travels with a reader
     * who manages the event.
     */
    public function test_the_action_sheet_ships_only_for_a_manager(): void
    {
        $plain = $this->render($this->person());

        $this->assertStringNotContainsString('id="participant-sheet"', $plain);
        $this->assertStringNotContainsString('data-member-id', $plain);

        $event = 'cac87b31-69cf-495f-93cc-3344e085ac20';
        $entry = 2248;
        $managed = $this->render($this->person(), compact('event', 'entry') + ['manage' => true]);

        $this->assertStringContainsString('id="participant-sheet"', $managed);
        $this->assertStringContainsString('data-member-id', $managed);
        $this->assertStringContainsString('data-entry="2248"', $managed);
        // It asks the desk to open rather than owning one: the host provides it.
        $this->assertStringContainsString('open-verification', $managed);
    }

    /** A chip is a door only when the host says where it leads. */
    public function test_a_division_chip_is_inert_without_a_draw_url(): void
    {
        $inert = $this->render($this->person());

        $this->assertStringContainsString('class="div closed"', $inert);
        $this->assertStringContainsString('onDrawClosed()', $inert);
        $this->assertStringNotContainsString('onDivisionTap(this)', $inert);

        $drawUrl = 'https://example.test/e/abc/draw';
        $live = $this->render($this->person(), compact('drawUrl'));

        $this->assertStringContainsString('onDivisionTap(this)', $live);
        $this->assertStringContainsString($drawUrl, str_replace('\/', '/', $live));
        $this->assertStringNotContainsString('class="div closed"', $live);
    }

    /**
     * The readiness rows are drawn from what it is HANDED and never decided
     * here — the host scopes them, and this component cannot leak what it was
     * not given.
     */
    public function test_readiness_draws_only_when_it_is_handed_one(): void
    {
        $this->assertStringNotContainsString('class="status"', $this->render($this->person()));

        $ready = ['status' => 'paid', 'paid' => true, 'weighed' => false, 'weight' => '72.4', 'weight_verified' => false];
        $html = $this->render($this->person(), compact('ready'));

        $this->assertSame(2, substr_count($html, 'class="status"'));
        $this->assertStringContainsString('data-status="ready"', $html);   // paid, green
        $this->assertStringContainsString('data-status="paid"', $html);    // not weighed, amber
        $this->assertStringContainsString('72.4', $html);
    }

    /** A pale belt is outlined so a white one is visible on a white card. */
    public function test_it_draws_the_rank_it_is_given(): void
    {
        $white = $this->render($this->person());
        $this->assertStringContainsString(BeltIcon::OUTLINE, $white);
        // `class="belt-name"` and not `belt-name`: the stylesheet the card
        // brings with it names that class too, and a test that matched CSS
        // would say the belt is drawn on a card that draws nothing.
        $this->assertStringContainsString('class="belt-name"', $white);

        $black = $this->render($this->person(['belt_icon' => BeltIcon::for('black')]));
        $this->assertStringNotContainsString(BeltIcon::OUTLINE, $black);

        $none = $this->render($this->person(['belt_icon' => null]));
        $this->assertStringNotContainsString('class="belt-name"', $none);
    }
}
