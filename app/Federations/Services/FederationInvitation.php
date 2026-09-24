<?php

namespace App\Federations\Services;

use App\Federations\Mail\ClubVerificationCode;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationStaff;
use App\Federations\Models\FederationTransfer;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Members\Support\ProfilePhoto;
use App\Support\Countries;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The public side: a club opening an event's invitation link, proving who it
 * is, and entering athletes.
 *
 * Identity is proved, never claimed: by a six-digit code emailed to the
 * club's address on file, or by the club's own secret personal link. Either
 * way the result is a session bound to ONE club for ONE event, and every read
 * and write here goes through it. Whether a contact belongs to a club is
 * never revealed — the answer to "send me a code" is the same either way.
 */
class FederationInvitation
{
    use StoresBase64Images;

    public const CODE_TTL = 600;

    public const CODE_ATTEMPTS = 5;

    public function __construct(private FederationRegistry $registry) {}

    /** An event a club may be invited to. */
    public function event(?string $uuid): ?FederationEvent
    {
        if (! $uuid || ! Str::isUuid($uuid)) {
            return null;
        }
        $e = FederationEvent::with('federation')->where('uuid', $uuid)->first();
        if (! $e || ! $e->federation || ! $e->federation->isApproved() || $e->federation->isSuspended() || $e->status === 'Completed') {
            return null;
        }

        return $e;
    }

    public function federationIds(FederationEvent $e): Collection
    {
        return $this->registry->tree($e->federation)->pluck('id');
    }

    private function sessionKey(FederationEvent $e): string
    {
        return 'fedinv.'.$e->uuid;
    }

    public function sessionClub(FederationEvent $e): FederationClub|FederationClubApplication|null
    {
        return $this->registry->clubFromKey(Session::get($this->sessionKey($e)), $this->federationIds($e));
    }

    public function bind(FederationEvent $e, FederationClub|FederationClubApplication $club): void
    {
        Session::migrate(true);   // a new identity gets a new session id
        Session::put($this->sessionKey($e), $this->registry->clubKey($club));
    }

    /** A personal link: `?club=<token>`. */
    public function bindToken(FederationEvent $e, ?string $token): void
    {
        $club = $this->registry->clubFromToken($token, $this->federationIds($e));
        if ($club) {
            $this->bind($e, $club);
        }
    }

    // ------------------------------------------------------------- the store

    /**
     * What the invitation's store starts with: the event, its documents, and —
     * only once a club is proved — that club, its roster, its entry and its
     * transfer requests.
     */
    /**
     * Where a club pays its entry fees: the event's own federation's account,
     * from its record (FederationProfiles). Shown to anyone holding the
     * invitation link, on purpose — an IBAN exists to be handed to payers, and
     * a club applying for the first time sees the transfer details before it
     * has a session. Nothing else from the record is sent.
     */
    private function bankDetails(\App\Federations\Models\Federation $f): array
    {
        $p = $f->profile;
        $iban = (string) ($p?->iban ?? '');

        return ['bank' => $iban === '' ? null : [
            'beneficiary' => $p->bank_account_name ?: $f->name,
            'bank' => $p->bank_name,
            'iban' => trim(chunk_split($iban, 4, ' ')),
            'swift' => $p->swift,
        ]];
    }

