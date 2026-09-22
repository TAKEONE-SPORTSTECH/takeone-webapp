<?php

namespace App\Scoreboard;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventMatch;

/**
 * Putting a corrected competitor back on the wall.
 *
 * ── The problem ────────────────────────────────────────────────────────────
 *
 * A mat's board is a SNAPSHOT. Every one of the three engines assembles what
 * the screens announce — the name, the club crest, the belt, the weight and the
 * photograph — once, when the bout is loaded, and keeps it on the mat's own
 * state. That is deliberate: it is what makes a board instant, what lets it
 * survive a power cut, and what stops two screens on one mat disagreeing.
 *
 * It also means the board stops listening. Correct a competitor's photograph
 * from the event's people list while their bout is on the mat and the database
 * changes underneath a wall that carries on showing the picture that was true
 * when they were called. Reported on 2026-09-16 against the Victory BJJ
 * championship: an entry photo was replaced, the row was written, and the VS
 * screen kept the old face. The mat had no idea anything had happened, because
 * nothing told it.
 *
 * ── What this does ─────────────────────────────────────────────────────────
 *
 * Finds the mats of this event that currently have a bout involving this entry
 * loaded, and sends each one the engine's own `refresh` command — the one that
 * re-reads both corners from the entries and touches nothing else: not the
 * clock, not the score, not the penalties, not the mode, not the result. Then
 * pushes the new state to that mat's screens down the path every other command
 * already uses.
 *
 * Best-effort, always. A board that could not be reached is a board showing a
 * slightly old photograph; an edit that failed because a television was off is
 * a worse outcome than the fault it was trying to fix. Every call here is
 * wrapped, and the caller is never told.
 */
class MatEntries
{
    /**
     * An entry changed — put it back on whatever screens are showing it.
     *
     * @param  int  $registrationId  club_event_registrations.id
     */
    public function entryChanged(ClubEvent $event, int $registrationId): void
    {
        $sport = (string) $event->sport;

        if (! isset(self::SPORTS[$sport]) || $registrationId <= 0) {
            return;
        }

        /*
         * Every entry this PERSON holds in this event, not only the row that
         * was edited.
         *
         * An athlete is entered once per division, the people list shows them
         * once, and the draw places whichever of their rows it placed. So the
         * row an organiser edits and the row standing on the mat are routinely
         * two different rows of the same person — which is exactly how a
         * corrected photograph failed to reach the wall. Only the mats are
         * widened by this: nothing here reads or returns an entry.
         */
        $entryIds = \App\Events\Models\ClubEventRegistration::where('event_id', $event->id)
            ->where(fn ($q) => $q->where('id', $registrationId)
                ->orWhereIn('user_id', \App\Events\Models\ClubEventRegistration::query()
                    ->where('id', $registrationId)
                    ->whereNotNull('user_id')
                    ->select('user_id')))
            ->pluck('id')->all();

        // Which bouts those entries are in. The mats are then matched against
        // these by the id they hold, so a mat showing somebody else is never
        // touched.
        $matchIds = EventMatch::where('event_id', $event->id)
            ->where(fn ($q) => $q->whereIn('a_competitor_id', $entryIds)
                ->orWhereIn('b_competitor_id', $entryIds))
            ->pluck('id')->all();

        if (! $matchIds) {
            return;
        }

        rescue(fn () => match ($sport) {
            'bjj' => $this->bjj($event, $matchIds),
            'karate' => $this->karate($event, $matchIds),
            'taekwondo' => $this->taekwondo($event, $matchIds),
        }, null, false);

        /*
         * And the UPCOMING board, which is a different surface from the mat.
         *
         * The block above refreshes the bout LOADED on a mat — one bout, the
         * one being fought. The wall beside it is showing the four bouts still
         * to come, drawn from its own payload, and an athlete whose name, club,
         * country or photograph was just corrected is standing in that queue
         * too. Only one of the two was ever told, so a correction appeared on
         * the scoreboard and not on the board next to it.
         *
         * Every mat, not the ones holding the bout: a competitor is routinely
         * queued on a mat they are not currently fighting on, and rebuilding
         * three or four boards is cheaper than one wrong wall — the same
         * reasoning the result path already states.
         */
        $this->boards($event);
    }

