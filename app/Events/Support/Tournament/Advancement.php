<?php

namespace App\Events\Support\Tournament;

use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Sports\Combat\CombatSport;
use App\Sports\Combat\Engine\GroupEngine;
use Illuminate\Support\Collection;

/**
 * Bracket progression for a single-elimination weight division.
 *
 * When a bout is decided the winner is carried into their next bout
 * automatically, all the way to the final; when the final is decided the
 * division's medals are awarded. Correcting an earlier result re-cuts every
 * bout downstream of it, so a mistyped winner can never leave a stale name
 * sitting in a later round.
 *
 * Bracket shape is derived from slot order, matching how DrawEngine lays a
 * division out: within a round, bout i feeds bout ⌊i/2⌋ of the next round,
 * taking side A when i is even and side B when it is odd.
 */
abstract class Advancement implements PropagatesResults
{
    /**
     * The play-off for bronze, cut by `DrawEngine` for a sport whose
     * `bronzeRule()` is `third_place_match`.
     *
     * It is NOT part of the bracket geometry: the winner-walk never visits it
     * (see `rounds()`), because it is fed by the two semi-finals' LOSERS.
     */
    public const ROUND_THIRD = 'Third place';

    /**
     * The win reasons this package accepts, from its own Scoring class.
     *
     * `Scoring` is PRIVATE to an event package — the shared base must never
     * import one — so the package hands its whitelist in. Anything outside it
     * is stored as `other` rather than rejected, so a console that grows a new
     * reason can never lose the fact that the bout was not won on points.
     *
     * @return array<int, string>
     */
    abstract protected function winReasons(): array;

    public function __construct(private CombatSport $sport) {}

    /**
     * The group engine, resolved rather than injected: every package builds its
     * Advancement with `new Advancement($sport)`, and widening that constructor
     * would mean editing three packages to add a dependency only the group
     * shape uses.
     */
    private function groups(): GroupEngine
    {
        return app(GroupEngine::class);
    }

    /**
     * Record a bout's outcome and propagate it.
     *
     * @param  array{winner?: ?string, a_score?: ?string, b_score?: ?string, status?: ?string}  $payload
     * @return array{match: array, advanced: array<int, array>, podium: array, division_complete: bool}
     */
    public function record(EventMatch $match, array $payload): array
    {
        $winner = in_array($payload['winner'] ?? null, ['a', 'b'], true) ? $payload['winner'] : null;

        /*
         * ⚠️ A SIDE WITH NOBODY IN IT CANNOT WIN — and being told it did is not
         * the same instruction as "clear this bout".
         *
         * These two were one code path: an impossible winner was quietly turned
         * into null, and null means "no result", which erases whatever the bout
         * held and walks that erasure down the bracket. So naming the empty
         * corner of a BYE as the winner deleted the bye's own decided result
         * and un-advanced the athlete who had walked through it — answering 200
         * "Result recorded" while doing the opposite.
         *
         * Clearing stays available, and stays explicit: send no winner.
         */
        if ($winner !== null && ! $match->{$winner.'_name'}) {
            return [
                'ok' => false,
                'message' => __('events.outcome_empty_side'),
                'match' => $this->row($match),
                'advanced' => [],
                'podium' => [],
                'division_complete' => false,
            ];
        }

        /*
         * ⚠️ ABSENT IS NOT BLANK (CLAUDE.md, Part 8).
         *
         * These two lines used to write BOTH scores on every call, reading a
         * key that was never sent as an instruction to clear. Every caller
         * that changes one thing at a time therefore erased the rest of the
         * bout: the board posts `{a_score: "5"}` when a score box is typed in,
         * and `{winner: "a"}` when a corner is tapped — so picking a winner
         * wiped the scoreline that explained it, and typing the red score
         * wiped the blue one. The record on this event shows exactly that,
         * 63 bouts with a winner and not one score among them.
         *
         * A key that IS sent, empty, still clears — that is how a score is
         * taken back off — and a caller that sends both (the mat console
         * does) behaves as it always has.
         */
        if (array_key_exists('a_score', $payload)) {
            $match->a_score = $this->score($payload['a_score']);
        }

        if (array_key_exists('b_score', $payload)) {
            $match->b_score = $this->score($payload['b_score']);
        }

        $match->winner = $winner;

        // Why, when the score was not the answer — a disqualification, a
        // withdrawal, a doctor's call. Only ever set alongside a winner, and
        // whitelisted here as well as at the console: this reaches the record,
        // and the record is read by the podium, the profile and the export.
        if ($winner !== null && ($payload['win_reason'] ?? null) !== null) {
            $reason = (string) $payload['win_reason'];
            $match->win_reason = in_array($reason, $this->winReasons(), true) ? $reason : 'other';
            $note = trim((string) ($payload['win_note'] ?? ''));
            $match->win_note = $note === '' ? null : mb_substr($note, 0, 200);
        } else {
            // Won on points, or the result is being cleared: nothing to explain.
            $match->win_reason = null;
            $match->win_note = null;
        }
        /*
         * A result somebody RECORDED is never a walkover, whatever it looks
         * like. An organiser may now declare a winner with no score and no
         * reason — which is shaped exactly like a bye — so the flag is cleared
         * here rather than left to be guessed at afterwards.
         */
        $match->won_by_bye = false;
        $match->status = $winner ? 'done' : (in_array($payload['status'] ?? null, ['upcoming', 'live'], true) ? $payload['status'] : 'upcoming');
        $match->save();

        $category = $match->category;
        $advanced = $this->propagate($category, $match);

        $podium = $this->awardIfComplete($category);

        return [
            'match' => $this->row($match->refresh()),
            'advanced' => $advanced,
            'podium' => $podium,
            'division_complete' => $podium !== [],
        ];
    }

