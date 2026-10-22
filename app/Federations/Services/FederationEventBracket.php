<?php

namespace App\Federations\Services;

use App\Events\Support\EntryClub;
use App\Federations\Models\FederationEvent;
use App\Support\KarateShotukanWeightclasses;
use Illuminate\Support\Facades\DB;

/**
 * The bracket a federation's event ACTUALLY has, for the dashboard's
 * Statistics tab: the divisions the platform event placed its athletes in,
 * the bouts its draw made, which mat each one is on, and every result the
 * mats recorded — read from the platform event (`club_event_id`), never
 * re-drawn in the browser.
 *
 * Only for events that run on the platform; one that does not has no bracket
 * to read, and the tab keeps building the draw it would make.
 */
class FederationEventBracket
{
    public function __construct(private FederationDirectory $directory) {}

    /** `__TAKEONE_FED.brackets`: event uuid => {mats, cats, matches, byes, athletes}. */
    public function payload(array $access): object
    {
        $q = FederationEvent::whereIn('federation_id', collect($this->directory->subtree($access['root']))->pluck('id'))
            ->whereNotNull('club_event_id');
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }

        $out = [];
        foreach ($q->get() as $fe) {
            $b = rescue(fn () => $this->of($fe), null, report: true);
            if ($b) {
                $out[$fe->uuid] = $b;
            }
        }