    /**
     * Rebuild and push every mat's upcoming board for this event.
     *
     * Best-effort and silent, like everything else on this path: an edit must
     * not fail because a screen is unreachable.
     */
    public function boards(ClubEvent $event): void
    {
        $sport = (string) $event->sport;

        if (! isset(self::SPORTS[$sport])) {
            return;
        }

        rescue(fn () => match ($sport) {
            'bjj' => \App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenChannel::notifyCourt($event, null),
            'karate' => \App\Scoreboard\Sports\Karate\HallScreen\ScreenChannel::notifyCourt($event, null),
            'taekwondo' => \App\Scoreboard\Sports\Taekwondo\HallScreen\ScreenChannel::notifyCourt($event, null),
        }, null, false);
    }

    /** The sports with a mat. Kept beside Fleet::MATS, and no wider. */
    private const SPORTS = ['bjj' => true, 'karate' => true, 'taekwondo' => true];

    /**
     * BJJ keeps its mats in a TABLE, so the mats showing this bout can be asked
     * for directly rather than guessed at from the draw.
     *
     * @param  array<int>  $matchIds
     */
    private function bjj(ClubEvent $event, array $matchIds): void
    {
        $scoring = app(\App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Scoring::class);

        $courts = \App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\MatState::where('event_id', $event->id)
            ->whereIn('match_id', $matchIds)
            ->pluck('court');

        foreach ($courts as $court) {
            $fresh = $scoring->apply($event, (string) $court, 'refresh');

            \App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenChannel::notifyCourt(
                $event, (string) $court, ['action' => 'mat', 'state' => $fresh->present()],
            );
        }
    }

    /**
     * Karate's mats live in the CACHE, keyed by court, so they cannot be
     * queried — the event's courts are read off the draw and each one's state
     * is opened and asked what it is showing.
     *
     * @param  array<int>  $matchIds
     */
    private function karate(ClubEvent $event, array $matchIds): void
    {
        $scoring = app(\App\Scoreboard\Sports\Karate\Mat\Scoring::class);

        foreach ($this->courts($event) as $court) {
            $state = \App\Scoreboard\Sports\Karate\Mat\MatState::load($event, $court);

            if (! in_array((int) $state->matchId, $matchIds, true)) {
                continue;
            }

            $fresh = $scoring->apply($event, $court, 'refresh');

            \App\Scoreboard\Sports\Karate\HallScreen\ScreenChannel::notifyCourt(
                $event, $court, ['action' => 'mat', 'state' => $fresh->toArray()],
            );
        }
    }

    /**
     * Taekwondo, the same way — and its engine has had the `refresh` command
     * since it was written, which is where the other two took the name from.
     *
     * @param  array<int>  $matchIds
     */
    private function taekwondo(ClubEvent $event, array $matchIds): void
    {
        $scoring = app(\App\Scoreboard\Sports\Taekwondo\Mat\Scoring::class);

        foreach ($this->courts($event) as $court) {
            $state = \App\Scoreboard\Sports\Taekwondo\Mat\MatState::load($event, $court);

            if (! in_array((int) $state->matchId, $matchIds, true)) {
                continue;
            }

            $fresh = $scoring->apply($event, $court, 'refresh');

            \App\Scoreboard\Sports\Taekwondo\HallScreen\ScreenChannel::notifyCourt(
                $event, $court, ['action' => 'mat', 'state' => $fresh->toArray()],
            );
        }
    }

    /**
     * The event's mats, as every console already derives them.
     *
     * @return array<string>
     */
    private function courts(ClubEvent $event): array
    {
        return $event->matches()->whereNotNull('court')
            ->distinct()->orderBy('court')->pluck('court')
            ->map(fn ($c) => (string) $c)->all();
    }
}
