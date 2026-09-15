<?php

namespace App\Events\Support;

use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventEntryExclusion;
use Illuminate\Support\Facades\Log;

/**
 * The weigh-in deadline, enforced.
 *
 * ── What it is for ──────────────────────────────────────────────────────────
 *
 * A bracketed championship already refuses to DRAW an entry nobody has checked:
 * the final draw takes only entries that are paid with a payments official's
 * name against them and weighed with a weigh-in official's name against them
 * (DrawEngine::build, `paidOnly`). Until now the entries that failed that test
 * simply stayed on the list — on the roster, on the participants page, in the
 * head count — and somebody had to notice and strike them off by hand, on the
 * one morning nobody has a spare minute.
 *
 * So the deadline the organiser already set does it: at `weigh_in_at`, an entry
 * that is not paid for and/or has not been on the scale comes off the list, the
 * athlete is told which of the two it was, and the draw is re-cut without them.
 *
 * ── Why it is opt-in, defaulted OFF ─────────────────────────────────────────
 *
 * It DELETES entries. No event that existed when this shipped asked for that,
 * and an organiser who runs a relaxed club competition may well want the
 * unweighed athlete to stay on the list. `club_events.entry_deadline_enforced`
 * is that consent, per event.
 *
 * ── Why the removal leaves a record ─────────────────────────────────────────
 *
 * Removal deletes the registration, so without `event_entry_exclusions` the
 * athlete would open the event and find the ordinary "enter this competition"
 * screen — no entry, no explanation, nothing to argue with. The row is what
 * lets the page say "you were taken off because your entry fee was never
 * confirmed", which is the difference between a system and a shrug.
 */
class EntryDeadline
{
    public function __construct(private EntryService $entries) {}

    /**
     * Every event whose deadline is due to be enforced right now.
     *
     * @return \Illuminate\Support\Collection<int, ClubEvent>
     */
    public function due(): \Illuminate\Support\Collection
    {
        return ClubEvent::query()
            ->where('entry_deadline_enforced', true)
            ->whereNotNull('weigh_in_at')
            ->where('weigh_in_at', '<=', now())
            ->where('status', '!=', 'cancelled')
            ->whereNull('started_at')      // once it is running the draw is final
            ->get()
            ->reject(fn (ClubEvent $event) => $event->hasEnded())
            ->values();
    }

    /**
     * Take the entries that never got ready off one event.
     *
     * Idempotent: an event swept twice removes nothing the second time, because
     * what it removes no longer exists. Safe to run hourly, and safe to run by
     * hand on the morning while the desk is still working — anybody weighed or
     * paid up between two runs simply stays.
     *
     * @param  User|null  $actor  the organiser, when a person pressed it. Null =
     *                            the deadline itself, which is the usual case.
     * @return array{removed: array<int, array{name: string, reason: string}>, kept: int, refused: array<int, string>}
     */
    public function enforce(ClubEvent $event, ?User $actor = null): array
    {
        $actor ??= $this->houseActor($event);

        // Anything at all — a base fee, a priced option, a late penalty. The
        // base-fee predicate alone reads an option-priced championship as free
        // (see EventFee::chargesAnything), and a deadline that thinks the event
        // is free would never take an unpaid entry off it.
        $charges = EventFee::chargesAnything($event, 'participant');
        $removed = [];
        $refused = [];
        $kept = 0;

        $entries = $event->participantRegistrations()->with('user')->get();

        foreach ($entries as $registration) {
            $unpaid = $charges && ! $this->settled($registration);
            $unweighed = ! $this->weighed($registration);

            if (! $unpaid && ! $unweighed) {
                $kept++;

                continue;
            }

            $reason = EventEntryExclusion::reasonFor($unpaid, $unweighed);
            $athlete = $registration->user;
            $name = $athlete?->full_name ?: ($athlete?->name ?: __('shared.unknown'));

            /*
             * The record is written BEFORE the removal, because the removal is
             * what destroys the link: after it there is no registration to read
             * the user off. A failed removal then leaves an exclusion nobody
             * acts on, which is visible and harmless — the reverse would be an
             * athlete removed with no explanation, which is the bug this exists
             * to prevent.
             */
            if ($athlete) {
                $this->record($event, $athlete, $reason, $actor);
            }

            $result = $this->entries->remove($event, $actor, $registration, notify: false);

            if (! $result['ok']) {
                // A bout already fought, or the event started underneath us.
                // The entry stays and the exclusion is withdrawn again: nothing
                // half-done survives this loop.
                $refused[] = $result['row']['message'] ?? $name;

                if ($athlete) {
                    EventEntryExclusion::where('event_id', $event->id)
                        ->where('user_id', $athlete->id)->delete();
                }

                continue;
            }

            $removed[] = ['name' => $name, 'reason' => $reason];

            if ($athlete) {
                $this->notify($event, $athlete, $reason);
            }
        }

        return ['removed' => $removed, 'kept' => $kept, 'refused' => $refused];
    }

    /** The standing answer for one person on one event, or null. */
    public function exclusionFor(ClubEvent $event, ?int $userId): ?EventEntryExclusion
    {
        if (! $userId) {
            return null;
        }

        return EventEntryExclusion::where('event_id', $event->id)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Re-entering somebody clears the answer — the deadline's verdict was about
     * one moment, and the organiser has overruled it.
     */
    public function forgive(ClubEvent $event, int $userId): void
    {
        EventEntryExclusion::where('event_id', $event->id)->where('user_id', $userId)->delete();
    }

    /**
     * Paid, and an official said so. `paid` alone is a claim — a member ticking
     * a box — and the final draw does not accept it either.
     */
    private function settled(ClubEventRegistration $registration): bool
    {
        return (bool) $registration->paid && $registration->paid_by !== null;
    }

    /** On the scale, with the official's name against it. */
    private function weighed(ClubEventRegistration $registration): bool
    {
        return $registration->weighed_in_at !== null
            && $registration->weighed_in_by !== null
            && $registration->weight !== null;
    }

    private function record(ClubEvent $event, User $athlete, string $reason, User $actor): void
    {
        EventEntryExclusion::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $athlete->id],
            ['reason' => $reason, 'decided_by' => $actor->id, 'excluded_at' => now()],
        );
    }

    /**
     * Tell them, and tell them WHICH thing was missing — "you were removed" on
     * its own is the message that generates the phone call.
     */
    private function notify(ClubEvent $event, User $athlete, string $reason): void
    {
        rescue(fn () => UserNotification::notifyUser($athlete->id, 'event', __('events.entry_deadline_notify_title', [
            'title' => $event->title,
        ]), [
            'body' => __('events.entry_deadline_reason_'.$reason),
            'icon' => 'bi-clock-history',
            'action_url' => route('me.events.show', $event->uuid),
            'tenant_id' => $event->tenant_id,
            'subject_type' => (new ClubEvent)->getMorphClass(),
            'subject_id' => $event->id,
        ]), null, false);
    }

    /**
     * Whose hand this is, when nobody's is: the organiser who set the deadline.
     * `EntryService::remove()` needs an actor for its own authority checks and
     * its audit trail, and "the system" is not a user id.
     */
    private function houseActor(ClubEvent $event): User
    {
        $actor = ($event->created_by ? User::find($event->created_by) : null) ?: $event->tenant?->owner;

        if (! $actor) {
            Log::warning('Entry deadline: event '.$event->id.' has no owner to act as.');

            abort(500, 'No actor for entry deadline enforcement.');
        }

        return $actor;
    }
}
