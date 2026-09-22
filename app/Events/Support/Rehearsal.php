<?php

namespace App\Events\Support;

use App\Members\Models\User;
use App\Models\ClubEvent;
use App\Models\EventRehearsal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Running the whole competition before the day, for real, and then taking it
 * all back.
 *
 * ── The problem ────────────────────────────────────────────────────────────
 *
 * Nobody wants to discover on the morning that the draw advances into the wrong
 * slot, that the wall board does not follow a result, that the console's End
 * modal asks the wrong question, or that a camera never stops. Every one of
 * those is only found by RUNNING a bout — and running a bout files a result,
 * moves a bracket, tells athletes they have won and puts medals on a division.
 *
 * So the platform gave organisers half a rehearsal: the clock, the score and
 * the boards all work before the day, and `end`/`decision`/`commit` are refused
 * (see NEEDS_EVENT_STARTED) precisely so a practice bout cannot reach the
 * bracket. The half that was refused is the half worth testing.
 *
 * ── How this works ─────────────────────────────────────────────────────────
 *
 * By snapshot, not by simulation. Beginning a rehearsal copies the event's
 * competition state — the event row, every division, every bout, all columns —
 * and notes how far each of the run-day log tables had got. Everything then
 * runs through the ORDINARY engines: the same `commit`, the same Advancement,
 * the same podium, the same screens. Nothing is special-cased and no engine
 * knows it is rehearsing, which is the only way a rehearsal can prove anything
 * about the real thing.
 *
 * Ending it writes the snapshot back and deletes what was written above the
 * marks. The event is as it was.
 *
 * ── What is held back while it runs ────────────────────────────────────────
 *
 * Three things must not escape the hall, because they reach people and records
 * OUTSIDE the event, where a snapshot cannot follow:
 *
 *   1. Athlete notifications — "you won", "you are called to Mat 1". Suppressed
 *      at the notifier (see CallNotifier), not cleaned up afterwards: an
 *      apology is not a rollback.
 *   2. The public page's draw and results — withheld exactly as a not-yet-
 *      published draw is, so a stranger reading /e/{uuid} never sees a
 *      rehearsal's bracket (see EventAccess::drawVisible and PublicEvent).
 *   3. Nothing else is held back. The wall boards, the mats, the cameras and
 *      the consoles all behave exactly as they will on the day — they are what
 *      is being tested.
 *
 * ── The line this will not cross ───────────────────────────────────────────
 *
 * A rehearsal cannot begin on an event that has STARTED, and starting the event
 * ends any rehearsal first. The competition day is not a thing to be rolled
 * back, and the one fatal version of this feature is a real result disappearing
 * into a snapshot somebody forgot they had taken.
 */
class Rehearsal
{
    /**
     * The tables whose rows are snapshotted and written back verbatim.
     *
     * All columns, on purpose. Enumerating "the ones a result touches" is a
     * list that goes stale the first time a package writes a new column, and
     * the failure would be silent: a rehearsal that quietly keeps something.
     *
     * @var array<string, string>  table => the column holding the event id
     */
    private const RESTORED = [
        'event_matches' => 'event_id',
        'event_categories' => 'event_id',
    ];

    /**
     * The run-day logs. Rows ABOVE the mark taken at the start were written by
     * the rehearsal and are deleted with it; rows below are real history.
     *
     * A table this platform does not have (a sport that has not shipped its
     * log yet) is skipped rather than assumed — see mark().
     *
     * @var array<string, string>
     */
    private const TRIMMED = [
        'bjj_match_events' => 'event_id',
        'event_match_events' => 'event_id',
        // The claim rows that stop one athlete being called twice. A rehearsal
        // must not leave a claim behind, or the REAL call on the day is read as
        // already sent and never goes out.
        'event_notifications_sent' => 'event_id',
    ];

    /**
     * Answered once per event per request.
     *
     * Every mat payload carries this flag now (MatState::present), and a
     * payload is built on every command and every screen poll — so the honest
     * shape is one query per request, not one per read.
     *
     * ⚠️ An INSTANCE property, and the class is bound `scoped` in
     * AppServiceProvider — never a static. A static cache outlives the request
     * that filled it: in a test process (and under Octane) a rehearsal begun in
     * one case is still "running" in the next, and `begin()` then refuses on an
     * event that has never rehearsed. That is not hypothetical — it broke five
     * tests the moment the cache was added.
     *
     * @var array<int, bool>
     */
    private array $memo = [];

    /** Is this event rehearsing right now? */
    public function active(ClubEvent $event): bool
    {
        return $this->memo[$event->id] ??= $this->current($event) !== null;
    }

    /** Forget what was memoised — the answer just changed. */
    public function forget(?int $eventId = null): void
    {
        if ($eventId === null) {
            $this->memo = [];

            return;
        }

        unset($this->memo[$eventId]);
    }

    /** The open rehearsal on this event, or null. */
    public function current(ClubEvent $event): ?EventRehearsal
    {
        return EventRehearsal::where('event_id', $event->id)
            ->whereNull('ended_at')
            ->latest('id')
            ->first();
    }

