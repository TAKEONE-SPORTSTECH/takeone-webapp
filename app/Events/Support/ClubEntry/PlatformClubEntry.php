<?php

namespace App\Events\Support\ClubEntry;

use App\Clubs\Models\Tenant;
use App\Events\Mail\ClubEntryCode;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventClub;
use App\Events\Models\EventEntryInvoice;
use App\Events\Models\EventPublicEntry;
use App\Events\Support\CategorySystems;
use App\Events\Support\EntryChannels;
use App\Events\Support\EntryService;
use App\Events\Support\EntryWindow;
use App\Events\Support\EventFee;
use App\Events\Support\PublicEntry;
use App\Events\Support\UnclaimedPerson;
use App\Members\Models\User;
use App\Members\Support\ProfilePhoto;
use App\Support\Countries;
use App\Support\StoragePath;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * "Register your club" for a CLUB-hosted event — the back office behind the
 * shared screens (owner, 2026-10-04: club and federation events identical).
 *
 * Speaks the same JSON the federation's invitation doors speak, so the one
 * browser-side store (club-entry-store.js) and the one set of screens drive it.
 *
 * WHO a club is lives in the reader's SESSION only (`clubentry.{event}`):
 *   · a TakeOne club (`tn-…`) — proved by a six-digit code sent to an address
 *     already on its record, or by its signed-in owner/admin;
 *   · a club not on TakeOne (`ec-{uuid}`) — written down for this event the way
 *     the organiser's console writes one down (EventClub), when it registers.
 * Browser keys for TakeOne clubs are opaque (an HMAC per event), resolved only
 * through the map the search filled in this session — a tenant id is never
 * accepted from the request.
 *
 * Entries go in through the same request → accept path federation entries use
 * (PublicEntry::accept, as the organiser), so classification, divisions, fees
 * and capacity are the platform's own. Because that path is the ORGANISER's,
 * every rule that protects the competition is checked HERE first: the club
 * channel, the entry window, an event underway, the event's scope, the reader's
 * acceptance of the rules.
 */
final class PlatformClubEntry
{
    use StoresBase64Images;

    private const CODE_TTL = 600;

    private const CODE_ATTEMPTS = 5;

    private const MAX_ATHLETES = 100;

    public function __construct(private ClubEvent $event) {}

    // ── who the reader acts for ─────────────────────────────────────────────

    private function sessionKey(): string
    {
        return 'clubentry.'.$this->event->uuid;
    }

    private function mapKey(): string
    {
        return 'clubentry.map.'.$this->event->uuid;
    }

    /** The opaque browser key of a TakeOne club, for THIS event. */
    public function tenantKey(Tenant $t): string
    {
        return 'tn-'.substr(hash_hmac('sha256', $this->event->uuid.'|'.$t->id, (string) config('app.key')), 0, 20);
    }

    /** Remember which tenant a key the browser was handed stands for. */
    private function remember(Tenant $t): string
    {
        $key = $this->tenantKey($t);
        $map = (array) Session::get($this->mapKey(), []);
        $map[$key] = $t->id;
        Session::put($this->mapKey(), array_slice($map, -40, null, true));

        return $key;
    }

    private function tenantFromKey(?string $key): ?Tenant
    {
        $id = $key ? (((array) Session::get($this->mapKey(), []))[$key] ?? null) : null;
        $t = $id ? Tenant::find($id) : null;

        return $t && hash_equals($this->tenantKey($t), (string) $key) ? $t : null;
    }

    private function bind(string $key): void
    {
        Session::migrate(true);   // a new identity gets a new session id
        Session::put($this->sessionKey(), $key);
    }

    /** The club this reader has proved: a Tenant, an EventClub of this event, or null. */
    public function sessionClub(): Tenant|EventClub|null
    {
        $key = (string) Session::get($this->sessionKey(), '');
        if (str_starts_with($key, 'tn-')) {
            return $this->tenantFromKey($key);
        }
        if (str_starts_with($key, 'ec-')) {
            return EventClub::where('event_id', $this->event->id)->where('uuid', substr($key, 3))->first();
        }

        return null;
    }

    public function keyOf(Tenant|EventClub $c): string
    {
        return $c instanceof Tenant ? $this->tenantKey($c) : 'ec-'.$c->uuid;
    }

    /** A signed-in owner/admin of exactly one club acts for it with no code. */
    public function bindSignedIn(?User $user): void
    {
        if (! $user || $this->sessionClub()) {
            return;
        }
        $ids = app(EntryService::class)->administeredClubIds($user);
        if (count($ids) === 1 && ($t = Tenant::find($ids[0]))) {
            $this->bind($this->remember($t));
        }
    }

    // ── finding a club ──────────────────────────────────────────────────────

