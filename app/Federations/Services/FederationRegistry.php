<?php

namespace App\Federations\Services;

use App\Federations\Models\Federation;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationTransfer;
use App\Members\Support\ProfilePhoto;
use App\Support\Countries;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The clubs, athletes, transfers and entries of a federation tree, in the
 * shapes the designed pages keep in `window.FedStore` — plus the rules both
 * pages share (an event's fee table, its phase, an entry's invoice).
 *
 * A club is named by an opaque key: `fc-<uuid>` for an affiliated platform
 * club, `fa-<uuid>` for an application. Never a tenant id, never a slug.
 */
class FederationRegistry
{
    public function __construct(private FederationDirectory $directory) {}

    // ------------------------------------------------------------------ tree

    /** Every body in the tree an event (or a body) belongs to. @return Collection<int, Federation> */
    public function tree(Federation $f): Collection
    {
        $top = $f;
        $guard = 0;
        while ($top->parent_id && $guard++ < 10) {
            $up = Federation::find($top->parent_id);
            if (! $up) {
                break;
            }
            $top = $up;
        }

        return collect($this->directory->subtree($top));
    }

    // ----------------------------------------------------------------- clubs

    public function clubKey(FederationClub|FederationClubApplication $c): string
    {
        return ($c instanceof FederationClub ? 'fc-' : 'fa-').$c->uuid;
    }

    public function clubFromKey(?string $key, Collection $federationIds): FederationClub|FederationClubApplication|null
    {
        if (! is_string($key) || ! preg_match('/^(fc|fa)-([0-9a-f-]{36})$/', $key, $m)) {
            return null;
        }

        return $m[1] === 'fc'
            ? FederationClub::where('uuid', $m[2])->whereIn('federation_id', $federationIds)->where('status', FederationClub::STATUS_APPROVED)->first()
            : FederationClubApplication::where('uuid', $m[2])->whereIn('federation_id', $federationIds)->where('status', '!=', FederationClubApplication::STATUS_REJECTED)->first();
    }

    /** A club by its secret personal link. */
    public function clubFromToken(?string $token, Collection $federationIds): FederationClub|FederationClubApplication|null
    {
        if (! is_string($token) || strlen($token) < 32) {
            return null;
        }

        return FederationClub::where('invite_token', $token)->whereIn('federation_id', $federationIds)->where('status', FederationClub::STATUS_APPROVED)->first()
            ?? FederationClubApplication::where('invite_token', $token)->whereIn('federation_id', $federationIds)->where('status', '!=', FederationClubApplication::STATUS_REJECTED)->first();
    }

    public function token(FederationClub|FederationClubApplication $c): string
    {
        if (! $c->invite_token) {
            $c->invite_token = Str::random(48);
            $c->save();
        }

        return $c->invite_token;
    }

    /** @return Collection<int, FederationClub|FederationClubApplication> */
    public function clubsOf(Collection $federationIds): Collection
    {
        $linked = FederationClub::with('club')->whereIn('federation_id', $federationIds)
            ->where('status', FederationClub::STATUS_APPROVED)->get()->filter(fn ($c) => $c->club);
        $apps = FederationClubApplication::whereIn('federation_id', $federationIds)
            ->where('status', FederationClubApplication::STATUS_PENDING)->get();

        return $linked->concat($apps)->values();
    }

    /** Officials as the page draws them: a photo is a link to our own gated route, never a path. */
    public function officials(FederationClub|FederationClubApplication $c): ?array
    {
        $o = $c->officials;
        if (! $o) {
            return null;
        }
        foreach ($o as $slot => $p) {
            if (! empty($p['photo'])) {
                $o[$slot]['photo'] = route('federations.invite.official', ['clubKey' => $this->clubKey($c), 'slot' => $slot, 'v' => substr(md5($p['photo']), 0, 8)]);
            }
        }

        return $o;
    }

