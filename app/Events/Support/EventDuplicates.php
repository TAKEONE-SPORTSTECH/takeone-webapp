<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Members\Models\User;
use App\Members\Support\ProfilePhoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The last check before the draw: is anybody in this competition twice?
 *
 * The entry doors now recognise a person they already have (KnownPerson), but
 * some duplicates still arrive — a new phone and a name typed differently, two
 * coaches entering the same athlete. A duplicate in a draw is found on the
 * mat, which is the worst place to find it, so the organiser is shown every
 * likely pair among THIS event's entrants, side by side, before the bracket
 * is built:
 *
 *   · the same name (case, spacing and punctuation ignored), or
 *   · a recognisably similar name (KnownPerson::samePerson) together with the
 *     same birthday, the same phone or the same club.
 *
 * and decides each one:
 *
 *   · SAME PERSON — keep one. The other's entries move onto them, except where
 *     the kept person is already in that division, where the extra entry is
 *     removed through EntryService::remove() (the one removal path, with its
 *     own rules: nothing is taken out of an event that has started). The two
 *     ACCOUNTS are left as they are — joining accounts is the platform's job
 *     (the members Scan), and the removed record still shows up there.
 *   · DIFFERENT PEOPLE — recorded in `member_duplicate_dismissals`, the same
 *     "not the same person" the platform Scan uses, so neither asks again.
 *
 * Organiser only (EventAccess::canManage — the same authority as removing an
 * entry). Behind `events.entry_dedupe`.
 */
class EventDuplicates
{
    /** Beyond this many people the pairwise comparison is not attempted. */
    private const MAX_PEOPLE = 1500;

    public function __construct(
        private KnownPerson $people,
        private EntryService $entries,
        private EventTypeRegistry $registry,
    ) {}

