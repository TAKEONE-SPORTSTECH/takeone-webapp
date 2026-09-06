<?php

namespace App\Events\Generic;

use App\Events\AbstractEventType;
use App\Events\Support\SyncsDivisions;
use App\Models\ClubEvent;
use App\Members\Models\User;

/**
 * TRANSITIONAL catch-all package.
 *
 * Holds the pre-package behaviour for every event type that has not been
 * extracted yet (class, race, belt test, tournament in a non-combat sport,
 * league). It exists so the app keeps working while types are ported one at a
 * time — it is NOT the template for a new type.
 *
 * Do not add type-specific rules here. When a type needs its own behaviour,
 * that is the signal to give it its own package under its sport's folder
 * (app/Events/Sports/<Sport>/<Type>/) and remove its share of this class. This file should shrink with every migration
 * and be deleted when the last type owns its package.
 */
class GenericEvent extends AbstractEventType
{
    use SyncsDivisions;

    public function key(): string
    {
        return 'generic';
    }

    public function label(): string
    {
        return 'Event';
    }

    /** Claims anything nothing else claimed — the registry uses it as the fallback. */
    public function owns(ClubEvent $event): bool
    {
        return true;
    }

    protected function schemaType(): string
    {
        return 'class';
    }

    /** The legacy form showed sections per config; keep that behaviour verbatim. */
    public function formSections(): array
    {
        return [];
    }

    public function validationRules(?ClubEvent $event = null): array
    {
        return parent::validationRules($event) + $this->divisionRules() + [
            'break_start' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'break_end' => ['nullable', 'date_format:H:i', 'after:break_start', 'before_or_equal:end_time'],
            'courts' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sport' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(config('event_schema.sports', [])))],

            'league' => ['nullable', 'array'],
            'league.teams' => ['nullable', 'array', 'max:64'],
            'league.teams.*' => ['nullable', 'string', 'max:80'],
            'league.fixtures' => ['nullable', 'array', 'max:300'],
            'league.fixtures.*.home' => ['nullable', 'string', 'max:80'],
            'league.fixtures.*.away' => ['nullable', 'string', 'max:80'],
            'league.fixtures.*.date' => ['nullable', 'string', 'max:40'],
            'league.fixtures.*.home_score' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'league.fixtures.*.away_score' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function columnsFromInput(array $data, ?ClubEvent $event = null): array
    {
        return parent::columnsFromInput($data, $event) + [
            'sport' => $data['sport'] ?? null,
            'league' => $this->cleanLeague($data['league'] ?? []),
            'courts' => isset($data['courts']) && $data['courts'] !== '' ? (int) $data['courts'] : null,
        ];
    }

    public function saveRelatedData(ClubEvent $event, array $data): void
    {
        $this->syncDivisions($event, $data['divisions'] ?? []);
    }

    public function viewData(ClubEvent $event, User $viewer): array
    {
        return ['league' => $this->leagueView($event->league)];
    }

    /* ---------------- League (moves to a FootballLeague package) ---------------- */

    /** @return array{teams: array, fixtures: array}|null */
    private function cleanLeague(array $league): ?array
    {
        $teams = collect($league['teams'] ?? [])
            ->map(fn ($t) => trim((string) $t))->filter()->values()->all();

        $fixtures = collect($league['fixtures'] ?? [])
            ->map(fn ($f) => [
                'home' => trim((string) ($f['home'] ?? '')),
                'away' => trim((string) ($f['away'] ?? '')),
                'date' => trim((string) ($f['date'] ?? '')),
                'home_score' => isset($f['home_score']) && $f['home_score'] !== '' ? (int) $f['home_score'] : null,
                'away_score' => isset($f['away_score']) && $f['away_score'] !== '' ? (int) $f['away_score'] : null,
            ])
            ->filter(fn ($f) => $f['home'] !== '' && $f['away'] !== '')->values()->all();

        return ($teams || $fixtures) ? ['teams' => $teams, 'fixtures' => $fixtures] : null;
    }

    /** Teams, fixtures and the computed standings table (3-1-0, then GD, then GF). */
    private function leagueView(?array $league): ?array
    {
        if (! $league || (empty($league['teams']) && empty($league['fixtures']))) {
            return null;
        }

        $row = fn ($n) => ['team' => $n, 'p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0, 'gd' => 0, 'pts' => 0];
        $tbl = [];
        foreach ($league['teams'] ?? [] as $t) {
            $tbl[$t] = $row($t);
        }

        foreach ($league['fixtures'] ?? [] as $f) {
            $h = $f['home'];
            $a = $f['away'];
            $tbl[$h] ??= $row($h);
            $tbl[$a] ??= $row($a);
            if ($f['home_score'] === null || $f['away_score'] === null) {
                continue; // unplayed
            }
            $hs = (int) $f['home_score'];
            $as = (int) $f['away_score'];
            $tbl[$h]['p']++;
            $tbl[$a]['p']++;
            $tbl[$h]['gf'] += $hs;
            $tbl[$h]['ga'] += $as;
            $tbl[$a]['gf'] += $as;
            $tbl[$a]['ga'] += $hs;
            if ($hs > $as) {
                $tbl[$h]['w']++;
                $tbl[$h]['pts'] += 3;
                $tbl[$a]['l']++;
            } elseif ($hs < $as) {
                $tbl[$a]['w']++;
                $tbl[$a]['pts'] += 3;
                $tbl[$h]['l']++;
            } else {
                $tbl[$h]['d']++;
                $tbl[$a]['d']++;
                $tbl[$h]['pts']++;
                $tbl[$a]['pts']++;
            }
        }

        $standings = collect($tbl)->map(function ($r) {
            $r['gd'] = $r['gf'] - $r['ga'];

            return $r;
        })->sortBy([['pts', 'desc'], ['gd', 'desc'], ['gf', 'desc']])->values()->all();

        return ['teams' => $league['teams'] ?? [], 'fixtures' => $league['fixtures'] ?? [], 'standings' => $standings];
    }
}
