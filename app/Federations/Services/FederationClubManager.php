<?php

namespace App\Federations\Services;

use App\Clubs\Models\Tenant;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationDraw;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationTransfer;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The Clubs page's management actions, for a federation MANAGER only:
 *
 *  - edit    a pending application's details, or a platform club this
 *            federation itself created by approving an application (any
 *            other club belongs to its owner and is edited by them);
 *  - unlink  a registered club — the affiliation ends, the club and its
 *            history stay; adding it again restores the same row;
 *  - decide  a pending application: approve it as a NEW club, LINK it to a
 *            club already on the platform (no duplicate), or refuse it.
 *
 * Behind `federations.club_management` (default off). Every club is resolved
 * through FederationRegistry::clubFromKey() inside the viewer's own subtree,
 * so a key from another federation resolves to nothing.
 */
class FederationClubManager
{
    public function __construct(
        private FederationDirectory $directory,
        private FederationRegistry $registry,
        private FederationClubPicker $picker,
        private FederationClubApproval $approval,
    ) {}

    public function mayManage(array $access): bool
    {
        return (bool) config('federations.club_management') && (bool) ($access['manager'] ?? false);
    }

    /** What the edit / decide sheet needs for one club, or null when it is not this viewer's. */
    public function detail(array $access, string $key): ?array
    {
        $c = $this->resolve($access, $key);
        if (! $c) {
            return null;
        }

        if ($c instanceof FederationClubApplication) {
            return [
                'key' => $key, 'kind' => 'pending', 'editable' => true, 'unlinkable' => false,
                'fields' => ['name' => (string) $c->name, 'coach' => (string) $c->coach, 'email' => (string) $c->email,
                    'phone' => (string) $c->phone, 'address' => (string) $c->address],
                'matches' => $this->picker->matches($access, $c),
                'deletion' => array_diff_key($this->deletionPlan($c), ['ids' => true]),
            ];
        }

        $t = $c->club;
        $phone = is_array($t->phone) ? $t->phone : [];

        return [
            'key' => $key, 'kind' => 'approved', 'editable' => $this->createdHere($access, $c), 'unlinkable' => true,
            'fields' => ['name' => (string) $t->getRawOriginal('club_name'), 'coach' => (string) $t->owner_name,
                'email' => (string) ($t->email ?: $t->owner_email),
                'phone' => trim(($phone['code'] ?? '').' '.($phone['number'] ?? '')), 'address' => (string) $t->getRawOriginal('address')],
            'upcoming' => $this->upcomingEntries($c)->all(),
            'matches' => [],
        ];
    }

    /** @return string|null an error to show, or null on success */
    public function update(User $by, array $access, string $key, array $f): ?string
    {
        $c = $this->resolve($access, $key);
        if (! $c) {
            return 'That club could not be found.';
        }
        $f = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $f);

        if ($c instanceof FederationClubApplication) {
            $c->fill(['name' => $f['name'], 'coach' => $f['coach'] ?: null, 'email' => $f['email'] ? mb_strtolower($f['email']) : null,
                'phone' => $f['phone'] ?: null, 'address' => $f['address'] ?: null])->save();
            activity()->causedBy($by)->performedOn($c)->log('federation.club_application_edited');

            return null;
        }

        if (! $this->createdHere($access, $c)) {
            return 'This club is run by its owner — only they can change its details.';
        }
        $t = $c->club;
        $t->club_name = $f['name'];
        $t->owner_name = $f['coach'] ?: null;
        $t->email = $f['email'] ? mb_strtolower($f['email']) : null;
        $t->address = $f['address'] ?: null;
        $was = is_array($t->phone) ? trim(($t->phone['code'] ?? '').' '.($t->phone['number'] ?? '')) : '';
        if (($f['phone'] ?? '') !== $was) {
            $t->phone = $f['phone'] ? ['code' => '', 'number' => $f['phone']] : null;
        }
        $t->save();
        activity()->causedBy($by)->performedOn($t)
            ->withProperties(['federation' => $access['root']->uuid ?? $access['root']->id])->log('federation.club_edited');
        $this->picker->signal($access, $by->id);

