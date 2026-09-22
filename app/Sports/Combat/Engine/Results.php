<?php

namespace App\Sports\Combat\Engine;

use App\Support\Cldr;
use App\Events\Models\ClubEvent;
use App\Sports\Combat\SportRegistry;

/** Derives medalists and the public lifecycle timeline from the bracket data. */
class Results
{
    public function __construct(private Scheduler $scheduler) {}

    /**
     * Medalists per weight class.
     *
     * ⚠️ ONE RULE, ONE PLACE. This used to derive the medals a second time —
     * gold and silver off the final, bronze to BOTH beaten semi-finalists,
     * hardcoded — while `Advancement::awardIfComplete()` wrote
     * `event_categories.podium` by the SPORT's own `bronzeRule()`. The two
     * disagreed on the same division: the bracket board showed one bronze (or,
     * before the play-off bout existed, none at all) and the member page showed
     * two. A medal is a fact about a championship and cannot have two answers.
     *
     * So the stored podium is authoritative, and this reads it. The derivation
     * below is kept only as a fallback for a division decided BEFORE the podium
     * was recorded — the divisions already in the database when this shipped —
     * and never overrides a stored one.
     *
     * Only divisions whose final is decided are returned.
     */
    public function podium(ClubEvent $event): array
    {
        $out = [];
        foreach ($event->categories()->with('matches')->orderBy('sort_order')->get() as $cat) {
            /*
             * ⚠️ NOT EVERY DECIDED DIVISION HAS A FINAL.
             *
             * A pool of three has none by design — everybody has met everybody,
             * so the table is the answer and `GroupEngine` cuts no knockout
             * round at all. Requiring a decided Final therefore SKIPPED such a
             * division entirely: the member page said "3 divisions decided"
             * where the public results page, which reads the stored podium,
             * correctly listed four. Two surfaces, two answers, same event.
             *
             * The platform's own award is the authority (it is what
             * Advancement::awardIfComplete wrote); the final is only the
             * fallback for a division decided before podiums were stored.
             */
            $medals = $cat->podium ?: [];

            if ($medals === []) {
                $final = $cat->matches->firstWhere('round', 'Final');

                if (! $final || ! $final->winner) {
                    continue;   // genuinely undecided
                }

                $medals = $this->derive($event, $cat, $final);
            }

            $out[] = ['division' => $cat->name, 'class' => $cat->weight_class, 'medals' => $medals];
        }

        /* A stored medal is a place, a name and the entry that won it. A medal
           DRAWN in a hall carries a face, a flag and a crest — read off that
           entry here, in one query for the whole sheet, by the one class both
           podium surfaces call. */
        return \App\Events\Support\PodiumFaces::enrich($out);
    }

    /**
     * The historical reading, for a division whose medals were never stored:
     * final winner = gold, final loser = silver, both beaten semi-finalists =
     * bronze. Never used for a division the platform has awarded itself.
     *
     * @return array<int, array{place: int, name: ?string, competitor_id: ?int}>
     */
    private function derive(ClubEvent $event, $cat, $final): array
    {
        // Medals carry the ENTRY that won them, so a result can reach the
        // athlete's profile, a club medal tally and ranking points — not
        // just a name printed on a board.
        $medal = fn (int $place, $bout, string $side) => [
            'place' => $place,
            'name' => $bout->{$side.'_name'},
            'competitor_id' => $bout->{$side.'_competitor_id'},
        ];

        $win = $final->winner;
        $lose = $win === 'a' ? 'b' : 'a';

        $medals = [$medal(1, $final, $win), $medal(2, $final, $lose)];

        /*
         * ⚠️ THE FALLBACK OBEYS THE SPORT TOO.
         *
         * Two beaten semi-finalists both taking bronze is ONE convention, and
         * it used to be written in here as though it were the only one — so a
         * division with no stored podium was handed back two bronzes even for
         * a sport that plays third place off. It is the reading that resurrects
         * itself the moment a stored podium is cleared, which is exactly how a
         * corrected division came back wearing the convention it had just been
         * taken out of.
         *
         * A sport with a play-off reads its bronze off THAT bout, and only when
         * it has been fought: an unfought play-off means third is still open,
         * which is the truth and reads as such.
         */
        if (\App\Events\Support\BronzeRule::playsOff($event)) {
            $third = $cat->matches->firstWhere('round', DrawEngine::ROUND_THIRD);

            if ($third?->winner) {
                // Three places: gold, silver, one bronze. The athlete who lost
                // the play-off is not recorded — see Advancement::awardIfComplete.
                $medals[] = $medal(3, $third, $third->winner);
            }

            return $medals;
        }

        foreach ($cat->matches->where('round', 'Semifinal') as $sf) {
            if (! $sf->winner) {
                continue;
            }
            $loserSide = $sf->winner === 'a' ? 'b' : 'a';
            if ($sf->{$loserSide.'_name'}) {
                $medals[] = $medal(3, $sf, $loserSide);
            }
        }

        return $medals;
    }

