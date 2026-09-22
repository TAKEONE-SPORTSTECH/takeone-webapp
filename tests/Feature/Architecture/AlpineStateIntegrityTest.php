<?php

namespace Tests\Feature\Architecture;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Models\ClubEvent;
use App\Events\Models\EventParticipantBan;
use Tests\TestCase;

/**
 * Every `x-data` on the organiser's screens closes where it means to.
 *
 * ── The bug this exists for ─────────────────────────────────────────────────
 *
 * An Alpine component writes a JavaScript object into an HTML attribute, and a
 * double-quoted attribute ends at the first double quote in it. So one ordinary
 * pair of quotes inside the expression — in a JS comment, a string, anywhere —
 * silently truncates it. The browser then reads the rest of the JavaScript as
 * attributes until it finds a `>`, which an arrow function obligingly supplies,
 * and prints everything after that onto the page as text.
 *
 * Nothing on the server side notices. Blade compiles, the view renders, PHPUnit
 * is green, `php -l` is happy. It is visible only to somebody looking at the
 * page — which is how both known instances were found:
 *
 *   · `<x-event-bans>`, reported from the live console on 2026-09-16, hours
 *     after it shipped, over the words "are you sure" in a comment;
 *   · `personal/event-create.blade.php`, found by the sweep that followed, over
 *     the word "League" in a comment — which had been truncating the event
 *     CREATE AND EDIT form's entire Alpine state since it shipped.
 *
 * ── Why a runtime test and not a grep ───────────────────────────────────────
 *
 * Source cannot answer it. `{{ __("some.key") }}` has double quotes in the
 * file and none in the output (Blade escapes them to `&quot;`), and `{{-- a
 * "quoted" comment --}}` never reaches the browser at all. Only the rendered
 * HTML knows. So this renders the pages and checks the invariant there: an
 * `x-data` holding an object literal has balanced braces, brackets and parens.
 * A truncated one never does — the cut always lands mid-expression.
 *
 * ── Adding a page ───────────────────────────────────────────────────────────
 *
 * Put it in `pages()`. The cost is one render; the thing it catches is invisible
 * until a user reports it.
 */
class AlpineStateIntegrityTest extends TestCase
{
    private function scenario(): array
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->createClub($organiser, ['country' => 'BH', 'club_name' => 'Emperor Taekwondo']);
        $organiser->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $organiser->id,
            'title' => 'Spring Open', 'event_type' => 'championship', 'sport' => 'taekwondo',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        // Something in the lists, so components render their populated branches
        // rather than their empty ones — a truncation can hide in either.
        $barred = $this->createUser(['full_name' => 'Barred Person']);
        EventParticipantBan::create([
            'scope' => 'event', 'event_id' => $event->id, 'tenant_id' => $club->id,
            'user_id' => $barred->id, 'reason' => 'withdrew after the draw was cut',
            'created_by' => $organiser->id,
        ]);

        return [$organiser->fresh(), $club, $event];
    }

    /** @return array<string, string> label => url */
    private function pages(ClubEvent $event): array
    {
        return [
            'the event console' => "/me/events/{$event->uuid}/manage",
            'the entry list' => "/me/events/{$event->uuid}/people",
            'the create form' => '/me/events/create',
            'the edit form' => "/me/events/{$event->uuid}/edit",
            'the divisions screen' => "/me/events/{$event->uuid}/divisions",
            'the officiating sheet' => "/me/events/{$event->uuid}/officiating",
            'the clubs screen' => "/me/events/{$event->uuid}/clubs",
        ];
    }

    public function test_no_alpine_state_is_cut_short_by_a_quote_on_any_organiser_screen(): void
    {
        [$organiser, , $event] = $this->scenario();

        foreach ($this->pages($event) as $label => $url) {
            $html = $this->actingAs($organiser)->get($url)->assertOk()->getContent();

            preg_match_all('/\sx-data="([^"]*)"/', $html, $matches);

            $this->assertNotEmpty(
                $matches[1],
                "{$label} renders no Alpine state at all — either the page changed or this scan is looking at nothing",
            );

            foreach ($matches[1] as $i => $state) {
                // `x-data="someComponent()"` and `x-data="{}"` are both fine; a
                // bare name has nothing to balance.
                if (! str_contains($state, '{')) {
                    continue;
                }

                foreach ([['{', '}'], ['[', ']'], ['(', ')']] as [$open, $close]) {
                    $this->assertSame(
                        substr_count($state, $open),
                        substr_count($state, $close),
                        "x-data #{$i} on {$label} is unbalanced in {$open}{$close}. Almost certainly a literal double "
                        ."quote inside the expression: it ends the attribute, and the rest of the JavaScript is "
                        ."printed on the page as text. The fix is to take the quotes out of the expression (a JS "
                        ."comment is the usual culprit), never to escape them. Starts: ".substr($state, 0, 100),
                    );
                }
            }
        }
    }
}
