<?php

namespace App\Events\Support\Tournament;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Models\EventNotificationSent;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Members\Models\UserRelationship;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

/**
 * Calling athletes to the mat.
 *
 * Deliberately only two pushes per bout — a warm-up warning and the call-room
 * summons. More than that and athletes silence the app, which is the failure
 * mode that kills tournament systems: the one notification that actually
 * matters ("report NOW") arrives muted.
 *
 * Each push is claimed in the same ledger the milestone system uses, keyed by
 * (bout, athlete, threshold), so a mat being re-scored — or two results landing
 * at once — can never call the same athlete twice.
 *
 * For a competitor under 18 the guardian is told as well; at that age it is the
 * parent standing outside the field of play who needs to hear it.
 */
abstract class CallNotifier
{
    use SpeaksPackageLanguage;

    public function __construct(private RunningOrder $order) {}

    /**
     * Push any calls that have just come due — after a bout on this mat is
     * decided, the queue behind it moves up.
     *
     * @return int notifications sent
     */
    public function pushDue(ClubEvent $event, ?string $court = null): int
    {
        if (! config('event_notifications.enabled', true) || $event->status === 'cancelled') {
            return 0;
        }

        $sent = 0;
        $thresholds = config('event_notifications.call_thresholds', []);

        foreach ($this->order->callsDue($event, $court) as $threshold => $calls) {
            foreach ($calls as $call) {
                $sent += $this->call($event, $call['bout'], $call['entry_id'], (int) $threshold, $thresholds[$threshold] ?? 'warmup', $call['ahead']);
            }
        }

        return $sent;
    }

    /** Tell one athlete (and their guardian, if a minor) about one bout, once. */
    private function call(ClubEvent $event, EventMatch $bout, int $entryId, int $threshold, string $kind, int $ahead): int
    {
        if (! $this->claim($event, $bout->id, $entryId, $threshold)) {
            return 0;   // already called at this threshold
        }

        $entry = ClubEventRegistration::with('user')->find($entryId);
        $athlete = $entry?->user;

        if (! $athlete) {
            return 0;
        }

        $mat = $bout->court ?: $this->t('call_mat_tbc');
        $eta = $this->order->eta($event, $ahead);

        $title = $kind === 'call_room'
            ? $this->t('call_room_title', ['mat' => $mat])
            : $this->t('call_warmup_title', ['mat' => $mat]);

        $body = $kind === 'call_room'
            ? $this->t('call_room_body', [
                'mat' => $mat,
                'bout' => $bout->match_no ?? '—',
                'opponent' => $this->opponentName($bout, $entryId),
            ])
            : $this->t('call_warmup_body', [
                'ahead' => $ahead,
                'minutes' => $eta ?? 0,
            ]);

        $recipients = array_merge([$athlete->id], $this->guardiansOf($athlete));

        foreach ($recipients as $userId) {
            rescue(fn () => UserNotification::notifyUser($userId, 'event', $title, [
                'body' => $body,
                'icon' => $kind === 'call_room' ? 'bi-megaphone-fill' : 'bi-stopwatch',
                'action_url' => route('me.events.next-up', $event->uuid),
                'tenant_id' => $event->tenant_id,
                'subject_type' => (new ClubEvent)->getMorphClass(),
                'subject_id' => $event->id,
                'context' => $kind,
                // The summons has to ring through a locked, idle phone in a
                // warm-up hall. The warm-up nudge does not.
                'urgent' => $kind === 'call_room',
            ]), null, false);
        }

        return count($recipients);
    }

    /**
     * Tell the athlete their bout is decided, and what is next.
     * Fired straight after a result — the moment they most want the app.
     */
    public function pushResult(ClubEvent $event, EventMatch $bout): int
    {
        if (! config('event_notifications.enabled', true)) {
            return 0;
        }

        $sent = 0;

        foreach ($bout->competitorIds() as $entryId) {
            if (! $this->claim($event, $bout->id, $entryId, threshold: -1)) {
                continue;
            }

            $entry = ClubEventRegistration::with('user')->find($entryId);
            if (! $entry?->user) {
                continue;
            }

            $won = $bout->winnerCompetitorId() === $entryId;
            $next = $this->order->nextFor($event, $entry);

            $body = $next
                ? $this->t('result_body_next', [
                    'round' => $next['round'],
                    'mat' => $next['court'] ?? '—',
                    'minutes' => $next['eta_minutes'] ?? 0,
                ])
                : $this->t('result_body_done');

            foreach (array_merge([$entry->user->id], $this->guardiansOf($entry->user)) as $userId) {
                rescue(fn () => UserNotification::notifyUser($userId, 'event', $won
                    ? $this->t('result_win_title')
                    : $this->t('result_loss_title'), [
                        'body' => $body,
                        'icon' => $won ? 'bi-trophy' : 'bi-flag',
                        'action_url' => route('me.events.next-up', $event->uuid),
                        'tenant_id' => $event->tenant_id,
                        'subject_type' => (new ClubEvent)->getMorphClass(),
                        'subject_id' => $event->id,
                        'context' => 'result',
                    ]), null, false);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Claim (bout, athlete, threshold) so it is announced exactly once, however
     * many times the mat is re-scored.
     */
    private function claim(ClubEvent $event, int $boutId, int $entryId, int $threshold): bool
    {
        try {
            EventNotificationSent::create([
                'event_id' => $event->id,
                'milestone' => 'call:'.$boutId.':'.$entryId.':'.$threshold,
                'sent_at' => now(),
            ]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }

    /** Guardians of a competitor who is still a minor. */
    private function guardiansOf(User $athlete): array
    {
        if (! $athlete->birthdate || Carbon::parse($athlete->birthdate)->age >= 18) {
            return [];
        }

        return UserRelationship::where('dependent_user_id', $athlete->id)
            ->pluck('guardian_user_id')->map('intval')->unique()->values()->all();
    }

    private function opponentName(EventMatch $bout, int $entryId): string
    {
        $side = $bout->a_competitor_id === $entryId ? 'b' : 'a';

        return $bout->{$side.'_name'} ?: $this->t('call_opponent_tbc');
    }
}