        return (object) $out;
    }

    /**
     * A platform category as the dashboard names its divisions: the age group
     * and "Kumite -45 kg", so a division keeps one key on every tab.
     *
     * @return array{ageGroup: string, division: string}
     */
    public static function division(?string $name, ?string $weightClass, ?int $minAge): array
    {
        $age = $minAge !== null ? KarateShotukanWeightclasses::ageCategoryOf($minAge) : null;

        return $age && $weightClass
            ? ['ageGroup' => $age, 'division' => 'Kumite '.$weightClass.' kg']
            : ['ageGroup' => $age ?: '—', 'division' => (string) $name];
    }

    /**
     * @param  bool  $withEntry  also carry each person's registration id
     *                           (`reg`) — for the server-side PDF only, which
     *                           needs it for the flag and crest. Never set for
     *                           the dashboard payload: an entry id is internal.
     */
    public function of(FederationEvent $fe, bool $withEntry = false): ?array
    {
        $eventId = (int) $fe->club_event_id;
        if (! $eventId) {
            return null;
        }

        $regs = DB::table('club_event_registrations as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')->whereNull('u.deleted_at')
            ->leftJoin('federation_athletes as fa', 'fa.user_id', '=', 'u.id')
            ->leftJoin('tenants as t', fn ($j) => $j->on('t.id', '=', 'r.representing_tenant_id')->whereNull('t.deleted_at'))
            ->where('r.event_id', $eventId)->where('r.role', 'participant')
            ->get(['r.id', 'r.category_id', 'r.weight', 'u.full_name', 'u.name', 'u.uuid', 'fa.takeone_id', 't.club_name', 't.logo as club_logo'])
            ->keyBy('id');
        $written = EntryClub::forMany($eventId, $regs->keys()->all());

        // Each entrant's face for the Results tab (2026-10-17): CompetitorFace's
        // reading — the desk photograph, else the member's own PUBLISHED
        // picture — and the gender silhouette when there is neither.
        $faces = \App\Events\Models\ClubEventRegistration::whereIn('id', $regs->keys()->all())
            ->with('user:id,gender,profile_picture,profile_picture_is_public,updated_at')
            ->get(['id', 'user_id', 'photo', 'updated_at'])
            ->mapWithKeys(fn ($e) => [$e->id => \App\Events\Support\CompetitorFace::for($e)
                ?: \App\Members\Support\Avatar::placeholder($e->user?->gender)]);

        // Each entrant's flag (2026-10-17): the event's own rule —
        // ClubEventRegistration::countryCode(), club country or nationality per
        // FlagSource — as the self-hosted file (App\Support\Flag).
        $flags = \App\Events\Models\ClubEventRegistration::whereIn('id', $regs->keys()->all())
            ->with(['user:id,nationality', 'representingTenant:id,country'])->get()
            ->mapWithKeys(fn ($e) => [$e->id => \App\Support\Flag::iso2($e->countryCode())]);

        $cats = DB::table('event_categories')->where('event_id', $eventId)
            ->whereIn('id', $regs->pluck('category_id')->filter()->unique())
            ->get(['id', 'name', 'gender', 'weight_class', 'min_age', 'sort_order', 'podium'])->keyBy('id');

        $keyOf = [];
        foreach ($cats as $c) {
            $d = self::division($c->name, $c->weight_class, $c->min_age !== null ? (int) $c->min_age : null);
            $keyOf[$c->id] = (str_starts_with(strtolower((string) $c->gender), 'f') ? 'F' : 'M').'|'.$d['ageGroup'].'|'.$d['division'];
        }

        $who = function (?int $regId) use ($regs, $written, $keyOf, $faces, $flags, $withEntry) {
            $r = $regId ? $regs->get($regId) : null;
            if (! $r) {
                return null;
            }

            return [
                'name' => (string) ($r->full_name ?: $r->name),
                'club' => (string) ($r->club_name ?: ($written[$r->id]['name'] ?? '—')),
                'id' => $r->takeone_id ?: 'r'.$r->id,
                'cat' => $keyOf[$r->category_id] ?? null,
                'photo' => $faces[$r->id] ?? null,
                // Their public profile (people.show authorises every request).
                'profile' => $r->uuid ? route('people.show', $r->uuid) : null,
                // Flag, crest and weight for the Results tab (2026-10-17). The crest
                // is the club named above: the represented club's, else the
                // organiser's written-down one. Weight as the desk writes it — the
                // scale's reading once signed, the declared one before.
                'iso' => ($iso = $flags[$r->id] ?? null) ? strtolower($iso) : null,
                'flag' => $iso ? \App\Support\Flag::url($iso) : null,
                'logo' => $r->club_name ? ($r->club_logo ? file_url($r->club_logo) : null) : ($written[$r->id]['logo'] ?? null),
                'kg' => $r->weight === null ? null : rtrim(rtrim(number_format((float) $r->weight, 2, '.', ''), '0'), '.'),
            ] + ($withEntry ? ['reg' => (int) $r->id] : []);
        };

        $rows = DB::table('event_matches')->where('event_id', $eventId)->whereIn('category_id', $cats->keys())
            ->orderBy('category_id')->orderBy('match_no')->orderBy('id')->get();

        $mat = fn (?string $court) => preg_match('/(\d+)/', (string) $court, $m) ? max(1, (int) $m[1]) : null;
        $matches = [];
        $byes = [];
        $catMat = [];
        foreach ($rows as $m) {
            $key = $keyOf[$m->category_id] ?? null;
            if (! $key) {
                continue;
            }
            $ring = $mat($m->court);
            if ($ring) {
                $catMat[$key][$ring] = ($catMat[$key][$ring] ?? 0) + 1;
            }
            if ($m->won_by_bye) {
                $byes[] = ['cat' => $key, 'ring' => $ring ?: 1];

                continue;
            }
            $a = $who($m->a_competitor_id);
            $b = $who($m->b_competitor_id);
            $w = in_array($m->winner, ['a', 'b'], true) && $a && $b ? $m->winner : null;
            $matches[] = [
                'no' => (int) ($m->match_no ?: count($matches) + 1),
                'cat' => $key,
                'round' => $this->round((string) $m->round),
                'ring' => $ring ?: 1,
                'A' => $a, 'B' => $b,
                'w' => $w,
                'by' => $w ? $this->how($m->win_reason, $m->a_score, $m->b_score) : null,
                'done' => (bool) $w,
                'ready' => (bool) ($a && $b),
                // The Draw tab's Bouts list (2026-10-17): scores, the mat as named, the day.
                'sa' => $m->a_score, 'sb' => $m->b_score,
                'court' => $m->court, 'day' => $m->day !== null ? (int) $m->day : null,
            ];
        }

        $perCat = $regs->whereNotNull('category_id')->countBy('category_id');

        // Each division's draw, opened on that division (the Results tab,
        // 2026-10-17). The draw desk decides for itself: whoever may arrange it
        // gets the desk, anyone else is sent to the ordinary draw page — and
        // Back returns to this event's Results tab.
        $eventUuid = DB::table('club_events')->where('id', $eventId)->value('uuid');
        $drawOf = fn (int $catId) => $eventUuid && \Illuminate\Support\Facades\Route::has('me.events.draw-desk')
            ? route('me.events.draw-desk', ['event' => $eventUuid, 'division' => $catId, 'back' => '/federation/events/'.$fe->uuid.'/results'])
            : null;
        $catsOut = $cats->sortBy('sort_order')->map(function ($c) use ($keyOf, $perCat, $catMat, $who, $drawOf, $withEntry, $regs) {
            $key = $keyOf[$c->id];
            [$g, $age, $div] = explode('|', $key, 3);
            $mats = $catMat[$key] ?? [];
            arsort($mats);

            return [
                'key' => $key,
                // Named as the event names it ("Séniors Men -75 kg"), like its divisions page.
                'label' => trim((string) $c->name) !== '' ? (string) $c->name : ($g === 'F' ? 'Female' : 'Male').' '.$age.' · '.preg_replace('/^Kumite\s*/i', '', $div),
                'athletes' => (int) ($perCat[$c->id] ?? 0),
                // The one entrant of a division of one — Results lists it as a walkover.
                'only' => (int) ($perCat[$c->id] ?? 0) === 1 ? (string) ($who((int) $regs->firstWhere('category_id', $c->id)?->id)['name'] ?? '') : '',
                'draw' => $drawOf((int) $c->id),
                'ring' => (int) (array_key_first($mats) ?? 1),
                // The division's stored podium (event_categories.podium), for the
                // Draw tab's Results: place + name only.
                'podium' => collect(json_decode((string) $c->podium, true) ?: [])
                    ->filter(fn ($p) => is_array($p) && ! empty($p['name']))
                    ->map(fn ($p) => ['place' => (int) ($p['place'] ?? 0), 'name' => (string) $p['name'],
                        'club' => (string) ($who(isset($p['competitor_id']) ? (int) $p['competitor_id'] : null)['club'] ?? ''),
                        'photo' => $who(isset($p['competitor_id']) ? (int) $p['competitor_id'] : null)['photo'] ?? \App\Members\Support\Avatar::placeholder(null),
                        'profile' => $who(isset($p['competitor_id']) ? (int) $p['competitor_id'] : null)['profile'] ?? null]
                        + array_intersect_key($who(isset($p['competitor_id']) ? (int) $p['competitor_id'] : null) ?? [], array_flip(['iso', 'flag', 'logo', 'kg']))
                        + ($withEntry ? ['reg' => $who(isset($p['competitor_id']) ? (int) $p['competitor_id'] : null)['reg'] ?? null] : []))
                    ->sortBy('place')->values()->all(),
            ];
        })->values()->all();

        $declared = (int) (((array) $fe->details)['mats'] ?? 0);
        $used = collect($matches)->pluck('ring')->merge(collect($catsOut)->pluck('ring'))->max() ?: 1;

        return [
            'mats' => max(1, $declared, $used),
            'cats' => $catsOut,
            'matches' => $matches,
            'byes' => $byes,
            'athletes' => $regs->whereNotNull('category_id')->count(),
        ];
    }

    /** The draw's round names in the tab's own words. */
    private function round(string $round): string
    {
        return match (strtolower(preg_replace('/[\s_-]+/', '', $round))) {
            'final' => 'Final',
            'semifinal' => 'Semi-final',
            'quarterfinal' => 'Quarter-final',
            default => $round !== '' ? $round : 'Bout',
        };
    }

    /**
     * How a bout was won, in the tab's five words. The mat records Karate's
     * own (WKF) reasons; a bout with no reason, or "points", was decided on the
     * scoreboard, and "other" with no score is the referees' call.
     */
    private function how(?string $reason, $aScore, $bScore): string
    {
        return match ($reason) {
            'hansoku', 'shikkaku' => 'Disqualification',
            'kiken', 'medical', 'no_show' => 'Withdrawal',
            'ippon' => 'Ippon',
            'hantei', 'decision' => 'Decision',
            // "other" is what the mat writes for a bout won on the scoreboard;
            // level scores mean the referees decided it.
            'other' => is_numeric($aScore) && is_numeric($bScore) && (float) $aScore !== (float) $bScore ? 'Points' : 'Decision',
            default => 'Points',
        };
    }
}
