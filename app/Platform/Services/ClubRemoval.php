<?php

namespace App\Platform\Services;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventClub;
use App\Events\Models\EventOfficial;
use App\Events\Support\EntryService;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEntry;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Remove a club and everything that is only the club's — the super-admin's
 * "Remove club" (2026-10-08).
 *
 * Written after removing one by hand went wrong three ways: the athletes were
 * found through ONE link when a club holds them through four (its roster, its
 * federation roster, entries it made, entries it pays for); the federation's
 * record of the club stayed and crashed its dashboard; and the deleted
 * athletes kept their national IDs, so the club could not enter them again.
 *
 * Two halves, and the second refuses to run unless it is the first:
 *
 *   plan()    — read-only. Who is DELETED (an athlete the club created: never
 *               claimed, no password, no ties anywhere else), who is only
 *               UNLINKED (anybody with their own account or another club), which
 *               entries leave which events, the federation records, the
 *               officials, and what BLOCKS it (an event already running, a bout
 *               already fought). Ends in a fingerprint of all of that.
 *   execute() — given the fingerprint the admin reviewed and the club's name
 *               typed out, takes a database backup and an archive of the photos
 *               it is about to delete, then removes, in the order that keeps
 *               every draw and dashboard correct: entries (the draw re-cuts),
 *               appointments, the event listings, the federation records, the
 *               athletes (files first, then rows), and last the club itself —
 *               archived, as the existing Delete does, so it can be restored.
 *
 * Nobody with an account of their own is ever deleted here.
 */
class ClubRemoval
{
    public function __construct(private EntryService $entries) {}

    public function plan(Tenant $club): array
    {
        $tid = (int) $club->id;
        $fcIds = FederationClub::where('tenant_id', $tid)->pluck('id');
        $appIds = FederationClubApplication::where('tenant_id', $tid)
            ->orWhereIn('federation_club_id', $fcIds)->pluck('id');

        // Everyone the club holds, by every link it can hold them through.
        $byClub = fn ($q) => $q->where('representing_tenant_id', $tid)
            ->orWhere(fn ($w) => $w->where('payer_type', 'club')->where('payer_id', $tid));
        $regs = ClubEventRegistration::where($byClub)->get();
        $rosterAthletes = FederationAthleteClub::where(fn ($q) => $q->whereIn('federation_club_id', $fcIds)->orWhereIn('club_application_id', $appIds))
            ->pluck('federation_athlete_id');
        $people = collect()
            ->merge(DB::table('memberships')->where('tenant_id', $tid)->pluck('user_id'))
            ->merge(DB::table('club_member_subscriptions')->where('tenant_id', $tid)->pluck('user_id'))
            ->merge(FederationAthlete::whereIn('id', $rosterAthletes)->pluck('user_id'))
            ->merge($regs->pluck('user_id'))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values();

        // The club's own people run it; they are never "its athletes".
        $staff = collect([(int) $club->owner_user_id])
            ->merge(DB::table('user_roles')->where('tenant_id', $tid)->pluck('user_id'))
            ->merge(DB::table('club_instructors')->where('tenant_id', $tid)->pluck('user_id'))
            ->map(fn ($id) => (int) $id)->filter()->unique();

        $users = User::whereIn('id', $people->diff($staff))->get()->keyBy('id');
        $delete = collect();
        $keep = collect();
        foreach ($users as $u) {
            $why = $this->whyKeep($u, $tid, $fcIds, $appIds);
            $why === null ? $delete->push($u) : $keep->push(['user' => $u, 'why' => $why]);
        }
        $deleteIds = $delete->pluck('id')->all();

        // Entries: the deleted athletes leave every event they are in; anybody
        // kept stays in the event, no longer under this club.
        $removeRegs = ClubEventRegistration::whereIn('user_id', $deleteIds)->get();
        $unlinkRegs = $regs->reject(fn ($r) => in_array((int) $r->user_id, $deleteIds, true));
        $events = ClubEvent::whereIn('id', $removeRegs->pluck('event_id')->merge($unlinkRegs->pluck('event_id'))->unique())->get()->keyBy('id');

        $blockers = [];
        foreach ($removeRegs->groupBy('event_id') as $eventId => $rows) {
            $ev = $events->get($eventId);
            if ($ev && ($ev->hasStarted() || $ev->isOverdueToStart())) {
                $blockers[] = '“'.$ev->title.'” is already under way — its entry list can no longer change.';
            } elseif ($this->fought($rows->pluck('id'))) {
                $blockers[] = 'Athletes of this club have already fought in “'.($ev?->title ?? 'an event').'”.';
            }
        }

        $officials = EventOfficial::where('representing_tenant_id', $tid)->get();
        $eventClubs = EventClub::where('tenant_id', $tid)->orWhere('promoted_tenant_id', $tid)
            ->orWhere(fn ($q) => $q->whereNull('tenant_id')->whereNull('promoted_tenant_id')->where('name', $club->club_name))
            ->get();
        $fedEntries = FederationEntry::whereIn('federation_club_id', $fcIds)->orWhereIn('club_application_id', $appIds)->get();

        $plan = [
            'club' => ['id' => $tid, 'name' => (string) $club->club_name, 'slug' => (string) $club->slug],
            'delete' => $delete->map(fn (User $u) => ['id' => $u->id, 'name' => $this->nameOf($u), 'photo' => (bool) $u->profile_picture])
                ->sortBy('name')->values()->all(),
            'keep' => $keep->map(fn ($k) => ['id' => $k['user']->id, 'name' => $this->nameOf($k['user']), 'why' => $k['why']])
                ->sortBy('name')->values()->all(),
            'events' => $events->map(fn (ClubEvent $ev) => [
                'title' => (string) $ev->title,
                'removed' => $removeRegs->where('event_id', $ev->id)->pluck('user_id')->unique()->count(),
                'unlinked' => $unlinkRegs->where('event_id', $ev->id)->pluck('user_id')->unique()->count(),
            ])->sortBy('title')->values()->all(),
            'federation' => [
                'memberships' => $fcIds->count(),
                'applications' => $appIds->count(),
                'entries' => $fedEntries->count(),
                'roster' => $rosterAthletes->count(),
            ],
            'officials' => $officials->count(),
            'listings' => $eventClubs->count(),
            'photos' => $delete->filter(fn ($u) => $u->profile_picture)->count(),
            'blockers' => array_values(array_unique($blockers)),
            // What the admin approves: if any of it changes before they press
            // the button, they review again.
            '_ids' => [
                'delete' => $deleteIds, 'removeRegs' => $removeRegs->pluck('id')->all(), 'unlinkRegs' => $unlinkRegs->pluck('id')->all(),
                'officials' => $officials->pluck('id')->all(), 'listings' => $eventClubs->pluck('id')->all(),
                'fedEntries' => $fedEntries->pluck('id')->all(), 'fc' => $fcIds->all(), 'apps' => $appIds->all(),
            ],
        ];
        $plan['fingerprint'] = hash('sha256', json_encode([$plan['_ids'], $plan['keep'], $plan['blockers']]));

        return $plan;
    }