    /**
     * Carry the result of $from into the next round, then keep walking forward
     * clearing anything the change invalidated.
     *
     * @return array<int, array> the bouts that changed, for an in-place UI patch
     */
    public function propagate(EventCategory $category, EventMatch $from): array
    {
        /*
         * A GROUP bout feeds nothing directly.
         *
         * In a ladder the winner walks one slot forward and that is the whole
         * of progression. In a group the bout changes a TABLE, and the table —
         * once every bout in it is decided — decides who is in the knockout. So
         * the walk-forward rule must not run here at all: bout ⌊i/2⌋ of the next
         * round is a knockout semifinal, and carrying a group winner into it
         * would seed the bracket by fixture order instead of by merit.
         */
        if ($from->round === GroupEngine::ROUND) {
            return array_map(fn (EventMatch $m) => $this->row($m), $this->groups()->seed($category));
        }

        $rounds = $this->rounds($category);
        $changed = [];
        $current = $from;

        // Every semi-final this change reached — the one just recorded, plus any
        // the walk-forward re-cut. Each of them owes a side of the bronze
        // play-off, so it is re-seeded once the walk is done.
        $semis = $from->round === 'Semifinal' ? [$from] : [];

        while ($next = $this->nextBout($rounds, $current)) {
            [$bout, $side] = $next;

            $won = $current->winner;
            $name = $won ? $current->{$won.'_name'} : null;
            $competitorId = $won ? $current->{$won.'_competitor_id'} : null;
            $country = $won ? $current->{$won.'_country'} : null;
            $provisional = $won ? (bool) $current->{$won.'_provisional'} : false;

            // Compare on the competitor, falling back to the name for a
            // hand-typed entrant who has no registration behind them.
            $unchanged = $competitorId
                ? $bout->{$side.'_competitor_id'} === $competitorId
                : ($bout->{$side.'_competitor_id'} === null && $bout->{$side.'_name'} === $name);

            if ($unchanged) {
                break; // nothing downstream changed
            }

            $bout->{$side.'_name'} = $name;
            $bout->{$side.'_competitor_id'} = $competitorId;
            $bout->{$side.'_country'} = $name ? $country : null;
            $bout->{$side.'_provisional'} = $name ? $provisional : false;
            $bout->{$side.'_score'} = null;

            // The competitor in this slot changed — any result this bout already
            // held is void, and so is everything after it.
            if ($bout->winner) {
                $bout->winner = null;
                $bout->status = 'upcoming';
            }
            $bout->save();

            $changed[] = $this->row($bout);

            if ($bout->round === 'Semifinal') {
                $semis[] = $bout;
            }

            $current = $bout;
        }

        foreach ($semis as $semifinal) {
            $changed = array_merge($changed, $this->syncThirdPlace($category, $semifinal));
        }

        return $changed;
    }

