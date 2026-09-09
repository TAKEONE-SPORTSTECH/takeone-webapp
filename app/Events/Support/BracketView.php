<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Sports\Combat\Engine\GroupEngine;
use Illuminate\Support\Collection;

/**
 * The one bracket payload every bracketed event type speaks.
 *
 * A tournament bracket is not a Taekwondo idea — a karate kumite draw, a padel
 * cup and a football knockout are the same picture with different words. So the
 * SHAPE lives here, shared, and each package decides what fills it (its round
 * names, its divisions, whether it has a bench at all). The renderer
 * (<x-tournament-bracket>) knows only this shape and never which sport it is
 * drawing.
 *
 * Bracket geometry is implicit and matches how DrawEngine lays a division out
 * and how Advancement walks it: within a round, bout i feeds bout ⌊i/2⌋ of the
 * next round, taking side A when i is even and side B when it is odd. The
 * renderer redraws the connectors from that rule, so no edge list is shipped
 * and the two can never disagree.
 *
 * Only what a bracket screen shows is returned: who is fighting, their seed,
 * their score, and where. Nothing about payment, weight, contact or club
 * membership crosses into this payload.
 */
class BracketView
{
    /** competitor_id => photo url, rebuilt per division. */
    private array $faces = [];

    /** Every division of an event, in display order. */
    public function divisions(ClubEvent $event): array
    {
        return $event->categories()
            ->with([
                'matches',
                'registrations' => fn ($q) => $q->where('role', 'participant')
                    ->with('user:id,full_name,name,profile_picture,profile_picture_is_public,updated_at'),
            ])
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (EventCategory $c) => $this->division($c))
            ->values()->all();
    }

    /** One division: its rounds, its unplaced entrants, its podium. */
    public function division(EventCategory $c): array
    {
        /*
         * A heading is a TITLE in the organiser's list, not a division: it
         * holds nobody and there is nothing to draw. It still travels in this
         * payload, in its own position, because that position is the whole
         * point of it — the reader needs "GI" to appear above the five Gi
         * brackets, and the client cannot know where it belongs unless it
         * arrives in order with them.
         *
         * Returned in a shape every consumer can already read: empty rounds,
         * an empty bench, no entrants. A client that has not been taught about
         * headings therefore draws nothing for one rather than breaking.
         */
        if ($c->isHeading()) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'is_heading' => true,
                'weight_class' => null,
                'status' => $c->status,
                'draw_state' => null,
                'entrants' => 0,
                'rounds' => [],
                'bench' => [],
                'podium' => [],
            ];
        }

        $matches = $c->matches->sortBy('slot')->values();

        // Competitors only — a division's registrations also hold spectators and
        // coaches, and neither belongs in a draw. Filtered here rather than
        // trusting the caller's eager load, so a division loaded any way at all
        // still yields the same bracket.
        $entrants = $c->registrations->where('role', 'participant');

        // A bout stores only the name it prints; the face comes from the entry
        // behind it, so build the lookup once per division rather than joining
        // every side back to a registration.
        $this->faces = $entrants
            ->mapWithKeys(fn (ClubEventRegistration $r) => [$r->id => $this->photo($r)])
            ->all();

        return [
            'id' => $c->id,
            'name' => $c->name,
            'is_heading' => false,
            'weight_class' => $c->weight_class ?: null,
            'status' => $c->status,
            'draw_state' => $c->draw_state,           // provisional | final | manual | null
            'entrants' => $entrants->count(),
            'rounds' => $this->rounds($matches),
            'bench' => $this->bench($entrants, $matches),
            'podium' => $c->podium ?: [],

            /*
             * A GROUP division carries a table as well as its bouts.
             *
             * The board can draw a ladder from `rounds` alone, because a ladder
             * IS its bouts. A group's answer — who is winning — exists in none
             * of them: it is derived from all of them together, so it has to
             * travel beside them (App\Sports\Combat\Engine\GroupEngine).
             * `format` is here so the board knows which of the two it is
             * looking at without inferring it from a round's name.
             */
            'format' => $c->format ?: EventCategory::FORMAT_KNOCKOUT,
            'standings' => ($c->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_ROUND_ROBIN
                ? $this->standings($c)
                : [],
        ];
    }

    /**
     * Bouts grouped into rounds, earliest first, each round in slot order —
     * the same ordering Advancement uses to decide what feeds what.
     *
     * @param  Collection<int, EventMatch>  $matches
     */
    private function rounds(Collection $matches): array
    {
        return $matches
            ->groupBy('round')
            ->sortBy(fn (Collection $bouts) => $bouts->min('slot'))
            ->map(fn (Collection $bouts, string $name) => [
                'name' => $name,
                // A group's bouts feed the knockout through the TABLE, not by
                // position, so the board must not draw a line from them (see
                // drawLinks in the bracket runtime).
                'group' => $name === GroupEngine::ROUND,
                'matches' => $bouts->sortBy('slot')->values()
                    ->map(fn (EventMatch $m) => $this->match($m))->all(),
            ])
            ->values()->all();
    }

    /**
     * The table, with the faces the board already knows how to draw.
     *
     * @return array<int, array<string, mixed>>
     */
    private function standings(EventCategory $c): array
    {
        return array_map(function (array $row) {
            $row['photo'] = $row['competitor_id'] ? ($this->faces[$row['competitor_id']] ?? null) : null;

            return $row;
        }, app(GroupEngine::class)->standings($c));
    }

    private function match(EventMatch $m): array
    {
        return [
            'id' => $m->id,
            'no' => $m->match_no,
            'court' => $m->court ?: null,
            'time' => $m->scheduled_time ?: null,
            'status' => $m->status,                   // upcoming | live | done
            'winner' => $m->winner,                   // 'a' | 'b' | null
            'a' => $this->side($m, 'a'),
            'b' => $this->side($m, 'b'),
        ];
    }

    private function side(EventMatch $m, string $side): array
    {
        $competitorId = $m->{$side.'_competitor_id'};

        return [
            'competitor_id' => $competitorId,
            'name' => $m->{$side.'_name'},
            'seed' => $m->{$side.'_seed'},
            'country' => $m->{$side.'_country'},
            'score' => $m->{$side.'_score'},
            'provisional' => (bool) $m->{$side.'_provisional'},
            'photo' => $competitorId ? ($this->faces[$competitorId] ?? null) : null,
        ];
    }

    /**
     * Entrants with no slot in the draw — the tray an operator drags from when
     * arranging by hand. Populated whether the draw was auto-cut (someone was
     * pulled out of it) or never cut at all (a bracket built from scratch).
     *
     * @param  Collection<int, ClubEventRegistration>  $entrants
     * @param  Collection<int, EventMatch>  $matches
     */
    private function bench(Collection $entrants, Collection $matches): array
    {
        $placed = $matches->flatMap(fn (EventMatch $m) => $m->competitorIds())->unique()->all();

        return $entrants
            ->reject(fn (ClubEventRegistration $r) => in_array($r->id, $placed, true))
            ->map(fn (ClubEventRegistration $r) => $this->entrant($r))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()->all();
    }

    private function entrant(ClubEventRegistration $r): array
    {
        return [
            'competitor_id' => $r->id,
            'name' => $r->user?->full_name ?: $r->user?->name ?: __('events.athlete'),
            'seed' => null,
            'country' => $r->countryCode(),
            // Unpaid or not yet weighed in — in the draw, but at risk of coming
            // out of it when the final one is locked.
            'provisional' => ! $r->paid || $r->weight === null,
            'photo' => $this->photo($r),
        ];
    }

    /**
     * A competitor's face, or null. Honours the athlete's own "show my picture"
     * setting — a bracket is seen by everyone the event's scope reaches, so it
     * is not a place to override that choice. Cache-busted like every other
     * avatar in the platform.
     */
    private function photo(ClubEventRegistration $r): ?string
    {
        $user = $r->user;

        if (! $user?->profile_picture || ! $user->profile_picture_is_public) {
            return null;
        }

        return file_url($user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0);
    }
}