    /**
     * @return array{ok: bool, message: string, removed?: array}
     */
    public function execute(Tenant $club, User $actor, string $typedName, string $fingerprint): array
    {
        if (trim($typedName) !== trim((string) $club->club_name)) {
            return ['ok' => false, 'message' => 'The name typed does not match the club’s name.'];
        }

        $lock = Cache::lock('club-removal:'.$club->id, 600);
        if (! $lock->get()) {
            return ['ok' => false, 'message' => 'This club is already being removed.'];
        }

        try {
            $plan = $this->plan($club);
            if (! hash_equals($plan['fingerprint'], $fingerprint)) {
                return ['ok' => false, 'stale' => true, 'message' => 'Something about this club changed since you reviewed it. Review it again.'];
            }
            if ($plan['blockers'] !== []) {
                return ['ok' => false, 'message' => $plan['blockers'][0]];
            }
            $ids = $plan['_ids'];
            $doomed = User::whereIn('id', $ids['delete'])->get();

            // Never without a way back: the database, and the exact photos
            // about to go (they are not in a database backup).
            if (! $this->backup()) {
                return ['ok' => false, 'message' => 'The backup failed, so nothing was removed.'];
            }
            [$files, $archive] = $this->archivePhotos($club, $doomed);
            if ($files && ! $archive) {
                return ['ok' => false, 'message' => 'The photos could not be archived, so nothing was removed.'];
            }

            // 1. Entries — through the event's own door, so each draw re-cuts.
            foreach (ClubEventRegistration::whereIn('id', $ids['removeRegs'])->get() as $r) {
                $event = ClubEvent::find($r->event_id);
                $v = $event ? $this->entries->remove($event, $actor, $r, notify: false) : ['ok' => true];
                if (! ($v['ok'] ?? false)) {
                    return ['ok' => false, 'message' => 'Stopped: '.($v['row']['message'] ?? 'an entry could not be removed.').' Nothing after it was changed.'];
                }
            }

            DB::transaction(function () use ($ids, $club, $doomed) {
                // Kept people stay in their events, no longer under this club.
                ClubEventRegistration::whereIn('id', $ids['unlinkRegs'])->get()->each(function (ClubEventRegistration $r) use ($club) {
                    $fill = [];
                    if ((int) $r->representing_tenant_id === (int) $club->id) {
                        $fill['representing_tenant_id'] = null;
                    }
                    if ($r->payer_type === 'club' && (int) $r->payer_id === (int) $club->id) {
                        // The club paid; with the club gone, the person owes it.
                        $fill += ['payer_type' => 'person', 'payer_id' => $r->user_id];
                    }
                    if ($fill) {
                        $r->forceFill($fill)->save();
                    }
                });

                // 2. The officials it named for events — the appointment only;
                // the photo is the person's, so it is unhooked first.
                EventOfficial::whereIn('id', $ids['officials'])->get()->each(function (EventOfficial $o) {
                    $o->forceFill(['photo' => null])->saveQuietly();
                    $o->delete();
                });

                // 3. Its listing on events' club lists.
                EventClub::whereIn('id', $ids['listings'])->get()->each->delete();

                // 4. The federation's record of it: entries, roster links
                // (ended for kept athletes, so their history keeps the club),
                // applications, memberships.
                FederationEntry::whereIn('id', $ids['fedEntries'])->get()->each->delete();
                $links = FederationAthleteClub::where(fn ($q) => $q->whereIn('federation_club_id', $ids['fc'])->orWhereIn('club_application_id', $ids['apps']));
                $doomedAthletes = FederationAthlete::whereIn('user_id', $doomed->pluck('id'))->pluck('id');
                (clone $links)->whereNotIn('federation_athlete_id', $doomedAthletes)->whereNull('to_ym')->update(['to_ym' => now()->format('Y-m')]);
                FederationClubApplication::whereIn('id', $ids['apps'])->delete();
            });

            // 5. The athletes: files first, then every row that is theirs.
            $disk = Storage::disk('local');
            foreach ($files as $p) {
                $disk->delete($p);
            }
            foreach ($doomed as $u) {
                $dir = 'members/'.$u->uuid;
                if ($u->uuid && $disk->exists($dir) && ! $disk->allFiles($dir)) {
                    $disk->deleteDirectory($dir);
                }
            }
            DB::transaction(function () use ($doomed, $ids) {
                DB::table('user_photos')->whereIn('user_id', $doomed->pluck('id'))->delete();
                FederationAthlete::whereIn('user_id', $doomed->pluck('id'))->delete();
                $doomed->each(fn (User $u) => $u->forceDelete());
                // Last, the club's federation memberships (their roster links go with them).
                FederationClub::whereIn('id', $ids['fc'])->delete();
            });

            // 6. The club itself — archived, exactly as the existing Delete does.
            $name = $club->club_name;
            $club->delete();

            $removed = [
                'athletes' => $doomed->count(), 'kept' => count($plan['keep']), 'photos' => count($files),
                'entries' => count($ids['removeRegs']), 'officials' => count($ids['officials']),
                'federation' => count($ids['fc']) + count($ids['apps']) + count($ids['fedEntries']),
                'archive' => $archive ? basename($archive) : null,
            ];
            activity()->causedBy($actor)->performedOn($club)->withProperties($removed)->log('club.removed');

            return ['ok' => true, 'removed' => $removed, 'message' => $name.' was removed with '
                .$removed['athletes'].' '.($removed['athletes'] === 1 ? 'athlete' : 'athletes').'.'];
        } finally {
            $lock->release();
        }
    }

