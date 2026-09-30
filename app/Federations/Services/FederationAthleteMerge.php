<?php

namespace App\Federations\Services;

use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationDraw;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationTransfer;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Members\Support\ProfilePhoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One person, one record: an athlete a club registered through the
 * invitation link — a fresh, unclaimed shell account — turns out to be
 * somebody the federation already knows. Two answers:
 *
 *  - LINK   the other person is a club MEMBER with no TakeOne ID yet: the
 *           athlete record (TakeOne ID, national ID, entries, club history)
 *           moves onto their real account; the shell goes.
 *  - MERGE  the other person is already an ATHLETE: everything the duplicate
 *           held — club rows, entries, transfers, draw places — moves onto the
 *           kept athlete; the duplicate record and its shell go.
 *
 * Deliberately narrow, so it can never fuse two real people's accounts:
 *   - the duplicate must be an UNCLAIMED shell (no email, never signed in);
 *   - the target must already be in this federation's own directory (its
 *     athletes, or members of its affiliated clubs) — merging never reveals
 *     anybody the federation could not already see;
 *   - two different national IDs, two different current clubs, or both in
 *     the same draw → refused, with the reason.
 *
 * Only federation-owned tables are rewritten; the shell account gets the
 * platform's own (soft) delete. A manager only, behind
 * `federations.club_management`.
 */
class FederationAthleteMerge
{
    public function __construct(private FederationDirectory $directory) {}

    public function mayMerge(array $access): bool
    {
        return (bool) config('federations.club_management') && (bool) ($access['manager'] ?? false);
    }

    /**
     * What merging would do, or why it may not.
     *
     * @return array{ok:bool, message:string, mode?:string}
     */
    public function preview(array $access, string $duplicate, string $into): array
    {
        $p = $this->plan($access, $duplicate, $into);
        if (isset($p['error'])) {
            return ['ok' => false, 'message' => $p['error']];
        }

        $d = $p['dup'];
        $parts = [];
        $n = FederationEntryAthlete::where('federation_athlete_id', $d->id)->count();
        if ($n) {
            $parts[] = $n.' event '.($n === 1 ? 'entry' : 'entries');
        }
        $name = $p['user']->full_name ?: $p['user']->name;
        $message = $p['mode'] === 'link'
            ? $d->takeone_id.' moves onto '.$name.'\'s account'.($parts ? ', with '.implode(', ', $parts) : '').'. The duplicate account is removed.'
            : 'Everything '.$d->takeone_id.' holds'.($parts ? ' ('.implode(', ', $parts).')' : '').' moves to '.$p['keep']->takeone_id.' ('.$name.'). '.$d->takeone_id.' and the duplicate account are removed.';

        return ['ok' => true, 'mode' => $p['mode'], 'message' => $message];
    }