    /**
     * Begin one.
     *
     * Refused on an event that has already started — that is the day itself,
     * and it is not rehearsable — and refused twice over, because a second
     * snapshot taken mid-rehearsal would record the rehearsal AS the truth to
     * restore, which is the one way this feature could destroy an event.
     *
     * @throws \RuntimeException
     */
    public function begin(ClubEvent $event, ?User $actor = null): EventRehearsal
    {
        if ($event->hasStarted()) {
            throw new \RuntimeException(__('events.rehearsal_event_started'));
        }

        if ($this->active($event)) {
            throw new \RuntimeException(__('events.rehearsal_already_running'));
        }

        $this->forget($event->id);

        return DB::transaction(function () use ($event, $actor) {
            return EventRehearsal::create([
                'event_id' => $event->id,
                'started_by' => $actor?->id,
                'started_at' => now(),
                'snapshot' => $this->snapshot($event),
                'marks' => $this->marks(),
                'bouts_run' => 0,
            ]);
        });
    }

    /**
     * End it, and put the event back exactly as it was.
     *
     * Everything in one transaction: a restore that half-happened would leave a
     * bracket advanced into a division that had been put back, which is worse
     * than either state on its own.
     */
    public function end(ClubEvent $event): ?EventRehearsal
    {
        $rehearsal = $this->current($event);

        if (! $rehearsal) {
            return null;
        }

        DB::transaction(function () use ($rehearsal) {
            $this->restore($rehearsal);
            $this->trim($rehearsal);

            $rehearsal->forceFill(['ended_at' => now()])->save();
        });

        $this->forget($event->id);

        return $rehearsal->fresh();
    }

    /* ==================== The snapshot ==================== */

    /**
     * The event's competition state, as it stands.
     *
     * @return array<string, mixed>
     */
    private function snapshot(ClubEvent $event): array
    {
        $tables = [];

        foreach (self::RESTORED as $table => $key) {
            $tables[$table] = DB::table($table)->where($key, $event->id)->get()
                ->map(fn ($row) => (array) $row)->all();
        }

        return [
            // The event row itself: status, started_at, ended_at, results — all
            // of which a run-day can move.
            'club_events' => (array) DB::table('club_events')->where('id', $event->id)->first(),
            'tables' => $tables,
        ];
    }

    /**
     * How far each log had got. A table that does not exist is not marked, and
     * is therefore never trimmed.
     *
     * @return array<string, int>
     */
    private function marks(): array
    {
        $marks = [];

        foreach (self::TRIMMED as $table => $key) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $marks[$table] = (int) DB::table($table)->max('id');
        }

        return $marks;
    }

    /* ==================== Putting it back ==================== */

    /** Every snapshotted row, written back column for column. */
    private function restore(EventRehearsal $rehearsal): void
    {
        $snapshot = $rehearsal->snapshot ?? [];
        $eventId = (int) $rehearsal->event_id;

        if ($event = $snapshot['club_events'] ?? null) {
            DB::table('club_events')->where('id', $eventId)
                ->update($this->writable((array) $event));
        }

        foreach (self::RESTORED as $table => $key) {
            $rows = $snapshot['tables'][$table] ?? [];
            $keep = [];

            // What still exists, asked ONCE rather than per row.
            $present = DB::table($table)->where($key, $eventId)->pluck('id')->all();
            $present = array_flip(array_map('intval', $present));

            foreach ($rows as $row) {
                $row = (array) $row;
                $id = (int) $row['id'];
                $keep[] = $id;

                if (isset($present[$id])) {
                    DB::table($table)->where('id', $id)->update($this->writable($row));

                    continue;
                }

                /*
                 * ⚠️ IT IS GONE, SO PUT IT BACK — with its original id.
                 *
                 * A division that is re-cut during the rehearsal is not edited:
                 * `DrawEngine` DELETES its bouts and builds new ones
                 * (`$cat->matches()->delete()`), and a re-cut is triggered by
                 * ordinary things — an entrant changing, a weigh-in, a page
                 * view on a provisional draw. Restoring by UPDATE alone would
                 * have found nothing to update, deleted the new rows as
                 * strangers, and handed back a division with no draw at all.
                 * That is the one shape of loss this whole feature exists to
                 * make impossible, and it was reachable until 2026-09-17.
                 *
                 * The id is written back deliberately: it is what a camera's
                 * clip, an officiating row and a result all point at.
                 */
                DB::table($table)->insert($row);
            }

            /*
             * A row that did not exist when the rehearsal began, and belongs to
             * this event, was made by the rehearsal — a bronze play-off the
             * advancement cut, say. It goes.
             *
             * Ids are never reinserted: `event_matches.id` is what a camera's
             * clip points at, and the organiser keeps their rehearsal footage
             * (their choice), so a deleted-and-recreated bout would orphan it.
             * Restoring in place is what keeps those attachments true.
             */
            DB::table($table)->where($key, $eventId)
                ->when($keep !== [], fn ($q) => $q->whereNotIn('id', $keep))
                ->delete();
        }
    }

    /** The rehearsal's own log rows, and nothing below the mark. */
    private function trim(EventRehearsal $rehearsal): void
    {
        foreach (($rehearsal->marks ?? []) as $table => $mark) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where(self::TRIMMED[$table] ?? 'event_id', $rehearsal->event_id)
                ->where('id', '>', (int) $mark)
                ->delete();
        }
    }

    /**
     * A snapshotted row, minus the two columns that must never be written back.
     *
     * `id` is the row's identity and `created_at` is when it was made; neither
     * is state a rehearsal can have changed, and writing them back is how a
     * restore turns into a reinsert.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function writable(array $row): array
    {
        unset($row['id'], $row['created_at']);

        return $row;
    }
}