    /** A verified database backup (takeone:backup --db-only). Its own method so a test can stand in for it. */
    protected function backup(): bool
    {
        return Artisan::call('takeone:backup', ['--db-only' => true]) === 0;
    }

    /** Null when this person is the club's alone to delete; otherwise why they are kept. */
    private function whyKeep(User $u, int $tid, Collection $fcIds, Collection $appIds): ?string
    {
        if (! $u->is_unclaimed || $u->password) {
            return 'Has their own account';
        }
        if ($u->isSuperAdmin() || $u->ownedOrganisationNames(includeTrashed: true)) {
            return 'Runs an organisation';
        }
        if (DB::table('memberships')->where('user_id', $u->id)->where('tenant_id', '!=', $tid)->exists()
            || DB::table('club_member_subscriptions')->where('user_id', $u->id)->where('tenant_id', '!=', $tid)->exists()) {
            return 'Member of another club';
        }
        if (DB::table('user_roles')->where('user_id', $u->id)->exists() || DB::table('club_instructors')->where('user_id', $u->id)->exists()) {
            return 'Works at a club';
        }
        $fa = FederationAthlete::where('user_id', $u->id)->value('id');
        if ($fa && FederationAthleteClub::where('federation_athlete_id', $fa)
            ->where(fn ($q) => $q->whereNotIn('federation_club_id', $fcIds)->orWhereNull('federation_club_id'))
            ->where(fn ($q) => $q->whereNotIn('club_application_id', $appIds)->orWhereNull('club_application_id'))
            ->exists()) {
            return 'On another club’s federation roster';
        }
        if (ClubEventRegistration::where('user_id', $u->id)
            ->where(fn ($q) => $q->whereNull('representing_tenant_id')->orWhere('representing_tenant_id', '!=', $tid))
            ->where(fn ($q) => $q->whereNull('payer_type')->orWhere('payer_type', '!=', 'club')->orWhere('payer_id', '!=', $tid))
            ->exists()) {
            return 'Entered in an event for someone else';
        }
        if (DB::table('user_relationships')->where('guardian_user_id', $u->id)->orWhere('dependent_user_id', $u->id)->exists()) {
            return 'Linked to family';
        }
        if (DB::table('event_officials')->where('user_id', $u->id)->exists()) {
            return 'Officiates at an event';
        }

        return null;
    }