    /**
     * Public lifecycle timeline derived from the real schedule:
     * Registration closes → Weigh-in & draw → Day-by-day (which sections + how
     * many classes + break). Each entry carries a date so the view derives status.
     */
    public function timeline(ClubEvent $event): array
    {
        $start = $event->date;
        if (! $start) {
            return [];
        }

        $cats = $event->categories()->get();
        /* ⚠️ TRANSLATED, all of it. This timeline is read on the public event
           page, which a stranger opens in whichever language they chose — and
           every label here was a hardcoded English string, so an Arabic reader
           got an Arabic page with an English schedule down the middle of it.
           The strings live in the event SYSTEM's file because this engine is
           shared by every combat sport (CLAUDE.md: put a string at the level
           that owns it). */
        $phaseLabel = [
            'preliminary' => __('events.timeline_phase_preliminary'),
            'quarterfinals' => __('events.timeline_phase_quarterfinals'),
            'finals' => __('events.timeline_phase_finals'),
        ];

        // A competition day is not one block of time — it is play, a break, then
        // play again. Emitted as segments so each gets its own line with its own
        // clock, rather than one range plus a footnote nobody reads.
        $segments = $this->daySegments($event);
        // The length stands alone whenever no break SEGMENT is shown — either
        // there is no usable clock at all, or the organiser sized the break
        // without placing it (see daySegments()).
        $breakShown = false;
        foreach ($segments as $seg) {
            if (($seg['kind'] ?? '') === 'break') {
                $breakShown = true;
                break;
            }
        }

        $breakLabel = (! $breakShown && $event->break_minutes)
            ? __('events.timeline_break_minutes', ['minutes' => $event->break_minutes])
            : null;

        $breakNote = $breakLabel ? ' · '.$breakLabel : '';

        $entries = [];

        // When entries OPEN. Without it the timeline began at the deadline,
        // which tells someone what they have missed, not what they can do.
        if ($event->enrollment_starts_at) {
            $entries[] = [
                'label' => __('events.timeline_enrol_opens'),
                'date' => $event->enrollment_starts_at->toDateString(),
                'note' => __('events.timeline_enrol_opens_note'),
                'icon' => 'bi-calendar-plus',
            ];
        }

        if ($event->enrollment_ends_at) {
            $entries[] = [
                'label' => __('events.timeline_enrol_closes'),
                'date' => $event->enrollment_ends_at->toDateString(),
                'note' => __('events.timeline_enrol_closes_note'),
                'icon' => 'bi-pencil-square',
            ];
        }

        // Weigh-in: the one appointment a competitor must physically make, so it
        // carries its time and its place, not just a date.
        $wi = $event->weigh_in_at ? \Carbon\Carbon::parse($event->weigh_in_at) : $start->copy();
        $entries[] = [
            'label' => __('events.timeline_weigh_in'),
            'date' => $wi->toDateString(),
            // translatedFormat: an Arabic reader gets ص/م, not AM/PM.
            'time' => $event->weigh_in_at ? Cldr::time($wi) : null,
            'note' => __('events.timeline_weigh_in_note')
                .($event->location ? ' · '.$event->location : ''),
            'icon' => 'bi-clipboard-data',
        ];

        $lastCompetitionDay = null;
        // The last day's running order, so the derived finish below can tell
        // whether the organiser has already written one.
        $lastDayRunSlots = [];

        for ($d = 1, $days = $this->scheduler->eventDayCount($event); $d <= $days; $d++) {
            $phasesOnDay = [];
            $classCount = 0;
            foreach ($cats as $c) {
                $hit = false;
                foreach (['preliminary', 'quarterfinals', 'finals'] as $ph) {
                    if ($this->scheduler->phaseDay($c, $ph) === $d) {
                        $phasesOnDay[$ph] = true;
                        $hit = true;
                    }
                }
                $classCount += $hit ? 1 : 0;
            }
            if (! $phasesOnDay) {
                continue;
            }
            $names = array_map(fn ($p) => $phaseLabel[$p], array_keys($phasesOnDay));
            $lastCompetitionDay = $start->copy()->addDays($d - 1);

            /*
             * The organiser's own running order for this day, when they have
             * written one. It SUPERSEDES the derived `note_items` below — those
             * say "Preliminaries · Quarter-finals · 16 weight classes", which is
             * a description of the day; this says what happens and when, which
             * is what somebody standing in the hall opened the page for.
             *
             * Additive on purpose: an event with no running order keeps exactly
             * the timeline it had, and the view falls back to the bullets.
             */
            $runSlots = (new \App\Events\Support\RunSheet)->rows($event, $d);
            $lastDayRunSlots = $runSlots;

            $entries[] = [
                'label' => __('events.timeline_day', ['day' => $d]),
                'date' => $lastCompetitionDay->toDateString(),
                /*
                 * Play / break / play, each its own line — UNLESS the organiser
                 * has written the day down, in which case the hours come from
                 * the day itself rather than from the event's nominal window.
                 * A header that says "10:00 AM – 9:00 PM" over a list whose last
                 * part ends at 7:30 is contradicting the list directly beneath
                 * it, and the list is the one somebody plans their day around.
                 */
                'segments' => $this->runOrderSegments($runSlots) ?? $segments,
                // The day, part by part — empty unless an organiser wrote it.
                'run_slots' => $runSlots,
                'note' => implode(' · ', $names).' — '
                    .trans_choice('events.timeline_weight_classes', $classCount, ['count' => $classCount])
                    .$breakNote,
                // The same facts as `note`, but as separate items so the view
                // can set them as chips rather than printing one long line
                // glued together with middots. `note` stays for any caller
                // that has not been taught about this.
                'note_items' => array_values(array_filter(array_merge(
                    $names,
                    [trans_choice('events.timeline_weight_classes', $classCount, ['count' => $classCount])],
                    [$breakLabel],
                ))),
                'icon' => 'bi-flag',
            ];
        }

        // The finish. A timeline that stops at the last bout leaves out the part
        // everyone came for, and a competitor's family needs to know whether to
        // stay for it.
        /*
         * ...UNLESS the organiser has already written the finish into the day.
         * A running order that ends with a ceremony ("التتويج النهائي وختام
         * البطولة", 7:00 PM) IS the finish, and appending our own derived one
         * beneath it printed the same moment twice — at two different times,
         * because this one comes from the event's nominal end_time while theirs
         * comes from the sheet. The organiser's line wins; ours is the fallback
         * for an event with no running order.
         */
        $sheetEndsInCeremony = $lastDayRunSlots
            && (($lastDayRunSlots[count($lastDayRunSlots) - 1]['kind'] ?? null) === \App\Events\Models\EventRunSlot::KIND_CEREMONY);

        if ($lastCompetitionDay && ! $sheetEndsInCeremony) {
            $entries[] = [
                'label' => __('events.timeline_finish'),
                'date' => $lastCompetitionDay->toDateString(),
                'time' => $event->end_time
                    ? Cldr::time(\Carbon\Carbon::parse($event->end_time))
                    : null,
                'note' => __('events.timeline_finish_note')
                    .($event->prize ? ' · '.$event->prize : '')
                    .' — '.__('events.timeline_finish_after'),
                'icon' => 'bi-award-fill',
            ];
        }

        return $entries;
    }