    /** @return list<array{key: string, name: string, logo: ?string, hint: string}> */
    public function searchClubs(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return Tenant::query()
            ->where('club_name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%')
            ->where(fn ($w) => $w->whereNull('status')->orWhere('status', 'active'))
            ->orderBy('club_name')->limit(8)->get()
            ->map(fn (Tenant $t) => [
                'key' => $this->remember($t),
                'name' => (string) $t->club_name,
                'logo' => $t->logo ? file_url($t->logo) : null,
                'hint' => $this->maskEmail($this->emailsOf($t)[0] ?? null),
            ])->values()->all();
    }

    /** A "new" club that is already on TakeOne: {reason, key, name, logo, hint} or null. */
    public function checkClub(string $name, string $email, string $phone): ?array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $email = strtolower(trim($email));
        $match = null;
        $reason = null;
        if ($name !== '') {
            $match = Tenant::whereRaw('lower(club_name) = ?', [mb_strtolower($name)])->first();
            $reason = $match ? 'name' : null;
        }
        if (! $match && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $match = $this->tenantsByEmail($email)->first();
            $reason = $match ? 'email' : null;
        }
        if (! $match) {
            return null;
        }

        return ['reason' => $reason, 'key' => $this->remember($match), 'name' => (string) $match->club_name,
            'logo' => $match->logo ? file_url($match->logo) : null, 'hint' => $this->maskEmail($this->emailsOf($match)[0] ?? null)];
    }

    // ── proving a club ──────────────────────────────────────────────────────

    /** A code to the club's own address, for a club picked from the search. Silent on anything else. */
    public function sendCodeToClub(string $key): void
    {
        $t = $this->tenantFromKey($key);
        $to = $t ? ($this->emailsOf($t)[0] ?? null) : null;
        if (! $t || ! $to || ! $this->underBurst($key)) {
            return;
        }
        $this->issue('club:'.$key, [$key], $to, (string) $t->club_name);
    }

    /** A code for whatever club(s) the typed email or phone belongs to. Silent when none. */
    public function sendCode(string $contact): void
    {
        $tenants = $this->tenantsByContact($contact);
        if ($tenants->isEmpty()) {
            return;
        }
        $norm = $this->normalise($contact);
        $to = str_contains($norm, '@') ? $norm : ($this->emailsOf($tenants->first())[0] ?? null);
        if (! $to) {
            return;
        }
        $tenants = $tenants->filter(fn (Tenant $t) => in_array($to, $this->emailsOf($t), true))->values();
        $keys = $tenants->map(fn (Tenant $t) => $this->remember($t))->all();
        if ($keys === [] || ! $this->underBurst($keys[0])) {
            return;
        }
        $this->issue('contact:'.$norm, $keys, $to, count($keys) === 1 ? (string) $tenants[0]->club_name : count($keys).' clubs');
    }

    /** @return array{ok: bool, choices?: list<array{key: string, name: string, logo: ?string}>} */
    public function verify(string $subject, string $code): array
    {
        $cacheKey = $this->codeKey($subject);
        $row = Cache::get($cacheKey);
        if (! is_array($row)) {
            return ['ok' => false];
        }
        if (($row['attempts'] ?? 0) >= self::CODE_ATTEMPTS) {
            Cache::forget($cacheKey);

            return ['ok' => false];
        }
        if (! hash_equals((string) $row['code'], hash('sha256', preg_replace('/\D/', '', $code).config('app.key')))) {
            $row['attempts'] = ($row['attempts'] ?? 0) + 1;
            Cache::put($cacheKey, $row, self::CODE_TTL);
            Log::info('club-entry: wrong code', ['event' => $this->event->uuid]);

            return ['ok' => false];
        }
        Cache::forget($cacheKey);

        $tenants = collect($row['keys'] ?? [])->map(fn ($k) => $this->tenantFromKey($k))->filter()->values();
        if ($tenants->isEmpty()) {
            return ['ok' => false];
        }
        if ($tenants->count() === 1) {
            $this->bind($this->tenantKey($tenants[0]));

            return ['ok' => true];
        }
        Session::put($this->sessionKey().'.choices', ['keys' => $tenants->map(fn ($t) => $this->tenantKey($t))->all(), 'until' => now()->addMinutes(10)->timestamp]);

        return ['ok' => true, 'choices' => $tenants->map(fn (Tenant $t) => ['key' => $this->tenantKey($t), 'name' => (string) $t->club_name, 'logo' => $t->logo ? file_url($t->logo) : null])->all()];
    }

    public function choose(string $key): bool
    {
        $held = Session::pull($this->sessionKey().'.choices');
        if (! is_array($held) || ($held['until'] ?? 0) < now()->timestamp || ! in_array($key, (array) ($held['keys'] ?? []), true) || ! $this->tenantFromKey($key)) {
            return false;
        }
        $this->bind($key);

        return true;
    }

    // ── what the screens read ───────────────────────────────────────────────

    public function store(): array
    {
        $club = $this->sessionClub();
        $host = $this->event->host();
        $out = [
            'event' => $this->eventRow(),
            'phase' => $this->phase(),
            'docs' => [],
            'federation' => ['name' => $host->name, 'country' => strtolower((string) (Countries::iso2((string) $host->country) ?: 'bh')), 'bank' => $this->bank()],
            'verifiedClub' => null,
            'clubs' => [],
            'athletes' => [],
            'entries' => (object) [],
            'transfers' => [],
        ];
        if (! $club) {
            return $out;
        }

        $key = $this->keyOf($club);
        $out['verifiedClub'] = $key;
        $out['clubs'] = [$this->clubRow($club)];
        $out['athletes'] = $this->roster($club)->map(fn (User $u) => $this->athleteRow($u, $key))->values()->all();
        $entry = $this->entryRow($club);
        if ($entry) {
            $out['entries'] = [$key => $entry];
        }

        return $out;
    }

    public function eventRow(): array
    {
        $ev = $this->event;
        $zone = $ev->zone();
        $deadline = $ev->enrollment_ends_at ? \Carbon\Carbon::parse($ev->enrollment_ends_at, $zone)->format('Y-m-d').'T23:59'
            : ($ev->date ? \Carbon\Carbon::parse($ev->date)->format('Y-m-d').'T00:00' : '2099-12-31T23:59');
        $drawAt = $ev->date ? \Carbon\Carbon::parse($ev->date)->format('Y-m-d').'T00:00' : '2099-12-31T23:59';
        $options = EventFee::options($ev);
        $fees = $options->isNotEmpty()
            ? $options->map(fn ($o) => ['type' => (string) $o->label, 'key' => (string) $o->uuid, 'price' => (float) $o->amount, 'unit' => 'athlete'])->values()->all()
            // No options: one plain "Entry" for the screens to tick; it carries no price of its own.
            : [['type' => __('events::events.fee_line_entry'), 'key' => null, 'price' => 0.0, 'unit' => 'athlete']];

        return [
            'id' => $ev->uuid,
            'code' => '',
            'name' => (string) $ev->title,
            'sport' => (string) ($ev->sport ? \Illuminate\Support\Str::headline($ev->sport) : ''),
            'currency' => EventFee::currency($ev),
            'deadline' => $deadline,
            'drawAt' => $drawAt,
            'base' => (float) (EventFee::amount($ev) ?? 0),
            'lateFee' => EventFee::lateFeeApplies($ev) ? (float) ($ev->late_fee_amount ?? 0) : 0.0,
            'fees' => $fees,
        ];
    }

    /** open | late | locked */
    public function phase(): string
    {
        if (! $this->acceptsEntries()) {
            return 'locked';
        }

        return EventFee::lateFeeApplies($this->event) ? 'late' : 'open';
    }

    /** May clubs enter right now — channel, window, not underway. The server's own answer. */
    public function acceptsEntries(): bool
    {
        $ev = $this->event;

        return app(EntryChannels::class)->allows($ev, EntryChannels::CLUB)
            && ! EntryWindow::hasNotOpened($ev) && ! EntryWindow::hasClosed($ev)
            && ! $ev->hasStarted() && ! $ev->isOverdueToStart() && ! $ev->hasEnded();
    }

    private function clubRow(Tenant|EventClub $c): array
    {
        if ($c instanceof Tenant) {
            $phone = $c->contactPhone() ?? [];

            return [
                'id' => $this->tenantKey($c), 'name' => (string) $c->club_name,
                'coach' => (string) ($c->owner_name ?: optional($c->owner)->full_name),
                'email' => (string) ($this->emailsOf($c)[0] ?? ''), 'phone' => trim(($phone['code'] ?? '').' '.($phone['number'] ?? '')),
                'address' => (string) $c->address, 'logo' => $c->logo ? file_url($c->logo) : null, 'logoShape' => 'circle',
                'status' => 'Approved', 'staff' => null,
            ];
        }

        return [
            'id' => 'ec-'.$c->uuid, 'name' => (string) $c->name, 'coach' => '', 'email' => '', 'phone' => '', 'address' => '',
            'logo' => $c->logo ? file_url($c->logo) : null, 'logoShape' => 'circle', 'status' => 'Pending', 'staff' => null,
        ];
    }

    /** The people the club may enter: a TakeOne club's active members; a written-down club's own entrants. */
    private function roster(Tenant|EventClub $c)
    {
        if ($c instanceof Tenant) {
            $ids = DB::table('memberships')->where('tenant_id', $c->id)->where('status', 'active')->pluck('user_id');

            return User::whereIn('id', $ids)->orderBy('full_name')->limit(400)->get();
        }

        return User::whereIn('id', $this->entrantUserIds($c))->get();
    }

    private function athleteRow(User $u, string $clubKey): array
    {
        return [
            'id' => 'u-'.$u->uuid, 'name' => (string) ($u->full_name ?: $u->name ?: '—'), 'clubId' => $clubKey,
            'natId' => '', 'gender' => $u->gender === 'Female' ? 'Female' : 'Male',
            'dob' => $u->birthdate ? \Carbon\Carbon::parse($u->birthdate)->format('Y-m-d') : '',
            'nat' => strtoupper((string) ($u->nationality ?: '')), 'photo' => ProfilePhoto::path($u) ? ProfilePhoto::url($u) : null,
        ];
    }

    /** This club's registrations in the event (participants). */
    private function clubRegistrations(Tenant|EventClub $c)
    {
        $q = ClubEventRegistration::with('user')->where('event_id', $this->event->id)->where('role', 'participant');

        return $c instanceof Tenant
            ? $q->where('representing_tenant_id', $c->id)->get()
            : $q->whereIn('id', DB::table('event_club_entrants')->where('event_club_id', $c->id)->pluck('registration_id'))->get();
    }

    private function entrantUserIds(EventClub $c)
    {
        return ClubEventRegistration::whereIn('id', DB::table('event_club_entrants')->where('event_club_id', $c->id)->pluck('registration_id'))->pluck('user_id');
    }

    private function entryRow(Tenant|EventClub $c): ?array
    {
        $regs = $this->clubRegistrations($c);
        if ($regs->isEmpty()) {
            return null;
        }
        $key = $this->keyOf($c);
        $labels = EventFee::optionLabelsForMany($regs->pluck('id'));

        return [
            'id' => 'ce-'.substr(hash('sha256', $this->event->uuid.$key), 0, 16),
            'eventId' => $this->event->uuid,
            'clubId' => $key,
            'ref' => strtoupper(substr(hash('sha256', $this->event->uuid.'|'.$key), 0, 6)),
            'teams' => (object) [],
            'payments' => $this->invoicesOf($c)->whereIn('state', [EventEntryInvoice::SUBMITTED, EventEntryInvoice::APPROVED])
                ->map(fn (EventEntryInvoice $i) => ['amount' => (float) $i->amount, 'status' => $i->state === EventEntryInvoice::APPROVED ? 'Paid' : 'Awaiting transfer'])->values()->all(),
            // Every proof the club sent, newest first — so a rejected one says why.
            'proofs' => $this->invoicesOf($c)->sortByDesc('id')->map(fn (EventEntryInvoice $i) => [
                'state' => $i->state, 'amount' => (float) $i->amount, 'reason' => $i->reason,
                'at' => $i->submitted_at?->getTimestampMs(),
            ])->values()->all(),
            'due' => $this->outstanding($c)['amount'],
            'log' => [],
            'athletes' => $regs->groupBy('user_id')->map(function ($rows) use ($labels, $key) {
                $u = $rows->first()->user;
                $a = $u ? $this->athleteRow($u, $key) : ['id' => 'u-?', 'name' => '—', 'gender' => 'Male', 'dob' => '', 'nat' => '', 'natId' => '', 'photo' => null];
                $types = $rows->flatMap(fn ($r) => (array) ($labels[$r->id] ?? []))->unique()->values()->all();

                return [
                    'athleteId' => $a['id'], 'name' => $a['name'], 'gender' => $a['gender'], 'dob' => $a['dob'],
                    'weight' => $rows->first()->weight, 'nat' => $a['nat'], 'natId' => '', 'photo' => $a['photo'],
                    'types' => $types ?: [__('events::events.fee_line_entry')], 'status' => 'Active', 'approval' => 'Approved',
                    'late' => false, 'chargeable' => true,
                ];
            })->values()->all(),
        ];
    }

    // ── entering ────────────────────────────────────────────────────────────

    /**
     * @return array{ok: bool, status?: int, message?: string, code?: string, match?: array, ref?: string, balance?: float, added?: int, skipped?: array}
     */
    public function submit(array $p, bool $consented): array
    {
        $sub = (string) ($p['submissionId'] ?? '');
        $replayKey = 'clubentry:sub:'.$this->event->id.':'.hash('sha256', $sub.'|'.Session::getId());
        if ($sub !== '' && ($prev = Cache::get($replayKey))) {
            return $prev;
        }

        if (! $consented) {
            return ['ok' => false, 'status' => 422, 'message' => __('events::club_entry.ui.eAccept')];
        }
        if (! $this->acceptsEntries()) {
            return ['ok' => false, 'status' => 422, 'message' => __('events::club_entry.ui.phaseLocked')];
        }

        $club = $this->sessionClub();
        if (($p['clubMode'] ?? null) === 'new' && ! $club) {
            // A club not on TakeOne has no verifiable home: it may enter only an
            // event open to every club (inter_club / worldwide). Asked BEFORE
            // anything is written down.
            if (! in_array((string) ($this->event->scope ?: 'internal'), ['inter_club', 'worldwide'], true)) {
                return ['ok' => false, 'status' => 422, 'message' => __('events::events.entry_out_of_scope', ['name' => (string) (($p['club'] ?? [])['name'] ?? '')])];
            }
            $c = (array) ($p['club'] ?? []);
            if (empty($p['club']['distinct']) && ($m = $this->checkClub((string) ($c['name'] ?? ''), (string) ($c['email'] ?? ''), (string) ($c['phone'] ?? '')))) {
                return ['ok' => false, 'status' => 422, 'code' => 'club_exists', 'match' => $m, 'message' => __('events::club_entry.ui.matchSubName')];
            }
            $club = $this->writeDownClub($c);
            if (! $club) {
                return ['ok' => false, 'status' => 422, 'message' => __('events::club_entry.ui.eClub')];
            }
            $this->bind('ec-'.$club->uuid);
        }
        if (! $club) {
            return ['ok' => false, 'status' => 422, 'message' => __('events::club_entry.ui.eVerify')];
        }
        if ($club instanceof EventClub && ! in_array((string) ($this->event->scope ?: 'internal'), ['inter_club', 'worldwide'], true)) {
            return ['ok' => false, 'status' => 422, 'message' => __('events::events.entry_out_of_scope', ['name' => $club->name])];
        }
        if ($club instanceof Tenant && ! $this->clubInScope($club)) {
            return ['ok' => false, 'status' => 422, 'message' => __('events::events.entry_out_of_scope', ['name' => $club->club_name])];
        }

        $actor = $this->hostActor();
        if (! $actor) {
            return ['ok' => false, 'status' => 422, 'message' => __('personal.event_show_action_failed')];
        }

        $rosterIds = $club instanceof Tenant
            ? DB::table('memberships')->where('tenant_id', $club->id)->where('status', 'active')->pluck('user_id')->flip()
            : $this->entrantUserIds($club)->flip();
        $optionByLabel = EventFee::options($this->event)->mapWithKeys(fn ($o) => [mb_strtolower((string) $o->label) => (string) $o->uuid]);

        $added = 0;
        $skipped = [];
        foreach (array_slice((array) ($p['entries'] ?? []), 0, self::MAX_ATHLETES) as $row) {
            $row = (array) $row;
            $user = $this->athleteFor($row, $rosterIds);
            if (! $user) {
                $skipped[] = ['name' => (string) ($row['name'] ?? ''), 'reason' => 'not_found'];

                continue;
            }
            $options = collect((array) ($row['types'] ?? []))->map(fn ($t) => $optionByLabel[mb_strtolower((string) $t)] ?? null)->filter()->values()->all();
            $ok = $this->enterOne($user, $club, $actor, $row, $options);
            $ok ? $added++ : ($skipped[] = ['name' => (string) ($user->full_name ?? ''), 'reason' => 'refused']);
        }

        $regIds = $this->clubRegistrations($club)->pluck('id');
        // What the club's entries cost: charged where a charge is recorded, estimated where not yet.
        $charged = EventFee::chargedForMany($this->event, $regIds);
        $balance = array_sum(array_map('floatval', (array) ($charged['amounts'] ?? []))) + array_sum(array_map('floatval', (array) ($charged['estimated'] ?? [])));
        $key = $this->keyOf($club);
        $result = [
            'ok' => true,
            'ref' => strtoupper(substr(hash('sha256', $this->event->uuid.'|'.$key), 0, 6)),
            'balance' => round($balance, 3),
            'added' => $added,
            'skipped' => $skipped,
        ];
        if ($sub !== '') {
            Cache::put($replayKey, $result, now()->addDay());
        }

        return $result;
    }

    /** Withdraw one of THIS club's athletes (never anybody else's). */
    public function withdraw(string $athleteId): bool
    {
        $club = $this->sessionClub();
        $actor = $this->hostActor();
        if (! $club || ! $actor || ! str_starts_with($athleteId, 'u-') || $this->event->hasStarted()) {
            return false;
        }
        $user = User::where('uuid', substr($athleteId, 2))->first();
        $regs = $user ? $this->clubRegistrations($club)->where('user_id', $user->id) : collect();
        foreach ($regs as $r) {
            app(EntryService::class)->remove($this->event, $actor, $r, notify: false);
        }

        return $regs->isNotEmpty();
    }

    private function enterOne(User $user, Tenant|EventClub $club, User $actor, array $row, array $options): bool
    {
        $ev = $this->event;
        if (ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')->exists()) {
            return false;   // already in this event
        }
        $weight = is_numeric($row['weight'] ?? null) && (float) $row['weight'] >= 15 && (float) $row['weight'] <= 200 ? (float) $row['weight'] : null;

        $request = EventPublicEntry::where('event_id', $ev->id)->where('user_id', $user->id)->first() ?? new EventPublicEntry;
        $request->forceFill([
            'uuid' => $request->uuid ?: (string) Str::uuid(),
            'event_id' => $ev->id,
            'user_id' => $user->id,
            'state' => 'pending',
            'birthdate' => $user->birthdate,
            'gender' => $user->gender,
            'weight' => $weight,
            'fee_options' => $options,
            'representing_tenant_id' => $club instanceof Tenant ? $club->id : null,
            'club_name' => $club instanceof EventClub ? $club->name : null,
        ])->save();

        $r = app(PublicEntry::class)->accept($request->fresh(), $actor);
        if (! ($r['ok'] ?? false)) {
            Log::info('club-entry: not accepted', ['event' => $ev->uuid, 'user' => $user->id, 'why' => $r['message'] ?? null]);

            return false;
        }

        $regs = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')->get();
        foreach ($regs as $reg) {
            if ($club instanceof Tenant) {
                $reg->forceFill(['entry_channel' => 'club', 'representing_tenant_id' => $club->id, 'payer_type' => 'club', 'payer_id' => $club->id])->save();
            } else {
                $reg->forceFill(['entry_channel' => 'club', 'representing_tenant_id' => null])->save();
                DB::table('event_club_entrants')->updateOrInsert(['registration_id' => $reg->id],
                    ['event_club_id' => $club->id, 'added_by' => $actor->id, 'updated_at' => now(), 'created_at' => now()]);
                ClubEventRegistration::billToEventClub($reg->id, $club->id);
            }
            if ($weight && ! $reg->weighed_in_at && (float) $reg->weight !== $weight) {
                $reg->forceFill(['weight' => $weight])->save();
            }
            if (! $reg->category_id) {
                rescue(fn () => app(CategorySystems::class)->placeCreating($ev, $reg->fresh()), null, report: true);
            }
        }

        return $regs->isNotEmpty();
    }

    // ── paying ──────────────────────────────────────────────────────────────

    /** Where entry fees are paid: the host club's account, or the person's own (EntryGates). */
    private function bank(): ?array
    {
        $b = rescue(fn () => app(\App\Events\Support\EntryGates::class)->bankFor($this->event), null, report: false);
        if (! $b) {
            return null;
        }

        return array_filter([
            'beneficiary' => $b['account_name'] ?? null, 'bank' => $b['bank_name'] ?? null,
            'iban' => $b['iban'] ?? ($b['account_number'] ?? null), 'benefitpay' => $b['benefitpay'] ?? null,
        ]) ?: null;
    }

    /** @return array{0: string, 1: int} */
    private function payerOf(Tenant|EventClub $c): array
    {
        return $c instanceof Tenant ? ['club', (int) $c->id] : ['event_club', (int) $c->id];
    }

    private function invoicesOf(Tenant|EventClub $c)
    {
        [$type, $id] = $this->payerOf($c);

        return EventEntryInvoice::where('event_id', $this->event->id)->where('payer_type', $type)->where('payer_id', $id)->orderBy('id')->get();
    }

    /**
     * What the club still owes: its unpaid entries that no proof waiting for
     * review already covers, and what they cost (the server's own figure).
     *
     * @return array{ids: list<int>, amount: float}
     */
    public function outstanding(Tenant|EventClub $c): array
    {
        $covered = $this->invoicesOf($c)->where('state', EventEntryInvoice::SUBMITTED)->flatMap(fn ($i) => (array) $i->registration_ids)->map('intval')->all();
        $ids = $this->clubRegistrations($c)->where('paid', false)->pluck('id')->map('intval')->diff($covered)->values()->all();
        $charged = EventFee::chargedForMany($this->event, $ids);
        $amount = array_sum(array_map('floatval', (array) ($charged['amounts'] ?? []))) + array_sum(array_map('floatval', (array) ($charged['estimated'] ?? [])));

        return ['ids' => $ids, 'amount' => round($amount, 3)];
    }

    /**
     * The club's proof of payment for what it owes — a picture, checked by its
     * real bytes, kept on the PRIVATE disk under the event. One proof covers
     * every entry still unpaid; the organiser approves or rejects it once.
     *
     * @return array{ok: bool, message?: string}
     */
    public function attachProof(string $dataUrl): array
    {
        $club = $this->sessionClub();
        if (! $club) {
            return ['ok' => false, 'message' => __('events::club_entry.ui.eVerify')];
        }
        $due = $this->outstanding($club);
        if ($due['ids'] === []) {
            return ['ok' => false, 'message' => __('events::events.club_pay_nothing_due')];
        }
        $path = $this->storeBase64Image($dataUrl, StoragePath::event($this->event, 'payments'), (string) Str::uuid(), 'local');
        if (! $path) {
            return ['ok' => false, 'message' => __('events::events.entry_pay_proof_rejected')];
        }
        [$type, $id] = $this->payerOf($club);
        $invoice = new EventEntryInvoice;
        $invoice->forceFill([
            'event_id' => $this->event->id, 'payer_type' => $type, 'payer_id' => $id,
            'payer_name' => $club instanceof Tenant ? $club->club_name : $club->name,
            'amount' => $due['amount'], 'currency' => EventFee::currency($this->event),
            'proof_path' => $path, 'state' => EventEntryInvoice::SUBMITTED,
            'registration_ids' => $due['ids'], 'submitted_at' => now(),
        ])->save();

        return ['ok' => true, 'message' => __('events::events.club_pay_proof_sent')];
    }

    /** The organiser's list of club payments for this event. */
    public function organiserList(): array
    {
        return EventEntryInvoice::where('event_id', $this->event->id)->orderByRaw("case state when 'submitted' then 0 when 'rejected' then 1 else 2 end")->orderByDesc('id')->get()
            ->map(fn (EventEntryInvoice $i) => [
                'uuid' => $i->uuid, 'club' => $i->payer_name, 'amount' => (float) $i->amount, 'currency' => $i->currency,
                'athletes' => count((array) $i->registration_ids), 'state' => $i->state, 'reason' => $i->reason,
                'submitted_at' => $i->submitted_at?->toIso8601String(),
                'proof' => $i->proof_path ? route('me.events.club-payments.proof', ['event' => $this->event->uuid, 'invoice' => $i->uuid]) : null,
            ])->all();
    }

    /**
     * The organiser's decision on a club's proof. Approving marks every entry it
     * covers paid (as the payment desk does for one); rejecting keeps them
     * unpaid and tells the club why, so it can send another.
     */
    public static function decide(ClubEvent $event, EventEntryInvoice $invoice, bool $approve, ?string $reason, User $by): void
    {
        abort_unless((int) $invoice->event_id === (int) $event->id, 404);
        DB::transaction(function () use ($event, $invoice, $approve, $reason, $by) {
            $invoice->forceFill([
                'state' => $approve ? EventEntryInvoice::APPROVED : EventEntryInvoice::REJECTED,
                'reason' => $approve ? null : (mb_substr(trim((string) $reason), 0, 500) ?: null),
                'decided_by' => $by->id, 'decided_at' => now(),
            ])->save();
            if ($approve) {
                ClubEventRegistration::where('event_id', $event->id)->whereIn('id', (array) $invoice->registration_ids)
                    ->update(['paid' => true, 'paid_at' => now(), 'paid_by' => $by->id]);
            }
        });
    }

    /** A roster member by their key, or a new person (no account) from the details typed. */
    private function athleteFor(array $row, $rosterIds): ?User
    {
        $id = (string) ($row['athleteId'] ?? '');
        if ($id !== '') {
            $u = str_starts_with($id, 'u-') ? User::where('uuid', substr($id, 2))->first() : null;

            return $u && isset($rosterIds[$u->id]) ? $u : null;   // never somebody outside the club
        }
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($row['name'] ?? '')) ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            return null;
        }
        $dob = (string) ($row['dob'] ?? '');
        $dob = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) && strtotime($dob) && strtotime($dob) < time() ? $dob : null;
        $nat = preg_match('/^[A-Za-z]{2,3}$/', (string) ($row['nat'] ?? '')) ? strtoupper((string) $row['nat']) : null;

        $user = UnclaimedPerson::make($name, [
            'gender' => in_array($row['gender'] ?? null, ['Male', 'Female'], true) ? $row['gender'] : null,
            'birthdate' => $dob,
            'nationality' => $nat,
        ]);
        if (is_string($row['photo'] ?? null) && str_starts_with($row['photo'], 'data:image/')) {
            rescue(fn () => ProfilePhoto::store($user, $row['photo']), null, report: true);
        }

        return $user;
    }

    /** A club not on TakeOne, written down for this event (as the organiser's console does). */
    private function writeDownClub(array $c): ?EventClub
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($c['name'] ?? '')) ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            return null;
        }
        $existing = EventClub::where('event_id', $this->event->id)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            return null;   // the same name twice is a start list that adds up wrong
        }
        $logo = null;
        if (is_string($c['logo'] ?? null) && str_starts_with($c['logo'], 'data:image/')) {
            $logo = $this->storeBase64Image($c['logo'], StoragePath::event($this->event, 'clubs'), (string) Str::uuid());
        }

        return EventClub::create([
            'event_id' => $this->event->id, 'name' => $name, 'logo' => $logo,
            'state' => 'listed', 'created_by' => $this->hostActor()?->id,
        ]);
    }

    /** Does the event's scope reach this club? (EventAccess::eligible, asked of a club.) */
    private function clubInScope(Tenant $t): bool
    {
        $ev = $this->event;
        $scope = (string) ($ev->scope ?: 'internal');
        if ((int) $ev->tenant_id === (int) $t->id || in_array($scope, ['inter_club', 'worldwide'], true)) {
            return true;
        }
        if (in_array($scope, ['nationwide', 'regional'], true)) {
            return strtoupper((string) $t->country) === strtoupper((string) ($ev->tenant?->country ?? $ev->host()->country));
        }

        return false;   // internal: the host club's own only
    }

    /** Who the entries are made as: the event's creator, else the host club's owner. */
    private function hostActor(): ?User
    {
        return ($this->event->created_by ? User::find($this->event->created_by) : null)
            ?? ($this->event->tenant?->owner_user_id ? User::find($this->event->tenant->owner_user_id) : null);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function issue(string $subject, array $keys, string $to, string $label): void
    {
        $code = (string) random_int(100000, 999999);
        Cache::put($this->codeKey($subject), ['keys' => $keys, 'code' => hash('sha256', $code.config('app.key')), 'attempts' => 0], self::CODE_TTL);
        rescue(fn () => Mail::to($to)->queue(new ClubEntryCode($code, $label, (string) $this->event->title)), report: true);
    }

    private function codeKey(string $subject): string
    {
        return 'clubentry:code:'.$this->event->id.':'.hash('sha256', $subject);
    }

    /** At most three codes per club per quarter hour, whoever asks. */
    private function underBurst(string $key): bool
    {
        $burst = 'clubentry:burst:'.$this->event->id.':'.$key;
        if ((int) Cache::get($burst, 0) >= 3) {
            return false;
        }
        Cache::put($burst, (int) Cache::get($burst, 0) + 1, 900);

        return true;
    }

    /** @return list<string> the club's addresses on file, lower-case, valid */
    private function emailsOf(Tenant $t): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($x) => strtolower(trim((string) $x)),
            [$t->contactEmail(), $t->owner_email, optional($t->owner)->email]), fn ($x) => filter_var($x, FILTER_VALIDATE_EMAIL))));
    }

    private function tenantsByEmail(string $email)
    {
        $ownerIds = User::whereRaw('lower(email) = ?', [$email])->pluck('id');

        return Tenant::where(fn ($q) => $q->whereRaw('lower(email) = ?', [$email])->orWhereRaw('lower(owner_email) = ?', [$email])->orWhereIn('owner_user_id', $ownerIds))
            ->limit(10)->get();
    }

    private function tenantsByContact(string $contact)
    {
        $norm = $this->normalise($contact);
        if ($norm === '') {
            return collect();
        }
        if (str_contains($norm, '@')) {
            return filter_var($norm, FILTER_VALIDATE_EMAIL) ? $this->tenantsByEmail($norm) : collect();
        }
        if (strlen($norm) < 7) {
            return collect();
        }
        $tail = substr($norm, -7);
        $owners = User::where('mobile', 'like', '%'.$tail.'%')->limit(20)->pluck('id');

        return Tenant::where(fn ($q) => $q->where('phone', 'like', '%'.$tail.'%')->orWhereIn('owner_user_id', $owners))->limit(20)->get()
            ->filter(function (Tenant $t) use ($norm) {
                $p = $t->contactPhone() ?? [];
                $digits = preg_replace('/\D/', '', ($p['code'] ?? '').($p['number'] ?? ''));
                $m = optional($t->owner)->mobile;
                $m = is_array($m) ? $m : (json_decode((string) $m, true) ?: []);
                $own = preg_replace('/\D/', '', ($m['code'] ?? '').($m['number'] ?? ''));

                return collect([$digits, $own])->contains(fn ($d) => strlen($d) >= 7 && (str_ends_with($d, $norm) || str_ends_with($norm, $d)));
            })->values();
    }

    private function normalise(string $contact): string
    {
        $c = strtolower(trim($contact));

        return str_contains($c, '@') ? $c : preg_replace('/\D/', '', $c);
    }

    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '';
        }
        [$user, $domain] = explode('@', $email, 2);

        return mb_substr($user, 0, 1).str_repeat('•', max(1, min(6, mb_strlen($user) - 1))).'@'.$domain;
    }
}