    public function store(FederationEvent $e): array
    {
        $ids = $this->federationIds($e);
        $club = $this->sessionClub($e);
        $ev = $this->registry->eventForInvite($e);

        $out = [
            'event' => $ev,
            'phase' => $this->registry->phase($e),
            'docs' => $e->documents->map(fn ($d) => [
                'name' => $d->title, 'file' => $d->filename, 'size' => $d->size < 1048576 ? max(1, (int) round($d->size / 1024)).' KB' : number_format($d->size / 1048576, 1).' MB',
                'data' => route('federations.event-doc', $d->uuid),
            ])->all(),
            'federation' => ['name' => $e->federation->name] + $this->bankDetails($e->federation),
            'texts' => $this->registry->inviteTexts($e),
            'cover' => $this->registry->coverArt($e),
            'verifiedClub' => null,
            'clubs' => [],
            'athletes' => [],
            'entries' => (object) [],
            'transfers' => [],
        ];

        if (! $club) {
            return $out;
        }

        $key = $this->registry->clubKey($club);
        $out['verifiedClub'] = $key;
        $out['clubs'] = [$this->registry->club($club)];
        $out['athletes'] = $this->registry->roster($club)->map(fn ($a) => $this->registry->athlete($a, $ids))->values()->all();

        $entry = $this->entryOf($e, $club);
        if ($entry) {
            $out['entries'] = [$key => $this->registry->entry($entry, $ids)];
        }

        $col = $club instanceof FederationClub ? 'to_club_id' : 'to_application_id';
        $transfers = FederationTransfer::with('athlete.user')->whereIn('federation_id', $ids)->where($col, $club->id)->get();
        $out['transfers'] = $transfers->map(fn ($t) => $this->registry->transfer($t))->all();
        // The clubs those athletes come from, by name only.
        $seen = collect($out['athletes'])->pluck('id')->flip();
        foreach ($transfers as $t) {
            foreach ([[$t->from_club_id, FederationClub::class], [$t->from_application_id, FederationClubApplication::class]] as [$id, $class]) {
                if ($id && ($c = $class::find($id))) {
                    $out['clubs'][] = $this->registry->club($c, false);
                }
            }
            if ($t->athlete && ! $seen->has($t->athlete->takeone_id)) {
                $out['athletes'][] = $this->registry->athlete($t->athlete, $ids, false);
                $seen->put($t->athlete->takeone_id, true);
            }
        }

        return $out;
    }

    private function entryOf(FederationEvent $e, FederationClub|FederationClubApplication $club): ?FederationEntry
    {
        $col = $club instanceof FederationClub ? 'federation_club_id' : 'club_application_id';

        return FederationEntry::where('federation_event_id', $e->id)->where($col, $club->id)->first();
    }

    // -------------------------------------------------------- verification

    private function normalise(string $contact): string
    {
        $c = strtolower(trim($contact));

        return str_contains($c, '@') ? $c : preg_replace('/\D/', '', $c);
    }

    private function findByContact(FederationEvent $e, string $contact): FederationClub|FederationClubApplication|null
    {
        $norm = $this->normalise($contact);
        if ($norm === '' || (! str_contains($norm, '@') && strlen($norm) < 7)) {
            return null;
        }

        foreach ($this->registry->clubsOf($this->federationIds($e)) as $c) {
            $row = $this->registry->club($c);
            if (str_contains($norm, '@')) {
                if (strtolower((string) $row['email']) === $norm) {
                    return $c;
                }
            } else {
                $digits = preg_replace('/\D/', '', (string) $row['phone']);
                if (strlen($digits) >= 7 && ($digits === $norm || str_ends_with($digits, $norm) || str_ends_with($norm, $digits))) {
                    return $c;
                }
            }
        }

        return null;
    }

    private function codeKey(FederationEvent $e, string $contact): string
    {
        return 'fedinv:code:'.hash('sha256', $e->uuid.'|'.$this->normalise($contact));
    }

    /** Always "sent" to the caller. A code goes out only if the club exists. */
    public function sendCode(FederationEvent $e, string $contact): void
    {
        $club = $this->findByContact($e, $contact);
        if (! $club) {
            return;
        }
        $row = $this->registry->club($club);
        if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // At most three codes per club per quarter hour, whoever asks — so
        // nobody can fill a club's inbox by pressing Resend.
        $burst = 'fedinv:burst:'.$this->registry->clubKey($club);
        if ((int) Cache::get($burst, 0) >= 3) {
            return;
        }
        Cache::put($burst, (int) Cache::get($burst, 0) + 1, 900);

        $code = (string) random_int(100000, 999999);
        Cache::put($this->codeKey($e, $contact), [
            'club' => $this->registry->clubKey($club),
            'code' => hash('sha256', $code.config('app.key')),
            'attempts' => 0,
        ], self::CODE_TTL);

        rescue(fn () => Mail::to($row['email'])->queue(new ClubVerificationCode($code, $row['name'], $e->name)), report: true);
    }

