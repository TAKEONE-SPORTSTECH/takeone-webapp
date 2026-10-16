<?php

namespace App\Federations\Services;

use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationTransfer;
use App\Members\Models\User;
use App\Members\Support\ProfilePhoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The federation staff's side of entries: what the dashboard's store holds
 * (clubs, athletes, transfers, entries per event, personal links) and the
 * decisions staff make on them.
 *
 * Scope is the viewer's own subtree, never the tree above it; a sport-limited
 * viewer's events and entries are their sports only.
 */
class FederationDesk
{
    public function __construct(
        private FederationDirectory $directory,
        private FederationRegistry $registry,
        private FederationEvents $events,
    ) {}

    private function ids(array $access): Collection
    {
        return collect($this->directory->subtree($access['root']))->pluck('id');
    }

    /** @return Collection<int, FederationEvent> */
    private function eventsOf(array $access): Collection
    {
        $q = FederationEvent::whereIn('federation_id', $this->ids($access));
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }

        return $q->get();
    }

    /** `window.__TAKEONE_FED.fedstore` for the dashboard. */
    public function store(array $access, ?User $viewer = null): array
    {
        $ids = $this->ids($access);
        $clubs = $this->registry->clubsOf($ids);
        $events = $this->eventsOf($access);
        $entries = FederationEntry::with('event', 'club.club', 'application')->whereIn('federation_event_id', $events->pluck('id'))->get();

        $athleteIds = FederationAthleteClub::whereIn('federation_id', $ids)->whereNull('to_ym')->pluck('federation_athlete_id')
            ->merge(FederationEntryAthlete::whereIn('federation_entry_id', $entries->pluck('id'))->pluck('federation_athlete_id'))
            ->unique();
        $transfers = FederationTransfer::with('athlete.user')->whereIn('federation_id', $ids)->latest('id')->limit(500)->get();
        $athleteIds = $athleteIds->merge($transfers->pluck('federation_athlete_id'))->unique();

        $byEvent = [];
        foreach ($entries as $en) {
            $row = $this->registry->entry($en, $ids);
            if ($row['clubId']) {
                $byEvent[$en->event->uuid][$row['clubId']] = $row;
            }
        }

        // Clubs that appear only as the other side of a transfer: name only.
        $known = $clubs->map(fn ($c) => $this->registry->clubKey($c))->all();
        $extra = collect();
        foreach ($transfers as $t) {
            foreach ([[$t->from_club_id, FederationClub::class], [$t->to_club_id, FederationClub::class], [$t->from_application_id, FederationClubApplication::class], [$t->to_application_id, FederationClubApplication::class]] as [$id, $class]) {
                if ($id && ($c = $class::find($id)) && ! in_array($this->registry->clubKey($c), $known, true)) {
                    $extra->push($this->registry->club($c, false));
                    $known[] = $this->registry->clubKey($c);
                }
            }
        }

        return [
            'directory' => $this->directory($ids, $athleteIds, $clubs, $this->sportsOf($access), $viewer),
            'officials' => $this->officials($entries),
            'clubs' => $clubs->map(fn ($c) => $this->registry->club($c) + ['rosterCount' => $this->headcount($c)])->concat($extra)->values()->all(),
            'athletes' => FederationAthlete::with('user')->whereIn('id', $athleteIds)->get()->map(fn ($a) => $this->registry->athlete($a, $ids))->values()->all(),
            'transfers' => $transfers->map(fn ($t) => $this->registry->transfer($t))->all(),
            'entriesByEvent' => (object) $byEvent,
            'inviteEvents' => (object) $events->mapWithKeys(fn ($e) => [$e->uuid => $this->registry->eventForInvite($e)])->all(),
            'clubLinks' => (object) $clubs->mapWithKeys(fn ($c) => [$this->registry->clubKey($c) => $this->registry->token($c)])->all(),
        ];
    }

    /**
     * The athlete's face for the Athletes page, or null (the page then draws
     * initials). Only a picture the member made public: this is a surface
     * wider than their own profile (CLAUDE.md -> Profile Pictures Are 3:4).
     */
    private function photo(?User $u): ?string
    {
        return $u && $u->profile_picture_is_public && ProfilePhoto::path($u) ? ProfilePhoto::url($u) : null;
    }

    /** The CPR number from the member's own identity documents (never the scan). */
    private function cpr(?User $u): ?string
    {
        foreach ((array) ($u?->documents ?? []) as $doc) {
            if (is_array($doc) && strtoupper((string) ($doc['type'] ?? '')) === 'CPR' && trim((string) ($doc['number'] ?? '')) !== '') {
                return trim((string) $doc['number']);
            }
        }

        return null;
    }

    private function mobile(?User $u): string
    {
        $m = is_array($u?->mobile) ? trim(($u->mobile['code'] ?? '').' '.($u->mobile['number'] ?? '')) : '';

        return $m !== '' ? $m : '—';
    }

    /**
     * The federation's sports this person competed in, named as the
     * federation names them (an event stores 'karate', the federation
     * 'Karate'). With no sport list, every sport they competed in counts.
     */
    private function competedSports(array $events, array $sports): array
    {
        $out = [];
        foreach ($events as $e) {
            $raw = trim((string) ($e['sport'] ?? ''));
            if ($raw === '') {
                continue;
            }
            $name = collect($sports)->first(fn ($sp) => strcasecmp($sp, $raw) === 0) ?? ($sports === [] ? ucfirst($raw) : null);
            if ($name !== null) {
                $out[$name] = true;
            }
        }

        return array_keys($out);
    }

    /** Club enrolment and competition, as the Athletes page's sport chips. */
    private function practiceChips(array $activities, array $competed): array
    {
        $chips = array_map(fn ($n) => [$n, 'Enrolled'], $activities);
        foreach ($competed as $sp) {
            if (! collect($activities)->contains(fn ($a) => stripos($a, $sp) !== false)) {
                $chips[] = [$sp, 'Competed'];
            }
        }

        return $chips;
    }

    /**
     * A platform club's own page, or null. Country-prefixed (`clubs.show` is
     * bound under `{country}`), and only for an ISO code — a country stored as
     * a name would be a 404, and a dead link is not rendered.
     */
    private function clubHref($tenant): ?string
    {
        $country = strtolower(trim((string) ($tenant->country ?? '')));

        return $tenant && $tenant->slug && preg_match('/^[a-z]{2,3}$/', $country)
            ? route('clubs.show', ['country' => $country, 'slug' => $tenant->slug]) : null;
    }

    /** The nationality as the card shows it: the full name and our own flag file. */
    private function nationality(?string $code): array
    {
        return [
            'natName' => $code ? (\App\Support\Countries::name($code) ?: $code) : '—',
            'natFlag' => $code ? \App\Support\Flag::url($code) : null,
        ];
    }

    /**
     * The events each person took part in, newest first: the federation's own
     * entries, and platform events they joined as a competitor. A club's
     * internal sessions stay the club's business.
     *
     * @return array<int, array<int, array{name: string, date: string}>> user id => events
     */
    private function eventsByUser(Collection $userIds, ?User $viewer = null): array
    {
        $out = [];
        if ($userIds->isEmpty()) {
            return $out;
        }
        FederationEntryAthlete::with('entry.event', 'athlete')->where('status', 'Active')
            ->whereHas('athlete', fn ($q) => $q->whereIn('user_id', $userIds))->get()
            ->each(function (FederationEntryAthlete $ea) use (&$out) {
                $e = $ea->entry?->event;
                if ($e) {
                    $out[$ea->athlete->user_id]['f'.$e->id] = ['name' => $e->name, 'sport' => (string) $e->sport, 'fed' => $e->uuid, 'url' => null, 'date' => $e->starts_on ? \Illuminate\Support\Carbon::parse($e->starts_on)->format('Y-m-d') : ''];
                }
            });
        $joined = DB::table('club_event_registrations as r')->join('club_events as e', 'e.id', '=', 'r.event_id')
            ->whereIn('r.user_id', $userIds)->where('r.status', 'joined')->where('r.role', 'participant')
            ->where('e.scope', '!=', 'internal')
            // A club's HIDDEN event is not the federation's to see (ClubEvent::isHidden).
            ->where(fn ($q) => $q->whereNull('e.visibility')->orWhere('e.visibility', '!=', \App\Events\Models\ClubEvent::HIDDEN))
            // The platform event BEHIND a federation event is the same
            // competition, already listed above from the federation's entries:
            // listing it again read as two events (2026-10-02).
            ->whereNotIn('e.id', FederationEvent::whereNotNull('club_event_id')->pluck('club_event_id'))
            // One row per event even with two entries in it (Kumite and Kata).
            ->distinct()
            ->get(['r.user_id', 'e.id', 'e.title', 'e.date', 'e.sport']);
        // A platform event links to the page THIS viewer can open, never to a
        // 404: the event page when EventAccess lets them see it (host club,
        // organisers, eligible members, platform staff), else its public page
        // when that opens for anybody, else nothing. No access is widened.
        $publisher = app(\App\Events\Support\PublicEvent::class);
        $access = app(\App\Events\Support\EventAccess::class);
        $urls = \App\Events\Models\ClubEvent::whereIn('id', $joined->pluck('id')->unique())->get()
            ->mapWithKeys(fn ($ev) => [$ev->id => $viewer && $access->visible($ev, $viewer) ? route('me.events.show', $ev->uuid)
                : ($publisher->isPublic($ev) ? route('me.events.show', $ev->uuid) : null)]);
        $joined->each(function ($row) use (&$out, $urls) {
            $out[$row->user_id]['c'.$row->id] = ['name' => (string) $row->title, 'sport' => (string) $row->sport, 'fed' => null, 'url' => $urls[$row->id] ?? null, 'date' => $row->date ? substr((string) $row->date, 0, 10) : ''];
        });

        return array_map(function (array $list) {
            $list = array_values($list);
            usort($list, fn ($a, $b) => strcmp($b['date'], $a['date']));

            return array_map(fn ($e) => ['name' => $e['name'], 'sport' => $e['sport'], 'fed' => $e['fed'], 'url' => $e['url'], 'date' => $e['date'] !== '' ? \Illuminate\Support\Carbon::parse($e['date'])->format('j M Y') : 'Date TBC'], $list);
        }, $out);
    }

    /**
     * The Athletes page's list, in the prototype's own row shape (its built-in
     * `athData`): who they are, where they compete, and what they have
     * entered. Real records only.
     */
    private function directory(Collection $ids, Collection $athleteIds, Collection $clubs, array $sports, ?User $viewer = null): array
    {
        $members = $this->clubMembers($clubs, $sports);
        $events = $this->eventsByUser(FederationAthlete::whereIn('id', $athleteIds)->pluck('user_id')->merge(array_keys($members))->unique()->values(), $viewer);

        // A deleted account leaves the Athletes list. Its entries, draws and
        // results are kept (they are the event's history), so only this list
        // drops it — whereHas skips soft-deleted users.
        $rows = FederationAthlete::with('user')->whereIn('id', $athleteIds)->whereNull('anonymised_at')->whereHas('user')->orderBy('takeone_id')->get()->map(function (FederationAthlete $a) use ($ids, $events) {
            $u = $a->user;
            $cur = $this->registry->currentClub($a, $ids);
            $club = $cur ? ($cur->club?->club?->club_name ?? $cur->application?->name) : null;
            // A platform club's own logo, or the one an applying club uploaded.
            $clubLogo = $cur?->club?->club?->logo ? file_url($cur->club->club->logo)
                : ($cur?->application?->logo ? route('federations.invite.club-logo', $cur->application->uuid) : null);
            $entries = FederationEntryAthlete::with('entry.event')->where('federation_athlete_id', $a->id)->where('status', 'Active')->get();
            $sports = $entries->map(fn ($ea) => [(string) $ea->entry?->event?->sport, $ea->weight ? rtrim(rtrim(number_format($ea->weight, 1), '0'), '.').' kg' : '—'])
                ->unique(fn ($x) => $x[0])->values()->all();

            return [
                // Never null: an athlete whose account was deleted keeps their entries
                // and results, and the dashboard draws initials from this string.
                'name' => $u?->full_name ?: $u?->name ?: 'Deleted member',
                'club' => $club ?: '—',
                'clubLogo' => $clubLogo,
                'clubUrl' => $cur?->club?->club ? $this->clubHref($cur->club->club) : null,
                'gender' => $u?->gender === 'Female' ? 'F' : 'M',
                'dob' => $u?->birthdate ? $u->birthdate->format('j M Y') : '—',
                'age' => $u?->birthdate ? $u->birthdate->age : '—',
                'nat' => $a->nationality ?: (\App\Support\Countries::iso3($u?->nationality) ?: '—'),
                ...$this->nationality($a->nationality ?: $u?->nationality),
                'natId' => (string) ($a->national_id ?: ($this->cpr($u) ?: '—')),
                'mobile' => $this->mobile($u),
                'email' => $u?->email ?: '—',
                'tk' => $a->takeone_id,
                'eventList' => $events[$a->user_id] ?? [],
                'events' => count($events[$a->user_id] ?? []),
                'sports' => $sports,
                'key' => $a->takeone_id,
                'photo' => $this->photo($u),
                'userId' => $a->user_id,
                '_tid' => $cur?->club?->tenant_id,
            ];
        })->values();

        // Club members: the federation's clubs' own rosters, whether or not
        // they have entered anything. One row per person — an athlete already
        // listed above only gains the "practises" mark.
        // A sport is practised through the club (an active package with that
        // activity) OR by competing in an event of that sport.
        $byUser = $rows->keyBy('userId');
        $out = $rows->map(function (array $r) use ($members, $events, $sports) {
            $m = $members[$r['userId']] ?? null;
            $r['_tid'] = $r['_tid'] ?: ($m['tenantId'] ?? null);
            $activities = $m ? $m['activities'] : [];
            $competed = $this->competedSports($events[$r['userId']] ?? [], $sports);
            $competed = array_values(array_filter($competed, fn ($sp) => ! collect($r['sports'])->contains(fn ($x) => strcasecmp($x[0], $sp) === 0)));
            if ($activities !== [] || $competed !== [] || $r['sports'] !== []) {
                $r['practises'] = true;
            }
            $r['sports'] = array_merge($this->practiceChips($activities, $competed), $r['sports']);

            return $r;
        })->all();
        foreach ($members as $uid => $m) {
            if ($byUser->has($uid)) {
                continue;
            }
            $u = $m['user'];
            // The federation sees its clubs' members in full (decided
            // 2026-09-26): CPR from their identity documents, contact details,
            // and the events they competed in.
            $out[] = [
                'name' => $u->full_name ?: $u->name,
                'club' => $m['club'],
                'clubLogo' => $m['clubLogo'],
                'clubUrl' => $m['clubUrl'],
                'gender' => $u->gender === 'Female' ? 'F' : 'M',
                'dob' => $u->birthdate ? $u->birthdate->format('j M Y') : '—',
                'age' => $u->birthdate ? $u->birthdate->age : '—',
                // Profiles store ISO-2 ('BH'); athletes read ISO-3 ('BHR').
                'nat' => \App\Support\Countries::iso3($u->nationality) ?: ($u->nationality ?: '—'),
                ...$this->nationality($u->nationality),
                'natId' => $this->cpr($u) ?: '—',
                'mobile' => $this->mobile($u),
                'email' => $u->email ?: '—',
                'tk' => '',
                'eventList' => $events[$uid] ?? [],
                'events' => count($events[$uid] ?? []),
                'sports' => $chips = $this->practiceChips($m['activities'], $this->competedSports($events[$uid] ?? [], $sports)),
                'practises' => $chips !== [],
                'member' => true,
                'key' => 'm-'.$u->uuid,
                'userId' => $u->id,
                '_tid' => $m['tenantId'] ?? null,
                'photo' => $this->photo($u),
            ];
        }

        $arts = $this->clubArts($out);

        return array_map(function (array $r) use ($arts) {
            // The martial arts they practise at the club shown on their row.
            $r['clubArts'] = $arts[($r['userId'] ?? 0).'|'.($r['_tid'] ?? 0)] ?? [];
            // No package enrolment on file (most clubs have none yet): the martial
            // arts they have actually competed in, rather than nothing.
            if ($r['clubArts'] === []) {
                $r['clubArts'] = collect($r['sports'] ?? [])->map(fn ($c) => trim((string) ($c[0] ?? '')))
                    ->filter(fn ($n) => $n !== '' && $this->isMartialArt($n))->unique()->values()->all();
            }
            unset($r['userId'], $r['_tid']);
            $r['practises'] ??= false;

            return $r;
        }, $out);
    }

    /** The sports this viewer's federation governs (a sport-limited viewer: theirs). */
    /**
     * "user|tenant" => the martial arts that person is actively enrolled in at
     * that club (an active subscription to a package carrying the activity).
     * A club's other activities (padel, swimming…) are not the federation's
     * business and are left out.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<int, string>>
     */
    private function clubArts(array $rows): array
    {
        $pairs = collect($rows)->filter(fn ($r) => ! empty($r['userId']) && ! empty($r['_tid']));
        if ($pairs->isEmpty()) {
            return [];
        }
        $out = [];
        DB::table('club_member_subscriptions as s')
            ->join('club_package_activities as pa', 'pa.package_id', '=', 's.package_id')
            ->join('club_activities as a', 'a.id', '=', 'pa.activity_id')
            ->whereIn('s.user_id', $pairs->pluck('userId')->unique())
            ->whereIn('s.tenant_id', $pairs->pluck('_tid')->unique())
            ->where('s.status', 'active')
            ->where(fn ($w) => $w->whereNull('s.end_date')->orWhereDate('s.end_date', '>=', now()->toDateString()))
            ->select('s.user_id', 's.tenant_id', 'a.name')->distinct()->get()
            ->each(function ($row) use (&$out) {
                $name = trim((string) $row->name);
                $k = $row->user_id.'|'.$row->tenant_id;
                if ($name !== '' && $this->isMartialArt($name) && ! in_array($name, $out[$k] ?? [], true)) {
                    $out[$k][] = $name;
                }
            });

        return $out;
    }

    /** A martial art, by the federation module's own sports list — tolerant of how a club spells it. */
    private function isMartialArt(string $activity): bool
    {
        $flat = preg_replace('/[^a-z]/', '', mb_strtolower(\Illuminate\Support\Str::ascii($activity)));
        $words = ['bjj', 'kickbox', 'muaythai', 'sambo', 'aikido', 'hapkido', 'kravmaga', 'capoeira', 'kendo', 'selfdefen', 'grappling'];
        foreach (FederationDirectory::SPORTS as $sport) {
            foreach (preg_split('#\s*/\s*#', $sport) as $part) {
                $words[] = preg_replace('/[^a-z]/', '', mb_strtolower($part));
            }
        }
        foreach (array_filter($words) as $w) {
            if (str_contains($flat, $w)) {
                return true;
            }
        }

        return false;
    }

    private function sportsOf(array $access): array
    {
        if ($access['sports'] !== []) {
            return $access['sports'];
        }
        $root = $access['root'];

        return array_values(array_filter((array) ($root->sports ?: [$root->sport])));
    }

    /**
     * Members of the federation's approved clubs, each with the activities of
     * the federation's sports they are enrolled in now (an active
     * subscription to a package that teaches one).
     *
     * @return array<int, array{user: User, club: string, activities: array<int,string>}>
     */
    private function clubMembers(Collection $clubs, array $sports): array
    {
        $tenants = $clubs->filter(fn ($c) => $c instanceof FederationClub && $c->club)->mapWithKeys(fn ($c) => [$c->tenant_id => $c->club->club_name]);
        $logos = $clubs->filter(fn ($c) => $c instanceof FederationClub && $c->club)->mapWithKeys(fn ($c) => [$c->tenant_id => $c->club->logo ? file_url($c->club->logo) : null]);
        $hrefs = $clubs->filter(fn ($c) => $c instanceof FederationClub && $c->club)->mapWithKeys(fn ($c) => [$c->tenant_id => $this->clubHref($c->club)]);
        if ($tenants->isEmpty()) {
            return [];
        }

        $enrolled = [];
        if ($sports !== []) {
            DB::table('club_member_subscriptions as s')
                ->join('club_package_activities as pa', 'pa.package_id', '=', 's.package_id')
                ->join('club_activities as a', 'a.id', '=', 'pa.activity_id')
                ->whereIn('s.tenant_id', $tenants->keys())
                ->where('s.status', 'active')
                ->where(fn ($w) => $w->whereNull('s.end_date')->orWhereDate('s.end_date', '>=', now()->toDateString()))
                ->where(function ($w) use ($sports) {
                    foreach ($sports as $sport) {
                        $w->orWhere('a.name', 'like', '%'.str_replace(['%', '_'], '', $sport).'%');
                    }
                })
                ->select('s.user_id', 'a.name')->distinct()->get()
                ->each(function ($row) use (&$enrolled) { $enrolled[$row->user_id][] = $row->name; });
        }

        $rows = DB::table('memberships')->whereIn('tenant_id', $tenants->keys())->where('status', 'active')
            ->orderBy('id')->limit(5000)->get(['tenant_id', 'user_id']);
        $users = User::whereIn('id', $rows->pluck('user_id')->unique())
            ->get(['id', 'uuid', 'name', 'full_name', 'email', 'mobile', 'documents', 'gender', 'birthdate', 'nationality', 'profile_picture', 'profile_picture_is_public'])->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $u = $users[$row->user_id] ?? null;
            if (! $u || isset($out[$u->id])) {
                continue;
            }
            $out[$u->id] = ['user' => $u, 'tenantId' => (int) $row->tenant_id, 'club' => $tenants[$row->tenant_id], 'clubLogo' => $logos[$row->tenant_id] ?? null, 'clubUrl' => $hrefs[$row->tenant_id] ?? null, 'activities' => array_values(array_unique($enrolled[$u->id] ?? []))];
        }

        return $out;
    }

    /**
     * The Officials page: one row per PERSON a club named on its entries (a
     * team manager or a referee), drawn the way the Athletes page draws an
     * athlete — face, role, licence, club, and every event they were named
     * for. The same name, role kind and club is the same person across events.
     * A photo is a link to the gated `invite.official` route, never a path, and
     * only while the club's current slot still names that person.
     */
    private function officials(Collection $entries): array
    {
        $rows = [];
        foreach ($entries as $en) {
            $club = $en->club ?? $en->application;
            $ev = $en->event;
            if (! $club || ! $ev) {
                continue;
            }
            $clubKey = $this->registry->clubKey($club);
            $clubRow = $this->registry->club($club, false);
            $current = (array) ($this->registry->officials($club) ?? []);
            foreach (['ref1', 'ref2', 'mgr'] as $slot) {
                $o = ($en->officials ?? [])[$slot] ?? null;
                $name = trim((string) ($o['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $kind = $slot === 'mgr' ? 'mgr' : 'ref';
                $key = substr(md5($clubKey.'|'.$kind.'|'.mb_strtolower($name)), 0, 16);
                $rows[$key] ??= [
                    'key' => $key, 'name' => $name, 'kind' => $kind,
                    'role' => $kind === 'mgr' ? 'Team manager' : 'Referee',
                    'license' => '', 'photo' => null,
                    'club' => (string) $clubRow['name'], 'clubLogo' => $clubRow['logo'] ?? null,
                    'sports' => [], 'events' => [],
                ];
                if (($o['license'] ?? '') !== '') {
                    $rows[$key]['license'] = (string) $o['license'];
                }
                foreach ($current as $cs => $cp) {
                    if (! $rows[$key]['photo'] && ($cs === 'mgr') === ($kind === 'mgr') && ! empty($cp['photo']) && mb_strtolower(trim((string) ($cp['name'] ?? ''))) === mb_strtolower($name)) {
                        $rows[$key]['photo'] = $cp['photo'];
                    }
                }
                if ((string) $ev->sport !== '' && ! in_array((string) $ev->sport, $rows[$key]['sports'], true)) {
                    $rows[$key]['sports'][] = (string) $ev->sport;
                }
                $rows[$key]['events'][$ev->uuid] = [
                    'name' => (string) $ev->name, 'fed' => $ev->uuid,
                    'sort' => $ev->starts_on ? \Illuminate\Support\Carbon::parse($ev->starts_on)->format('Y-m-d') : '',
                ];
            }
        }

        return array_values(array_map(function (array $r) {
            $events = array_values($r['events']);
            usort($events, fn ($a, $b) => strcmp($b['sort'], $a['sort']));
            $r['events'] = array_map(fn ($e) => ['name' => $e['name'], 'fed' => $e['fed'], 'date' => $e['sort'] !== '' ? \Illuminate\Support\Carbon::parse($e['sort'])->format('j M Y') : 'Date TBC'], $events);

            return $r;
        }, $rows));
    }

    /** `takeone_club_regs`: the registration feed, every event in scope. */
    public function regs(array $access): array
    {
        $ids = $this->ids($access);

        return FederationEntry::with('event', 'club.club', 'application')->whereIn('federation_event_id', $this->eventsOf($access)->pluck('id'))
            ->get()->map(fn ($en) => $this->registry->regFeed($en, $ids))->values()->all();
    }

    /**
     * One athlete on one entry, if both are within this viewer's federation
     * (FederationAthleteEditor). Active entries only: a withdrawn athlete is
     * not on the event's list to be edited from it.
     */
    public function entryAthleteInScope(array $access, ?string $entryUuid, string $takeoneId): ?FederationEntryAthlete
    {
        $entry = $this->entryInScope($access, $entryUuid);

        return $entry && $takeoneId !== ''
            ? $entry->athletes()->with(['athlete.user', 'entry.event'])->where('status', 'Active')
                ->whereHas('athlete', fn ($q) => $q->where('takeone_id', $takeoneId))->first()
            : null;
    }

    private function entryInScope(array $access, ?string $uuid): ?FederationEntry
    {
        return $uuid ? FederationEntry::whereIn('federation_event_id', $this->eventsOf($access)->pluck('id'))->where('uuid', $uuid)->first() : null;
    }

    /** The federation confirms a club's bank transfer arrived. */
    public function confirmEntryPayment(User $user, array $access, string $entryUuid): bool
    {
        $entry = $this->entryInScope($access, $entryUuid);
        if (! $entry) {
            return false;
        }
        $pays = $entry->payments ?: [];
        $sum = 0;
        foreach ($pays as $i => $p) {
            if (($p['status'] ?? '') === 'Awaiting transfer') {
                $pays[$i]['status'] = 'Paid';
                $pays[$i]['confirmedAt'] = now()->getTimestampMs();
                $pays[$i]['confirmedBy'] = $user->id;
                $sum += (float) $p['amount'];
            }
        }
        if (! $sum) {
            return true;
        }
        $entry->payments = $pays;
        $entry->log = array_merge($entry->log ?: [], [['ts' => now()->getTimestampMs(), 'key' => 'logPaid', 'a' => 'BHD '.number_format($sum, 3), 'm' => 'Bank transfer confirmed']]);
        $entry->save();

        return true;
    }

    private function transferInScope(array $access, string $uuid): ?FederationTransfer
    {
        return FederationTransfer::whereIn('federation_id', $this->ids($access))->where('uuid', $uuid)->first();
    }

    /** Record the old club's answer (the federation takes it by phone or email). */
    public function transferReply(array $access, string $uuid, bool $released): bool
    {
        $t = $this->transferInScope($access, $uuid);
        if (! $t || $t->status !== 'Pending') {
            return false;
        }
        $t->update(['old_club' => $released ? 'Released' : 'Objected']);

        return true;
    }

    /**
     * Approve or decline. Approving moves the athlete's FEDERATION record to
     * the new club and closes the old history row — nothing on the club side.
     */
    public function decideTransfer(User $user, array $access, string $uuid, bool $approve): bool
    {
        $t = $this->transferInScope($access, $uuid);
        if (! $t || $t->status !== 'Pending') {
            return false;
        }

        DB::transaction(function () use ($t, $user, $approve) {
            $t->update(['status' => $approve ? 'Approved' : 'Declined', 'decided_at' => now(), 'decided_by' => $user->id]);
            if (! $approve) {
                return;
            }
            $ym = now()->format('Y-m');
            FederationAthleteClub::where('federation_athlete_id', $t->federation_athlete_id)
                ->where('federation_id', $t->federation_id)->whereNull('to_ym')->update(['to_ym' => $ym]);
            FederationAthleteClub::create([
                'federation_athlete_id' => $t->federation_athlete_id,
                'federation_id' => $t->federation_id,
                'federation_club_id' => $t->to_club_id,
                'club_application_id' => $t->to_application_id,
                'from_ym' => $ym,
            ]);
        });

        return true;
    }

    /** The federation approves one athlete's registration. */
    public function approveReg(array $access, ?string $entryUuid, string $takeoneId): bool
    {
        $entry = $this->entryInScope($access, $entryUuid);
        if (! $entry) {
            return false;
        }

        return (bool) $entry->athletes()->whereHas('athlete', fn ($q) => $q->where('takeone_id', $takeoneId))
            ->where('status', 'Active')->update(['approval' => 'Approved']);
    }

    /**
     * The Registrations tab's delete / clear-all. On the platform it is a
     * withdrawal, never a deletion — and only a manager may do it.
     *
     * @param  array<int, array{entry?: string, athlete?: string}>  $rows
     */
    /**
     * Take a whole club off one event (the event's Clubs tab, owner
     * 2026-10-15): every athlete still Active on that club's entry becomes
     * Withdrawn, exactly as withdrawRegs() does one by one, and the route's
     * SyncPlatformEntries then takes them out of the platform event. The
     * entry itself, its payments and the officials it names are left alone.
     *
     * @return int|null how many athletes were withdrawn; null when not allowed or not found
     */
    public function withdrawClub(array $access, string $eventUuid, string $clubKey): ?int
    {
        if (! $access['manager'] || ! preg_match('/^(fc|fa)-([0-9a-f-]{36})$/', $clubKey, $m)) {
            return null;
        }
        $event = $this->eventsOf($access)->firstWhere('uuid', $eventUuid);
        if (! $event) {
            return null;
        }
        $club = $m[1] === 'fc'
            ? FederationClub::where('uuid', $m[2])->first()
            : \App\Federations\Models\FederationClubApplication::where('uuid', $m[2])->first();
        if (! $club) {
            return null;
        }
        $entries = FederationEntry::where('federation_event_id', $event->id)
            ->where($m[1] === 'fc' ? 'federation_club_id' : 'club_application_id', $club->id)->pluck('id');
        if ($entries->isEmpty()) {
            return null;
        }

        return FederationEntryAthlete::whereIn('federation_entry_id', $entries)->where('status', 'Active')
            ->update(['status' => 'Withdrawn', 'withdrawn_at' => now()]);
    }

    public function withdrawRegs(array $access, array $rows): bool
    {
        if (! $access['manager']) {
            return false;
        }
        foreach (array_slice($rows, 0, 1000) as $r) {
            $entry = $this->entryInScope($access, is_string($r['entry'] ?? null) ? $r['entry'] : null);
            if (! $entry) {
                continue;
            }
            $entry->athletes()->whereHas('athlete', fn ($q) => $q->where('takeone_id', (string) ($r['athlete'] ?? '')))
                ->where('status', 'Active')->update(['status' => 'Withdrawn', 'withdrawn_at' => now()]);
        }

        return true;
    }

    /**
     * How many people a club has, as the dashboard's Clubs page prints it
     * (2026-10-02, the owner's ruling: "active or not it has members").
     *
     * Everybody on the platform club's own roster, whatever their membership
     * status, plus every federation athlete whose current club this is — each
     * person once. A club still applying has no platform roster, so only the
     * federation's athletes count for it. Only on the dashboard's payload: a
     * club reading the invitation is never told another club's headcount.
     */
    private function headcount(FederationClub|FederationClubApplication $c): int
    {
        $users = $this->registry->roster($c)->pluck('user_id')->filter();

        if ($c instanceof FederationClub && $c->tenant_id) {
            $users = $users->merge(\App\Members\Models\Membership::where('tenant_id', $c->tenant_id)->pluck('user_id'));
        }

        return $users->filter()->unique()->count();
    }
}