    /** `FedStore.club()` shape. `$full` = contact details (the club itself, or federation staff). */
    public function club(FederationClub|FederationClubApplication $c, bool $full = true): array
    {
        if ($c instanceof FederationClub) {
            $t = $c->club;
            $phone = is_array($t->phone) ? $t->phone : (json_decode((string) $t->phone, true) ?: []);
            $row = [
                'id' => $this->clubKey($c),
                'name' => $t->club_name,
                'coach' => $t->owner_name ?: optional($t->owner)->full_name,
                'email' => $t->email ?: $t->owner_email,
                'phone' => trim(($phone['code'] ?? '').' '.($phone['number'] ?? '')),
                'address' => $t->address,
                'logo' => $t->logo ? file_url($t->logo) : null,
                'logoShape' => 'circle',
                'status' => 'Approved',
                'staff' => $this->officials($c),
            ];
        } else {
            $row = [
                'id' => $this->clubKey($c),
                'name' => $c->name,
                'coach' => $c->coach,
                'email' => $c->email,
                'phone' => $c->phone,
                'address' => $c->address,
                'logo' => $c->logo ? route('federations.invite.club-logo', $c->uuid) : null,
                'logoShape' => $c->logo_shape ?: 'circle',
                'status' => 'Pending',
                'staff' => $this->officials($c),
            ];
        }

        if (! $full) {
            return ['id' => $row['id'], 'name' => $row['name'], 'status' => $row['status'], 'coach' => '', 'email' => '', 'phone' => '', 'address' => '', 'logo' => $row['logo'], 'logoShape' => $row['logoShape']];
        }

        return $row;
    }

    // -------------------------------------------------------------- athletes

    /** Where the athlete competes now, in this federation tree. */
    public function currentClub(FederationAthlete $a, Collection $federationIds): ?FederationAthleteClub
    {
        return $a->clubs()->whereIn('federation_id', $federationIds)->whereNull('to_ym')->latest('id')->first();
    }

    public function clubKeyOf(?FederationAthleteClub $row): ?string
    {
        if (! $row) {
            return null;
        }
        if ($row->federation_club_id && $row->club) {
            return $this->clubKey($row->club);
        }

        return $row->application ? $this->clubKey($row->application) : null;
    }

    /**
     * `FedStore.athlete()` shape. `$private` adds what only the athlete's own
     * club and federation staff may see (national ID, date of birth, photo).
     */
    public function athlete(FederationAthlete $a, Collection $federationIds, bool $private = true): array
    {
        $u = $a->user;
        $cur = $this->currentClub($a, $federationIds);
        $row = [
            'id' => $a->takeone_id,
            // Never null: an athlete whose account was deleted keeps their entries
            // and results, and the dashboard draws initials from this string.
            'name' => $u?->full_name ?: $u?->name ?: 'Deleted member',
            'clubId' => $this->clubKeyOf($cur),
        ];
        if (! $private) {
            return $row;
        }

        $hist = $a->clubs()->whereIn('federation_id', $federationIds)->orderBy('from_ym')->orderBy('id')->get()
            ->map(fn (FederationAthleteClub $h) => ['clubId' => $this->clubKeyOf($h), 'from' => $h->from_ym, 'to' => $h->to_ym])->all();

        return $row + [
            'natId' => (string) $a->national_id,
            'gender' => $u?->gender === 'Female' ? 'Female' : 'Male',
            'dob' => $u?->birthdate ? $u->birthdate->format('Y-m-d') : '',
            'nat' => $a->nationality ?: Countries::iso3($u?->nationality),
            'photo' => $u && ProfilePhoto::path($u) ? ProfilePhoto::url($u) : null,
            'history' => $hist,
        ];
    }

    /** @return Collection<int, FederationAthlete> athletes whose current club is this one */
    public function roster(FederationClub|FederationClubApplication $c): Collection
    {
        $col = $c instanceof FederationClub ? 'federation_club_id' : 'club_application_id';
        $ids = FederationAthleteClub::where($col, $c->id)->whereNull('to_ym')->pluck('federation_athlete_id');

        return FederationAthlete::with('user')->whereIn('id', $ids)->get();
    }

    // ---------------------------------------------------------------- events