        return null;
    }

    public function unlink(User $by, array $access, string $key): ?string
    {
        $c = $this->resolve($access, $key);
        if (! $c instanceof FederationClub) {
            return 'Only a registered club can be unlinked.';
        }
        $upcoming = $this->upcomingEntries($c);
        if ($upcoming->isNotEmpty()) {
            return 'This club is entered in '.$upcoming->implode(', ').'. Withdraw its entry first, or unlink it after the event.';
        }

        $c->fill(['status' => FederationClub::STATUS_REMOVED, 'decided_at' => now(), 'decided_by' => $by->id])->save();
        $club = $c->club;
        activity()->causedBy($by)->performedOn($club)
            ->withProperties(['federation' => $access['root']->uuid ?? $access['root']->id])->log('federation.club_unlinked');

        if ($club?->owner_user_id) {
            rescue(fn () => UserNotification::notifyUser((int) $club->owner_user_id, 'federation_club_removed',
                $access['root']->name.' removed '.$club->club_name.' from its clubs', [
                    'actor_id' => $by->id,
                    'tenant_id' => $club->id,
                    'body' => 'Your club is no longer affiliated with '.$access['root']->name.'.',
                    'icon' => 'bi-building-dash',
                ]), report: true);
        }
        $this->picker->signal($access, $by->id);

        return null;
    }

    /** $decision: new | link | reject. $club: the platform club's slug, for `link`. */
    public function decide(User $by, array $access, string $key, string $decision, ?string $club): ?string
    {
        $app = $this->resolve($access, $key);
        if (! $app instanceof FederationClubApplication || $app->status !== FederationClubApplication::STATUS_PENDING) {
            return 'That application is no longer waiting for a decision.';
        }

        try {
            if ($decision === 'reject') {
                $this->approval->reject($app, $by);
                activity()->causedBy($by)->performedOn($app)->log('federation.club_application_rejected');
            } elseif ($decision === 'new') {
                $link = $this->approval->approve($app, $by);
                activity()->causedBy($by)->performedOn($link->club)->log('federation.club_application_approved');
            } else {
                $tenant = $club ? Tenant::where('slug', $club)->first() : null;
                if (! $tenant) {
                    return 'That club could not be found.';
                }
                $this->approval->approveAsExisting($app, $tenant, $by);
                activity()->causedBy($by)->performedOn($tenant)
                    ->withProperties(['application' => $app->uuid, 'federation' => $access['root']->uuid ?? $access['root']->id])
                    ->log('federation.club_application_linked');
                if ($tenant->owner_user_id) {
                    rescue(fn () => UserNotification::notifyUser((int) $tenant->owner_user_id, 'federation_club_added',
                        $access['root']->name.' linked an application to '.$tenant->club_name, [
                            'actor_id' => $by->id,
                            'tenant_id' => $tenant->id,
                            'body' => 'An application made through an invitation link was recognised as your club. Your club is affiliated with '.$access['root']->name.'.',
                            'icon' => 'bi-building-check',
                        ]), report: true);
                }
            }
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
        $this->picker->signal($access, $by->id);

        return null;
    }

    /**
     * Delete a club that came in through the registration link and was never
     * approved: the application, its entries, its pending transfer requests,
     * its files — and the athletes it registered who exist NOWHERE else
     * (decided with the user, 2026-09-26). An athlete with any other tie is
     * kept and only loses this club.
     */
    public function deleteApplication(User $by, array $access, string $key): ?string
    {
        $app = $this->resolve($access, $key);
        if (! $app instanceof FederationClubApplication) {
            return 'Only a club waiting for approval can be deleted.';
        }
        $plan = $this->deletionPlan($app);
        if ($plan['blocked']) {
            return $plan['blocked'];
        }

        $entryIds = FederationEntry::where('club_application_id', $app->id)->pluck('id');
        $doomed = FederationAthlete::with(['user' => fn ($q) => $q->withTrashed()])->whereIn('id', $plan['ids'])->get();
        $summary = ['club' => $app->name, 'application' => $app->uuid, 'entries' => $entryIds->count(),
            'athletes_deleted' => $doomed->pluck('takeone_id')->all(), 'athletes_kept' => $plan['kept']];

        DB::transaction(function () use ($app, $entryIds, $doomed) {
            FederationEntryAthlete::whereIn('federation_entry_id', $entryIds)->delete();
            FederationEntry::whereIn('id', $entryIds)->delete();
            FederationTransfer::where('to_application_id', $app->id)->orWhere('from_application_id', $app->id)->delete();
            FederationAthleteClub::where('club_application_id', $app->id)->delete();

            foreach ($doomed as $a) {
                $user = $a->user;
                // The athlete record goes for good — it holds the unique national
                // ID, which must not block registering the person again later.
                $a->delete();
                // The person: the platform's own (soft) delete, which cleans up after itself.
                if ($user && ! $user->trashed()) {
                    $user->delete();
                }
            }

            // Files before the record (the logo goes with the row via DeletesUploadedFiles).
            foreach ((array) ($app->officials ?: []) as $o) {
                if (! empty($o['photo'])) {
                    rescue(fn () => Storage::disk('local')->delete($o['photo']), null, false);
                }
            }
            $app->delete();
        });

        activity()->causedBy($by)->withProperties($summary + ['federation' => $access['root']->uuid ?? $access['root']->id])
            ->log('federation.club_application_deleted');
        $this->picker->signal($access, $by->id);

        return null;
    }

    /**
     * What deleting this application would remove, and whether it may be.
     *
     * @return array{athletes:int, kept:int, entries:int, ids:array<int,int>, blocked:?string}
     */
    private function deletionPlan(FederationClubApplication $app): array
    {
        $entryIds = FederationEntry::where('club_application_id', $app->id)->pluck('id');
        $athleteIds = FederationAthleteClub::where('club_application_id', $app->id)->pluck('federation_athlete_id')
            ->merge(FederationEntryAthlete::whereIn('federation_entry_id', $entryIds)->pluck('federation_athlete_id'))
            ->unique()->values();

        $blocked = null;
        if (FederationTransfer::where('to_application_id', $app->id)->where('status', 'Approved')->exists()) {
            $blocked = 'An athlete was already transferred into this club, so it cannot be deleted. Refuse it instead.';
        }

        $athletes = FederationAthlete::with(['user' => fn ($q) => $q->withTrashed()])->whereIn('id', $athleteIds)->get();
        if (! $blocked) {
            // A draw names competitors by TakeOne ID; only a draw holding one of THESE athletes blocks.
            $tk = $athletes->pluck('takeone_id')->filter()->flip();
            $drawn = FederationDraw::whereIn('federation_event_id', FederationEntry::whereIn('id', $entryIds)->pluck('federation_event_id'))->get()
                ->contains(fn ($d) => collect($d->slots ?: [])->flatten()->contains(fn ($v) => is_string($v) && isset($tk[$v])));
            if ($drawn) {
                $blocked = 'Some of this club\'s athletes are already in a draw. Take them out of the draw first, or refuse the club instead.';
            }
        }

        $ids = [];
        foreach ($athletes as $a) {
            $u = $a->user;
            // An account already deleted elsewhere counts as nothing else — only its
            // leftover athlete record (and national ID) remains to clear.
            $elsewhere = ! $u
                || (! $u->trashed() && (! $u->is_unclaimed || $u->email))
                || ($app->created_at && $u->created_at && $u->created_at->lt($app->created_at))
                || FederationAthleteClub::where('federation_athlete_id', $a->id)
                    ->where(fn ($w) => $w->whereNull('club_application_id')->orWhere('club_application_id', '!=', $app->id))->exists()
                || FederationEntryAthlete::where('federation_athlete_id', $a->id)->whereNotIn('federation_entry_id', $entryIds)->exists()
                || FederationTransfer::where('federation_athlete_id', $a->id)->exists()
                || DB::table('memberships')->where('user_id', $u->id)->exists()
                || DB::table('club_event_registrations')->where('user_id', $u->id)->exists();
            if (! $elsewhere) {
                $ids[] = $a->id;
            }
        }

        return ['athletes' => count($ids), 'kept' => $athleteIds->count() - count($ids), 'entries' => $entryIds->count(),
            'ids' => $ids, 'blocked' => $blocked];
    }

    private function resolve(array $access, string $key): FederationClub|FederationClubApplication|null
    {
        $c = $this->registry->clubFromKey($key, $this->ids($access));
        if ($c instanceof FederationClubApplication && $c->status !== FederationClubApplication::STATUS_PENDING) {
            return null;
        }

        return $c instanceof FederationClub && ! $c->club ? null : $c;
    }

    /**
     * The club was made by this federation tree approving an application — so
     * the federation may edit it. A club LINKED from an application existed
     * before the application did: it is its owner's, not ours to edit.
     */
    private function createdHere(array $access, FederationClub $c): bool
    {
        $made = optional($c->club)->created_at;

        return $made && FederationClubApplication::whereIn('federation_id', $this->ids($access))
            ->where('status', FederationClubApplication::STATUS_APPROVED)
            ->where('federation_club_id', $c->id)->where('tenant_id', $c->tenant_id)
            ->where('created_at', '<=', $made)
            ->exists();
    }

    /** Names of events not yet over in which this club holds an entry. */
    private function upcomingEntries(FederationClub $c): Collection
    {
        return FederationEntry::with('event:id,name,status,ends_on,starts_on')->where('federation_club_id', $c->id)->get()
            ->map->event->filter()
            ->filter(fn ($e) => $e->status !== 'Completed' && (! ($e->ends_on ?: $e->starts_on) || ($e->ends_on ?: $e->starts_on) >= now()->toDateString()))
            ->pluck('name')->unique()->values();
    }

    private function ids(array $access): Collection
    {
        return collect($this->directory->subtree($access['root']))->pluck('id');
    }
}
