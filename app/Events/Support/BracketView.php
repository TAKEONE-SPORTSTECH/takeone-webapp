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

    /**
     * competitor id => the club printed under their name, for this division.
     *
     * The board draws a club beside every competitor — it is half of what a
     * corner IS on a competition sheet — and the two kinds of club are the same
     * two everywhere else: a tenant with an account, or a name the organiser
     * wrote down on the day. `App\Events\Support\EntryClub` is the one place
     * that answers for both, so the board can never disagree with the entry
     * list about who somebody fights for.
     *
     * @var array<int, array{name: string, ab: string}|null>
     */
    private array $clubs = [];

    /**
     * competitor id => the flag beside their name, for this division.
     *
     * Built from the REGISTRATIONS, beside the faces and for the same reason —
     * see the note on `side()` for why the bout's own column is not the answer.
     *
     * @var array<int, string|null>
     */
    private array $flags = [];

    /** Every division of an event, in display order. */
    public function divisions(ClubEvent $event): array
    {
        return $event->categories()
            /*
             * A heading is a caption in the organiser's list, not a division:
             * nobody is entered in it and there is nothing to draw. Without
             * this it arrived here as a division with zero rounds, and every
             * reader of this one payload drew it — the manage board, the
             * PUBLIC draw board and the MCP bracket tool all offered "Gi" as
             * an empty bracket.
             */
            ->where('is_heading', false)
            ->with([
                'matches',
                /*
                 * ⚠️ `nationality` IS NOT OPTIONAL IN THIS SELECT.
                 *
                 * A constrained eager load returns only the columns it names,
                 * and a column it did not name reads as NULL rather than
                 * raising — so `countryCode()`, which falls back to the
                 * athlete's own nationality when they compete for nobody,
                 * silently answered "no flag" for every unattached competitor.
                 * On the event that reported it, 77 of 132 corners came out
                 * with no flag at all (2026-09-18). Same trap as the stat
                 * cards' missing `updated_at`.
                 *
                 * `representingTenant` is eager-loaded for the other half of
                 * that answer: the club they compete FOR. One query for the
                 * division rather than one per corner.
                 */
                'registrations' => fn ($q) => $q->where('role', 'participant')
                    ->with([
                        'user:id,full_name,name,nationality,profile_picture,profile_picture_is_public,updated_at',
                        /* club_name is NOT optional here either: the board
                           prints the club under every name, and a constrained
                           eager load that did not name the column would read
                           it back as NULL for every competitor. */
                        'representingTenant:id,club_name,country',
                        'user.memberClubs:id,club_name,country',
                    ]),
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
                'arrangeable' => true,
                'podium' => [],
            ];
        }

        $matches = $c->matches->sortBy('slot')->values();

        // Competitors only — a division's registrations also hold spectators and
        // coaches, and neither belongs in a draw. Filtered here rather than
        // trusting the caller's eager load, so a division loaded any way at all
        // still yields the same bracket.
        $entrants = $c->registrations->where('role', 'participant');

        // A bout stores only the name it prints; the face and the flag come
        // from the entry behind it, so build the lookups once per division
        // rather than joining every side back to a registration.
        $this->faces = $entrants
            ->mapWithKeys(fn (ClubEventRegistration $r) => [$r->id => $this->photo($r)])
            ->all();

        /*
         * The flag follows the CLUB, and a club is one of two things: a tenant,
         * or a club the organiser wrote down for this event (`event_clubs`).
         * `countryCode()` knows only the first, so an athlete entered under a
         * written-down club flew no flag at all on the board while the entry
         * list showed their crest — App\Events\Support\EntryClub is the one
         * place that answers for both, and it is asked once for the division
         * rather than once per corner.
         */
        $written = EntryClub::forMany($c->event_id, $entrants->pluck('id'));

        $this->flags = $entrants
            ->mapWithKeys(fn (ClubEventRegistration $r) => [
                $r->id => $r->countryCode() ?: ($written[$r->id]['country'] ?? null),
            ])
            ->all();

        // ...and the club printed under the name, from the same two sources and
        // in the same order of precedence a tenant beats a written-down club.
        $this->clubs = $entrants
            ->mapWithKeys(fn (ClubEventRegistration $r) => [$r->id => $this->club($r, $written)])
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
            /* The podium, with the face and the club the board draws beside
               each place. The stored column holds only a name, a place and a
               competitor id — enough for a results list, not enough for the
               champion's card — and the two lookups above already hold the
               rest, so this costs nothing beyond the map. */
            'podium' => $this->podium($c),

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

            /*
             * ...and whether this division can be ARRANGED at all.
             *
             * Arranging is a bracket idea — who stands in which slot decides
             * who meets whom. A pool has no such choice: everybody meets
             * everybody, so there is nothing to place and dragging a name out
             * of one fixture only breaks it. Arrangeability therefore belongs
             * to the DIVISION, not to the board: one event may run a bracket
             * the organiser can seed and a pool they cannot, side by side.
             *
             * The board still receives its own `can_arrange` (may this PERSON
             * arrange, is the draw still open); this says whether this
             * particular division is the kind of thing that is arranged at all.
             * Arrangement::move() refuses the same case server-side.
             */
            'arrangeable' => ($c->format ?: EventCategory::FORMAT_KNOCKOUT) !== EventCategory::FORMAT_ROUND_ROBIN,
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
            /* Was that winner FOUGHT for, or is it a walkover the draw wrote?
               The board needs to say which — "Decided" and "Walks over" are
               not the same sentence — and the two are otherwise identical. */
            'bye' => (bool) $m->won_by_bye,
            'a' => $this->side($m, 'a'),
            'b' => $this->side($m, 'b'),
        ];
    }

    /**
     * One corner of a bout.
     *
     * ⚠️ THE FLAG COMES FROM THE ENTRY, NOT FROM THE BOUT.
     *
     * `event_matches.{a,b}_country` is a copy taken when the draw was cut, and
     * a copy goes stale the moment anything it was copied from changes — an
     * athlete claimed by a club, a club's own country corrected, a disowned
     * claim falling back to a passport. On the event that reported it
     * (2026-09-18: "I don't see the flags of the other people's
     * nationalities") 77 of 132 slots held NULL and the remaining 55 all held
     * the host country, so a Turkmen and a Kuwaiti competitor were drawn with
     * no flag at all while the board looked uniformly Bahraini.
     *
     * `ClubEventRegistration::countryCode()` is the one answer, and it is the
     * same one the entrants list below and the participant cards already use —
     * the club they compete for, or their own nationality when they compete for
     * nobody. The stored column stays as the fallback for a corner with no
     * registration behind it: a name typed at the desk that was never linked to
     * an entry still prints whatever was recorded with it.
     */
    private function side(EventMatch $m, string $side): array
    {
        $competitorId = $m->{$side.'_competitor_id'};

        return [
            'competitor_id' => $competitorId,
            'name' => $m->{$side.'_name'},
            'seed' => $m->{$side.'_seed'},
            'country' => ($competitorId ? ($this->flags[$competitorId] ?? null) : null)
                ?: $m->{$side.'_country'},
            'score' => $m->{$side.'_score'},
            'provisional' => (bool) $m->{$side.'_provisional'},
            'photo' => $competitorId ? ($this->faces[$competitorId] ?? null) : null,
            // The club under the name, and its two-letter mark for the chip
            // beside it. Null when they compete for nobody — the board then
            // simply draws no second line rather than an empty one.
            'club' => $competitorId ? ($this->clubs[$competitorId]['name'] ?? null) : null,
            'club_ab' => $competitorId ? ($this->clubs[$competitorId]['ab'] ?? null) : null,
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
            'club' => $this->clubs[$r->id]['name'] ?? null,
            'club_ab' => $this->clubs[$r->id]['ab'] ?? null,
        ];
    }

    /**
     * A competitor's face, or null — in the order the platform decides it.
     *
     * 1. The ENTRY's own photo. Taken for THIS competition, at the weigh-in
     *    desk or through the public entry link, which asks for one. It belongs
     *    to the entry, is scoped to this event and goes when the entry goes, so
     *    showing it on this event's board is showing the thing it was taken
     *    for — it discloses nothing about their account.
     * 2. Otherwise their profile picture, and ONLY if they published it
     *    (`profile_picture_is_public`). A bracket is seen by everyone the
     *    event's scope reaches, so it is not a place to override that choice.
     * 3. Otherwise nothing, silently. A "hidden" label would disclose the very
     *    fact the setting protects.
     *
     * The entry photo was MISSING from this order until 2026-09-18 — the board
     * went straight to the profile picture — which is why a competitor
     * photographed at the desk still showed a blank on the draw while the same
     * face appeared on their participant card. Same order as
     * `PublicEvent::face()`, `RosterPeople` and the sports' own
     * CompetitorPhoto: one question, one answer, wherever a competitor is drawn.
     *
     * `showable` rather than a bare truth test: an entry can NAME a file that is
     * no longer there, and preferring it unconditionally would stop the
     * fall-through (EntryPhoto::showable).
     */
    /**
     * The podium, dressed with what the board draws.
     *
     * `event_categories.podium` is the record — place, name, competitor id —
     * and it stays exactly as it is. The face, the club and the flag are
     * joined on here from the same per-division lookups every corner already
     * uses, so a champion's card can never show a different photograph from
     * the bout that produced them.
     *
     * A place whose competitor is no longer in the division (removed after the
     * fact) still prints: the NAME is the answer, and the extras are simply
     * absent.
     *
     * @return array<int, array<string, mixed>>
     */
    private function podium(EventCategory $c): array
    {
        return array_map(function (array $row) {
            $id = $row['competitor_id'] ?? null;

            $row['photo'] = $id ? ($this->faces[$id] ?? null) : null;
            $row['club'] = $id ? ($this->clubs[$id]['name'] ?? null) : null;
            $row['club_ab'] = $id ? ($this->clubs[$id]['ab'] ?? null) : null;
            $row['country'] = ($row['country'] ?? null) ?: ($id ? ($this->flags[$id] ?? null) : null);

            return $row;
        }, $c->podium ?: []);
    }

    /**
     * The club printed under a competitor's name on the board.
     *
     * Tenant first, written-down club second — the same precedence EntryClub
     * states and the entry list already follows. `$written` is the lookup the
     * caller already built for the flags, passed in rather than re-queried.
     *
     * @param  array<int, array{name: string, logo: ?string, country: ?string}>  $written
     * @return array{name: string, ab: string}|null
     */
    private function club(ClubEventRegistration $r, array $written): ?array
    {
        $name = $r->competingClub()?->club_name ?: ($written[$r->id]['name'] ?? null);

        if (! $name) {
            return null;
        }

        return ['name' => (string) $name, 'ab' => $this->abbreviate((string) $name)];
    }

    /**
     * Two letters standing for a club — the gold chip beside the club's name.
     *
     * Initials of the first two words, or the first two letters of a one-word
     * name. Deliberately crude: it is a mark, not an identifier, and the club's
     * own name is printed right beside it.
     *
     * Public, because the late-entry roster draws the identical chip and two
     * implementations of "two letters standing for a club" would drift the
     * first time somebody adjusted one (CLAUDE.md → Shared Stays Shared).
     */
    public static function mark(?string $name): ?string
    {
        return $name ? (new self)->abbreviate($name) : null;
    }

    private function abbreviate(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    /**
     * A competitor's face, at a size a bracket slot actually paints.
     *
     * ⚠️ NOT the original. A slot's portrait is 39×52 in board units, and the
     * board is drawn at 1920×1080 and then scaled to whatever screen it is on
     * — so on a laptop, with a 16-competitor draw fitted to the viewport, that
     * portrait paints at something like fifteen pixels across. A 600×800 JPEG
     * squeezed into fifteen pixels is where a browser's downscaling gives up:
     * it takes every Nth pixel rather than averaging, and a face comes out
     * speckled and hard-edged. The fix is to hand it an image near the size it
     * is drawing, which is what the `face` rendition is for — generous enough
     * (240×320) to stay sharp when an organiser zooms the board right in, and
     * a fraction of the bytes on the way down.
     *
     * `thumb_url()` falls back to the original whenever a rendition cannot be
     * drawn, so this can never leave a slot blank.
     */
    private function photo(ClubEventRegistration $r): ?string
    {
        /* The reading — entry photograph first, the member's own published
           picture second, nothing third — is CompetitorFace, shared with the
           podium and with the opponents list on a person's profile. It used to
           be written out here, and the third copy of it had never learned
           about the entry photograph at all. */
        return CompetitorFace::for($r);
    }
}
