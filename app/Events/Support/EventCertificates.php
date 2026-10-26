<?php

namespace App\Events\Support;

use Ahl\Certificates\CertificateService;
use Ahl\Certificates\Certificates as Engine;
use Ahl\Certificates\Models\Certificate;
use App\Certificates\Contracts\CertificateContext;
use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventCertificateSetting;
use App\Federations\Models\Federation;
use App\Members\Models\User;
use App\Support\StoragePath;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What a competition gives the people who took part in it (2026-09-29).
 *
 *   participation  — everyone who stepped on the mat, one per division entered
 *   podium         — gold, silver and bronze, read off the division's stored podium
 *   official_thanks — every official of the event, once per person: a thank-you
 *                    for helping make it a success (2026-10-15; off by default)
 *   club_thanks    — every club that brought athletes, once per club: a thank-you
 *                    for taking part (2026-10-20; off by default). The CLUB holds
 *                    it — a Tenant, or the EventClub the organiser wrote down.
 *
 * COMMON to every bracketed sport (CLAUDE.md → Shared Stays Shared): it reads
 * `event_categories.podium` — the one record Advancement writes, whatever the
 * sport or its bronze rule — and the bouts in `event_matches`, never a sport's
 * own engine. A division counts only once its podium is stored; until then it
 * is "still running" and nothing is drafted for it.
 *
 * Nothing reaches an athlete from here: this makes DRAFTS. The organiser
 * approves them in the console, and only then is the PDF stored and the
 * athlete told (App\Certificates).
 */
class EventCertificates implements CertificateContext
{
    public const ALIAS = 'club_event';

    public const SOURCE_ENTRY = 'event_entry';

    public const SOURCE_PODIUM = 'event_podium';

    public const SOURCE_OFFICIAL = 'event_official';

    public const TYPE_OFFICIALS = 'official_thanks';

    public const SOURCE_CLUB = 'event_club_thanks';

    public const TYPE_CLUBS = 'club_thanks';

    /** @var array<int, array> brand per event, for one request */
    private array $brands = [];

    /** While generateFor() runs: the holders asked for (holderKey => true); null otherwise. */
    private ?array $holderFilter = null;