    /** @return array<int, array{key: string, a: array, b: array, reasons: string[], strong: bool}> */
    public function pairs(ClubEvent $event): array
    {
        $rows = $event->participantRegistrations()
            ->with(['user', 'category:id,name', 'representingTenant:id,club_name'])
            ->get()
            ->filter(fn (ClubEventRegistration $r) => $r->user !== null);

        $byPerson = $rows->groupBy('user_id');
        if ($byPerson->count() < 2 || $byPerson->count() > self::MAX_PEOPLE) {
            return [];
        }

        $dismissed = $this->dismissed($byPerson->keys()->all());
        $people = $byPerson->map(fn (Collection $regs) => $this->person($regs))->values()->all();

        $pairs = [];
        $n = count($people);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                [$a, $b] = [$people[$i], $people[$j]];
                if (isset($dismissed[min($a['id'], $b['id']).'-'.max($a['id'], $b['id'])])) {
                    continue;
                }
                if ($pair = $this->judge($a, $b)) {
                    $pairs[] = $pair;
                }
            }
        }

        usort($pairs, fn ($x, $y) => [$y['strong'], count($y['reasons'])] <=> [$x['strong'], count($x['reasons'])]);

        return array_map(function ($p) {
            unset($p['a']['id'], $p['b']['id'], $p['a']['_m'], $p['b']['_m']);

            return $p;
        }, $pairs);
    }

    /** Is this pair worth a human's look, and why? */
    private function judge(array $a, array $b): ?array
    {
        $exact = $a['_m']['name'] !== '' && $a['_m']['name'] === $b['_m']['name'];
        $similar = $exact || $this->people->samePerson($a['name'], $b['name']);
        if (! $similar) {
            return null;
        }

        $reasons = [$exact ? 'name' : 'similar'];
        $bday = $a['_m']['bday'] && $a['_m']['bday'] === $b['_m']['bday'];
        $phone = $a['_m']['phone'] && $a['_m']['phone'] === $b['_m']['phone'];
        $club = $a['_m']['club'] && $a['_m']['club'] === $b['_m']['club'];
        if ($bday) {
            $reasons[] = 'birthday';
        }
        if ($phone) {
            $reasons[] = 'phone';
        }
        if ($club) {
            $reasons[] = 'club';
        }
        // A similar spelling on its own is two different people far too often.
        if (! $exact && ! $bday && ! $phone && ! $club) {
            return null;
        }
        $differentBirthdays = $a['_m']['bday'] && $b['_m']['bday'] && ! $bday;

        return [
            'key' => $a['key'].'|'.$b['key'],
            'a' => $a,
            'b' => $b,
            'reasons' => $reasons,
            'strong' => ! $differentBirthdays && ($bday || $phone || ($exact && $club)),
            'different_birthdays' => $differentBirthdays,
        ];
    }

    /** What the organiser needs to tell two people apart — and what the check compares. */
    private function person(Collection $regs): array
    {
        /** @var ClubEventRegistration $first */
        $first = $regs->first();
        $u = $first->user;
        $m = is_array($u->mobile) ? $u->mobile : [];
        $club = $first->representingTenant?->club_name;

        return [
            'id' => (int) $u->id,
            'key' => (string) $u->uuid,
            'name' => (string) ($u->full_name ?: $u->name),
            'photo' => ProfilePhoto::url($u),
            'birthdate' => $u->birthdate?->format('d/m/Y'),
            'phone' => filled($m['number'] ?? null) ? trim(($m['code'] ?? '').' '.$m['number']) : null,
            'club' => $club,
            'divisions' => $regs->map(fn ($r) => $r->category?->name)->filter()->unique()->values()->all(),
            'entries' => $regs->count(),
            'signs_in' => ! $u->is_unclaimed && filled($u->getRawOriginal('password')),
            '_m' => [
                'name' => $this->people->normalised((string) ($u->full_name ?: $u->name)),
                'bday' => $u->birthdate?->toDateString(),
                'phone' => $u->phone_key,
                'club' => $first->representing_tenant_id,
            ],
        ];
    }

    /** @return array<string, true> "lo-hi" => true */
    private function dismissed(array $userIds): array
    {
        if (! Schema::hasTable('member_duplicate_dismissals')) {
            return [];
        }

        return DB::table('member_duplicate_dismissals')
            ->whereIn('user_low_id', $userIds)->whereIn('user_high_id', $userIds)
            ->get(['user_low_id', 'user_high_id'])
            ->mapWithKeys(fn ($d) => [$d->user_low_id.'-'.$d->user_high_id => true])
            ->all();
    }

    /**
     * Two people this event holds, by uuid — or null if either is not in it.
     *
     * @return array{0: User, 1: User}|null
     */
    public function resolvePair(ClubEvent $event, string $a, string $b): ?array
    {
        $ids = $event->participantRegistrations()->pluck('user_id')->unique();
        $users = User::whereIn('uuid', [$a, $b])->whereIn('id', $ids)->get()->keyBy('uuid');

        return ($a !== $b && isset($users[$a], $users[$b])) ? [$users[$a], $users[$b]] : null;
    }

    /**
     * SAME PERSON: $drop's entries in this event become $keep's.
     *
     * @return array{ok: bool, message: string, moved?: int, removed?: int}
     */
    public function combine(ClubEvent $event, User $actor, User $keep, User $drop): array
    {
        if ($event->hasStarted()) {
            return ['ok' => false, 'message' => __('events::events.dup_started')];
        }

        $held = ClubEventRegistration::where('event_id', $event->id)->where('user_id', $keep->id)
            ->pluck('category_id')->map(fn ($c) => (string) $c)->flip();
        $theirs = ClubEventRegistration::where('event_id', $event->id)->where('user_id', $drop->id)->with('user')->get();

        $moved = 0;
        $removed = 0;
        $refused = [];

        foreach ($theirs as $r) {
            if ($held->has((string) $r->category_id)) {
                // Already in that division as the kept person: this one is the double.
                $v = $this->entries->remove($event, $actor, $r, false);
                $v['ok'] ? $removed++ : $refused[] = $v['row']['message'] ?? __('events::events.dup_refused');
            } else {
                $r->forceFill(['user_id' => $keep->id])->save();
                $held->put((string) $r->category_id, true);
                $moved++;
            }
        }

        activity()->causedBy($actor)->performedOn($keep)
            ->withProperties(['event' => $event->uuid, 'dropped' => $drop->uuid, 'moved' => $moved, 'removed' => $removed])
            ->log('events.duplicate_combined');
        $this->registry->for($event)->onEntrantsChanged($event);

        if ($refused) {
            return ['ok' => false, 'message' => $refused[0], 'moved' => $moved, 'removed' => $removed];
        }

        return ['ok' => true, 'moved' => $moved, 'removed' => $removed,
                'message' => __('events::events.dup_combined', ['name' => $keep->full_name ?: $keep->name])];
    }
}
