<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Translation\Translations;
use Illuminate\Support\Carbon;

/**
 * What a withheld draw shows INSTEAD of itself.
 *
 * A member taps "Draw" the week before a championship and the honest answer is
 * "not yet". `<x-draw-veil>` says that, and for a long time that was the whole
 * page — one padlock and a sentence. But the reader came with a real question
 * underneath the one they asked ("is this thing happening, and how big is it?"),
 * and the event already knows the answer: who has entered, from how many clubs,
 * flying how many flags, spread across which divisions. None of that is the
 * draw. Withholding it withholds nothing the reveal setting exists to protect.
 *
 * So this builds the waiting room: a countdown to the next thing that actually
 * happens (the weigh-in, else the first bell), and the shape of the field as it
 * stands.
 *
 * ── Deliberately sport-neutral ─────────────────────────────────────────────
 * Every bracketed championship has entrants, divisions, clubs and flags, so
 * this lives in `Events\Support` and takes nothing from any package (CLAUDE.md
 * → Shared Stays Shared). A sport with no weigh-in simply has a null
 * `weigh_in_at` and counts down to the start instead.
 *
 * ── What it will not return ────────────────────────────────────────────────
 * AGGREGATES ONLY — counts, and the names of CLUBS, which are public entities
 * whose participation the event poster already announces. No competitor names,
 * no photos, no weights, no payment or weigh-in state, nothing keyed to a
 * person. The caller has already decided this viewer may read the event; it has
 * NOT decided they may read its roster, and this must never be the surface that
 * quietly grants that.
 */
class DrawWaitingRoom
{
    /**
     * @return array{
     *     target: ?string, at: ?string, label: ?string,
     *     weigh_in: ?string, starts: ?string,
     *     athletes: int, entries: int,
     *     divisions: list<array{name: string, count: int, pct: int}>,
     *     divisions_total: int, largest: int,
     *     countries: list<array{code: string, count: int}>, countries_unknown: int, countries_total: int,
     *     clubs: list<array{name: string, count: int, logo: ?string}>, clubs_unattached: int, clubs_total: int
     * }
     */
    public function build(ClubEvent $event): array
    {
        $entries = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->with([
                'user:id,nationality',
                'user.memberClubs:id,club_name,slug,logo,country',
                'representingTenant:id,club_name,slug,logo,country',
            ])
            ->get();

        $tr = Translations::of($event);
        $people = $this->fold($entries);

        return [
            /*
             * The countdown's subject, named rather than implied: the view
             * prints a different heading for a weigh-in than for a first bell,
             * and a client that has to guess from a bare timestamp guesses
             * wrong on the events that have no weigh-in at all.
             */
            ...$this->countdown($event),
            'weigh_in' => $event->weigh_in_at?->toIso8601String(),
            'starts' => $this->startsAt($event)?->toIso8601String(),

            // People, not rows. An athlete entered in three divisions is one
            // person on this card and three entries in the draw — the same
            // distinction the participants tile makes (PersonalEventController
            // ::eventView, `participants_people`).
            'athletes' => count($people),
            'entries' => $entries->count(),

            // Counted per DIVISION, so these are entries by definition: a
            // weight class holds entries, and the same athlete appearing in
            // two of them is two competitors to draw.
            ...$this->divisions($event, $entries, $tr),

            // Counted per PERSON. A flag and a club describe a human being,
            // and folding by entry made 62 athletes fly 94 flags.
            ...$this->countries($people),
            ...$this->clubs($people),
        ];
    }

    /**
     * One entry per PERSON.
     *
     * An athlete who enters Gi, No-Gi and the absolute is three rows in
     * `club_event_registrations` and one human being in the hall. Anything that
     * describes the HUMAN — their flag, their club — must be counted off this
     * list, or a field of 62 athletes reports 94 flags, which is what shipped
     * first and what this fold exists to prevent.
     *
     * The representative is their FIRST entry, which is exactly how
     * `RosterPeople::byPerson()` builds the one card per person that the
     * participants list draws: the entries are merged, and the club and flag
     * come from the first of them. Two surfaces disagreeing about which club an
     * athlete fights for would be worse than either answer.
     *
     * An entry with no user account is its own person — an unclaimed name an
     * official typed at the desk is still somebody standing on the mat.
     *
     * @param  \Illuminate\Support\Collection<int, ClubEventRegistration>  $entries
     * @return list<ClubEventRegistration>
     */
    private function fold($entries): array
    {
        $people = [];

        foreach ($entries as $i => $entry) {
            $key = $entry->user_id !== null ? 'u'.$entry->user_id : 'r'.$entry->id.'-'.$i;

            $people[$key] ??= $entry;
        }

        return array_values($people);
    }

    /**
     * The next moment that actually happens.
     *
     * Weigh-in first, because that is the deadline an entrant is counting down
     * to; once it is behind us the question becomes "when do we start". Once
     * BOTH are behind us there is nothing honest to count towards, and the card
     * shows the field alone rather than a clock running backwards.
     *
     * @return array{target: ?string, at: ?string, label: ?string}
     */
    private function countdown(ClubEvent $event): array
    {
        $weighIn = $event->weigh_in_at;
        $starts = $this->startsAt($event);

        if ($weighIn && $weighIn->isFuture()) {
            return ['target' => 'weigh_in', 'at' => $weighIn->toIso8601String(), 'label' => __('events.waiting_until_weigh_in')];
        }

        if ($starts && $starts->isFuture() && ! $event->hasStarted()) {
            return ['target' => 'start', 'at' => $starts->toIso8601String(), 'label' => __('events.waiting_until_start')];
        }

        return ['target' => null, 'at' => null, 'label' => null];
    }