    /**
     * Who a certificate is for, as the outside names them: a person by their
     * public id (`u:<uuid>`), a club by its name (`n:<name>`) — the name is what
     * the club's two records share, as clubsPlan() merges them.
     */
    public function holderKey(Model $holder): string
    {
        return $holder instanceof User
            ? 'u:'.$holder->uuid
            : 'n:'.mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $holder instanceof \Ahl\Certificates\Contracts\Recipient ? $holder->certificateName() : '')));
    }

    /**
     * Draft one kind of certificate for the holders named only (holderKey()),
     * not for everyone due one — "issue to these people" (owner, 2026-10-20).
     *
     * @param  list<string>  $holders
     */
    public function generateFor(Model $context, ?User $by, string $type, array $holders): array
    {
        $this->holderFilter = array_fill_keys($holders, true);
        try {
            return $this->generate($context, $by, $type);
        } finally {
            $this->holderFilter = null;
        }
    }

    private function wanted(Model $holder): bool
    {
        return $this->holderFilter === null || isset($this->holderFilter[$this->holderKey($holder)]);
    }

    public function __construct(private EventAccess $access) {}

    public function alias(): string
    {
        return self::ALIAS;
    }

    public function ownerTypes(): array
    {
        return [self::ALIAS, 'club', 'federation'];
    }

    public function find(string $key): ?Model
    {
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $key)) {
            return null;
        }

        return ClubEvent::where('uuid', $key)->where('is_archived', false)->first();
    }

    public function key(Model $context): string
    {
        return (string) $context->uuid;
    }

    public function handles(Model $context): bool
    {
        return $context instanceof ClubEvent;
    }

    public function canManage(Model $context, User $user): bool
    {
        return $context instanceof ClubEvent && ! $context->is_archived && $this->access->canManage($context, $user);
    }

    /**
     * May this user change a design that belongs to $owner? The event's own
     * design: anyone who runs the event. The host's default, which every other
     * event of that host inherits: only the host's own people — an official
     * appointed to one event does not get to redesign a federation's paper.
     */
    public function canManageOwner(Model $context, array $owner, User $user): bool
    {
        if (! $this->canManage($context, $user)) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        [$type, $id] = [$owner[0] ?? '', (int) ($owner[1] ?? 0)];

        return match ($type) {
            self::ALIAS => $id === (int) $context->id,
            'club' => $id === (int) $context->tenant_id
                && (Tenant::whereKey($id)->where('owner_user_id', $user->id)->exists() || $user->isClubAdmin($id)),
            default => ! $context->tenant_id && $type === (string) $context->host_type && $id === (int) $context->host_id
                && $context->host()->manages($context, $user),
        };
    }

    public function audience(Model $context): array
    {
        return app(AudienceResolver::class)->managers($context);
    }

    public function headline(Model $context): string
    {
        return (string) ($context->getRawOriginal('title') ?: $context->title);
    }

    public function issuer(Model $context, string $locale): string
    {
        return EventHost::of($context)->name ?: $this->headline($context);
    }

    public function templateOwners(Model $context): array
    {
        $chain = [[self::ALIAS, (int) $context->id]];
        $host = EventHost::of($context);
        if ($host->id && $host->kind !== 'none') {
            $chain[] = [$host->kind, (int) $host->id];
        }

        return $chain;
    }

    public function ownerLabel(Model $context, array $owner, string $locale): string
    {
        return ($owner[0] ?? '') === self::ALIAS
            ? __('cert::messages.owner_event', [], $locale)
            : __('cert::messages.owner_host', ['host' => EventHost::of($context)->name], $locale);
    }

    public function ownerStorageRoot(array $owner): ?string
    {
        [$type, $id] = [$owner[0] ?? '', (int) ($owner[1] ?? 0)];

        return match ($type) {
            self::ALIAS => ($e = ClubEvent::find($id)) ? StoragePath::event($e, 'certificates') : null,
            'club' => ($c = Tenant::find($id)) ? StoragePath::club($c, 'certificates') : null,
            'federation' => ($f = Federation::find($id)) ? 'federations/'.$f->uuid.'/certificates' : null,
            default => null,
        };
    }

    public function storageRoot(Model $context): string
    {
        return StoragePath::event($context, 'certificates');
    }

    public function brand(Model $context): array
    {
        return $this->brands[$context->id] ??= $this->readBrand($context);
    }

    public function placeholderKeys(): array
    {
        return ['division', 'event', 'place', 'medal', 'date', 'venue', 'club', 'representing', 'role', 'athletes'];
    }

    public function placeholders(Certificate $certificate, string $locale): array
    {
        $event = $certificate->context;
        $d = (array) ($certificate->details ?? []);
        $place = isset($d['place']) ? (int) $d['place'] : null;
        $club = trim((string) ($d['club'] ?? ''));

        return [
            'division' => (string) ($d['division'] ?? $certificate->achievement ?? ''),
            'event' => $event instanceof ClubEvent ? $this->headline($event) : (string) ($d['event'] ?? ''),
            'place' => $place ? __("cert::messages.place_word.{$place}", [], $locale) : '',
            'medal' => $place && $place <= 3 ? __("cert::messages.medal_word.{$place}", [], $locale) : '',
            'date' => $event instanceof ClubEvent ? $this->dates($event, $locale) : '',
            'venue' => $event instanceof ClubEvent ? (string) ($event->getRawOriginal('location') ?? '') : '',
            'club' => $club,
            'representing' => $club !== '' ? __('cert::messages.representing', ['club' => $club], $locale) : '',
            // An official's job(s) at the event, for their thank-you certificate.
            'role' => (string) ($d['role'] ?? ''),
            // How many athletes a club brought, for its thank-you certificate.
            'athletes' => isset($d['athletes']) ? trans_choice('cert::messages.athletes_count', (int) $d['athletes'], ['count' => (int) $d['athletes']], $locale) : '',
        ];
    }

    public function previewDetails(string $type, Model $context): array
    {
        $division = EventCategory::where('event_id', $context->id)->competing()->orderBy('sort_order')->value('name');

        return [
            'division' => $division ?: __('cert::messages.sample_division'),
            'club' => EventHost::of($context)->name ?: null,
            'place' => $type === 'podium' ? 1 : null,
            'role' => $type === self::TYPE_OFFICIALS ? __('personal.personal_event_officials_role_jury') : null,
            'athletes' => $type === self::TYPE_CLUBS ? 12 : null,
        ];
    }

    public function types(): array
    {
        return ['participation', 'podium', self::TYPE_OFFICIALS, self::TYPE_CLUBS];
    }

    /**
     * The {tokens} that mean something on each design (the design room offers only
     * these): an official's certificate knows their job, not a division or a medal.
     *
     * @return list<string>
     */
    public function typePlaceholders(string $type): array
    {
        return match ($type) {
            self::TYPE_OFFICIALS => ['name', 'role', 'club', 'event', 'date', 'venue'],
            // {name} is the club's own name here.
            self::TYPE_CLUBS => ['name', 'athletes', 'event', 'date', 'venue'],
            'podium' => ['name', 'division', 'place', 'medal', 'club', 'representing', 'event', 'date', 'venue'],
            default => ['name', 'division', 'club', 'representing', 'event', 'date', 'venue'],
        };
    }

    public function settings(Model $context): array
    {
        $s = EventCertificateSetting::for($context);

        return [
            'participation' => (bool) $s->participation,
            'podium' => (bool) $s->podium,
            'officials' => (bool) $s->officials,
            'clubs' => (bool) $s->clubs,
            'trigger' => in_array($s->trigger, EventCertificateSetting::TRIGGERS, true) ? $s->trigger : 'division',
        ];
    }

    public function saveSettings(Model $context, array $input): array
    {
        $s = EventCertificateSetting::for($context);
        foreach (['participation', 'podium', 'officials', 'clubs'] as $flag) {
            if (array_key_exists($flag, $input)) {
                $s->{$flag} = (bool) $input[$flag];
            }
        }
        if (isset($input['trigger']) && in_array($input['trigger'], EventCertificateSetting::TRIGGERS, true)) {
            $s->trigger = $input['trigger'];
        }
        $s->save();

        return $this->settings($context);
    }

    public function eligible(Model $context): array
    {
        $plan = $this->plan($context);

        return [
            'participation' => $plan['participation']->count(),
            'podium' => $plan['podium']->count(),
            'officials' => $plan['officials']->count(),
            'clubs' => $plan['clubs']->count(),
            'decided' => $plan['decided'],
            'pending' => $plan['pending'],
        ];
    }

    /**
     * Draft what has been earned and not yet given. Idempotent: an entry that
     * already has a certificate of that kind (a draft, an approved one, or one
     * the organiser revoked) is never given another. A podium DRAFT whose place
     * changed since — a result corrected at the table — follows the correction;
     * an approved one is left alone for the organiser to revoke.
     */
    public function generate(Model $context, ?User $by, ?string $only = null): array
    {
        $settings = $this->settings($context);
        // One kind only, whatever the console's switches say: asked for by name
        // (the federation view's "Issue"), so asking IS the switch.
        if ($only !== null) {
            $flag = ['participation' => 'participation', 'podium' => 'podium', self::TYPE_OFFICIALS => 'officials', self::TYPE_CLUBS => 'clubs'][$only] ?? null;
            foreach (['participation', 'podium', 'officials', 'clubs'] as $f) {
                $settings[$f] = $f === $flag;
            }
        }
        $plan = $this->plan($context);
        $service = app(CertificateService::class);
        $created = 0;

        return DB::transaction(function () use ($context, $by, $settings, $plan, $service, &$created) {
            if ($settings['podium']) {
                $this->reconcilePodiumDrafts($context, $plan['podium'], $service);
            }
            $this->refreshIssued($context, $plan, $settings);

            foreach (['participation' => self::SOURCE_ENTRY, 'podium' => self::SOURCE_PODIUM] as $type => $source) {
                if (! $settings[$type]) {
                    continue;
                }
                foreach ($plan[$type] as $row) {
                    if (! $this->wanted($row['user']) || $service->exists($row['user'], $source, $row['registration_id'])) {
                        continue;
                    }
                    $service->createDraft($row['user'], $type, [
                        'achievement' => $row['division'],
                        'context' => $context,
                        'source' => $source,
                        'source_id' => $row['registration_id'],
                        'details' => array_filter([
                            'division' => $row['division'],
                            'category_id' => $row['category_id'],
                            'registration_id' => $row['registration_id'],
                            'club' => $row['club'],
                            'place' => $row['place'] ?? null,
                        ], fn ($v) => $v !== null && $v !== ''),
                    ], $by);
                    $created++;
                }
            }

            // Officials: one thank-you per person, whatever jobs they held.
            if ($settings['officials']) {
                foreach ($plan['officials'] as $row) {
                    if (! $this->wanted($row['user']) || $service->exists($row['user'], self::SOURCE_OFFICIAL, $context->id)) {
                        continue;
                    }
                    $service->createDraft($row['user'], self::TYPE_OFFICIALS, [
                        'achievement' => $row['role'],
                        'context' => $context,
                        'source' => self::SOURCE_OFFICIAL,
                        'source_id' => $context->id,
                        'details' => array_filter(['role' => $row['role'], 'club' => $row['club']], fn ($v) => $v !== null && $v !== ''),
                    ], $by);
                    $created++;
                }
            }

            // Clubs: one thank-you per club that brought athletes.
            if ($settings['clubs']) {
                foreach ($plan['clubs'] as $row) {
                    if (! $this->wanted($row['club']) || $this->clubHasCertificate($row['club'], $context, $service)) {
                        continue;
                    }
                    $service->createDraft($row['club'], self::TYPE_CLUBS, [
                        'achievement' => $row['name'],
                        'context' => $context,
                        'source' => self::SOURCE_CLUB,
                        'source_id' => $context->id,
                        'details' => ['club' => $row['name'], 'athletes' => $row['athletes']],
                    ], $by);
                    $created++;
                }
            }

            return ['created' => $created, 'pending' => $plan['pending']];
        });
    }

    public function describe(Certificate $certificate, string $locale): string
    {
        $d = (array) ($certificate->details ?? []);
        $division = (string) ($d['division'] ?? $certificate->achievement ?? '');
        $place = isset($d['place']) ? (int) $d['place'] : null;
        if ($certificate->source === self::SOURCE_OFFICIAL) {
            return (string) ($d['role'] ?? $certificate->achievement ?? '');
        }
        if ($certificate->source === self::SOURCE_CLUB) {
            return isset($d['athletes'])
                ? trans_choice('cert::messages.athletes_count', (int) $d['athletes'], ['count' => (int) $d['athletes']], $locale)
                : __('cert::messages.types.club_thanks', [], $locale);
        }

        return $place && $place <= 3
            ? $division.' · '.__("cert::messages.medal_short.{$place}", [], $locale)
            : $division;
    }

    /* ─────────────────────────── the plan ─────────────────────────── */

    /**
     * Who has earned what, right now.
     *
     * @return array{participation: Collection, podium: Collection, decided: int, pending: int}
     */
    public function plan(ClubEvent $event): array
    {
        $divisions = EventCategory::where('event_id', $event->id)->competing()->orderBy('sort_order')->get(['id', 'name', 'podium']);
        $decided = $divisions->filter(fn ($c) => is_array($c->podium) && $c->podium !== []);

        $fought = $decided->isEmpty() ? collect() : DB::table('event_matches')
            ->where('event_id', $event->id)
            ->whereIn('category_id', $decided->pluck('id'))
            ->whereNotNull('winner')
            ->get(['category_id', 'a_competitor_id', 'b_competitor_id'])
            ->flatMap(fn ($m) => [[$m->category_id, $m->a_competitor_id], [$m->category_id, $m->b_competitor_id]])
            ->filter(fn ($pair) => $pair[1] !== null)
            ->map(fn ($pair) => $pair[0].':'.$pair[1])
            ->unique()
            ->flip();

        $medals = $decided->flatMap(fn ($c) => collect($c->podium)
            ->filter(fn ($m) => ! empty($m['competitor_id']) && (int) ($m['place'] ?? 0) >= 1 && (int) ($m['place'] ?? 0) <= 3)
            ->map(fn ($m) => ['category_id' => $c->id, 'registration_id' => (int) $m['competitor_id'], 'place' => (int) $m['place']]));

        $ids = $fought->keys()->map(fn ($k) => (int) explode(':', $k)[1])->merge($medals->pluck('registration_id'))->unique()->values();

        $entries = $ids->isEmpty() ? collect() : ClubEventRegistration::with(['user', 'representingTenant:id,club_name'])
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->where('role', 'participant')
            ->get()
            ->filter(fn ($r) => $r->user !== null)
            ->keyBy('id');

        $clubs = EntryClub::forMany($event, $entries->keys());
        $names = $decided->mapWithKeys(fn ($c) => [$c->id => (string) ($c->getRawOriginal('name') ?: $c->name)]);

        $row = function (ClubEventRegistration $r, int $categoryId, ?int $place = null) use ($names, $clubs) {
            $club = $r->competingClub()?->club_name ?: ($clubs[$r->id]['name'] ?? null);

            return [
                'registration_id' => $r->id,
                'category_id' => $categoryId,
                'division' => $names[$categoryId] ?? '',
                'user' => $r->user,
                'club' => $club ?: null,
                'place' => $place,
            ];
        };

        $participation = $entries
            ->filter(fn ($r) => $r->category_id && $decided->contains('id', $r->category_id)
                && ($fought->has($r->category_id.':'.$r->id) || $medals->contains('registration_id', $r->id)))
            ->map(fn ($r) => $row($r, (int) $r->category_id))
            ->values();

        $podium = $medals
            ->filter(fn ($m) => $entries->has($m['registration_id']))
            ->map(fn ($m) => $row($entries[$m['registration_id']], $m['category_id'], $m['place']))
            ->values();

        return [
            'participation' => $participation,
            'podium' => $podium,
            'officials' => $this->officialsPlan($event),
            'clubs' => $this->clubsPlan($event),
            'decided' => $decided->count(),
            'pending' => $divisions->count() - $decided->count(),
        ];
    }

    /**
     * The event's officials, one row per PERSON: their jobs joined ("Referee 1 ·
     * Scorekeeper") and the club they came with.
     *
     * @return Collection<int, array{user: User, role: string, club: ?string}>
     */
    public function officialsPlan(ClubEvent $event): Collection
    {
        $badges = app(EventBadges::class);
        $locale = $badges->locale($event);

        return \App\Events\Models\EventOfficial::where('event_id', $event->id)->with('user')->orderBy('id')->get()
            ->filter(fn ($o) => $o->user !== null)
            ->groupBy('user_id')
            ->map(fn ($jobs) => [
                'user' => $jobs->first()->user,
                'role' => $jobs->map(fn ($o) => $badges->roleLabel($o, $locale))->unique()->implode(' · '),
                'club' => $jobs->map(fn ($o) => $o->club()['name'] ?? null)->filter()->first(),
            ])->values();
    }

    /**
     * The clubs that took part, one row per CLUB: every club with at least one
     * athlete still entered, resolved the way the board resolves it (a club on
     * the platform first, then the club the organiser wrote down — EntryClub).
     * Unlike participation it waits for no result: a club took part the moment
     * its athletes were entered.
     *
     * @return Collection<int, array{club: Tenant|\App\Events\Models\EventClub, name: string, athletes: int}>
     */
    public function clubsPlan(ClubEvent $event): Collection
    {
        $regs = $event->participantRegistrations()
            ->where(fn ($q) => $q->whereNull('entry_state')->orWhereNotIn('entry_state', ['withdrawn', 'removed']))
            ->with(['user.memberClubs', 'representingTenant'])
            ->get();
        if ($regs->isEmpty()) {
            return collect();
        }

        $written = DB::table('event_club_entrants as l')
            ->join('event_clubs as c', 'c.id', '=', 'l.event_club_id')
            ->where('c.event_id', $event->id)
            ->whereIn('l.registration_id', $regs->pluck('id'))
            ->pluck('c.id', 'l.registration_id');
        $eventClubs = \App\Events\Models\EventClub::with('tenant')->whereIn('id', $written->unique()->values())->get()->keyBy('id');

        $clubs = [];
        foreach ($regs as $r) {
            $club = $r->competingClub();
            if (! $club && ($ec = $eventClubs[$written[$r->id] ?? 0] ?? null)) {
                // A written-down club that is really a platform club is that club.
                $club = $ec->tenant ?: $ec;
            }
            if (! $club || trim($club->certificateName()) === '') {
                continue;
            }
            // One club, one certificate: a typed-in copy of a platform club (same
            // name) is that club, as the results PDF counts it (BracketPdf::clubPages).
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim($club->certificateName())));
            $clubs[$key] ??= ['club' => $club, 'name' => $club->certificateName(), 'people' => []];
            if ($club instanceof Tenant && ! $clubs[$key]['club'] instanceof Tenant) {
                $clubs[$key]['club'] = $club;   // the platform club holds it
                $clubs[$key]['name'] = $club->certificateName();
            }
            // Athletes, not entries: one person in two divisions is one athlete.
            $clubs[$key]['people'][$r->user_id ? 'u'.$r->user_id : 'r'.$r->id] = true;
        }

        return collect($clubs)
            ->map(fn ($c) => ['club' => $c['club'], 'name' => $c['name'], 'athletes' => count($c['people'])])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Who holds one JOB at this event, for a print-shop file of that job's
     * certificates only (CertificateBundles — `official_thanks.<job>`). Null when
     * the type has no jobs or the job is not one this event has.
     *
     * @return list<int>|null user ids
     */
    public function bundleRecipients(Model $context, string $type, string $job): ?array
    {
        if ($type !== self::TYPE_OFFICIALS || ! $context instanceof ClubEvent) {
            return null;
        }
        $roles = app(OfficialRoles::class);
        if (! array_key_exists($job, $roles->jobs($context))) {
            return null;
        }

        return \App\Events\Models\EventOfficial::where('event_id', $context->id)->get()
            ->filter(fn ($o) => $roles->jobKey($context, $o) === $job)->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * The designs to try for one certificate before its type's general one, most
     * specific first: an official's thank-you prints from the design of their
     * job (CertificatePrintable — `official_thanks.<job>`), the first of their
     * jobs that has one.
     *
     * @return list<string>
     */
    public function designSlots(Certificate $certificate): array
    {
        $event = $certificate->context;
        if ($certificate->source !== self::SOURCE_OFFICIAL || ! $event instanceof ClubEvent || ! $certificate->recipient instanceof User) {
            return [];
        }
        $roles = app(OfficialRoles::class);

        return \App\Events\Models\EventOfficial::where('event_id', $event->id)->where('user_id', $certificate->recipient->id)->orderBy('id')->get()
            ->map(fn ($o) => self::TYPE_OFFICIALS.'.'.$roles->jobKey($event, $o))->unique()->values()->all();
    }

    /**
     * Has this club had its thank-you here already — under either of its two
     * records? A typed-in club later linked to the platform club (event_clubs.
     * tenant_id) is the same club: its certificate counts for both, so linking
     * never makes a second one.
     */
    private function clubHasCertificate(Model $club, ClubEvent $event, CertificateService $service): bool
    {
        if ($service->exists($club, self::SOURCE_CLUB, $event->id)) {
            return true;
        }
        $twins = $club instanceof Tenant
            ? \App\Events\Models\EventClub::where('event_id', $event->id)->where('tenant_id', $club->id)->get()
            : collect($club instanceof \App\Events\Models\EventClub && $club->tenant_id ? [Tenant::find($club->tenant_id)] : [])->filter();

        return $twins->contains(fn ($twin) => $service->exists($twin, self::SOURCE_CLUB, $event->id));
    }

    /**
     * The crest of the club a certificate is about, as a data: URI — the
     * designer's "Club logo" field (2026-10-20). The club itself for a club's
     * thank-you; the club an athlete competed for (a platform club first, then
     * the one the organiser wrote down — EntryClub's order); the club an
     * official came with. Null when there is none, or it has no crest.
     */
    public function clubLogo(Certificate $certificate): ?string
    {
        return $this->crest($this->clubOf($certificate));
    }

    /** The flag of the country the certificate's club is in — the designer's "Club's country flag". */
    public function clubFlag(Certificate $certificate): ?string
    {
        return self::flagOfClub($this->clubOf($certificate));
    }

    /** The holder's own nationality flag — the designer's "Nationality flag". None for a club. */
    public function nationalityFlag(Certificate $certificate): ?string
    {
        return $certificate->recipient instanceof User ? self::flagDataUri($certificate->recipient->nationality) : null;
    }

    /**
     * Everything this certificate draws that lives OUTSIDE its own row: the holder's
     * name, language, face and nationality, and the name, crest and country of the
     * club it is about. A printed copy made under a different stamp is stale — so a
     * corrected person or club is never handed back from an old file
     * (CertificateBundles, CertificateService::pdfContents).
     */
    public function pageStamp(Certificate $certificate): string
    {
        $holder = $certificate->recipient;
        $person = $holder instanceof User
            ? implode('|', [$holder->full_name, $holder->name, $holder->locale, $holder->nationality, $holder->profile_picture, (int) $holder->profile_picture_is_public])
            : ($holder && method_exists($holder, 'certificateStamp') ? $holder->certificateStamp() : '');
        $club = $this->clubOf($certificate);
        $clubStamp = $club && method_exists($club, 'certificateStamp') ? $club->certificateStamp() : '';
        if ($club instanceof \App\Events\Models\EventClub) {
            $clubStamp .= '|'.$club->tenant?->updated_at;
        }

        return sha1($person.'#'.($club ? $club->getMorphClass().':'.$club->getKey() : '').'@'.$clubStamp);
    }

    /** The club a certificate is about: the club itself, the club an official came with, or the one an athlete competed for. */
    public function clubOf(Certificate $certificate): ?Model
    {
        $event = $certificate->context;
        if (! $event instanceof ClubEvent) {
            return null;
        }
        if ($certificate->source === self::SOURCE_CLUB) {
            return $certificate->recipient;
        }
        if ($certificate->source === self::SOURCE_OFFICIAL) {
            $o = \App\Events\Models\EventOfficial::where('event_id', $event->id)
                ->where('user_id', $certificate->recipient instanceof User ? $certificate->recipient->id : 0)
                ->where(fn ($q) => $q->whereNotNull('representing_tenant_id')->orWhereNotNull('event_club_id'))
                ->orderBy('id')->first();

            return $o ? $this->officialClub($o) : null;
        }
        $regId = (int) (((array) ($certificate->details ?? []))['registration_id'] ?? $certificate->source_id ?? 0);
        $reg = $regId ? ClubEventRegistration::where('event_id', $event->id)->with(['user.memberClubs', 'representingTenant'])->find($regId) : null;

        return $reg ? $this->entryClub($event, $reg) : null;
    }

    /** The crest of the club an official came with, as a data: URI (badges too). */
    public function officialCrest(\App\Events\Models\EventOfficial $o): ?string
    {
        return $this->crest($this->officialClub($o));
    }

    public function officialClub(\App\Events\Models\EventOfficial $o): ?Model
    {
        return $o->representing_tenant_id ? Tenant::find($o->representing_tenant_id) : ($o->event_club_id ? \App\Events\Models\EventClub::find($o->event_club_id) : null);
    }

    /** The crest of the club an entry competes for — a platform club first, then the one written down (badges too). */
    public function entryCrest(ClubEvent $event, ClubEventRegistration $reg): ?string
    {
        return $this->crest($this->entryClub($event, $reg));
    }

    public function entryClub(ClubEvent $event, ClubEventRegistration $reg): ?Model
    {
        $club = $reg->competingClub();
        if (! $club) {
            // Linked on this entry, else on the same person's other entries (EntryClub::byPerson).
            $ids = DB::table('event_club_entrants as l')->join('event_clubs as c', 'c.id', '=', 'l.event_club_id')
                ->join('club_event_registrations as r', 'r.id', '=', 'l.registration_id')
                ->where('c.event_id', $event->id)
                ->where(fn ($q) => $q->where('r.id', $reg->id)->when($reg->user_id, fn ($q) => $q->orWhere('r.user_id', $reg->user_id)))
                ->distinct()->pluck('c.id');
            $club = $ids->count() === 1 ? \App\Events\Models\EventClub::find($ids->first()) : null;
        }

        return $club;
    }

    /** A club's country flag: a platform club's own country, a written-down club's (or the platform club it is). */
    public static function flagOfClub(?Model $club): ?string
    {
        $code = $club instanceof Tenant ? $club->country
            : ($club instanceof \App\Events\Models\EventClub ? ($club->tenant?->country ?: $club->country) : null);

        return self::flagDataUri($code);
    }

    /** A country's flag from the local set (public/images/flags), as a data: URI for print. Never a remote fetch. */
    public static function flagDataUri(?string $country): ?string
    {
        $iso = \App\Support\Flag::iso2($country);
        $file = $iso ? public_path('images/flags/'.$iso.'.svg') : null;

        return $file && is_file($file) ? 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents($file)) : null;
    }

    private function crest(?Model $club): ?string
    {
        return $club instanceof \Ahl\Certificates\Contracts\Recipient && ! $club instanceof User ? $club->certificatePhoto() : null;
    }

    /**
     * What a certificate already given says about its holder follows the event as it
     * stands now — their club, their division, their job, their photo — so correcting an entry and
     * issuing again prints it corrected. A revoked one is left as it was, and the PLACE
     * on a podium certificate is a result, not a detail: never touched here.
     */
    private function refreshIssued(ClubEvent $event, array $plan, array $settings): void
    {
        $want = [];
        foreach (['participation' => self::SOURCE_ENTRY, 'podium' => self::SOURCE_PODIUM] as $type => $source) {
            foreach ($settings[$type] ? $plan[$type] : [] as $row) {
                $want[$source][$row['registration_id']] = ['achievement' => $row['division'], 'division' => $row['division'], 'club' => $row['club']];
            }
        }
        foreach ($settings['officials'] ? $plan['officials'] : [] as $row) {
            $want[self::SOURCE_OFFICIAL][$row['user']->id] = ['achievement' => $row['role'], 'role' => $row['role'], 'club' => $row['club']];
        }
        if ($want === []) {
            return;
        }

        Engine::model()::query()->forContext($event)
            ->whereIn('source', array_keys($want))
            ->where('status', '!=', 'revoked')
            ->get()
            ->each(function (Certificate $c) use ($want) {
                $key = $c->source === self::SOURCE_OFFICIAL ? $c->recipient_id : $c->source_id;
                $now = $want[$c->source][$key] ?? null;
                if (! $now) {
                    return;
                }
                $details = (array) $c->details;
                $achievement = $now['achievement'] ?: $c->achievement;
                unset($now['achievement']);
                foreach ($now as $field => $value) {
                    if ($value === null || $value === '') {
                        unset($details[$field]);
                    } else {
                        $details[$field] = $value;
                    }
                }
                if ($details !== (array) $c->details || $achievement !== $c->achievement) {
                    $c->forceFill(['details' => $details, 'achievement' => $achievement])->save();
                }
                \App\Certificates\Support\CertificateSnapshot::refreshPhoto($c);
            });
    }

    /** A podium draft follows its division's result until it is approved. */
    private function reconcilePodiumDrafts(ClubEvent $event, Collection $podium, CertificateService $service): void
    {
        $current = $podium->keyBy('registration_id');

        Engine::model()::query()->forContext($event)
            ->where('source', self::SOURCE_PODIUM)
            ->where('status', 'draft')
            ->get()
            ->each(function (Certificate $c) use ($current, $service) {
                $now = $current[$c->source_id] ?? null;
                if (! $now) {
                    $service->delete($c);   // no longer on the podium: the draft goes (files first)

                    return;
                }
                $details = (array) $c->details;
                if ((int) ($details['place'] ?? 0) !== (int) $now['place']) {
                    $details['place'] = $now['place'];
                    $c->forceFill(['details' => $details])->save();
                }
            });
    }

    /* ─────────────────────────── branding ─────────────────────────── */

    private function readBrand(ClubEvent $event): array
    {
        $host = EventHost::of($event);
        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $event->color) ? strtolower($event->color)
            : (preg_match('/^#[0-9a-fA-F]{6}$/', (string) $host->color) ? strtolower($host->color) : '#6d5bd0');

        $logoPath = null;
        if ($host->club) {
            $logoPath = $host->club->logo;
        } elseif ($event->host_type === 'federation' && $event->host_id) {
            $f = Federation::find($event->host_id);
            $logoPath = $f && $f->isApproved() ? $f->logo : null;
        }
        $logo = $this->logoPng($logoPath);

        return [
            'accent' => $accent,
            'watermark' => $logo,
            'logos' => $logo ? ['data:image/png;base64,'.base64_encode($logo)] : [],
            'seal' => $this->initials($this->headline($event)),
        ];
    }

    /** The host's logo as a small transparent PNG, or null (never a remote fetch). */
    private function logoPng(?string $path): ?string
    {
        $path = preg_replace('#^(storage/|public/)#', '', ltrim((string) $path, '/'));
        if ($path === '' || str_contains($path, '://') || str_contains($path, '..') || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $bytes = (string) Storage::disk('public')->get($path);
        $im = @imagecreatefromstring($bytes);
        if (! $im instanceof \GdImage) {
            return null;
        }
        [$w, $h] = [imagesx($im), imagesy($im)];
        $scale = min(1, 400 / max($w, $h));
        [$tw, $th] = [max(1, (int) ($w * $scale)), max(1, (int) ($h * $scale))];
        $out = imagecreatetruecolor($tw, $th);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
        ob_start();
        imagepng($out);
        $png = (string) ob_get_clean();
        imagedestroy($im);
        imagedestroy($out);

        return $png;
    }

    /** "Bahrain Open Karate Championship 2026" → "BOKC". Latin titles only; otherwise no seal word. */
    private function initials(string $title): ?string
    {
        $words = preg_split('/\s+/u', trim($title)) ?: [];
        $letters = collect($words)
            ->filter(fn ($w) => preg_match('/^[A-Za-z]/', $w) && ! in_array(strtolower($w), ['of', 'the', 'and', 'for', 'in', 'at'], true))
            ->map(fn ($w) => strtoupper($w[0]))
            ->take(5)
            ->implode('');

        return strlen($letters) >= 2 ? $letters : null;
    }

    /**
     * What the public certificate check (App\Certificates\Controllers\VerifyController)
     * shows beyond the paper itself: the holder's face and club, the event, the bouts
     * they fought, and a way to the division's draw.
     *
     * Every piece obeys the rule its own surface already obeys — nothing is shown here
     * that its usual home would not show the same stranger:
     *   face      CompetitorFace — only a picture the member chose to make public
     *   club      EntryClub — club branding is public by intent
     *   bouts     only when the event is public AND its draw is visible to this viewer
     *             (PublicEvent::isPublic + EventAccess::drawVisible), as on the draw page
     *   draw link the public read-only board, under the same two conditions
     *   videos    the bout page's own link; the footage stays behind its login and
     *             its own permission check (BoutVideoController::mayWatch)
     *
     * @return array<string, mixed>
     */
    public function verifyStory(Certificate $certificate, ?User $viewer, string $locale): array
    {
        $event = $certificate->context;
        if (! $event instanceof ClubEvent) {
            return [];
        }
        $details = (array) ($certificate->details ?? []);
        $regId = (int) ($details['registration_id'] ?? (in_array($certificate->source, [self::SOURCE_ENTRY, self::SOURCE_PODIUM], true) ? $certificate->source_id : 0));
        $reg = $regId ? ClubEventRegistration::query()->where('event_id', $event->id)->with(['user.memberClubs', 'category', 'representingTenant'])->find($regId) : null;
        $host = EventHost::of($event);
        $holder = $certificate->recipient instanceof User ? $certificate->recipient : $reg?->user;
        $clubUrl = fn (?Tenant $t) => $t && $t->slug && $t->country_code ? \App\Http\Controllers\QrController::clubPageUrl($t) : null;

        // Weight and height are private on the platform: the person themselves and the
        // event's staff only — the rule PublicEventController::readinessFor keeps.
        $access = app(EventAccess::class);
        $mayBody = $viewer !== null && (($holder && $viewer->is($holder)) || $access->canManage($event, $viewer) || $access->canOfficiate($event, $viewer));

        // Age on the day of the competition (public, as on the entry cards).
        $on = $event->date ? Carbon::parse($event->getRawOriginal('date') ?? $event->date) : ($certificate->issued_on ?? now());
        $age = $holder?->birthdate ? Carbon::parse($holder->birthdate)->diffInYears($on) : ($reg?->declared_age ?: null);

        // Once issued, the certificate shows who the holder WAS (CertificateSnapshot), never today's profile.
        $snap = \App\Certificates\Support\CertificateSnapshot::of($certificate);
        if ($snap) {
            $age = $snap['age'] ?? $age;
        }

        $story = [
            'photo' => $snap ? \App\Certificates\Support\CertificateSnapshot::photoUrl($certificate) : ($reg ? CompetitorFace::for($reg) : null),
            'profile_url' => $holder?->uuid ? route('people.show', $holder->uuid) : null,
            'age' => $age !== null ? (int) $age : null,
            'weight' => $mayBody && (($w = $snap ? ($snap['weight'] ?? null) : $reg?->weight) !== null) ? rtrim(rtrim(number_format((float) $w, 1), '0'), '.') : null,
            'weighed' => $mayBody && ($snap ? ! empty($snap['weighed']) : $reg?->weighed_in_at !== null),
            'height' => $mayBody && ($h = $snap ? ($snap['height'] ?? null) : $holder?->height_cm) ? (int) $h : null,
            'club' => $reg ? (EntryClub::for($reg) ?? []) + ['url' => $clubUrl($reg->competingClub())] : null,
            'division' => $reg?->category?->name ?: ($details['division'] ?? null),
            'role' => $details['role'] ?? null,
            'event' => [
                'title' => $this->headline($event),
                'dates' => $this->dates($event, $locale),
                'venue' => (string) ($event->getRawOriginal('location') ?? ''),
                'host' => $host->name,
                'host_logo' => $host->logo,
                'host_url' => $clubUrl($host->club),
                'entrants' => $reg?->category ? $reg->category->registrations()->where('role', 'participant')->count() : null,
                'url' => route('me.events.show', $event->uuid),
                'results_url' => route('me.events.results.page', $event->uuid),
                'entrants_url' => route('me.events.participants', $event->uuid),
                'map_url' => \App\Support\MapLink::isGoogle((string) $event->location_url) ? $event->location_url
                    : ($event->gps_lat && $event->gps_long ? 'https://www.google.com/maps/search/?api=1&query='.((float) $event->gps_lat).','.((float) $event->gps_long)
                    : ($event->getRawOriginal('location') ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) $event->getRawOriginal('location')) : null)),
            ],
            'bouts' => [],
            'draw_url' => null,
        ];

        // A club's thank-you: the club is the holder — its name, crest and page.
        if ($certificate->source === self::SOURCE_CLUB) {
            $club = $certificate->recipient;
            $tenant = $club instanceof Tenant ? $club : ($club instanceof \App\Events\Models\EventClub ? $club->tenant : null);
            $logo = $tenant?->logo ?: ($club instanceof \App\Events\Models\EventClub ? $club->logo : null);
            $story['club'] = $club ? [
                'name' => $club->certificateName(), 'logo' => $logo ? file_url($logo) : null,
                'country' => $tenant?->country ?: ($club->country ?? null), 'url' => $clubUrl($tenant),
            ] : null;
            $story['team'] = $club ? $this->teamOf($event, $club->certificateName(), $viewer, $locale) : null;

            return $story;
        }

        // A team official's thank-you (team manager, coach…): the team they came with.
        if ($certificate->source === self::SOURCE_OFFICIAL && $holder instanceof User) {
            $roles = app(OfficialRoles::class);
            $teamJob = \App\Events\Models\EventOfficial::where('event_id', $event->id)->where('user_id', $holder->id)->orderBy('id')->get()
                ->first(fn ($o) => ($o->club()['name'] ?? null) && $roles->sectionOf($event, (string) $o->role, $o->role_label) === 'team');
            if ($teamJob) {
                $story['team'] = $this->teamOf($event, (string) $teamJob->club()['name'], $viewer, $locale);
                $story['team_managed'] = true;
            }
        }

        $public = app(PublicEvent::class)->isPublic($event) && app(EventAccess::class)->drawVisible($event, $viewer);
        if ($public && $reg && $reg->user) {
            $history = app(\App\Members\Support\BoutHistory::class)->forEntry($event, $reg, $viewer);
            // Every pairing the draw holds — a bout never fought (a final not held) is listed as undecided, not hidden.
            $bouts = collect($history['bouts'] ?? [])->filter(fn ($b) => $b['bye'] || ! empty($b['opponent_entry']))->values();
            $opponentIds = $bouts->pluck('opponent_entry')->filter()->unique()->all();
            $clubs = EntryClub::forMany($event, $opponentIds);
            $opponents = ClubEventRegistration::query()->whereIn('id', $opponentIds)->with(['user.memberClubs', 'representingTenant'])->get()->keyBy('id');
            $story['bouts'] = $bouts->map(fn ($b) => [
                'opponent' => $b['bye'] ? null : $b['opponent'],
                'opponent_photo' => $b['opponent_photo'],
                'opponent_url' => ($o = $opponents[$b['opponent_entry']] ?? null)?->user?->uuid ? route('people.show', $o->user->uuid) : null,
                'opponent_club' => $b['opponent_entry'] ? (($clubs[$b['opponent_entry']] ?? []) + ['url' => $clubUrl(($opponents[$b['opponent_entry']] ?? null)?->competingClub())]) : null,
                'bout_url' => ! $b['bye'] && $b['match_no'] !== null ? route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $b['match_no']]) : null,
                'bye' => (bool) $b['bye'],
                'won' => (bool) $b['won'],
                'decided' => (bool) $b['decided'],
                'score' => $b['bye'] ? null : trim(($b['my_score'] ?? '').' – '.($b['their_score'] ?? ''), ' –'),
                'round' => $b['round'],
                'video_url' => $b['video_url'],
            ])->all();
            if ($reg->category_id) {
                $story['draw_url'] = route('me.events.bracket.manage', ['event' => $event->uuid, 'division' => $reg->category_id]);
            }
        }

        return $story;
    }

    /**
     * The athletes one club brought, for the certificate check page (owner,
     * 2026-10-20): one row per PERSON — best medal, face, flag, name, age, and
     * each division with a way to its bracket. A club is matched by its name,
     * the same rule its certificate is issued by (clubsPlan), so a typed-in copy
     * and the platform club read as one team.
     *
     * Every piece obeys the rule its own surface already obeys:
     *   the list   only when the event is public, or for the event's staff
     *   faces      CompetitorFace — a picture the member made public
     *   weight     the event's staff only (the owner's choice — option 1);
     *              athletes here are often children, and this page is public
     *   brackets   linked only where the draw is visible to this viewer
     *
     * @return array{athletes: list<array>, medals: array<int, int>, staff: bool}|null
     */
    public function teamOf(ClubEvent $event, string $clubName, ?User $viewer, string $locale): ?array
    {
        $access = app(EventAccess::class);
        $staff = $viewer !== null && ($access->canManage($event, $viewer) || $access->canOfficiate($event, $viewer));
        if (! $staff && ! app(PublicEvent::class)->isPublic($event)) {
            return null;
        }
        $drawOpen = $staff || $access->drawVisible($event, $viewer);
        $norm = fn (?string $n) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $n)));
        $want = $norm($clubName);

        $regs = $event->participantRegistrations()->with(['user.memberClubs', 'category', 'representingTenant'])->orderBy('id')->get();
        $written = DB::table('event_club_entrants as l')->join('event_clubs as c', 'c.id', '=', 'l.event_club_id')
            ->where('c.event_id', $event->id)->whereIn('l.registration_id', $regs->pluck('id'))
            ->pluck('c.id', 'l.registration_id');
        $eventClubs = \App\Events\Models\EventClub::with('tenant')->whereIn('id', $written->unique()->values())->get()->keyBy('id');
        $mine = $regs->filter(function ($r) use ($written, $eventClubs, $norm, $want) {
            $club = $r->competingClub();
            if (! $club && ($ec = $eventClubs[$written[$r->id] ?? 0] ?? null)) {
                $club = $ec->tenant ?: $ec;
            }

            return $club && $norm($club->certificateName()) === $want;
        });
        if ($mine->isEmpty()) {
            return null;
        }

        // Places won, per entry, from the podiums the event stored.
        $placed = [];
        foreach (EventCategory::where('event_id', $event->id)->whereIn('id', $mine->pluck('category_id')->filter()->unique())->get(['id', 'podium']) as $c) {
            foreach ((array) $c->podium as $m) {
                $place = (int) ($m['place'] ?? 0);
                if (! empty($m['competitor_id']) && $place >= 1 && $place <= 3) {
                    $placed[(int) $m['competitor_id']] = min($placed[(int) $m['competitor_id']] ?? 9, $place);
                }
            }
        }
        $on = $event->date ? Carbon::parse($event->getRawOriginal('date') ?? $event->date) : now();

        $athletes = $mine->groupBy(fn ($r) => $r->user_id ? 'u'.$r->user_id : 'r'.$r->id)->map(function ($rows) use ($placed, $on, $staff, $drawOpen, $event) {
            $first = $rows->first();
            $user = $first->user;
            $face = $rows->map(fn ($r) => CompetitorFace::for($r))->filter()->first();
            $iso = $first->countryCode();
            $best = $rows->map(fn ($r) => $placed[$r->id] ?? null)->filter()->min();
            $weighed = $rows->first(fn ($r) => $r->weighed_in_at !== null) ?? $rows->first(fn ($r) => $r->weight !== null);

            return [
                'name' => (string) ($user?->full_name ?: $user?->name ?: ''),
                'url' => $user?->uuid ? route('people.show', $user->uuid) : null,
                'photo' => $face,
                'gender' => $user?->gender === 'Female' ? 'female' : 'male',
                'flag' => $iso ? \App\Support\Flag::url($iso) : null,
                'country' => $iso ? (\App\Support\Countries::name($iso) ?: strtoupper($iso)) : null,
                'age' => $user?->birthdate ? (int) Carbon::parse($user->birthdate)->diffInYears($on) : ($first->declared_age ?: null),
                // The event's staff only (see above).
                'weight' => $staff && $weighed && $weighed->weight !== null ? rtrim(rtrim(number_format((float) $weighed->weight, 1), '0'), '.') : null,
                'weighed' => $staff && $weighed && $weighed->weighed_in_at !== null,
                'place' => $best,
                'divisions' => $rows->filter(fn ($r) => $r->category)->unique('category_id')->map(fn ($r) => [
                    'name' => (string) ($r->category->getRawOriginal('name') ?: $r->category->name),
                    'url' => $drawOpen ? route('me.events.bracket.manage', ['event' => $event->uuid, 'division' => $r->category_id]) : null,
                    'place' => $placed[$r->id] ?? null,
                ])->values()->all(),
            ];
        })->sortBy(fn ($a) => [$a['place'] ?? 9, mb_strtolower($a['name'])])->values();

        return [
            'athletes' => $athletes->all(),
            // Medals won, not medallists: two golds in two divisions are two.
            'medals' => collect([1, 2, 3])->mapWithKeys(fn ($p) => [$p => $athletes->sum(fn ($a) => collect($a['divisions'])->where('place', $p)->count())])->all(),
            'staff' => $staff,
        ];
    }

    private function dates(ClubEvent $event, string $locale): string
    {
        if (! $event->date) {
            return '';
        }
        $start = Carbon::parse($event->getRawOriginal('date') ?? $event->date)->locale($locale);
        $end = $event->end_date ? Carbon::parse($event->getRawOriginal('end_date') ?? $event->end_date)->locale($locale) : null;

        if (! $end || $end->isSameDay($start)) {
            return $start->translatedFormat('j F Y');
        }
        if ($end->isSameMonth($start)) {
            return $start->translatedFormat('j').'–'.$end->translatedFormat('j F Y');
        }

        return $start->translatedFormat('j F').' – '.$end->translatedFormat('j F Y');
    }
}