    /** @return string|null an error, or null once merged */
    public function merge(User $by, array $access, string $duplicate, string $into): ?string
    {
        $p = $this->plan($access, $duplicate, $into);
        if (isset($p['error'])) {
            return $p['error'];
        }
        $dup = $p['dup'];
        $shell = $dup->user;
        $target = $p['user'];
        $summary = ['duplicate' => $dup->takeone_id, 'shell_user' => $shell->uuid, 'into_user' => $target->uuid,
            'into_athlete' => $p['keep']?->takeone_id, 'mode' => $p['mode']];

        DB::transaction(function () use ($p, $dup, $shell, $target) {
            if ($p['mode'] === 'link') {
                $dup->user_id = $target->id;
                $dup->save();
            } else {
                $keep = $p['keep'];
                // Club history: a duplicate current row at the SAME club is the same fact twice.
                foreach (FederationAthleteClub::where('federation_athlete_id', $dup->id)->get() as $row) {
                    $twin = $row->to_ym === null ? FederationAthleteClub::where('federation_athlete_id', $keep->id)->whereNull('to_ym')
                        ->where('federation_id', $row->federation_id)->first() : null;
                    $twin ? $row->delete() : $row->update(['federation_athlete_id' => $keep->id]);
                }
                foreach (FederationEntryAthlete::where('federation_athlete_id', $dup->id)->get() as $ea) {
                    FederationEntryAthlete::where('federation_entry_id', $ea->federation_entry_id)->where('federation_athlete_id', $keep->id)->exists()
                        ? $ea->delete() : $ea->update(['federation_athlete_id' => $keep->id]);
                }
                FederationTransfer::where('federation_athlete_id', $dup->id)->update(['federation_athlete_id' => $keep->id]);
                foreach ($p['draws'] as $draw) {
                    $swap = fn ($v) => $v === $dup->takeone_id ? $keep->takeone_id : $v;
                    $draw->slots = array_map($swap, (array) $draw->slots);
                    $draw->results = $this->swapDeep((array) ($draw->results ?: []), $dup->takeone_id, $keep->takeone_id);
                    $draw->save();
                }
                // The national ID moves when only the duplicate had one (unique: clear it first).
                if (! $keep->national_id_hash && $dup->national_id_hash) {
                    [$nid, $hash, $nat] = [$dup->national_id, $dup->national_id_hash, $dup->nationality];
                    $dup->forceFill(['national_id' => null, 'national_id_hash' => null])->save();
                    $keep->forceFill(['national_id' => $nid, 'national_id_hash' => $hash, 'nationality' => $keep->nationality ?: $nat])->save();
                }
                $dup->delete();
            }

            // What the registration told us fills the real account's BLANKS only.
            $fill = [];
            if (! $target->birthdate && $shell->birthdate) {
                $fill['birthdate'] = $shell->birthdate;
            }
            if (! $target->nationality && $shell->nationality) {
                $fill['nationality'] = $shell->nationality;
            }
            if ($fill) {
                $target->forceFill($fill)->save();
            }
            // A face has one home — the person's (the shell's file now belongs to them too).
            if ($shell->profile_picture) {
                ProfilePhoto::adopt($target, $shell->profile_picture);
            }
            $shell->delete();
        });

        activity()->causedBy($by)->performedOn($target)
            ->withProperties($summary + ['federation' => $access['root']->uuid ?? $access['root']->id])
            ->log('federation.athlete_merged');

        if (! $target->is_unclaimed && $target->email) {
            $tk = $p['keep']?->takeone_id ?? $dup->takeone_id;
            rescue(fn () => UserNotification::notifyUser((int) $target->id, 'federation_athlete_merged',
                $access['root']->name.' linked your athlete record', [
                    'actor_id' => $by->id,
                    'body' => 'A registration made by a club was recognised as you. Your TakeOne ID is '.$tk.'.',
                    'icon' => 'bi-person-check',
                ]), report: true);
        }

        return null;
    }