    /**
     * Put a beaten semi-finalist into the bronze play-off — or take them back
     * out when the result that put them there was corrected.
     *
     * The side is decided by the semi-final's own position, so the board reads
     * the way the hall does: the first semi-final's loser is the red corner of
     * the play-off, the second's is the blue.
     *
     * @return array<int, array> the rows that changed, for an in-place UI patch
     */
    private function syncThirdPlace(EventCategory $category, EventMatch $semifinal): array
    {
        $third = $category->matches()->where('round', self::ROUND_THIRD)->first();

        if (! $third) {
            return [];   // a sport that settles bronze another way
        }

        $semifinals = $category->matches()->where('round', 'Semifinal')->orderBy('slot')->get();
        $index = $semifinals->search(fn (EventMatch $m) => $m->id === $semifinal->id);

        if ($index === false || $index > 1) {
            return [];
        }

        $side = $index === 0 ? 'a' : 'b';

        // The LOSER — and nobody at all while the bout is undecided, or when it
        // was a bye and there was never anybody to beat.
        $lost = $semifinal->winner === 'a' ? 'b' : ($semifinal->winner === 'b' ? 'a' : null);
        $name = $lost ? $semifinal->{$lost.'_name'} : null;
        $competitorId = $lost ? $semifinal->{$lost.'_competitor_id'} : null;

        $unchanged = $competitorId
            ? $third->{$side.'_competitor_id'} === $competitorId
            : ($third->{$side.'_competitor_id'} === null && $third->{$side.'_name'} === $name);

        if ($unchanged) {
            return $this->settleThirdPlaceWalkover($category, $third);
        }

        $third->{$side.'_name'} = $name;
        $third->{$side.'_competitor_id'} = $competitorId;
        $third->{$side.'_country'} = $name ? ($lost ? $semifinal->{$lost.'_country'} : null) : null;
        $third->{$side.'_provisional'} = $name ? (bool) $semifinal->{$lost.'_provisional'} : false;
        $third->{$side.'_score'} = null;

        // A competitor changed, so any result this bout held is void.
        if ($third->winner) {
            $third->winner = null;
            $third->status = 'upcoming';
        }

        $third->save();

        return array_merge([$this->row($third)], $this->settleThirdPlaceWalkover($category, $third));
    }

    /**
     * Three people in a division means one semi-final was a bye, so only one
     * athlete ever arrives at the play-off. Nobody fights a bout alone: they
     * take bronze the way a bye takes a round.
     *
     * @return array<int, array>
     */
    private function settleThirdPlaceWalkover(EventCategory $category, EventMatch $third): array
    {
        if ($third->winner) {
            return [];
        }

        $undecided = $category->matches()->where('round', 'Semifinal')
            ->whereNull('winner')->exists();

        if ($undecided) {
            return [];   // somebody is still to be beaten
        }

        $here = array_filter(['a', 'b'], fn (string $s) => filled($third->{$s.'_name'}));

        if (count($here) !== 1) {
            return [];   // a real bout, or an empty one
        }

        $third->winner = array_values($here)[0];
        $third->status = 'done';
        $third->save();

        return [$this->row($third)];
    }

    /**
     * Award the division when its final is decided: gold, silver, and bronze by
     * the sport's own convention (`CombatSport::bronzeRule()`).
     *
     * @return array<int, array{place: int, name: string}>
     */
    public function awardIfComplete(EventCategory $category): array
    {
        $matches = $category->matches()->orderBy('slot')->get();
        $final = $matches->firstWhere('round', 'Final');

        if (! $final || ! $final->winner) {
            /*
             * A small group has no final — three people who have each fought
             * each other have answered the question, so the table awards the
             * medals. Returns [] for every other division, so a ladder still
             * waits for its final exactly as before.
             */
            $table = $this->groups()->podium($category);

            if ($table !== []) {
                $category->update(['podium' => $table, 'status' => 'completed']);
            }

            return $table;
        }

        $win = $final->winner;
        $lose = $win === 'a' ? 'b' : 'a';

        $podium = [$this->medal(1, $final, $win)];
        if ($final->{$lose.'_name'}) {
            $podium[] = $this->medal(2, $final, $lose);
        }

        // Bronze: both semi-final losers (repechage / both_sf_losers), or a
        // single third-place bout winner when the sport says so.
        // Whether every medal this division awards has actually been won. A
        // ladder with an unfought bronze play-off is not finished, however
        // decided its final is.
        $settled = true;

        /* The EVENT's answer, not only the sport's — an organiser can say what
           their own championship does (App\Events\Support\BronzeRule), and a
           NULL there still means the sport's rule book. */
        if (\App\Events\Support\BronzeRule::playsOff($category->event)) {
            $third = $matches->firstWhere('round', self::ROUND_THIRD);

            if ($third?->winner) {
                $podium[] = $this->medal(3, $third, $third->winner);

                /*
                 * ⚠️ THREE PLACES. NO FOURTH.
                 *
                 * The loser of the play-off used to be recorded as place 4 —
                 * "not a medal, but a placing an athlete carries". The owner,
                 * who runs these championships, settled it on 2026-09-21: a
                 * podium here is gold, silver and ONE bronze, and nothing
                 * else. A fourth line on the sheet is a place nobody awards,
                 * and it read as a second bronze on every surface that drew it.
                 *
                 * Deliberately not made configurable: one convention, decided,
                 * in the one method that writes a podium.
                 */
            } elseif ($third && filled($third->a_name) && filled($third->b_name)) {
                // Both semi-finalists are waiting on the mat: the division has
                // a gold and a silver, and is not over.
                $settled = false;
            } elseif ($matches->contains('round', GroupEngine::ROUND)) {
                /*
                 * A GROUP has no third-place bout to hold — and does not need
                 * one. Everybody has already met everybody, so the third medal
                 * is simply the third line of the table: the best competitor
                 * who is not in the final. Without this a group division in a
                 * sport that settles bronze by a play-off awarded two medals
                 * and stopped.
                 */
                $table = collect(app(GroupEngine::class)->standings($category));
                $inFinal = [$final->a_competitor_id, $final->b_competitor_id];

                $bronze = $table->first(fn (array $r) => ! in_array($r['competitor_id'], $inFinal, true));

                if ($bronze) {
                    $podium[] = [
                        'place' => 3,
                        'name' => $bronze['name'],
                        'competitor_id' => $bronze['competitor_id'],
                    ];
                }
            }
        } else {
            foreach ($matches->where('round', 'Semifinal') as $sf) {
                if (! $sf->winner) {
                    continue;
                }
                $loserSide = $sf->winner === 'a' ? 'b' : 'a';
                if ($sf->{$loserSide.'_name'}) {
                    $podium[] = $this->medal(3, $sf, $loserSide);
                }
            }
        }

        $category->update([
            'podium' => $podium,
            'status' => $settled ? 'completed' : $category->status,
        ]);

        return $podium;
    }