    public function verify(FederationEvent $e, string $contact, string $code): bool
    {
        $key = $this->codeKey($e, $contact);
        $row = Cache::get($key);
        if (! is_array($row)) {
            return false;
        }
        if ($row['attempts'] >= self::CODE_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }
        if (! hash_equals($row['code'], hash('sha256', preg_replace('/\D/', '', $code).config('app.key')))) {
            $row['attempts']++;
            Cache::put($key, $row, self::CODE_TTL);

            return false;
        }

        Cache::forget($key);
        $club = $this->registry->clubFromKey($row['club'], $this->federationIds($e));
        if (! $club) {
            return false;
        }
        $this->bind($e, $club);

        return true;
    }

    // --------------------------------------------------------------- lookup

    /**
     * An athlete by exact Takeone ID or national ID — only for a proved club,
     * and only name + club: enough to ask for a transfer, nothing more.
     */
    public function lookup(FederationEvent $e, string $q): array
    {
        $club = $this->sessionClub($e);
        $q = trim($q);
        if (! $club || strlen(preg_replace('/[^0-9a-z]/i', '', $q)) < 4) {
            return ['athletes' => [], 'clubs' => []];
        }
        $ids = $this->federationIds($e);

        $a = FederationAthlete::with('user')->where('takeone_id', strtoupper($q))->first()
            ?? (($h = FederationAthlete::hashNationalId($q)) ? FederationAthlete::with('user')->where('national_id_hash', $h)->first() : null);
        if (! $a) {
            return ['athletes' => [], 'clubs' => []];
        }

        $row = $this->registry->athlete($a, $ids, false);
        $cur = $this->registry->currentClub($a, $ids);
        $from = $cur ? ($cur->club ?? $cur->application) : null;

        return [
            'athletes' => [$row + ['natKey' => FederationAthlete::hashNationalId($q) === $a->national_id_hash ? $q : null]],
            'clubs' => $from ? [$this->registry->club($from, false)] : [],
        ];
    }

    // --------------------------------------------------------------- submit