    /** Has any of these entries stood in a bout that was actually fought (a bye is not a bout)? */
    private function fought(Collection $regIds): bool
    {
        return $regIds->isNotEmpty() && DB::table('event_matches')
            ->where(fn ($q) => $q->whereIn('a_competitor_id', $regIds)->orWhereIn('b_competitor_id', $regIds))
            ->where(fn ($q) => $q->whereNotNull('winner')->orWhere('status', 'done'))
            ->whereNotNull('a_name')->whereNotNull('b_name')->where('won_by_bye', 0)
            ->exists();
    }

    /**
     * The photos about to be deleted, copied into one archive beside the
     * backups first — a database backup does not hold files. Only files no
     * remaining record names (a shared picture is left alone).
     *
     * @return array{0: array<int, string>, 1: ?string}  [files to delete, archive path]
     */
    private function archivePhotos(Tenant $club, Collection $doomed): array
    {
        $ids = $doomed->pluck('id')->all();
        $disk = Storage::disk('local');
        $shared = fn (string $p) => DB::table('users')->where('profile_picture', $p)->whereNotIn('id', $ids)->exists()
            || DB::table('user_photos')->where('path', $p)->whereNotIn('user_id', $ids)->exists()
            || DB::table('event_officials')->where('photo', $p)->exists()
            || DB::table('club_event_registrations')->where('photo', $p)->whereNotIn('user_id', $ids)->exists();

        $files = [];
        foreach ($doomed as $u) {
            $paths = collect([$u->profile_picture])
                ->merge(DB::table('user_photos')->where('user_id', $u->id)->pluck('path'))
                ->merge($u->uuid && $disk->exists('members/'.$u->uuid) ? $disk->allFiles('members/'.$u->uuid) : []);
            foreach ($paths->filter()->unique() as $p) {
                if (! str_contains($p, '..') && $disk->exists($p) && ! $shared($p)) {
                    $files[] = $p;
                }
            }
        }
        $files = array_values(array_unique($files));
        if ($files === []) {
            return [[], null];
        }

        $dir = (string) config('backup.path');
        $path = rtrim($dir, '/').'/club-removal-'.preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $club->slug)).'-'.now()->format('Ymd-His').'.zip';
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            return [$files, null];
        }
        foreach ($files as $p) {
            $zip->addFile($disk->path($p), $p);
        }
        $zip->close();

        // Read it back before trusting it.
        $check = new \ZipArchive;
        $opened = $check->open($path) === true;
        $ok = $opened && $check->numFiles === count($files);
        if ($opened) {
            $check->close();
        }

        return [$files, $ok ? $path : null];
    }

    private function nameOf(User $u): string
    {
        return (string) ($u->full_name ?: $u->name ?: 'Unnamed');
    }
}