    /**
     * Delete athletes from the federation (Athletes page, one or many).
     *
     * Removes the FEDERATION record — TakeOne ID, national ID, club history,
     * entries, transfer requests. The person's platform account is deleted only
     * when it is a placeholder the federation or a registration link made
     * (unclaimed, no email, no password, no club membership); a real member
     * keeps their account and simply stops being a federation athlete.
     *
     * Refused, per athlete and with the reason: placed in a draw (the draw would
     * break), or registered with ANOTHER federation (not this one's to delete).
     *
     * @param  array<int, string>  $tks  TakeOne IDs
     * @return array<int, array{id:string, ok:bool, message:string}>
     */
    public function remove(User $by, array $access, array $tks): array
    {
        $ids = collect($this->directory->subtree($access['root']))->pluck('id');
        $visible = $this->visibleAthleteIds($ids)->flip();
        $allDraws = FederationDraw::get(['federation_event_id', 'slots', 'results']);
        $out = [];

        foreach (array_values(array_unique($tks)) as $tk) {
            $a = FederationAthlete::with(['user' => fn ($q) => $q->withTrashed()])->where('takeone_id', $tk)->first();
            $name = $a?->user?->full_name ?: $tk;
            if (! $a || ! $visible->has($a->id)) {
                $out[] = ['id' => $tk, 'ok' => false, 'message' => $tk.' could not be found.'];

                continue;
            }
            if (FederationAthleteClub::where('federation_athlete_id', $a->id)->whereNotIn('federation_id', $ids)->exists()) {
                $out[] = ['id' => $tk, 'ok' => false, 'message' => $name.' is also registered with another federation, so they cannot be deleted here.'];

                continue;
            }

            $u = $a->user;
            $placeholder = $u && ! $u->trashed() && $u->is_unclaimed && ! $u->email && ! $u->password
                && ! DB::table('memberships')->where('user_id', $u->id)->exists();
            $summary = ['takeone_id' => $a->takeone_id, 'user' => $u?->uuid, 'account_deleted' => $placeholder,
                'entries' => FederationEntryAthlete::where('federation_athlete_id', $a->id)->count()];

            // Placed in a draw (they may have fought): the bracket names them by
            // TakeOne ID, so the RECORD stays as "Deleted athlete" and the PERSON
            // goes — decided with the user, 2026-09-26. Otherwise: deleted outright.
            $drawEvents = $allDraws->filter(fn ($d) => $this->holds($d, $a->takeone_id))->pluck('federation_event_id')->unique();
            $summary['kept_in_draws'] = $drawEvents->count();

            DB::transaction(function () use ($a, $u, $placeholder, $drawEvents) {
                if ($drawEvents->isEmpty()) {
                    FederationEntryAthlete::where('federation_athlete_id', $a->id)->delete();
                } else {
                    // Keep only the entries in events where they are drawn.
                    FederationEntryAthlete::where('federation_athlete_id', $a->id)
                        ->whereDoesntHave('entry', fn ($q) => $q->whereIn('federation_event_id', $drawEvents))->delete();
                }
                FederationTransfer::where('federation_athlete_id', $a->id)->delete();
                FederationAthleteClub::where('federation_athlete_id', $a->id)->delete();

                if ($drawEvents->isEmpty()) {
                    $a->delete();
                } else {
                    // A nameless stand-in carries the record; the person is detached.
                    $stand = User::create(['full_name' => 'Deleted athlete', 'name' => 'Deleted athlete',
                        'is_unclaimed' => true, 'is_discoverable' => false]);
                    $a->forceFill(['user_id' => $stand->id, 'national_id' => null, 'national_id_hash' => null,
                        'nationality' => null, 'anonymised_at' => now()])->save();
                }
                if ($placeholder) {
                    $u->delete();   // the platform's own (soft) delete
                }
            });

            activity()->causedBy($by)->withProperties($summary + ['federation' => $access['root']->uuid ?? $access['root']->id])
                ->log('federation.athlete_deleted');
            $out[] = ['id' => $tk, 'ok' => true, 'message' => $drawEvents->isEmpty() ? $name.' was deleted.'
                : $name.' was deleted — their place in the draw now reads "Deleted athlete", so the bracket stays intact.'];
        }

        return $out;
    }

