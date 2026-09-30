<?php

namespace App\Events\Support\Competition;

/**
 * One sport, as the generic competition engine runs it — read from that
 * sport's own `recipe.php` (app/Events/Sports/<Sport>/recipe.php) by
 * SportRecipes. A recipe is DATA: which building blocks the sport uses and
 * its official numbers. Nothing here names a sport.
 *
 * Shape of a recipe file (every key optional except `result`):
 *
 *   entrants     list of Entrant values the organiser may choose between
 *                (tennis: individual or pair); the first is the default
 *   team_size    ['min' => 5, 'max' => 12] for a team sport
 *   criteria     list of Criterion values its divisions use
 *   contest      a Contest value (default head_to_head)
 *   result       a ResultKind value
 *   formats      list of Format values it runs; the first is the default
 *   draws        may a contest be drawn (football group games, chess)
 *   points       standings points ['win' => 3, 'draw' => 1, 'loss' => 0]
 *   tie_breakers ordered list of keys (points, difference, scored, sets,
 *                games, head_to_head, name)
 *   medals       the bronze convention (both_sf_losers | third_place_match)
 *   scoring      the result kind's rules — sets: best_of, games_to, win_by,
 *                tiebreak_at, final_set; score: extra_time, penalties;
 *                time/distance/marks: better (lower|higher), attempts, unit
 *   venue        mat | court | pitch | table | lane | track | pool | board
 *   officials    list of official role keys (labels: events::competition)
 *   levels       skill levels offered as divisions (padel 1–7…)
 *   age_groups   ['U11' => ['max' => 10], 'Open' => [], 'Veterans' => ['min' => 40]]
 */
final class SportRecipe
{
    public function __construct(public readonly string $key, public readonly array $data) {}

    /** @return list<Entrant> */
    public function entrants(): array
    {
        $list = array_map(fn ($v) => Entrant::from($v), (array) ($this->data['entrants'] ?? ['individual']));

        return $list !== [] ? $list : [Entrant::Individual];
    }

    public function allows(Entrant $entrant): bool
    {
        return in_array($entrant, $this->entrants(), true);
    }

    public function result(): ResultKind
    {
        return ResultKind::from($this->data['result'] ?? 'score');
    }

    public function contest(): Contest
    {
        return Contest::from($this->data['contest'] ?? 'head_to_head');
    }

    /** @return list<Format> */
    public function formats(): array
    {
        return array_map(fn ($v) => Format::from($v), (array) ($this->data['formats'] ?? ['knockout']));
    }

    public function allowsFormat(Format $format): bool
    {
        return in_array($format, $this->formats(), true);
    }

    public function drawsAllowed(): bool
    {
        return (bool) ($this->data['draws'] ?? false);
    }

    public function scoring(): array
    {
        return (array) ($this->data['scoring'] ?? []);
    }

    /** @return list<string> */
    public function levels(): array
    {
        return array_values((array) ($this->data['levels'] ?? []));
    }

    /** @return array<string, array{min?: int, max?: int}> */
    public function ageGroups(): array
    {
        return (array) ($this->data['age_groups'] ?? []);
    }

    /**
     * The profile an event of this sport runs under — the organiser's own
     * choices (entrant, format) where an event has made them.
     */
    public function profile(?Entrant $entrant = null, ?Format $format = null): CompetitionProfile
    {
        $entrant = $entrant && $this->allows($entrant) ? $entrant : $this->entrants()[0];
        $formats = $this->formats();

        if ($format && $this->allowsFormat($format)) {
            $formats = array_values(array_unique(array_merge([$format], $formats), SORT_REGULAR));
        }

        return new CompetitionProfile(
            sport: $this->key,
            entrant: $entrant,
            criteria: array_map(fn ($v) => Criterion::from($v), (array) ($this->data['criteria'] ?? ['age', 'gender'])),
            contest: $this->contest(),
            result: $this->result(),
            formats: $formats,
            points: $this->data['points'] ?? ($this->contest() === Contest::HeadToHead ? ['win' => $this->drawsAllowed() ? 3 : 2, 'draw' => 1, 'loss' => 0] : null),
            tieBreakers: array_values((array) ($this->data['tie_breakers'] ?? ['points', 'difference', 'scored', 'head_to_head', 'name'])),
            medals: $this->data['medals'] ?? null,
            teamSize: $entrant === Entrant::Team ? ($this->data['team_size'] ?? null) : null,
            venue: (string) ($this->data['venue'] ?? 'venue'),
            officials: array_map(fn (string $k) => ['key' => $k, 'label' => __('events::competition.official_'.$k)], (array) ($this->data['officials'] ?? [])),
        );
    }
}