    /**
     * The whole of the invitation's "Submit": club, officials, new athletes,
     * transfer requests, the entry and its payment — in one transaction.
     *
     * @return array{ok: bool, message?: string, ref?: string, balance?: float, store?: array}
     */
    public function submit(FederationEvent $e, array $p): array
    {
        $ids = $this->federationIds($e);
        $phase = $this->registry->phase($e);
        if ($phase === 'locked') {
            return ['ok' => false, 'message' => 'The draw has been made — entries are closed.'];
        }

        $club = $this->sessionClub($e);
        $mode = ($p['clubMode'] ?? '') === 'new' ? 'new' : 'existing';
        if ($mode === 'existing' && ! $club) {
            return ['ok' => false, 'message' => 'Verify your club again to continue.'];
        }

        $ev = $this->registry->eventForInvite($e);
        $types = collect($ev['fees'])->pluck('type')->all();

        try {
            $result = DB::transaction(function () use ($e, $p, $mode, $club, $ids, $ev, $types, $phase) {
                // 1. The club.
                if ($mode === 'new') {
                    $club = $this->newApplication($e, (array) ($p['club'] ?? []));
                }
                $officials = $this->officials($e, $club, (array) ($p['staff'] ?? []));
                $club->officials = $officials;
                $club->save();

                $clubFederationId = $club->federation_id;
                $key = $this->registry->clubKey($club);
                $rosterIds = $this->registry->roster($club)->pluck('takeone_id')->all();
                $now = now();
                $ym = $now->format('Y-m');

                // 2. The entry.
                $entry = $this->entryOf($e, $club) ?? FederationEntry::create([
                    'federation_event_id' => $e->id,
                    'federation_club_id' => $club instanceof FederationClub ? $club->id : null,
                    'club_application_id' => $club instanceof FederationClubApplication ? $club->id : null,
                    'ref' => $this->nextRef($e, $ev, $club),
                    'teams' => [], 'payments' => [], 'log' => [['ts' => $now->getTimestampMs(), 'key' => 'logCreated']],
                    'officials' => $officials,
                ]);
                $isNewEntry = $entry->wasRecentlyCreated;
                $log = $entry->log ?: [];

                // 3. Athletes: from the roster, or new people.
                $rows = array_slice(is_array($p['entries'] ?? null) ? $p['entries'] : [], 0, 300);
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $athlete = null;
                    $tid = (string) ($row['athleteId'] ?? '');
                    if ($tid !== '') {
                        if (! in_array($tid, $rosterIds, true)) {
                            throw new RuntimeException('An athlete on this entry is not registered with your club.');
                        }
                        $athlete = FederationAthlete::where('takeone_id', $tid)->first();
                    } else {
                        $athlete = $this->newAthlete($row, $club, $clubFederationId, $ym);
                    }
                    if (! $athlete) {
                        continue;
                    }

                    $weight = is_numeric($row['weight'] ?? null) ? max(10, min(250, round((float) $row['weight'], 1))) : null;
                    $picked = array_values(array_intersect($types, is_array($row['types'] ?? null) ? $row['types'] : []));
                    $existing = $entry->athletes()->where('federation_athlete_id', $athlete->id)->first();
                    if ($existing && $existing->status === 'Active') {
                        continue;   // already on the entry
                    }
                    $entry->athletes()->updateOrCreate(['federation_athlete_id' => $athlete->id], [
                        'weight' => $weight, 'types' => $picked, 'status' => 'Active', 'approval' => 'Pending',
                        'late' => $phase === 'late', 'chargeable' => false, 'withdrawn_at' => null,
                    ]);
                    if (! $isNewEntry) {
                        $log[] = ['ts' => $now->getTimestampMs(), 'key' => 'logAdded', 'n' => $athlete->user?->full_name];
                    }
                }

                // 4. Transfer requests for athletes registered elsewhere.
                foreach (array_slice(is_array($p['transferReq'] ?? null) ? $p['transferReq'] : [], 0, 50) as $tid) {
                    $this->requestTransfer($e, $club, (string) $tid, mb_substr(trim((string) ($p['coachName'] ?? '')), 0, 150), $ids);
                }

                // 5. Teams.
                $teamTypes = collect($ev['fees'])->where('unit', 'team')->pluck('type')->all();
                $oldTeams = $entry->teams ?: [];
                $teams = [];
                foreach ((array) ($p['teams'] ?? []) as $t => $n) {
                    if (in_array($t, $teamTypes, true) && (int) $n > 0) {
                        $teams[$t] = min(20, (int) $n);
                    }
                }
                $entry->teams = $teams;
                if (! $isNewEntry && $oldTeams != $teams) {
                    $log[] = ['ts' => $now->getTimestampMs(), 'key' => 'logTeams'];
                }

                // 6. What is owed now, and the transfer that covers it.
                $entry->log = $log;
                $entry->save();
                $shape = $this->registry->entry($entry->fresh(), $ids);
                $inv = $this->registry->invoice($shape, $ev);
                if ($inv['balance'] > 0.0005) {
                    $entry->payments = array_merge($entry->payments ?: [], [[
                        'amount' => $inv['balance'], 'method' => 'Bank transfer', 'status' => 'Awaiting transfer', 'ts' => $now->getTimestampMs(),
                    ]]);
                    $entry->log = array_merge($entry->log, [['ts' => $now->getTimestampMs(), 'key' => 'logPaid', 'a' => 'BHD '.number_format($inv['balance'], 3), 'm' => 'Bank transfer']]);
                    $entry->save();
                }

                return ['club' => $club, 'entry' => $entry, 'balance' => $inv['balance']];
            });
        } catch (RuntimeException $ex) {
            return ['ok' => false, 'message' => $ex->getMessage()];
        }

        if ($mode === 'new') {
            $this->bind($e, $result['club']);
        }
        $this->notifyStaff($e, $result['club']);

