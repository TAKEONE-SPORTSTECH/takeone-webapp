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
            : $this->becameClub(FederationClubApplication::where('uuid', $m[2])->whereIn('federation_id', $federationIds)->where('status', '!=', FederationClubApplication::STATUS_REJECTED)->first());
    }

    /**
     * An APPROVED application answers as the club it became.
     *
     * A browser that remembered a club's application key from before its
     * approval kept sending it, and its new entries and athletes were filed
     * under the application — a club the dashboard no longer lists, so its
     * club history printed the raw `fa-…` key (2026-10-02, Sparta). An
     * application still pending, or approved without a club behind it, is
     * returned unchanged.
     */
    private function becameClub(?FederationClubApplication $app): FederationClub|FederationClubApplication|null
    {
        if ($app && $app->status === FederationClubApplication::STATUS_APPROVED && $app->federation_club_id) {
            return FederationClub::whereKey($app->federation_club_id)->where('status', FederationClub::STATUS_APPROVED)->first() ?? $app;
        }

        return $app;
    }

    /** A club by its secret personal link. */
    public function clubFromToken(?string $token, Collection $federationIds): FederationClub|FederationClubApplication|null
    {
        if (! is_string($token) || strlen($token) < 32) {
            return null;
        }

        return FederationClub::where('invite_token', $token)->whereIn('federation_id', $federationIds)->where('status', FederationClub::STATUS_APPROVED)->first()
            ?? $this->becameClub(FederationClubApplication::where('invite_token', $token)->whereIn('federation_id', $federationIds)->where('status', '!=', FederationClubApplication::STATUS_REJECTED)->first());
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
            // The club itself was deleted (soft-deleted tenant) while its
            // federation rows remain: a name-only row, never a 500.
            if (! $t) {
                return ['id' => $this->clubKey($c), 'name' => 'Deleted club', 'status' => 'Approved', 'coach' => '', 'email' => '', 'phone' => '', 'address' => '', 'logo' => null, 'logoShape' => 'circle', 'staff' => []];
            }
            // Whichever contact the club chose on its details page — its own, or its owner's.
            $phone = $t->contactPhone() ?? [];
            $row = [
                'id' => $this->clubKey($c),
                'name' => $t->club_name,
                'coach' => $t->owner_name ?: optional($t->owner)->full_name,
                'email' => $t->contactEmail() ?: $t->owner_email,
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

    /**
     * The club's NAME for a history row, whatever state the club is in —
     * registered, applying, approved or not (owner's ruling, 2026-10-02: a
     * club history always says which club). Sent beside the key so the page
     * never has to print the key itself.
     */
    public function clubNameOf(?FederationAthleteClub $row): ?string
    {
        if (! $row) {
            return null;
        }
        $club = $row->federation_club_id ? $row->club : ($row->application ? $this->becameClub($row->application) : null);

        return $club instanceof FederationClub
            ? ($club->club?->club_name ?? $row->application?->name)
            : ($club?->name ?? $row->application?->name);
    }

    public function clubKeyOf(?FederationAthleteClub $row): ?string
    {
        if (! $row) {
            return null;
        }
        if ($row->federation_club_id && $row->club) {
            return $this->clubKey($row->club);
        }

        // An approved application is the club it became (see becameClub()).
        return $row->application ? $this->clubKey($this->becameClub($row->application)) : null;
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
            ->map(fn (FederationAthleteClub $h) => ['clubId' => $this->clubKeyOf($h), 'clubName' => $this->clubNameOf($h), 'from' => $h->from_ym, 'to' => $h->to_ym])->all();

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
        $lines = collect(is_array($d['fees'] ?? null) ? $d['fees'] : [])->filter(fn ($f) => is_array($f));
        // An event is free only when it SAYS so. A missing flag used to read as
        // free, which priced a paid event at nothing.
        $free = array_key_exists('free', $d)
            ? $d['free'] !== false
            : ! $lines->contains(fn ($f) => self::amount($f['amount'] ?? 0) > 0);
        $teamSize = ['team kata' => 3, 'team kumite' => 5];
        $types = collect($d['types'] ?? [])->filter(fn ($t) => is_string($t) && $t !== '')->values();

        $fees = $types->map(function ($type) use ($free, $lines, $teamSize, $types) {
            $key = mb_strtolower($type);
            $team = str_starts_with($key, 'team ');
            $fee = ['type' => $type, 'price' => $free ? 0 : self::priceFor($type, $lines, $types), 'unit' => $team ? 'team' : 'athlete'];
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
            'lateFee' => $free ? 0 : self::amount($d['lateFee'] ?? 0),
            'fees' => $fees,
        ];
    }

    private static function amount(mixed $v): float
    {
        return (float) preg_replace('/[^0-9.]/', '', (string) $v);
    }

    /**
     * What one competition type costs, from the organiser's fee lines.
     *
     * The lines are free text ("Kumite", "Registration", "KUMITE COMPETITION")
     * and the types come from a fixed list ("Individual Kumite"), so an exact
     * label match alone priced almost every event at nothing. In order:
     *  1. a line that names this type (`type`, set in the event editor);
     *  2. a line whose label IS the type;
     *  3. a line whose label's words all appear in the type, or the type's in
     *     the label — the most specific such line wins;
     *  4. one single unlinked line: it is the price of everything.
     * Nothing matched is 0 — the event editor warns about that before it saves.
     */
    private static function priceFor(string $type, Collection $lines, Collection $types): float
    {
        $words = fn (string $x) => array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($x)) ?: [], fn ($w) => $w !== '' && ! in_array($w, ['entry', 'fee', 'fees', 'competition', 'the', 'and', 'per', 'athlete'], true)));
        $key = mb_strtolower(trim($type));

        $linked = $lines->first(fn ($f) => is_string($f['type'] ?? null) && mb_strtolower(trim($f['type'])) === $key);
        if ($linked) {
            return self::amount($linked['amount'] ?? 0);
        }
        $unlinked = $lines->filter(fn ($f) => ! is_string($f['type'] ?? null) || ! $types->contains($f['type']));

        $exact = $unlinked->first(fn ($f) => mb_strtolower(trim((string) ($f['label'] ?? ''))) === $key);
        if ($exact) {
            return self::amount($exact['amount'] ?? 0);
        }

        $tw = $words($type);
        $best = null;
        $bestScore = 0;
        foreach ($unlinked as $f) {
            $lw = $words((string) ($f['label'] ?? ''));
            if ($lw === [] || $tw === []) {
                continue;
            }
            $inType = array_diff($lw, $tw) === [];     // "kumite" ⊂ "individual kumite"
            $inLabel = array_diff($tw, $lw) === [];    // "individual kumite" ⊂ "individual kumite entry"
            if (! $inType && ! $inLabel) {
                continue;
            }
            $score = count(array_intersect($lw, $tw));
            if ($score > $bestScore) {
                [$best, $bestScore] = [$f, $score];
            }
        }
        if ($best) {
            return self::amount($best['amount'] ?? 0);
        }

        return $unlinked->count() === 1 && $lines->count() === 1 ? self::amount($unlinked->first()['amount'] ?? 0) : 0.0;
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
                // The rules panel is the ORGANISER'S words — the event's About and
                // its requirements — never the prototype's sample text. It lands
                // in innerHTML: About was sanitised on save and is cleaned again,
                // requirements are escaped.
                'rulesHtml' => $this->rulesHtml($d, $lang),
                // The opening screen's sections — the organiser's own words and places.
                'orgName' => (string) ($e->federation?->name ?? ''),
                'venueLine' => $where,
                'locationLine' => trim((string) ($d['location'] ?? '')),
                'mapUrl' => $this->mapUrl($d),
                // The pin the organiser dropped, for the Location card's map ('' = none).
                'mapLat' => ($pin = $this->pin($d)) ? (string) $pin[0] : '',
                'mapLng' => $pin ? (string) $pin[1] : '',
                'aboutHtml' => trim(html_entity_decode(strip_tags($about = FederationHtml::clean(is_string($d['about'] ?? null) ? $d['about'] : '')))) !== '' ? $about : '',
                'reqList' => array_values(array_filter(array_map(fn ($r) => is_string($r) ? mb_substr(trim($r), 0, 300) : '', (array) ($d['reqs'] ?? [])), fn ($r) => $r !== '')),
            ];
        }

        return $out;
    }

    /** The organiser's dropped pin as [lat, lng], or null when there is none (0,0 is "none"). */
    private function pin(array $d): ?array
    {
        $lat = $d['lat'] ?? null;
        $lng = $d['lng'] ?? null;
        if (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180 && ((float) $lat != 0.0 || (float) $lng != 0.0)) {
            return [round((float) $lat, 6), round((float) $lng, 6)];
        }
        // No pin dropped — find the place from the organiser's Google Maps link.
        $url = trim((string) ($d['mapUrl'] ?? ''));

        return $url !== '' ? app(GoogleMapsPin::class)->resolve($url) : null;
    }

    /** A map link for the venue: the organiser's own link when it is http(s), else the pin they dropped. */
    private function mapUrl(array $d): string
    {
        $u = trim((string) ($d['mapUrl'] ?? ''));
        if ($u !== '' && preg_match('#^https?://#i', $u) && filter_var($u, FILTER_VALIDATE_URL)) {
            return $u;
        }
        $lat = $d['lat'] ?? null;
        $lng = $d['lng'] ?? null;
        if (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180 && ((float) $lat != 0.0 || (float) $lng != 0.0)) {
            return 'https://www.openstreetmap.org/?mlat='.round((float) $lat, 6).'&mlon='.round((float) $lng, 6).'#map=16/'.round((float) $lat, 6).'/'.round((float) $lng, 6);
        }

        return '';
    }

    private function rulesHtml(array $d, string $lang): string
    {
        $ar = $lang === 'ar';
        $h = fn (string $t) => '<div style="font-size:.7rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#8a6d20;margin:0 0 8px">'.e($t).'</div>';
        $out = '';

        $about = FederationHtml::clean(is_string($d['about'] ?? null) ? $d['about'] : '');
        if (trim(html_entity_decode(strip_tags($about))) !== '') {
            $out .= $h($ar ? 'عن البطولة' : 'About this competition').'<div style="margin:0 0 18px">'.$about.'</div>';
        }

        $reqs = array_values(array_filter(array_map(fn ($r) => is_string($r) ? trim($r) : '', (array) ($d['reqs'] ?? [])), fn ($r) => $r !== ''));
        if ($reqs !== []) {
            $out .= $h($ar ? 'المتطلبات' : 'Requirements').'<ol style="margin:0;padding-inline-start:20px">'
                .implode('', array_map(fn ($r) => '<li style="margin:0 0 6px">'.e($r).'</li>', $reqs)).'</ol>';
        }

        return $out !== '' ? $out : '<p style="margin:0;color:#6b7689">'.e($ar
            ? 'لم ينشر المنظم شروطاً لهذه البطولة بعد. راجع المستندات المرفقة إن وُجدت، أو تواصل مع الاتحاد.'
            : 'The organiser has not published competition rules for this event yet. Check the attached documents if there are any, or contact the federation.').'</p>';
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
            // The late fee rides on an entry into something, as the page shows it.
            if (! empty($a['late']) && $items !== []) {
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
        $placed = $this->placements($entry);

        return [
            'eventId' => $e['eventId'], 'entryId' => $e['id'], 'clubId' => $e['clubId'], 'ref' => $e['ref'],
            'club' => $c['name'], 'coach' => $c['coach'], 'phone' => $c['phone'], 'email' => $c['email'], 'address' => $c['address'],
            'logo' => $c['logo'], 'logoShape' => $c['logoShape'], 'ts' => $e['updatedAt'],
            'athletes' => collect($e['athletes'])->where('status', '!=', 'Withdrawn')
                // A deleted account is not an entrant any more.
                ->filter(fn ($a) => array_key_exists((string) $a['athleteId'], $placed['live']))
                ->map(fn ($a) => [
                    'name' => $a['name'], 'gender' => $a['gender'], 'dob' => $a['dob'], 'weight' => $a['weight'], 'nat' => $a['nat'],
                    'natId' => $a['natId'], 'photo' => $a['photo'], 'athleteId' => $a['athleteId'], 'takeoneId' => $a['athleteId'],
                    'types' => $a['types'], 'approval' => $a['approval'],
                ] + ($placed['division'][(string) $a['athleteId']] ?? []))->values()->all(),
        ];
    }

    /**
     * Who on this entry still has an account, and — when the federation's
     * event runs on the platform — the division the EVENT placed each one in.
     * The page used to guess the division from today's age and the generic
     * weight table; the event's own placement is the one the draw uses.
     *
     * @return array{live: array<string, true>, division: array<string, array{ageGroup: string, division: string}>}
     */
    private function placements(FederationEntry $entry): array
    {
        $eventId = $entry->event?->club_event_id;

        $rows = \Illuminate\Support\Facades\DB::table('federation_entry_athletes as ea')
            ->join('federation_athletes as fa', 'fa.id', '=', 'ea.federation_athlete_id')
            ->join('users as u', 'u.id', '=', 'fa.user_id')->whereNull('u.deleted_at')
            ->when($eventId, fn ($q) => $q
                ->leftJoin('club_event_registrations as r', fn ($j) => $j->on('r.user_id', '=', 'u.id')
                    ->where('r.event_id', $eventId)->where('r.role', 'participant'))
                ->leftJoin('event_categories as c', 'c.id', '=', 'r.category_id')
                ->addSelect('c.name as cat', 'c.weight_class', 'c.min_age', 'c.sort_order'))
            ->where('ea.federation_entry_id', $entry->id)
            ->addSelect('fa.takeone_id')
            ->get();

        $live = [];
        $division = [];
        foreach ($rows->sortBy(fn ($r) => $r->sort_order ?? PHP_INT_MAX) as $r) {
            $tk = (string) $r->takeone_id;
            $live[$tk] = true;
            if (isset($division[$tk]) || empty($r->cat)) {
                continue;
            }
            // The page's own vocabulary, so its division keys stay what they were —
            // one mapping, shared with the Statistics tab's bracket.
            $division[$tk] = FederationEventBracket::division((string) $r->cat, $r->weight_class, $r->min_age !== null ? (int) $r->min_age : null);
        }

        return ['live' => $live, 'division' => $division];
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
