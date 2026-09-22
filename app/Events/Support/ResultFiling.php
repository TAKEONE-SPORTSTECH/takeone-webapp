<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Members\Models\User;
use App\Events\Models\ClubEvent;

/**
 * Whether a result may be FILED against this event yet, and how the day starts.
 *
 * ── Why it is shared ───────────────────────────────────────────────────────
 *
 * Enrolment, the draw, single and double elimination, a round robin and the
 * money are the same in every martial art. What differs is the scoreboard's
 * design, how it is operated, the rules of the game, and how points and fouls
 * are awarded. "Has this competition begun?" is none of those — no rule book,
 * governing body or mat design has an opinion on it — so it is ONE decision,
 * here, and every sport calls it.
 *
 * It did not start that way. The check lived inside one sport's scoring class
 * (Brazilian jiu-jitsu's), and karate and taekwondo had NO CHECK AT ALL: on
 * those mats a result went into the bracket for a competition that had not
 * begun, with nothing recorded about who did it. Same shape as the Advancement
 * / Roster / RunningOrder triplication on the house-cleaning register — common
 * logic in a private place drifts, and the drift is invisible.
 *
 * ── Why it refuses AND offers ──────────────────────────────────────────────
 *
 * Two real failures shape this, pulling in opposite directions:
 *
 *  · 2026-09-18 — refusing outright stopped a championship. The refusal lands
 *    on a SCORING TABLE with a finished bout on the mat and a hall waiting,
 *    and the button that clears it is on somebody else's screen. Two bouts
 *    fought, neither recordable, the running order stuck behind them.
 *
 *  · Starting the day silently, as a side effect of a result arriving, is the
 *    other extreme: a competition is put under way by a person, deliberately,
 *    after their pre-flight checks — not because an official pressed Commit.
 *
 * So: REFUSE, and hand the console something it can act on. EventNotUnderWay
 * is a type precisely so the console can offer "Start the day?" and let the
 * table proceed in one deliberate tap. Nobody is stuck, and nothing starts by
 * accident.
 *
 * A REHEARSAL is exempt throughout: it exists to run the mat beforehand with
 * the event's state snapshotted and every athlete-facing message held back, so
 * nothing a practice bout does survives it. See App\Events\Support\Rehearsal.
 */
class ResultFiling
{
    /** May a result be written against this event as things stand? */
    public function permitted(ClubEvent $event): bool
    {
        if (app(Rehearsal::class)->active($event)) {
            return true;
        }

        return $event->hasStarted();
    }

    /**
     * Refuse the command unless the day is genuinely under way.
     *
     * Each sport's console catches this and returns 422 carrying the message,
     * the mat's current state, and the flag that lets it offer the start.
     *
     * @throws EventNotUnderWay
     */
    public function refuseUnlessUnderWay(ClubEvent $event): void
    {
        if ($this->permitted($event)) {
            return;
        }

        throw new EventNotUnderWay(__('events.result_before_start'));
    }

    /**
     * Put the competition under way from the mat.
     *
     * ⚠️ THE SECOND HALF IS NOT OPTIONAL. Writing `started_at` alone is the
     * trap: the draw stays `provisional`, and the next thing to call
     * DrawEngine::ensure() re-cuts the division as the final paid-only draw —
     * DELETING the bout just filed along with every other result on it. A
     * ten-bout championship was lost that way on 2026-09-13. So the draw is
     * locked here, through the package's own hook, exactly as the event
     * console's Start does it, BEFORE any result is written.
     *
     * Best-effort by design: this is bookkeeping around somebody's result, and
     * a failure here must never cost them the result itself. If the lock
     * throws, the day is still started and the bout is still filed — and
     * DrawEngine's own guard refuses to re-cut a division that has been
     * fought, which is the belt to this pair of braces.
     */
    public function startDay(ClubEvent $event, ?User $operator = null): void
    {
        if ($event->hasStarted()) {
            return;
        }

        $event->started_at = now();
        // The official at the table, not the organiser who never pressed it —
        // the record should say who actually put the day under way.
        $event->started_by = $operator?->id ?? $event->created_by;
        // Started from the mat, so the organiser's pre-flight checklist was by
        // definition not walked. Flagged rather than hidden; the event console
        // and the MCP readiness tool both read this.
        $event->start_overridden = true;
        $event->save();

        try {
            app(EventTypeRegistry::class)->for($event)->onEntrantsChanged($event->fresh());
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