    /**
     * A competition day split into play / break / play.
     *
     * `approx` flags a break the organiser sized but never placed on the clock —
     * we centre it in the bout time. The view currently prints it like any other
     * time; the flag is kept so a caller that wants to qualify it still can.
     *
     * The durable fix is upstream: make break_start/break_end the input on the
     * event form and derive break_minutes from them, so the time shown is always
     * one the organiser actually set.
     *
     * @return array<int, array{label:string, time:string, kind:string, approx:bool}>
     */
    /**
     * The day's hours, taken from the running order the organiser wrote.
     *
     * One block, first part to last: the break is already a row in the list
     * below, so splitting the header around it would print it twice.
     *
     * Min and max rather than first and last, because a pinned part may sit
     * anywhere in the order — a pin is a clock time, not a position.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label:string, time:string, kind:string, approx:bool}>|null
     */
    private function runOrderSegments(array $rows): ?array
    {
        if (! $rows) {
            return null;
        }

        $from = min(array_column($rows, 'starts_at'));
        $to = max(array_column($rows, 'ends_at'));

        if ($to <= $from) {
            return null;
        }

        return [[
            'label' => __('events.timeline_play'),
            'kind' => 'play',
            'approx' => false,
            'time' => $this->clock((int) $from).' – '.$this->clock((int) $to),
        ]];
    }