    /** The two-to-five letter code entry references start with, e.g. GKO26. */
    public function eventCode(FederationEvent $e): string
    {
        $letters = collect(preg_split('/\s+/', preg_replace('/[^A-Za-z ]/', ' ', $e->name)))->filter()
            ->map(fn ($w) => strtoupper($w[0]))->take(4)->implode('');
        $year = $e->starts_on ? substr($e->starts_on, 2, 2) : now()->format('y');

        return ($letters ?: 'EVT').$year;
    }

    /**
     * `FedStore.event()` shape: the fee table per participation type, built
     * from the event's own competition types and fee lines.
     */
    public function eventForInvite(FederationEvent $e): array
    {
        $d = (array) $e->details;
        $free = ($d['free'] ?? true) !== false;
        $lines = collect($d['fees'] ?? [])->mapWithKeys(fn ($f) => [mb_strtolower(trim($f['label'] ?? '')) => (float) preg_replace('/[^0-9.]/', '', (string) ($f['amount'] ?? '0'))]);
        $teamSize = ['team kata' => 3, 'team kumite' => 5];

        $fees = collect($d['types'] ?? [])->map(function ($type) use ($free, $lines, $teamSize) {
            $key = mb_strtolower($type);
            $team = str_starts_with($key, 'team ');
            $fee = ['type' => $type, 'price' => $free ? 0 : (float) ($lines[$key] ?? 0), 'unit' => $team ? 'team' : 'athlete'];
            if ($team) {
                $fee['teamSize'] = $teamSize[$key] ?? 3;
            }
            if (str_contains($key, 'youth')) {
                $fee['maxAge'] = 13;
            }

            return $fee;
        })->values()->all();

        $deadline = ($d['enrollEnd'] ?? '') ? substr($d['enrollEnd'], 0, 10).'T23:59' : ($e->starts_on ? substr($e->starts_on, 0, 10).'T00:00' : '2099-12-31T23:59');
        $drawAt = ($d['drawDate'] ?? '') ? substr($d['drawDate'], 0, 10).'T00:00' : ($e->starts_on ? substr($e->starts_on, 0, 10).'T00:00' : '2099-12-31T23:59');

        return [
            'id' => $e->uuid,
            'code' => $this->eventCode($e),
            'name' => $e->name,
            'sport' => $e->sport,
            'currency' => $e->federation?->currency ?: 'BHD',
            'deadline' => $deadline,
            'drawAt' => $drawAt,
            'lateFee' => $free ? 0 : (float) preg_replace('/[^0-9.]/', '', (string) ($d['lateFee'] ?? '0')),
            'fees' => $fees,
        ];
    }

    /**
     * The invitation's event texts (its `T()` dictionary keys), in English and
     * Arabic, from the event's own fields. A blank field reads as a dash —
     * never the prototype's sample dates.
     *
     * @return array{en: array<string,string>, ar: array<string,string>}
     */
    public function inviteTexts(FederationEvent $e): array
    {
        $d = (array) $e->details;
        $out = [];
        foreach (['en', 'ar'] as $lang) {
            $date = function (?string $v, bool $time = false) use ($lang) {
                if (! $v) {
                    return '—';
                }
                $c = \Carbon\Carbon::parse($v)->locale($lang);

                return $c->translatedFormat('j M Y').($time && strlen($v) > 10 ? ' · '.$c->format('H:i') : '');
            };
            $range = function (?string $a, ?string $b) use ($lang) {
                if (! $a) {
                    return '—';
                }
                $x = \Carbon\Carbon::parse($a)->locale($lang);
                $y = $b ? \Carbon\Carbon::parse($b)->locale($lang) : $x;
                if ($x->isSameDay($y)) {
                    return $x->translatedFormat('j M Y');
                }

                return $x->isSameMonth($y) ? $x->format('j').' – '.$y->translatedFormat('j M Y') : $x->translatedFormat('j M').' – '.$y->translatedFormat('j M Y');
            };
            $where = trim(implode(', ', array_filter([$d['venue'] ?? null, $d['city'] ?? null], fn ($v) => $v && $v !== 'TBC')));
            $startTime = ($d['dayStart'] ?? '') ?: ($e->starts_on && strlen($e->starts_on) > 10 ? substr($e->starts_on, 11, 5) : '');

            $out[$lang] = [
                'eventName' => $e->name,
                'eventWhen' => $range($e->starts_on, $e->ends_on).($where !== '' ? ' · '.$where : ''),
                'opensVal' => $date($d['enrollStart'] ?? null),
                'deadlineVal' => ($d['enrollEnd'] ?? '') ? $date($d['enrollEnd']).' · 23:59' : '—',
                'weighinVal' => $date($d['weighIn'] ?? null, true),
                'weighinWhere' => ($d['location'] ?? '') ?: $where,
                'drawVal' => $date($d['drawDate'] ?? null),
                'compVal' => $range($e->starts_on, $e->ends_on),
                'compSub' => $startTime ? ($lang === 'ar' ? 'تبدأ '.$startTime.' يومياً' : 'Starts '.$startTime.' daily') : '',
                'brkVal' => ! empty($d['breakOn']) && ($d['breakFrom'] ?? '') ? $d['breakFrom'].' – '.($d['breakTo'] ?? '').($lang === 'ar' ? ' يومياً' : ' daily') : '—',
                'rulesDocMeta' => $e->name.' · '.$e->sport,
            ];
        }

        return $out;
    }