    /**
     * Give a club member who has no TakeOne ID one: an athlete record on
     * their own account, at the federation club they belong to. Refused when
     * their national ID already belongs to another athlete — that is the
     * same person twice, and "Same person as…" is the answer.
     *
     * @return array{error?:string, takeoneId?:string}
     */
    public function issue(User $by, array $access, string $memberKey): array
    {
        $ids = collect($this->directory->subtree($access['root']))->pluck('id');
        $user = str_starts_with($memberKey, 'm-') ? User::where('uuid', substr($memberKey, 2))->first() : null;
        if (! $user || ! $this->memberIds($ids)->contains($user->id)) {
            return ['error' => 'That person is not a member of one of the federation\'s clubs.'];
        }
        if ($existing = FederationAthlete::where('user_id', $user->id)->value('takeone_id')) {
            return ['error' => 'They already have a TakeOne ID: '.$existing.'.'];
        }

        $nid = null;
        foreach ((array) ($user->documents ?? []) as $doc) {
            if (is_array($doc) && strtoupper((string) ($doc['type'] ?? '')) === 'CPR' && trim((string) ($doc['number'] ?? '')) !== '') {
                $nid = mb_substr(trim((string) $doc['number']), 0, 40);
                break;
            }
        }
        $hash = FederationAthlete::hashNationalId($nid);
        if ($hash && ($other = FederationAthlete::where('national_id_hash', $hash)->value('takeone_id'))) {
            return ['error' => 'Their national ID already belongs to '.$other.'. Use "Same person as…" on that athlete to link them instead.'];
        }

        $tenants = DB::table('memberships')->where('user_id', $user->id)->where('status', 'active')->pluck('tenant_id');
        $club = FederationClub::whereIn('federation_id', $ids)->where('status', FederationClub::STATUS_APPROVED)
            ->whereIn('tenant_id', $tenants)->orderBy('id')->first();
        $iso3 = \App\Support\Countries::iso3((string) $user->nationality) ?: 'XXX';

        $athlete = DB::transaction(function () use ($user, $nid, $hash, $iso3, $club) {
            $a = FederationAthlete::create([
                'user_id' => $user->id,
                'takeone_id' => FederationAthlete::issueTakeoneId($iso3 === 'UNK' ? 'XXX' : $iso3, (string) ($user->full_name ?: $user->name)),
                'national_id' => $nid,
                'national_id_hash' => $hash,
                'nationality' => in_array($iso3, ['XXX', 'UNK'], true) ? null : $iso3,
            ]);
            if ($club) {
                FederationAthleteClub::create([
                    'federation_athlete_id' => $a->id, 'federation_id' => $club->federation_id,
                    'federation_club_id' => $club->id, 'from_ym' => now()->format('Y-m'),
                ]);
            }

            return $a;
        });

        activity()->causedBy($by)->performedOn($user)
            ->withProperties(['takeone_id' => $athlete->takeone_id, 'federation' => $access['root']->uuid ?? $access['root']->id])
            ->log('federation.takeone_id_issued');
        if (! $user->is_unclaimed && $user->email) {
            rescue(fn () => UserNotification::notifyUser((int) $user->id, 'federation_takeone_id',
                'Your TakeOne ID is '.$athlete->takeone_id, [
                    'actor_id' => $by->id,
                    'body' => $access['root']->name.' registered you as an athlete.',
                    'icon' => 'bi-person-badge',
                ]), report: true);
        }

        return ['takeoneId' => $athlete->takeone_id];
    }