    private function daySegments(ClubEvent $event): array
    {
        if (! $event->start_time || ! $event->end_time) {
            return [];
        }

        $s = $this->minsOfDay($event->start_time);
        $t = $this->minsOfDay($event->end_time);
        if ($t <= $s) {
            return [];
        }

        $exact = $event->break_start && $event->break_end;
        $len = $exact
            ? max(0, $this->minsOfDay($event->break_end) - $this->minsOfDay($event->break_start))
            : (int) ($event->break_minutes ?? 0);

        $wholeDay = [[
            'label' => __('events.timeline_play'), 'kind' => 'play', 'approx' => false,
            'time' => $this->clock($s).' – '.$this->clock($t),
        ]];

        // No break, or one that would swallow the day: a single block of play.
        if ($len <= 0 || $len >= ($t - $s)) {
            return $wholeDay;
        }

        /*
         * Sized but never PLACED — we know how long, not when.
         *
         * This used to centre it in the bout time and print "Break 5:30 PM –
         * 6:30 PM" in exactly the same type as a time the organiser actually
         * chose. Two things made that worse than it looks: `break_minutes`
         * carries a DATABASE DEFAULT of 60, so every event ever created has
         * one whether anybody asked or not; and the `approx` flag meant to
         * qualify it was computed and then ignored by the view. The result was
         * a public poster announcing a break to competitors and their families
         * that the organiser had never set and could not see anywhere.
         *
         * A made-up clock time is worse than no clock time. The length still
         * travels — as a note beside the day, where it reads as "the day
         * includes an hour's break" rather than "be back at half past six".
         * The Scheduler goes on using `break_minutes` to reserve the time,
         * which is what that column is actually for.
         *
         * Put break_start/break_end on the event form and this branch stops
         * being reached — the segments below are the good path.
         */
        if (! $exact) {
            return $wholeDay;
        }

        $bStart = $this->minsOfDay($event->break_start);
        $bEnd = min($t, $bStart + $len);

        return [
            ['label' => __('events.timeline_play'), 'kind' => 'play', 'approx' => false,
                'time' => $this->clock($s).' – '.$this->clock($bStart)],
            ['label' => __('events.timeline_break'), 'kind' => 'break', 'approx' => ! $exact,
                'time' => $this->clock($bStart).' – '.$this->clock($bEnd)],
            ['label' => __('events.timeline_play'), 'kind' => 'play', 'approx' => false,
                'time' => $this->clock($bEnd).' – '.$this->clock($t)],
        ];
    }

    /** "09:30" / "09:30:00" -> minutes since midnight. */
    private function minsOfDay(string $time): int
    {
        [$h, $m] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $h * 60 + $m;
    }

    /** Minutes since midnight -> "9:30 AM", wrapping safely past midnight. */
    private function clock(int $mins): string
    {
        $mins = ((int) $mins % 1440 + 1440) % 1440;

        // Cldr, so an Arabic reader gets ص/م, a Chinese one gets a 24-hour
        // clock, and neither gets English word order. See App\Support\Cldr.
        return Cldr::time(\Carbon\Carbon::createFromTime(intdiv($mins, 60), $mins % 60));
    }
}
