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
use App\Federations\Support\FederationTestMode;
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
            // The country the phone picker opens on: the federation's own.
            'federation' => ['name' => $e->federation->name, 'country' => strtolower((string) (Countries::iso2((string) $e->federation->country) ?: $e->federation->country))] + $this->bankDetails($e->federation),
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

    /**
     * The club a contact belongs to, and the address its code goes to.
     *
     * A club is found by ITS OWN email or phone, or — for a club on the platform
     * — by its owner's account email or mobile: a club with nothing on the club
     * record itself (it happens) is still reachable by the person who runs it.
     *
     * ⚠️ The code only ever goes to an address ON FILE for that club: the email
     * typed when it matches one, otherwise the first one the club has (club,
     * then owner). A phone number never chooses where mail goes.
     *
     * Several clubs can answer to one contact (one owner, several clubs): all of
     * them come back, limited to those the chosen address belongs to — owning
     * that inbox is what proves the right to each of them.
     *
     * @return array{0: array<int, FederationClub|FederationClubApplication>, 1: string}|null
     */
    private function findByContact(FederationEvent $e, string $contact): ?array
    {
        $norm = $this->normalise($contact);
        if ($norm === '' || (! str_contains($norm, '@') && strlen($norm) < 7)) {
            return null;
        }

        $hits = [];
        foreach ($this->registry->clubsOf($this->federationIds($e)) as $c) {
            [$emails, $phones] = $this->contactsOf($c);
            if (str_contains($norm, '@')) {
                if (in_array($norm, $emails, true)) {
                    $hits[] = [$c, $emails];
                }
            } else {
                foreach ($phones as $digits) {
                    if (strlen($digits) >= 7 && ($digits === $norm || str_ends_with($digits, $norm) || str_ends_with($norm, $digits))) {
                        $hits[] = [$c, $emails];
                        break;
                    }
                }
            }
        }

        $sendTo = str_contains($norm, '@') ? $norm : collect($hits)->map(fn ($h) => $h[1][0] ?? null)->filter()->first();
        if (! $sendTo) {
            return null;
        }
        $clubs = collect($hits)->filter(fn ($h) => in_array($sendTo, $h[1], true))->map(fn ($h) => $h[0])->values()->all();

        return $clubs === [] ? null : [$clubs, $sendTo];
    }

    /** @return array{0: array<int, string>, 1: array<int, string>} valid emails (club first, then owner) and phone digits */
    private function contactsOf(FederationClub|FederationClubApplication $c): array
    {
        $row = $this->registry->club($c);
        $emails = [$row['email'] ?? null];
        $phones = [$row['phone'] ?? null];
        if ($c instanceof FederationClub && ($owner = $c->club?->owner)) {
            $emails[] = $owner->email;
            $m = is_array($owner->mobile) ? $owner->mobile : (json_decode((string) $owner->mobile, true) ?: ['number' => (string) $owner->mobile]);
            $phones[] = ($m['code'] ?? '').($m['number'] ?? '');
        }
        $emails = array_values(array_unique(array_filter(array_map(fn ($x) => strtolower(trim((string) $x)), $emails), fn ($x) => filter_var($x, FILTER_VALIDATE_EMAIL))));
        $phones = array_values(array_unique(array_filter(array_map(fn ($x) => preg_replace('/\D/', '', (string) $x), $phones))));

        return [$emails, $phones];
    }

    private function codeKey(FederationEvent $e, string $contact): string
    {
        return 'fedinv:code:'.hash('sha256', $e->uuid.'|'.$this->normalise($contact));
    }

    /** Always "sent" to the caller. A code goes out only if the club exists. */
    public function sendCode(FederationEvent $e, string $contact): void
    {
        $found = $this->findByContact($e, $contact);
        if (! $found) {
            return;
        }
        [$clubs, $sendTo] = $found;
        $club = $clubs[0];
        $row = $this->registry->club($club);

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
            'clubs' => array_map(fn ($c) => $this->registry->clubKey($c), $clubs),
            'code' => hash('sha256', $code.config('app.key')),
            'attempts' => 0,
        ], self::CODE_TTL);

        // One club: its name in the mail. Several: the mail says so rather than naming one.
        $label = count($clubs) === 1 ? $row['name'] : count($clubs).' clubs';
        rescue(fn () => Mail::to($sendTo)->queue(new ClubVerificationCode($code, $label, FederationTestMode::label().$e->name)), report: true);
    }

    /**
     * "Find your club": the federation's clubs whose name holds what was typed.
     * Name, logo and a masked hint of the inbox the code goes to — never the
     * contact itself. A club's name is not a secret; its roster is, and that
     * still needs the code.
     *
     * @return list<array{key: string, name: string, logo: ?string, hint: ?string}>
     */
    public function searchClubs(FederationEvent $e, string $q): array
    {
        $q = $this->personKey($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return $this->registry->clubsOf($this->federationIds($e))
            ->filter(fn ($c) => str_contains($this->personKey((string) ($this->registry->club($c, false)['name'] ?? '')), $q))
            ->sortBy(fn ($c) => mb_strtolower((string) $this->registry->club($c, false)['name']))
            ->take(8)->map(fn ($c) => $this->clubCard($c))->values()->all();
    }

    /**
     * Before a "new club" form goes any further: is this club already on file?
     * The page asks at the club step so a coach learns it there, not at Submit.
     */
    public function checkNewClub(FederationEvent $e, array $c): ?array
    {
        return $this->sessionClub($e) ? null : $this->existingClubFor($e, $c);
    }

    /**
     * A code (and a one-tap link) to the inbox a picked club has on file. The
     * same silent answer whatever happens, like sendCode().
     */
    public function sendCodeToClub(FederationEvent $e, string $clubKey): void
    {
        $club = $this->registry->clubFromKey($clubKey, $this->federationIds($e));
        if (! $club) {
            return;
        }
        [$emails] = $this->contactsOf($club);
        if (! $emails) {
            return;
        }
        $burst = 'fedinv:burst:'.$clubKey;
        if ((int) Cache::get($burst, 0) >= 3) {
            return;
        }
        Cache::put($burst, (int) Cache::get($burst, 0) + 1, 900);

        $code = (string) random_int(100000, 999999);
        Cache::put($this->clubCodeKey($e, $clubKey), [
            'club' => $clubKey, 'clubs' => [$clubKey],
            'code' => hash('sha256', $code.config('app.key')),
            'attempts' => 0,
        ], self::CODE_TTL);

        rescue(fn () => Mail::to($emails[0])->queue(new ClubVerificationCode($code, $this->registry->club($club)['name'], FederationTestMode::label().$e->name, $this->magicLink($e, $clubKey))), report: true);
    }

    private function clubCodeKey(FederationEvent $e, string $clubKey): string
    {
        return 'fedinv:code:club:'.hash('sha256', $e->uuid.'|'.$clubKey);
    }

    /** One tap from the email instead of typing the code: single use, as short-lived as the code. */
    private function magicLink(FederationEvent $e, string $clubKey): string
    {
        $token = Str::random(48);
        Cache::put('fedinv:magic:'.hash('sha256', $token), ['event' => $e->uuid, 'club' => $clubKey], self::CODE_TTL);

        return route('federations.invite.magic', ['event' => $e->uuid, 'token' => $token]);
    }

    /** The link was opened: bind this browser to the club, once. False for anything stale. */
    public function useMagicLink(FederationEvent $e, string $token): bool
    {
        $row = Cache::pull('fedinv:magic:'.hash('sha256', $token));
        if (! is_array($row) || ($row['event'] ?? null) !== $e->uuid) {
            return false;
        }
        $club = $this->registry->clubFromKey($row['club'] ?? null, $this->federationIds($e));
        if (! $club) {
            return false;
        }
        $this->bind($e, $club);
        Cache::forget($this->clubCodeKey($e, $row['club']));   // the code it came with is spent too

        return true;
    }

    /**
     * A signed-in platform member who owns exactly one of this federation's
     * clubs is that club already — no code to ask for.
     */
    public function bindOwner(FederationEvent $e, ?User $user): void
    {
        if (! $user || $this->sessionClub($e)) {
            return;
        }
        $mine = FederationClub::with('club')->whereIn('federation_id', $this->federationIds($e))
            ->where('status', FederationClub::STATUS_APPROVED)->get()
            ->filter(fn ($c) => $c->club && (int) $c->club->owner_user_id === (int) $user->id)->values();
        if ($mine->count() === 1) {
            $this->bind($e, $mine[0]);
        }
    }

    /**
     * @return array{ok: bool, choices?: array<int, array{key: string, name: string, logo: ?string}>}
     *         `choices` when the code covers several clubs — the owner picks one (choose()).
     */
    public function verify(FederationEvent $e, string $contact, string $code, bool $byClub = false): array
    {
        $key = $byClub ? $this->clubCodeKey($e, $contact) : $this->codeKey($e, $contact);
        $row = Cache::get($key);
        if (! is_array($row)) {
            return ['ok' => false];
        }
        if ($row['attempts'] >= self::CODE_ATTEMPTS) {
            Cache::forget($key);

            return ['ok' => false];
        }
        if (! hash_equals($row['code'], hash('sha256', preg_replace('/\D/', '', $code).config('app.key')))) {
            $row['attempts']++;
            Cache::put($key, $row, self::CODE_TTL);

            return ['ok' => false];
        }

        Cache::forget($key);
        $ids = $this->federationIds($e);
        $clubs = collect($row['clubs'] ?? [$row['club']])->map(fn ($k) => $this->registry->clubFromKey($k, $ids))->filter()->values();
        if ($clubs->isEmpty()) {
            return ['ok' => false];
        }
        if ($clubs->count() === 1) {
            $this->bind($e, $clubs[0]);

            return ['ok' => true];
        }

        // Several: remember WHICH clubs this code proved, for ten minutes, in this session only.
        session()->put($this->choiceKey($e), ['clubs' => $clubs->map(fn ($c) => $this->registry->clubKey($c))->all(), 'until' => now()->addMinutes(10)->timestamp]);

        return ['ok' => true, 'choices' => $clubs->map(fn ($c) => ['key' => $this->registry->clubKey($c)] + array_intersect_key($this->registry->club($c, false), array_flip(['name', 'logo'])))->all()];
    }

    /** The owner picks one of the clubs their code proved. False for anything else. */
    public function choose(FederationEvent $e, string $clubKey): bool
    {
        $held = session()->pull($this->choiceKey($e));
        if (! is_array($held) || ($held['until'] ?? 0) < now()->timestamp || ! in_array($clubKey, (array) ($held['clubs'] ?? []), true)) {
            return false;
        }
        $club = $this->registry->clubFromKey($clubKey, $this->federationIds($e));
        if (! $club) {
            return false;
        }
        $this->bind($e, $club);

        return true;
    }

    private function choiceKey(FederationEvent $e): string
    {
        return 'fedinv-choice.'.$e->uuid;
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

        // Only athletes this federation has ever had: a lookup is for asking a
        // transfer inside the tree, not for finding people across the platform.
        $inTree = fn ($query) => $query->whereHas('clubs', fn ($c) => $c->whereIn('federation_id', $ids));
        $a = $inTree(FederationAthlete::with('user')->where('takeone_id', strtoupper($q)))->first()
            ?? (($h = FederationAthlete::hashNationalId($q)) ? $inTree(FederationAthlete::with('user')->where('national_id_hash', $h))->first() : null);
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

        // The same submission twice — a retry after a lost answer, a second
        // tap — is answered with the first one's result, never saved again.
        $sid = is_string($p['submissionId'] ?? null) && Str::isUuid($p['submissionId']) ? strtolower($p['submissionId']) : null;
        $doneKey = $sid ? 'fedinv:done:'.hash('sha256', $e->uuid.'|'.$sid) : null;
        if ($doneKey && ($replay = $this->replay($e, $doneKey))) {
            return $replay;
        }

        $club = $this->sessionClub($e);
        // A session that has proved a club IS that club: it never makes another.
        $mode = ! $club && ($p['clubMode'] ?? '') === 'new' ? 'new' : 'existing';
        if ($mode === 'existing' && ! $club) {
            return ['ok' => false, 'message' => 'Verify your club again to continue.'];
        }
        if ($mode === 'new') {
            $c = (array) ($p['club'] ?? []);
            $match = $this->existingClubFor($e, $c);
            // Same email or phone is the same club, full stop. A name alone may be a namesake.
            if ($match && ($match['reason'] !== 'name' || empty($c['distinct']))) {
                return ['ok' => false, 'code' => 'club_exists', 'match' => $match, 'message' => $match['name'].' is already registered with the federation. Verify it is yours to continue.'];
            }
        }

        $ev = $this->registry->eventForInvite($e);
        $types = collect($ev['fees'])->pluck('type')->all();

        // One submission per club at a time: two coaches of one club, or a
        // double tap, queue here instead of racing for the same entry.
        $lockKey = 'fedinv:submit:'.$e->id.':'.($club ? $this->registry->clubKey($club) : 'new:'.hash('sha256', $this->normalise((string) (($p['club'] ?? [])['email'] ?? ''))));
        $lock = Cache::lock($lockKey, 30);
        try {
            $lock->block(15);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return ['ok' => false, 'message' => 'Another entry for your club is being saved. Try again in a moment.'];
        }
        // Checked again under the lock: the request queued behind this one's twin
        // must see what the twin saved.
        if ($doneKey && ($replay = $this->replay($e, $doneKey))) {
            $lock->release();

            return $replay;
        }
        if ($mode === 'new' && ($match = $this->existingClubFor($e, (array) ($p['club'] ?? []))) && ($match['reason'] !== 'name' || empty(($p['club'] ?? [])['distinct']))) {
            $lock->release();

            return ['ok' => false, 'code' => 'club_exists', 'match' => $match, 'message' => $match['name'].' is already registered with the federation. Verify it is yours to continue.'];
        }

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

                // 3. Athletes: from the roster, or new people. Each person once:
                // a new name that is already on the roster IS that athlete, and
                // the same person twice in one list is entered once.
                $rows = array_slice(is_array($p['entries'] ?? null) ? $p['entries'] : [], 0, 300);
                $seen = [];
                $added = 0;
                $skipped = [];
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
                        $athlete = FederationAthlete::with('user')->where('takeone_id', $tid)->first();
                    } else {
                        $athlete = $this->rosterMatch($row, $club) ?? $this->newAthlete($row, $club, $clubFederationId, $ym, $ids);
                    }
                    if (! $athlete || isset($seen[$athlete->id])) {
                        continue;
                    }
                    $seen[$athlete->id] = true;
                    $label = $athlete->user?->full_name ?: $athlete->takeone_id;

                    $existing = $entry->athletes()->where('federation_athlete_id', $athlete->id)->first();
                    if ($existing && $existing->status === 'Active') {
                        $skipped[] = $label;   // already on this entry
                        continue;
                    }
                    // One entry per athlete per event, whichever club it is under.
                    $elsewhere = FederationEntryAthlete::where('federation_athlete_id', $athlete->id)->where('status', 'Active')
                        ->where('federation_entry_id', '!=', $entry->id)
                        ->whereHas('entry', fn ($q) => $q->where('federation_event_id', $e->id))->exists();
                    if ($elsewhere) {
                        throw new RuntimeException($label.' is already entered in this event by another club.');
                    }

                    $weight = is_numeric($row['weight'] ?? null) ? max(10, min(250, round((float) $row['weight'], 1))) : null;
                    $picked = array_values(array_intersect($types, is_array($row['types'] ?? null) ? $row['types'] : []));
                    $entry->athletes()->updateOrCreate(['federation_athlete_id' => $athlete->id], [
                        'weight' => $weight, 'types' => $picked, 'status' => 'Active', 'approval' => 'Pending',
                        'late' => $phase === 'late', 'chargeable' => false, 'withdrawn_at' => null,
                    ]);
                    $added++;
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

                return ['club' => $club, 'entry' => $entry, 'balance' => $inv['balance'], 'added' => $added, 'skipped' => $skipped];
            });
        } catch (RuntimeException $ex) {
            return ['ok' => false, 'message' => $ex->getMessage()];
        } catch (\Illuminate\Database\QueryException $ex) {
            report($ex);

            return ['ok' => false, 'message' => 'This entry could not be saved. Nothing was changed — try again.'];
        } finally {
            $lock->release();
        }

        if ($mode === 'new') {
            $this->bind($e, $result['club']);
        }
        $this->notifyStaff($e, $result['club']);

        $out = ['ref' => $result['entry']->ref, 'balance' => $result['balance'], 'added' => $result['added'], 'skipped' => $result['skipped']];
        if ($doneKey) {
            Cache::put($doneKey, $out + ['club' => $this->registry->clubKey($result['club'])], 3600);
        }

        return ['ok' => true] + $out + ['store' => $this->store($e)];
    }

    /**
     * A submission already saved: the first answer again. The session that
     * lost that answer is bound to the club it made — the submission id is a
     * random secret only that browser ever held.
     */
    private function replay(FederationEvent $e, string $doneKey): ?array
    {
        $done = Cache::get($doneKey);
        if (! is_array($done)) {
            return null;
        }
        $club = $this->registry->clubFromKey($done['club'] ?? null, $this->federationIds($e));
        if (! $club) {
            return null;
        }
        $bound = $this->sessionClub($e);
        if (! $bound) {
            $this->bind($e, $club);
        } elseif ($this->registry->clubKey($bound) !== $done['club']) {
            return ['ok' => false, 'message' => 'This entry was sent from another club\'s session.'];
        }
        unset($done['club']);

        return ['ok' => true] + $done + ['store' => $this->store($e)];
    }

    // ------------------------------------------------------- duplicates

    /** A club name reduced to what tells clubs apart: "Riffa Karate Club" → "riffa". */
    private function nameKey(string $name): string
    {
        $stop = ['club', 'clubs', 'academy', 'karate', 'sport', 'sports', 'team', 'center', 'centre', 'the', 'of', 'and', 'al', 'dojo', 'school', 'fc', 'bh', 'bahrain', 'نادي', 'أكاديمية', 'اكاديمية', 'مركز', 'فريق', 'كاراتيه'];
        $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name)) ?: [], fn ($w) => $w !== '' && ! in_array($w, $stop, true));
        sort($words);

        return implode(' ', $words);
    }

    /** "office@club.com" → "o•••••@club.com": enough to know which inbox to open. */
    private function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).str_repeat('•', max(3, min(6, mb_strlen($local) - 1))).'@'.$domain;
    }

    /** What the page may show about a club it is pointing somebody at: never the contact itself. */
    private function clubCard(FederationClub|FederationClubApplication $c): array
    {
        [$emails] = $this->contactsOf($c);
        $row = $this->registry->club($c, false);

        return ['key' => $this->registry->clubKey($c), 'name' => $row['name'], 'logo' => $row['logo'] ?? null, 'hint' => $this->maskEmail($emails[0] ?? null)];
    }

    /**
     * A club already on file that a "new club" form describes: the same email,
     * the same phone, or the same name once the words every club shares are
     * dropped. @return array{reason: string, key: string, name: string, logo: ?string, hint: ?string}|null
     */
    private function existingClubFor(FederationEvent $e, array $c): ?array
    {
        $email = $this->normalise((string) ($c['email'] ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($c['phone'] ?? ''));
        $name = $this->nameKey((string) ($c['name'] ?? ''));
        $byName = null;

        foreach ($this->registry->clubsOf($this->federationIds($e)) as $club) {
            [$emails, $phones] = $this->contactsOf($club);
            if ($email !== '' && in_array($email, $emails, true)) {
                return ['reason' => 'email'] + $this->clubCard($club);
            }
            foreach ($phones as $digits) {
                if (strlen($phone) >= 7 && strlen($digits) >= 7 && ($digits === $phone || str_ends_with($digits, substr($phone, -8)) || str_ends_with($phone, substr($digits, -8)))) {
                    return ['reason' => 'phone'] + $this->clubCard($club);
                }
            }
            if (! $byName && $name !== '' && $this->nameKey($this->registry->club($club, false)['name'] ?? '') === $name) {
                $byName = ['reason' => 'name'] + $this->clubCard($club);
            }
        }

        return $byName;
    }

    /** "+973 3333-4444" → "+973 33334444": the dial code, one space, the digits. */
    private function phoneShape(string $raw): string
    {
        $raw = trim(preg_replace('/[^\d+\s]/', ' ', $raw));
        if (preg_match('/^(\+\d{1,4})\s+(.*)$/', $raw, $m)) {
            return mb_substr($m[1].' '.preg_replace('/\D/', '', $m[2]), 0, 40);
        }

        return mb_substr(preg_replace('/[^\d+]/', '', $raw), 0, 40);
    }

    /** A person's name as it is compared: case, spacing and punctuation dropped. */
    private function personKey(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N} ]+/u', '', mb_strtolower($name))));
    }

    /**
     * A "new" athlete who is already on this club's roster: the same national
     * ID, or the same name (and birthdate, when both are known). A name alone
     * matches only when it matches exactly one athlete.
     */
    private function rosterMatch(array $row, FederationClub|FederationClubApplication $club): ?FederationAthlete
    {
        $roster = $this->registry->roster($club);
        $hash = FederationAthlete::hashNationalId(mb_substr(trim((string) ($row['natId'] ?? '')), 0, 40));
        if ($hash && ($hit = $roster->firstWhere('national_id_hash', $hash))) {
            return $hit;
        }
        $key = $this->personKey((string) ($row['name'] ?? ''));
        if ($key === '') {
            return null;
        }
        $dob = is_string($row['dob'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['dob']) ? $row['dob'] : null;
        $same = $roster->filter(fn ($a) => $this->personKey((string) $a->user?->full_name) === $key);
        if ($dob) {
            $dated = $same->filter(fn ($a) => ! $a->user?->birthdate || $a->user->birthdate->format('Y-m-d') === $dob);

            return $dated->count() === 1 ? $dated->first() : null;
        }

        return $same->count() === 1 ? $same->first() : null;
    }

    /**
     * Federation staff enrol one athlete by hand — the Registrations tab's
     * "Enroll athlete". Same doors as a club's own submission: a new person is
     * made by newAthlete(), the club's entry for this event is found or opened
     * with its reference, and what is owed becomes an "Awaiting transfer" line.
     * Differences, because the federation itself is entering them: the row is
     * APPROVED at once, and the athlete must already stand at this club (or be
     * new to it) — moving someone between clubs stays a transfer.
     *
     * @param  array|null  $new  ['name','gender','dob','nat','natId'] when the person is new
     * @return array{ok:bool, message:string, takeoneId?:string}
     */
    public function enterByStaff(FederationEvent $e, FederationClub|FederationClubApplication $club, ?FederationAthlete $athlete, ?array $new, ?float $weight, array $types, User $by, bool $categoryOptional = false): array
    {
        $ids = $this->federationIds($e);
        $phase = $this->registry->phase($e);
        $ev = $this->registry->eventForInvite($e);
        $allowed = collect($ev['fees'])->where('unit', 'athlete')->pluck('type')->all();
        $picked = array_values(array_intersect($allowed, $types));
        // A bulk import may leave the category to be completed later ($categoryOptional).
        if ($allowed !== [] && $picked === [] && ! $categoryOptional) {
            return ['ok' => false, 'message' => 'Pick at least one category.'];
        }

        try {
            $result = DB::transaction(function () use ($e, $club, $athlete, $new, $weight, $picked, $phase, $ev, $ids, $by) {
                $now = now();
                if (! $athlete) {
                    $athlete = $this->newAthlete((array) $new, $club, (int) $club->federation_id, $now->format('Y-m'));
                    if (! $athlete) {
                        throw new RuntimeException('Enter the athlete\'s full name.');
                    }
                }

                // One entry per athlete per event, whichever club it is under.
                $already = FederationEntryAthlete::with('entry')->where('federation_athlete_id', $athlete->id)->where('status', 'Active')
                    ->whereHas('entry', fn ($q) => $q->where('federation_event_id', $e->id))->first();
                if ($already) {
                    throw new RuntimeException(($athlete->user?->full_name ?: $athlete->takeone_id).' is already entered in this event.');
                }

                $entry = $this->entryOf($e, $club) ?? FederationEntry::create([
                    'federation_event_id' => $e->id,
                    'federation_club_id' => $club instanceof FederationClub ? $club->id : null,
                    'club_application_id' => $club instanceof FederationClubApplication ? $club->id : null,
                    'ref' => $this->nextRef($e, $ev, $club),
                    'teams' => [], 'payments' => [], 'log' => [['ts' => $now->getTimestampMs(), 'key' => 'logCreated']],
                    'officials' => $club->officials,
                ]);
                $entry->athletes()->updateOrCreate(['federation_athlete_id' => $athlete->id], [
                    'weight' => $weight, 'types' => $picked, 'status' => 'Active', 'approval' => 'Approved',
                    'late' => $phase !== 'open', 'chargeable' => false, 'withdrawn_at' => null,
                ]);
                $entry->log = array_merge($entry->log ?: [], [['ts' => $now->getTimestampMs(), 'key' => 'logAdded', 'n' => $athlete->user?->full_name, 'by' => 'federation']]);
                $entry->save();

                // What the club owes now that the athlete is on its entry.
                $inv = $this->registry->invoice($this->registry->entry($entry->fresh(), $ids), $ev);
                if ($inv['balance'] > 0.0005) {
                    $entry->payments = array_merge($entry->payments ?: [], [[
                        'amount' => $inv['balance'], 'method' => 'Bank transfer', 'status' => 'Awaiting transfer', 'ts' => $now->getTimestampMs(),
                    ]]);
                    $entry->save();
                }

                return $athlete;
            });
        } catch (RuntimeException $ex) {
            return ['ok' => false, 'message' => $ex->getMessage()];
        }

        activity()->causedBy($by)->performedOn($e)
            ->withProperties(['takeone_id' => $result->takeone_id, 'club' => $this->registry->clubKey($club)])->log('federation.athlete_enrolled');

        return ['ok' => true, 'message' => ($result->user?->full_name ?: $result->takeone_id).' is enrolled.', 'takeoneId' => $result->takeone_id];
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
            // "+973 3333 4444" → "+973 33334444": one shape, so the next search finds it.
            'phone' => $this->phoneShape((string) ($c['phone'] ?? '')),
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
            // Optional until the draw: a blank slot keeps what is on file.
            $name = mb_substr(trim(strip_tags((string) ($p['name'] ?? ''))), 0, 150);
            if ($name === '') {
                if (! empty($prev[$slot])) {
                    $out[$slot] = $prev[$slot];
                }

                continue;
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
                'license' => $slot === 'mgr' ? null : mb_substr(trim(strip_tags((string) ($p['license'] ?? $prev[$slot]['license'] ?? ''))), 0, 60),
                'photo' => $photo,
            ], fn ($v) => $v !== null);
        }

        return $out;
    }

    private function newAthlete(array $row, FederationClub|FederationClubApplication $club, int $federationId, string $ym, ?Collection $federationIds = null): ?FederationAthlete
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

        // The same name and birthdate already standing at another club of this
        // federation is the same person: they move by transfer, not by a second record.
        if ($dob && $federationIds) {
            $key = $this->personKey($name);
            $twin = FederationAthlete::with('user')
                ->whereHas('user', fn ($q) => $q->whereDate('birthdate', $dob))
                ->whereHas('clubs', fn ($q) => $q->whereIn('federation_id', $federationIds)->whereNull('to_ym'))
                ->get()->first(fn ($a) => $this->personKey((string) $a->user?->full_name) === $key);
            if ($twin) {
                $cur = $this->registry->currentClub($twin, $federationIds);
                $from = $cur ? ($cur->club ?? $cur->application) : null;
                throw new RuntimeException($name.' (born '.$dob.') is already registered'.($from ? ' with '.$this->registry->club($from, false)['name'] : '').'. Look them up by Takeone ID or national ID and request a transfer instead.');
            }
        }

        $user = User::create([
            'full_name' => $name,
            'name' => $name,
            'email' => null,
            'password' => null,
            'is_unclaimed' => true,
            'is_discoverable' => false,
            'gender' => match ($row['gender'] ?? null) { 'Female' => 'Female', 'Male' => 'Male', null, '' => null, default => 'Male' },
            'birthdate' => $dob,
            'nationality' => $natIso3 !== 'UNK' ? strtoupper((string) Countries::iso2($natIso3)) : null,
        ]);
        if (is_string($row['photo'] ?? null) && str_starts_with($row['photo'], 'data:image/')) {
            ProfilePhoto::store($user, $row['photo']);
        }

        $athlete = FederationAthlete::create([
            'user_id' => $user->id,
            'takeone_id' => $this->nextTakeoneId($natIso3 === 'UNK' ? 'XXX' : $natIso3, $name),
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
    /**
     * A TakeOne ID: nationality ISO-3, the athlete's initials, and a random
     * six-digit number — BHR-FA739215. Random, never sequential: a running
     * number told anybody how many athletes there are and let them walk the
     * list (CLAUDE.md -> Unpredictable Resource Identifiers). Not date-based
     * either, which would say when the athlete joined. Fits the 16-char column.
     */
    private function nextTakeoneId(string $iso3, string $name = ''): string
    {
        return FederationAthlete::issueTakeoneId($iso3, $name);
    }

    /**
     * Staff move one entered athlete onto another club's entry of the same
     * event (the Participants tab's edit window, owner 2026-10-15). The fee
     * follows the athlete: each club's invoice is worked out from who is on
     * its entry, so the old club's total drops and the new club's rises —
     * the new club is asked for the difference exactly as enterByStaff() asks.
     * Payments already made stay with whichever club made them.
     *
     * @return FederationEntryAthlete the athlete's row, now on the new entry
     */
    public function moveAthlete(FederationEntryAthlete $row, FederationClub|FederationClubApplication $club, User $by): FederationEntryAthlete
    {
        $from = $row->entry;
        $e = $from->event;
        $ids = $this->federationIds($e);
        $ev = $this->registry->eventForInvite($e);

        return DB::transaction(function () use ($row, $club, $from, $e, $ids, $ev, $by) {
            $now = now();
            $entry = $this->entryOf($e, $club) ?? FederationEntry::create([
                'federation_event_id' => $e->id,
                'federation_club_id' => $club instanceof FederationClub ? $club->id : null,
                'club_application_id' => $club instanceof FederationClubApplication ? $club->id : null,
                'ref' => $this->nextRef($e, $ev, $club),
                'teams' => [], 'payments' => [], 'log' => [['ts' => $now->getTimestampMs(), 'key' => 'logCreated']],
                'officials' => $club->officials,
            ]);
            if ((int) $entry->id === (int) $from->id) {
                return $row;
            }
            $name = $row->athlete?->user?->full_name;

            // An old, withdrawn row of the same athlete on that entry is taken over.
            $moved = $entry->athletes()->updateOrCreate(['federation_athlete_id' => $row->federation_athlete_id], [
                'weight' => $row->weight, 'types' => $row->types, 'status' => 'Active', 'approval' => $row->approval,
                'late' => $row->late, 'chargeable' => $row->chargeable, 'withdrawn_at' => null,
            ]);
            $row->delete();

            $from->log = array_merge($from->log ?: [], [['ts' => $now->getTimestampMs(), 'key' => 'logWithdrawn', 'n' => $name, 'by' => 'federation']]);
            $from->save();
            $entry->log = array_merge($entry->log ?: [], [['ts' => $now->getTimestampMs(), 'key' => 'logAdded', 'n' => $name, 'by' => 'federation']]);
            $entry->save();

            $inv = $this->registry->invoice($this->registry->entry($entry->fresh(), $ids), $ev);
            if ($inv['balance'] > 0.0005) {
                $entry->payments = array_merge($entry->payments ?: [], [[
                    'amount' => $inv['balance'], 'method' => 'Bank transfer', 'status' => 'Awaiting transfer', 'ts' => $now->getTimestampMs(),
                ]]);
                $entry->save();
            }

            activity()->causedBy($by)->performedOn($e)->withProperties([
                'takeone_id' => $row->athlete?->takeone_id, 'from' => $from->uuid, 'to' => $entry->uuid,
            ])->log('federation.athlete_moved_club');

            return $moved->fresh(['athlete.user', 'entry.event']);
        });
    }

    private function nextRef(FederationEvent $e, array $ev, FederationClub|FederationClubApplication $club): string
    {
        $row = $this->registry->club($club);
        $abbr = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $row['name']), 0, 3)) ?: 'CLB';
        // A random tail, not a running count: 001, 002… told every club how
        // many had entered before it. No 0/O/1/I, so it reads back over a phone.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $tail = '';
            for ($i = 0; $i < 6; $i++) {
                $tail .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $ref = $ev['code'].'-'.$abbr.'-'.$tail;
        } while (FederationEntry::where('ref', $ref)->exists());

        return $ref;
    }

    private function requestTransfer(FederationEvent $e, FederationClub|FederationClubApplication $to, string $tid, string $by, Collection $ids): void
    {
        $a = FederationAthlete::where('takeone_id', $tid)->first();
        if (! $a) {
            return;
        }
        // Test mode never moves a real athlete between real clubs.
        if (FederationTestMode::isActive()) {
            throw new RuntimeException('Transfers are switched off while this event is in test mode.');
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
            UserNotification::notifyUser((int) $id, 'federation_entry', FederationTestMode::label().'New club submission', [
                'body' => $name.' · '.$e->name,
                'icon' => 'bi-people-fill',
                'action_url' => route('federations.home'),
            ]);
        }
    }
}