    /**
     * @return array{error?:string, mode?:string, dup?:FederationAthlete, keep?:?FederationAthlete, user?:User, draws?:Collection}
     */
    private function plan(array $access, string $duplicate, string $into): array
    {
        $ids = collect($this->directory->subtree($access['root']))->pluck('id');
        $visible = $this->visibleAthleteIds($ids);

        $dup = FederationAthlete::with(['user' => fn ($q) => $q->withTrashed()])->where('takeone_id', $duplicate)->first();
        if (! $dup || ! $visible->contains($dup->id)) {
            return ['error' => 'That athlete could not be found.'];
        }
        if ($dup->anonymised_at) {
            return ['error' => 'That athlete was deleted.'];
        }
        $shell = $dup->user;
        if (! $shell || $shell->trashed() || ! $shell->is_unclaimed || $shell->email || $shell->password) {
            return ['error' => 'Only an athlete a club registered through the invitation link (with no account of their own) can be merged away.'];
        }

        if (str_starts_with($into, 'm-')) {
            $user = User::where('uuid', substr($into, 2))->first();
            if (! $user || ! $this->memberIds($ids)->contains($user->id)) {
                return ['error' => 'That person is not a member of one of the federation\'s clubs.'];
            }
            $keep = FederationAthlete::where('user_id', $user->id)->first();
            if ($keep && ! $visible->contains($keep->id)) {
                return ['error' => 'That person could not be found.'];
            }
        } else {
            $keep = FederationAthlete::with('user')->where('takeone_id', $into)->whereNull('anonymised_at')->first();
            if (! $keep || ! $visible->contains($keep->id) || ! $keep->user) {
                return ['error' => 'That athlete could not be found.'];
            }
            $user = $keep->user;
        }
        if ($user->id === $shell->id || ($keep && $keep->id === $dup->id)) {
            return ['error' => 'That is the same person.'];
        }

        if ($keep && $keep->national_id_hash && $dup->national_id_hash && $keep->national_id_hash !== $dup->national_id_hash) {
            return ['error' => 'They have different national IDs, so they are not the same person.'];
        }

        $draws = collect();
        if ($keep) {
            // Two current clubs in the same federation means two different people — or a transfer to make first.
            foreach (FederationAthleteClub::where('federation_athlete_id', $dup->id)->whereNull('to_ym')->get() as $row) {
                $twin = FederationAthleteClub::where('federation_athlete_id', $keep->id)->whereNull('to_ym')->where('federation_id', $row->federation_id)->first();
                if ($twin && ($twin->federation_club_id !== $row->federation_club_id || $twin->club_application_id !== $row->club_application_id)) {
                    return ['error' => 'They are registered to different clubs. Request a transfer first, or check they are really the same person.'];
                }
            }
            $draws = FederationDraw::whereIn('federation_event_id', FederationEvent::whereIn('federation_id', $ids)->pluck('id'))->get()->filter(fn ($d) => $this->holds($d, $dup->takeone_id));
            if ($draws->contains(fn ($d) => $this->holds($d, $keep->takeone_id))) {
                return ['error' => 'Both are in the same draw. Take one of them out of it first.'];
            }
        }

        return ['mode' => $keep ? 'merge' : 'link', 'dup' => $dup, 'keep' => $keep, 'user' => $user, 'draws' => $draws->values()];
    }

    private function holds(FederationDraw $d, string $tk): bool
    {
        return in_array($tk, array_filter((array) $d->slots, 'is_string'), true)
            || str_contains(json_encode($d->results ?: []), '"'.$tk.'"');
    }

    private function swapDeep(array $a, string $from, string $to): array
    {
        foreach ($a as $k => $v) {
            $a[$k] = is_array($v) ? $this->swapDeep($v, $from, $to) : ($v === $from ? $to : $v);
        }

        return $a;
    }

    /** Athletes this federation tree sees: its club rosters, its events' entries, its transfers. */
    private function visibleAthleteIds(Collection $ids): Collection
    {
        $events = FederationEvent::whereIn('federation_id', $ids)->pluck('id');
        $entries = FederationEntry::whereIn('federation_event_id', $events)->pluck('id');

        return FederationAthleteClub::whereIn('federation_id', $ids)->pluck('federation_athlete_id')
            ->merge(FederationEntryAthlete::whereIn('federation_entry_id', $entries)->pluck('federation_athlete_id'))
            ->merge(FederationTransfer::whereIn('federation_id', $ids)->pluck('federation_athlete_id'))
            ->unique()->values();
    }

    /** Active members of the federation's affiliated clubs — the directory's member rows. */
    private function memberIds(Collection $ids): Collection
    {
        $tenants = FederationClub::whereIn('federation_id', $ids)->where('status', FederationClub::STATUS_APPROVED)->pluck('tenant_id');

        return DB::table('memberships')->whereIn('tenant_id', $tenants)->where('status', 'active')->pluck('user_id')->unique()->values();
    }
}