    /** The invitation's cover: the portrait artwork, else the landscape one. */
    public function coverArt(FederationEvent $e): ?string
    {
        foreach (['art_portrait' => 'art-p', 'art_landscape' => 'art-l'] as $col => $kind) {
            if ($e->{$col}) {
                return route('federations.event-media', ['event' => $e->uuid, 'kind' => $kind, 'v' => substr(md5($e->{$col}), 0, 8)]);
            }
        }

        return null;
    }

    /** open | late | locked — in the event federation's own time zone. */
    public function phase(FederationEvent $e): string
    {
        $ev = $this->eventForInvite($e);
        $tz = $e->federation?->timezone ?: 'Asia/Bahrain';
        $now = now($tz);
        if ($now->greaterThan(\Carbon\Carbon::parse($ev['drawAt'], $tz))) {
            return 'locked';
        }

        return $now->greaterThan(\Carbon\Carbon::parse($ev['deadline'], $tz)) ? 'late' : 'open';
    }

    // --------------------------------------------------------------- entries

    /** `FedStore.entry()` shape. */
    public function entry(FederationEntry $entry, Collection $federationIds): array
    {
        $club = $entry->club ?? $entry->application;

        return [
            'id' => $entry->uuid,
            'eventId' => $entry->event?->uuid,
            'clubId' => $club ? $this->clubKey($club) : null,
            'ref' => $entry->ref,
            'teams' => (object) ($entry->teams ?: []),
            'payments' => $entry->payments ?: [],
            'log' => $entry->log ?: [],
            'createdAt' => $entry->created_at?->getTimestampMs(),
            'updatedAt' => $entry->updated_at?->getTimestampMs(),
            'athletes' => $entry->athletes()->with('athlete.user')->orderBy('id')->get()->map(function (FederationEntryAthlete $ea) use ($federationIds) {
                $a = $this->athlete($ea->athlete, $federationIds);

                return [
                    'athleteId' => $a['id'], 'name' => $a['name'], 'gender' => $a['gender'], 'dob' => $a['dob'],
                    'weight' => $ea->weight, 'nat' => $a['nat'], 'natId' => $a['natId'], 'photo' => $a['photo'],
                    'types' => $ea->types ?: [], 'status' => $ea->status, 'approval' => $ea->approval,
                    'late' => $ea->late, 'chargeable' => $ea->chargeable,
                    'withdrawnAt' => $ea->withdrawn_at?->getTimestampMs(),
                ];
            })->all(),
        ];
    }

