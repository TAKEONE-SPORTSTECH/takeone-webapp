<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventMatch;
use Illuminate\Support\Facades\DB;

/**
 * An entry left in no division while its person holds another entry in the
 * same event — and folding it into that one.
 *
 * Putting somebody into a second division COPIES their entry (the division
 * screen, the board, the desk); taking them out of a division only un-places
 * the row. Move X to Y by ticking Y and unticking X, or delete X's division, and
 * the row that was in X stays behind in no division: counted on the entry list,
 * listed under "Not in a division", and — if it never had a fee record — priced
 * at the list price the first time anyone marks the club paid. Event 105 (Energy
 * Zone Combat 4) carried twelve of them on the day, four charged twice.
 *
 * Folding keeps the money and the paperwork: fee lines, the public-entry
 * request and the club-entrant link move to the entry the person keeps, and only
 * then is the spare row deleted (through the model, so EntryPhoto decides about
 * a shared photo). The entry kept is the person's oldest PLACED one; a person
 * placed nowhere keeps their oldest row.
 *
 * ── Never folded ───────────────────────────────────────────────────────────
 *  · An event entered in separate PARTS (Kata · Kumite): a second row there is
 *    a second activity, paid for separately, not a spare.
 *  · A row any bout names — it is somebody's corner.
 *  · A row anything else still points at that cannot move (a claim, a
 *    withdrawal request, a team, a performance), or whose club link / payment
 *    proof would collide with the kept entry's own.
 *
 * Behind events.spare_entries (OFF by default, RULE #1).
 */
class SpareEntries
{
    /** Tables whose rows we do not know how to carry — their presence blocks a fold. */
    private const BLOCK = ['event_entry_claims', 'event_withdrawal_requests', 'event_side_members', 'event_performances'];

    public static function enabled(): bool
    {
        return (bool) config('events.spare_entries');
    }

    /**
     * Fold the spare rows of these people in this event. Returns how many rows
     * were folded away.
     *
     * @param  iterable<int>|null  $userIds  null = everybody in the event
     */
    public static function fold(ClubEvent $event, ?iterable $userIds = null): int
    {
        if (! self::enabled() || EventStreams::many($event)) {
            return 0;
        }

        $rows = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->whereNotNull('user_id')
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', collect($userIds)->map(fn ($id) => (int) $id)->unique()->all()))
            ->orderBy('id')
            ->get()
            ->groupBy('user_id');

        $folded = 0;

        foreach ($rows as $userRows) {
            if ($userRows->count() < 2) {
                continue;
            }

            $keep = $userRows->whereNotNull('category_id')->first() ?? $userRows->first();

            foreach ($userRows->whereNull('category_id') as $spare) {
                if ($spare->id !== $keep->id && self::foldInto($spare, $keep)) {
                    $folded++;
                }
            }
        }

        if ($folded) {
            rescue(fn () => activity()->withProperties(['event' => $event->uuid, 'folded' => $folded])
                ->log('events.spare_entries_folded'), null, true);
        }

        return $folded;
    }

    private static function foldInto(ClubEventRegistration $spare, ClubEventRegistration $keep): bool
    {
        $id = $spare->id;

        $named = EventMatch::where(fn ($q) => $q->where('a_competitor_id', $id)->orWhere('b_competitor_id', $id)
            ->orWhere('a_side_id', $id)->orWhere('b_side_id', $id))->exists();
        if ($named) {
            return false;
        }

        foreach (self::BLOCK as $table) {
            if (DB::getSchemaBuilder()->hasTable($table) && DB::table($table)->where('registration_id', $id)->exists()) {
                return false;
            }
        }

        // One club link per entry (unique index) — carried only when the kept
        // entry has none of its own.
        $club = DB::table('event_club_entrants')->where('registration_id', $id)->exists();
        if ($club && DB::table('event_club_entrants')->where('registration_id', $keep->id)->exists()) {
            return false;
        }

        if ($spare->payment_proof && $keep->payment_proof && $spare->payment_proof !== $keep->payment_proof) {
            return false;
        }

        DB::transaction(function () use ($spare, $keep, $id, $club) {
            $lines = DB::table('event_registration_fee_lines')->where('registration_id', $id);

            // A real charge arriving replaces the kept row's "Free" marker, so
            // the entry reads as what was actually bought; a free one is dropped.
            if ((clone $lines)->where('amount', '>', 0)->exists()) {
                DB::table('event_registration_fee_lines')->where('registration_id', $keep->id)->where('amount', 0)->delete();
                $lines->update(['registration_id' => $keep->id, 'updated_at' => now()]);
            } else {
                $lines->delete();
            }

            DB::table('event_public_entries')->where('registration_id', $id)->update(['registration_id' => $keep->id, 'updated_at' => now()]);

            if ($club) {
                DB::table('event_club_entrants')->where('registration_id', $id)->update(['registration_id' => $keep->id, 'updated_at' => now()]);
            }

            // What was settled on the spare is the person's, not the row's.
            if ($spare->paid && ! $keep->paid) {
                $keep->forceFill(['paid' => true, 'paid_at' => $spare->paid_at, 'paid_by' => $spare->paid_by])->save();
            }
            if ($spare->payment_proof && ! $keep->payment_proof) {
                $keep->forceFill(['payment_proof' => $spare->payment_proof])->save();
            }

            $spare->delete();
        });

        return true;
    }
}
