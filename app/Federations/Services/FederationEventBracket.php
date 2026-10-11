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

    public function of(FederationEvent $fe): ?array
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
            ->get(['r.id', 'r.category_id', 'u.full_name', 'u.name', 'fa.takeone_id', 't.club_name'])
            ->keyBy('id');
        $written = EntryClub::forMany($eventId, $regs->keys()->all());

        $cats = DB::table('event_categories')->where('event_id', $eventId)
            ->whereIn('id', $regs->pluck('category_id')->filter()->unique())
            ->get(['id', 'name', 'gender', 'weight_class', 'min_age', 'sort_order'])->keyBy('id');

        $keyOf = [];
        foreach ($cats as $c) {
            $d = self::division($c->name, $c->weight_class, $c->min_age !== null ? (int) $c->min_age : null);
            $keyOf[$c->id] = (str_starts_with(strtolower((string) $c->gender), 'f') ? 'F' : 'M').'|'.$d['ageGroup'].'|'.$d['division'];
        }

        $who = function (?int $regId) use ($regs, $written, $keyOf) {
            $r = $regId ? $regs->get($regId) : null;
            if (! $r) {
                return null;
            }

            return [
                'name' => (string) ($r->full_name ?: $r->name),
                'club' => (string) ($r->club_name ?: ($written[$r->id]['name'] ?? '—')),
                'id' => $r->takeone_id ?: 'r'.$r->id,
                'cat' => $keyOf[$r->category_id] ?? null,
            ];
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
            ];
        }

        $perCat = $regs->whereNotNull('category_id')->countBy('category_id');
        $catsOut = $cats->sortBy('sort_order')->map(function ($c) use ($keyOf, $perCat, $catMat) {
            $key = $keyOf[$c->id];
            [$g, $age, $div] = explode('|', $key, 3);
            $mats = $catMat[$key] ?? [];
            arsort($mats);

            return [
                'key' => $key,
                'label' => ($g === 'F' ? 'Female' : 'Male').' '.$age.' · '.preg_replace('/^Kumite\s*/i', '', $div),
                'athletes' => (int) ($perCat[$c->id] ?? 0),
                'ring' => (int) (array_key_first($mats) ?? 1),
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