    /** The event's own start instant, time included where the organiser gave one. */
    private function startsAt(ClubEvent $event): ?Carbon
    {
        if (! $event->date) {
            return null;
        }

        $at = $event->date->copy()->startOfDay();

        if ($event->start_time && preg_match('/^(\d{1,2}):(\d{2})/', (string) $event->start_time, $m)) {
            $at->setTime((int) $m[1], (int) $m[2]);
        }

        return $at;
    }

    /**
     * How the field is spread across the weight classes.
     *
     * Headings are captions, not divisions (DivisionSections) — a bar for "GI"
     * sitting at zero beside five real weight classes reads as a broken import.
     * Empty divisions are kept: "nobody in -100 kg yet" is information an
     * entrant weighing 98 kg wants very much.
     *
     * @param  \Illuminate\Support\Collection<int, ClubEventRegistration>  $entries
     * @return array{divisions: list<array{name: string, count: int, pct: int}>, divisions_total: int, largest: int}
     */
    private function divisions(ClubEvent $event, $entries, object $tr): array
    {
        $counts = $entries->groupBy('category_id')->map->count();

        $rows = $event->categories()
            ->where('is_heading', false)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (EventCategory $c) => [
                'name' => (string) $tr->get('divisions.'.$c->id, (string) $c->name),
                'count' => (int) ($counts[$c->id] ?? 0),
            ])
            ->values()->all();

        $largest = max(1, (int) max(array_column($rows, 'count') ?: [0]));

        return [
            // Bar width against the BIGGEST division rather than the total:
            // thirteen divisions of a hundred entries each draw thirteen 7%
            // slivers, which says nothing. Relative height is the shape.
            'divisions' => array_map(
                fn (array $r) => $r + ['pct' => (int) round($r['count'] / $largest * 100)],
                $rows
            ),
            'divisions_total' => count($rows),
            'largest' => $largest,
        ];
    }

    /**
     * The flags in the hall.
     *
     * The COUNTRY OF THE CLUB an athlete competes for, never their passport —
     * `ClubEventRegistration::countryCode()` is the one place that rule lives.
     *
     * @param  list<ClubEventRegistration>  $people  one entry per person (see fold())
     * @return array{countries: list<array{code: string, count: int}>, countries_unknown: int, countries_total: int}
     */
    private function countries(array $people): array
    {
        $codes = [];
        $unknown = 0;

        foreach ($people as $entry) {
            $code = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $entry->countryCode()), 0, 2));

            if (strlen($code) === 2) {
                $codes[$code] = ($codes[$code] ?? 0) + 1;

                continue;
            }

            /*
             * Nothing on file. COUNTED, not dropped — the chips used to add up
             * to 52 over a field of 62 and the reader was left to wonder where
             * the other ten went (reported 2026-09-16). A number that does not
             * reconcile is worse than a blunt "none": it reads as a bug in the
             * page rather than as a gap in the data, which is what it is, and
             * it is the organiser's cue to go and fill it in.
             */
            $unknown++;
        }

        arsort($codes);

        return [
            'countries' => array_map(
                fn ($code, $n) => ['code' => $code, 'count' => $n],
                array_keys($codes),
                array_values($codes)
            ),
            // Its own key rather than a `code => null` row: it is not a country,
            // and anything counting countries must not count it as one.
            'countries_unknown' => $unknown,
            'countries_total' => count($codes),
        ];
    }

    /**
     * The clubs that have sent somebody.
     *
     * A club is a public entity with a public page, and the event poster
     * already lists who is coming — so naming them here discloses nothing new,
     * and it is the single fact an entrant most wants before a competition.
     * An unattached entry is counted in the total head count and belongs to no
     * club, which is the truth rather than an "Unattached" pseudo-club.
     *
     * @param  list<ClubEventRegistration>  $people  one entry per person (see fold())
     * @return array{clubs: list<array{name: string, count: int, logo: ?string}>, clubs_unattached: int, clubs_total: int}
     */
    private function clubs(array $people): array
    {
        $clubs = [];
        $unattached = 0;

        foreach ($people as $entry) {
            $club = $entry->competingClub();

            if (! $club) {
                // Competing on their own — a real standing at a competition,
                // not a missing value, and counted for the same reason the
                // flagless are: the rows must add up to the head count.
                $unattached++;

                continue;
            }

            $clubs[$club->id] ??= ['name' => (string) $club->club_name, 'count' => 0, 'logo' => $club->logo ? file_url($club->logo) : null];
            $clubs[$club->id]['count']++;
        }

        usort($clubs, fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']));

        return [
            'clubs' => array_values($clubs),
            'clubs_unattached' => $unattached,
            'clubs_total' => count($clubs),
        ];
    }
}