    /** The prototype's `FedStore.invoice()`, on the server — this one decides what is owed. */
    public function invoice(array $entry, array $event): array
    {
        $fee = collect($event['fees'])->keyBy('type');
        $lines = [];
        $perAthlete = [];

        foreach ($entry['athletes'] ?? [] as $a) {
            if (($a['status'] ?? '') === 'Withdrawn' && empty($a['chargeable'])) {
                continue;
            }
            $sum = 0;
            $items = [];
            foreach ($a['types'] ?? [] as $t) {
                $f = $fee->get($t);
                if ($f && $f['unit'] === 'athlete') {
                    $sum += $f['price'];
                    $items[] = ['label' => $t, 'amount' => $f['price']];
                }
            }
            if (! empty($a['late'])) {
                $sum += $event['lateFee'];
                $items[] = ['label' => 'Late entry fee', 'amount' => $event['lateFee']];
            }
            if ($sum) {
                $perAthlete[] = ['name' => $a['name'], 'items' => $items, 'total' => $sum, 'withdrawn' => ($a['status'] ?? '') === 'Withdrawn'];
                $lines[] = ['label' => $a['name'], 'amount' => $sum];
            }
        }
        foreach ((array) ($entry['teams'] ?? []) as $t => $n) {
            $f = $fee->get($t);
            if ((int) $n > 0 && $f) {
                $lines[] = ['label' => $t.' × '.$n, 'amount' => (int) $n * $f['price']];
            }
        }

        $total = array_sum(array_column($lines, 'amount'));
        $pays = collect($entry['payments'] ?? []);
        $paid = $pays->where('status', 'Paid')->sum('amount');
        $pending = $pays->where('status', 'Awaiting transfer')->sum('amount');

        return ['total' => round($total, 3), 'paid' => round($paid, 3), 'pending' => round($pending, 3), 'balance' => round($total - $paid - $pending, 3)];
    }

    /** `takeone_club_regs` — the dashboard's registration feed, one row per entry. */
    public function regFeed(FederationEntry $entry, Collection $federationIds): array
    {
        $club = $entry->club ?? $entry->application;
        $c = $club ? $this->club($club) : ['name' => '—', 'coach' => '', 'phone' => '', 'email' => '', 'address' => '', 'logo' => null, 'logoShape' => 'circle'];
        $e = $this->entry($entry, $federationIds);

        return [
            'eventId' => $e['eventId'], 'entryId' => $e['id'], 'clubId' => $e['clubId'], 'ref' => $e['ref'],
            'club' => $c['name'], 'coach' => $c['coach'], 'phone' => $c['phone'], 'email' => $c['email'], 'address' => $c['address'],
            'logo' => $c['logo'], 'logoShape' => $c['logoShape'], 'ts' => $e['updatedAt'],
            'athletes' => collect($e['athletes'])->where('status', '!=', 'Withdrawn')->map(fn ($a) => [
                'name' => $a['name'], 'gender' => $a['gender'], 'dob' => $a['dob'], 'weight' => $a['weight'], 'nat' => $a['nat'],
                'natId' => $a['natId'], 'photo' => $a['photo'], 'athleteId' => $a['athleteId'], 'takeoneId' => $a['athleteId'],
                'types' => $a['types'], 'approval' => $a['approval'],
            ])->values()->all(),
        ];
    }

    // ------------------------------------------------------------- transfers

    public function transfer(FederationTransfer $t): array
    {
        $key = fn ($clubId, $appId) => $clubId
            ? (($c = FederationClub::find($clubId)) ? $this->clubKey($c) : null)
            : (($a = $appId ? FederationClubApplication::find($appId) : null) ? $this->clubKey($a) : null);

        return [
            'id' => $t->uuid,
            'athleteId' => $t->athlete?->takeone_id,
            'fromClubId' => $key($t->from_club_id, $t->from_application_id),
            'toClubId' => $key($t->to_club_id, $t->to_application_id),
            'requestedBy' => $t->requested_by,
            'status' => $t->status,
            'oldClub' => $t->old_club,
            'ts' => $t->created_at?->getTimestampMs(),
            'due' => $t->due_at?->getTimestampMs(),
            'decidedAt' => $t->decided_at?->getTimestampMs(),
        ];
    }
}