    /**
     * One medal, carrying the entry it was won by — and the flag it was won
     * under, which the board expects because a hand-typed podium has always
     * had one. A platform-awarded podium without it made the bracket page
     * fatal on `country` the first time a division was ever completed.
     */
    private function medal(int $place, EventMatch $bout, string $side): array
    {
        return [
            'place' => $place,
            'name' => $bout->{$side.'_name'},
            'competitor_id' => $bout->{$side.'_competitor_id'},
            'country' => $bout->{$side.'_country'},
        ];
    }

    /* ---------------- Bracket shape ---------------- */

    /**
     * Bouts grouped into rounds, in bracket order (earliest round first), each
     * round's bouts in slot order.
     *
     * @return Collection<int, Collection<int, EventMatch>>
     */
    private function rounds(EventCategory $category): Collection
    {
        return $category->matches()->orderBy('slot')->get()
            /*
             * The bronze play-off sits at the end of the slot order and would
             * otherwise read as "the round after the final" — so the final's
             * winner would be walked into it. It is fed by `syncThirdPlace()`
             * instead, from the semi-finals' losers.
             */
            ->reject(fn (EventMatch $m) => $m->round === self::ROUND_THIRD)
            ->groupBy('round')
            ->sortBy(fn (Collection $bouts) => $bouts->min('slot'))
            ->values();
    }

    /**
     * The bout $from feeds into, and which side of it.
     *
     * @return array{0: EventMatch, 1: string}|null
     */
    private function nextBout(Collection $rounds, EventMatch $from): ?array
    {
        foreach ($rounds as $r => $bouts) {
            $index = $bouts->search(fn (EventMatch $m) => $m->id === $from->id);
            if ($index === false) {
                continue;
            }

            $nextRound = $rounds->get($r + 1);
            if (! $nextRound) {
                return null; // this was the final
            }

            $bout = $nextRound->get(intdiv($index, 2));

            return $bout ? [$bout, $index % 2 === 0 ? 'a' : 'b'] : null;
        }

        return null;
    }

    private function score(?string $raw): ?string
    {
        $s = trim((string) $raw);

        return $s === '' ? null : mb_substr($s, 0, 16);
    }

    /** @return array<string, mixed> */
    private function row(EventMatch $m): array
    {
        return [
            'id' => $m->id,
            'round' => $m->round,
            'no' => $m->match_no,
            'court' => $m->court,
            'status' => $m->status,
            'winner' => $m->winner,
            'a' => ['name' => $m->a_name, 'competitor_id' => $m->a_competitor_id, 'score' => $m->a_score, 'provisional' => (bool) $m->a_provisional],
            'b' => ['name' => $m->b_name, 'competitor_id' => $m->b_competitor_id, 'score' => $m->b_score, 'provisional' => (bool) $m->b_provisional],
        ];
    }
}
