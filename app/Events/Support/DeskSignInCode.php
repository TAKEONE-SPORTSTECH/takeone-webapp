<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventSignInCode;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * A way back in, from the entry desk, for an athlete who forgot their password.
 *
 * The platform has no SMS and most athletes enter with a phone and no email,
 * so "forgot password" used to end in a second account. Instead the organiser
 * — or an official appointed to the desk — issues a one-time code on the
 * athlete's card, reads it out, and the athlete types it with their own number
 * on the event's sign-in page and picks a new password.
 *
 * What keeps it from being a takeover tool:
 *   · issued only by somebody who may enter athletes in THIS event, only for
 *     somebody entered in THIS event, and redeemable only on this event;
 *   · never for an account with two-factor, or one that runs clubs or events
 *     (EntryDesk staff must not be able to reset each other, or an admin);
 *   · six digits, fifteen minutes, one use, five wrong tries and it is dead,
 *     and the door itself is throttled like a login; only the HMAC is stored;
 *   · every issue and use is in the activity log, and the athlete is told.
 */
class DeskSignInCode
{
    public const TTL_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private EventAccess $access, private KnownPerson $people) {}

    public function mayIssue(ClubEvent $event, User $actor): bool
    {
        return $this->access->canManage($event, $actor) || $this->access->canEnterAthletes($event, $actor);
    }

    /** @return array{ok: bool, message: string, code?: string, phone?: string, expires_minutes?: int} */
    public function issue(ClubEvent $event, ClubEventRegistration $entry, User $actor): array
    {
        if ((int) $entry->event_id !== (int) $event->id || ! $this->mayIssue($event, $actor)) {
            return ['ok' => false, 'message' => __('events::events.desk_code_not_allowed')];
        }

        $person = $entry->user;
        if (! $person || $person->isSuperAdmin() || $person->hasTwoFactorEnabled() || $person->entersPeopleOnBehalfOfOthers()) {
            return ['ok' => false, 'message' => __('events::events.desk_code_protected')];
        }
        if (blank($person->phone_key)) {
            return ['ok' => false, 'message' => __('events::events.desk_code_no_phone')];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($event, $person, $actor, $code) {
            EventSignInCode::where('event_id', $event->id)->where('user_id', $person->id)->whereNull('used_at')
                ->update(['used_at' => now()]);
            EventSignInCode::create([
                'event_id' => $event->id,
                'user_id' => $person->id,
                'code_hash' => $this->hash($code),
                'issued_by' => $actor->id,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);
        });

        activity()->causedBy($actor)->performedOn($person)->withProperties(['event' => $event->uuid])->log('events.desk_sign_in_code_issued');

        $m = is_array($person->mobile) ? $person->mobile : [];

        return [
            'ok' => true,
            'message' => __('events::events.desk_code_issued'),
            'code' => $code,
            // Shown so the desk can check it is the right person's phone —
            // the organiser already sees it on the entry.
            'phone' => trim(($m['code'] ?? '').' '.($m['number'] ?? '')),
            'expires_minutes' => self::TTL_MINUTES,
        ];
    }

    /**
     * Redeem: the right code for somebody on this phone, in this event → they
     * get the new password and are handed back to be signed in. One answer for
     * every failure.
     */
    public function redeem(ClubEvent $event, ?string $dial, ?string $number, string $code, string $password): ?User
    {
        $ids = $this->people->byPhone($dial, $number)->pluck('id');
        if ($ids->isEmpty() || ! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        return DB::transaction(function () use ($event, $ids, $code, $password) {
            $live = EventSignInCode::live()->where('event_id', $event->id)->whereIn('user_id', $ids)->lockForUpdate()->get();
            $hit = $live->first(fn (EventSignInCode $c) => hash_equals($c->code_hash, $this->hash($code)));

            if (! $hit) {
                // A wrong guess spends a try on every live code for this phone.
                EventSignInCode::whereIn('id', $live->pluck('id'))->increment('attempts');

                return null;
            }

            $person = User::find($hit->user_id);
            if (! $person || $person->hasTwoFactorEnabled() || $person->entersPeopleOnBehalfOfOthers()) {
                return null;
            }

            $hit->update(['used_at' => now()]);
            $person->forceFill(['password' => Hash::make($password), 'is_unclaimed' => false])->save();
            // The account now has a password: a coach's claim link for it is spent.
            \App\Events\Models\EventEntryClaim::where('user_id', $person->id)->whereNull('claimed_at')->update(['claimed_at' => now()]);

            activity()->performedOn($person)->withProperties(['event' => $event->uuid])->log('events.desk_sign_in_code_used');

            return $person;
        });
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