        return ['ok' => true, 'ref' => $result['entry']->ref, 'balance' => $result['balance'], 'store' => $this->store($e)];
    }

    private function newApplication(FederationEvent $e, array $c): FederationClubApplication
    {
        $s = fn ($v, $n = 150) => mb_substr(trim(strip_tags((string) $v)), 0, $n);
        $name = $s($c['name'] ?? '');
        $email = strtolower($s($c['email'] ?? '', 191));
        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter the club name and a valid email.');
        }

        $app = FederationClubApplication::create([
            'federation_id' => $e->federation_id,
            'federation_event_id' => $e->id,
            'name' => $name,
            'coach' => $s($c['coach'] ?? ''),
            'email' => $email,
            'phone' => $s($c['phone'] ?? '', 40),
            'address' => $s($c['address'] ?? '', 300),
            'logo_shape' => in_array($c['logoShape'] ?? null, ['circle', 'rect'], true) ? $c['logoShape'] : 'circle',
            'invite_token' => Str::random(48),
            'status' => FederationClubApplication::STATUS_PENDING,
        ]);

        $logo = $c['logo'] ?? null;
        if (is_string($logo) && str_starts_with($logo, 'data:image/')) {
            $path = $this->storeBase64Image($logo, 'federations/'.$e->federation->uuid.'/clubs/'.$app->uuid.'/branding', (string) Str::uuid(), 'public');
            if ($path) {
                $app->logo = $path;
                $app->save();
            }
        }

        return $app;
    }

    /**
     * The team manager and two referees. Names and licence numbers as typed;
     * a new photo (a data: URL) is stored, an unchanged one (our own link) kept.
     */
    private function officials(FederationEvent $e, FederationClub|FederationClubApplication $club, array $staff): array
    {
        $prev = (array) ($club->officials ?: []);
        $out = [];
        foreach (['mgr', 'ref1', 'ref2'] as $slot) {
            $p = (array) ($staff[$slot] ?? []);
            $name = mb_substr(trim(strip_tags((string) ($p['name'] ?? ''))), 0, 150);
            if ($name === '') {
                throw new RuntimeException('Name the team manager and both referees.');
            }
            $photo = $prev[$slot]['photo'] ?? null;
            $raw = $p['photo'] ?? null;
            if (is_string($raw) && str_starts_with($raw, 'data:image/')) {
                $path = $this->storeBase64Image($raw, 'federations/'.$e->federation->uuid.'/clubs/'.$club->uuid.'/officials', (string) Str::uuid(), 'local');
                if ($path) {
                    $photo = $path;
                }
            }
            $out[$slot] = array_filter([
                'name' => $name,
                'license' => $slot === 'mgr' ? null : mb_substr(trim(strip_tags((string) ($p['license'] ?? ''))), 0, 60),
                'photo' => $photo,
            ], fn ($v) => $v !== null);
        }

        return $out;
    }

    private function newAthlete(array $row, FederationClub|FederationClubApplication $club, int $federationId, string $ym): ?FederationAthlete
    {
        $name = mb_substr(trim(strip_tags((string) ($row['name'] ?? ''))), 0, 150);
        if ($name === '') {
            return null;
        }
        $natIso3 = Countries::iso3((string) ($row['nat'] ?? ''));
        $natId = mb_substr(trim((string) ($row['natId'] ?? '')), 0, 40);
        $hash = FederationAthlete::hashNationalId($natId);
        if ($hash && FederationAthlete::where('national_id_hash', $hash)->exists()) {
            throw new RuntimeException($name.': that national ID is already registered. Look the athlete up and request a transfer instead.');
        }
        $dob = is_string($row['dob'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['dob']) && $row['dob'] < now()->toDateString() ? $row['dob'] : null;

        $user = User::create([
            'full_name' => $name,
            'name' => $name,
            'email' => null,
            'password' => null,
            'is_unclaimed' => true,
            'is_discoverable' => false,
            'gender' => ($row['gender'] ?? '') === 'Female' ? 'Female' : 'Male',
            'birthdate' => $dob,
            'nationality' => $natIso3 !== 'UNK' ? strtoupper((string) Countries::iso2($natIso3)) : null,
        ]);
        if (is_string($row['photo'] ?? null) && str_starts_with($row['photo'], 'data:image/')) {
            ProfilePhoto::store($user, $row['photo']);
        }

        $athlete = FederationAthlete::create([
            'user_id' => $user->id,
            'takeone_id' => $this->nextTakeoneId($natIso3 === 'UNK' ? 'XXX' : $natIso3),
            'national_id' => $natId !== '' ? $natId : null,
            'national_id_hash' => $hash,
            'nationality' => $natIso3 === 'UNK' ? null : $natIso3,
        ]);
        FederationAthleteClub::create([
            'federation_athlete_id' => $athlete->id,
            'federation_id' => $federationId,
            'federation_club_id' => $club instanceof FederationClub ? $club->id : null,
            'club_application_id' => $club instanceof FederationClubApplication ? $club->id : null,
            'from_ym' => $ym,
        ]);

        return $athlete;
    }

    /** ISO3 + a running number unique across every nationality, e.g. BHR-00021. */
    private function nextTakeoneId(string $iso3): string
    {
        $max = FederationAthlete::lockForUpdate()->pluck('takeone_id')
            ->map(fn ($id) => (int) substr((string) strrchr($id, '-'), 1))->max() ?? 0;

        return $iso3.'-'.str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    private function nextRef(FederationEvent $e, array $ev, FederationClub|FederationClubApplication $club): string
    {
        $row = $this->registry->club($club);
        $abbr = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $row['name']), 0, 3)) ?: 'CLB';
        $n = FederationEntry::where('federation_event_id', $e->id)->count() + 1;
        do {
            $ref = $ev['code'].'-'.$abbr.'-'.str_pad((string) $n++, 3, '0', STR_PAD_LEFT);
        } while (FederationEntry::where('ref', $ref)->exists());

        return $ref;
    }

    private function requestTransfer(FederationEvent $e, FederationClub|FederationClubApplication $to, string $tid, string $by, Collection $ids): void
    {
        $a = FederationAthlete::where('takeone_id', $tid)->first();
        if (! $a) {
            return;
        }
        $cur = $this->registry->currentClub($a, $ids);
        $toCol = $to instanceof FederationClub ? 'to_club_id' : 'to_application_id';
        if ($cur && (($to instanceof FederationClub && $cur->federation_club_id === $to->id) || ($to instanceof FederationClubApplication && $cur->club_application_id === $to->id))) {
            return;   // already theirs
        }
        if (FederationTransfer::where('federation_athlete_id', $a->id)->where($toCol, $to->id)->where('status', 'Pending')->exists()) {
            return;
        }

        FederationTransfer::create([
            'federation_id' => $to->federation_id,
            'federation_athlete_id' => $a->id,
            'from_club_id' => $cur?->federation_club_id,
            'from_application_id' => $cur?->club_application_id,
            $toCol => $to->id,
            'requested_by' => $by,
            'status' => 'Pending',
            'old_club' => 'Awaiting reply',
            'due_at' => now()->addDays(7),
        ]);
    }

    /** A club withdrawing one of its athletes (the amend screen). */
    public function withdraw(FederationEvent $e, string $takeoneId): array
    {
        $club = $this->sessionClub($e);
        $entry = $club ? $this->entryOf($e, $club) : null;
        $phase = $this->registry->phase($e);
        if (! $entry || $phase === 'locked') {
            return ['ok' => false];
        }
        $row = $entry->athletes()->whereHas('athlete', fn ($q) => $q->where('takeone_id', $takeoneId))->where('status', 'Active')->first();
        if ($row) {
            $row->update(['status' => 'Withdrawn', 'withdrawn_at' => now(), 'chargeable' => $phase !== 'open']);
            $entry->log = array_merge($entry->log ?: [], [['ts' => now()->getTimestampMs(), 'key' => 'logWithdrawn', 'n' => $row->athlete?->user?->full_name]]);
            $entry->save();
        }

        return ['ok' => true, 'store' => $this->store($e)];
    }

    /** Tell the people who run the event that a club has entered. */
    private function notifyStaff(FederationEvent $e, FederationClub|FederationClubApplication $club): void
    {
        $tree = $this->registry->tree($e->federation);
        $userIds = collect([$e->federation->owner_user_id])
            ->merge(FederationStaff::whereIn('federation_id', $tree->pluck('id'))->get()
                ->filter(fn ($s) => empty($s->sports) || in_array($e->sport, (array) $s->sports, true))->pluck('user_id'))
            ->unique();
        $name = $this->registry->club($club)['name'];

        foreach ($userIds as $id) {
            UserNotification::notifyUser((int) $id, 'federation_entry', 'New club submission', [
                'body' => $name.' · '.$e->name,
                'icon' => 'bi-people-fill',
                'action_url' => route('federations.home'),
            ]);
        }
    }
}
