<?php

namespace App\Events\Support\Competition;

use App\Sports\Combat\CombatSport;

/**
 * How a kind of competition works — the one description every event type
 * gives of itself (EventType::profile()).
 *
 * The platform has one championship engine and many sports. What differs
 * between sports is not the machinery (entries, fees, the draw, the schedule,
 * results, every screen) but six answers:
 *
 *   1. who takes part          Entrant     individual · pair · team
 *   2. what groups them fairly Criterion   age · gender · weight · rank · level · rating
 *   3. what one contest is     Contest     head-to-head · field · judged · measured
 *   4. what is recorded        ResultKind  score · sets · time · distance · marks · place
 *   5. how contests chain      Format      knockout · round robin · groups→knockout · league · swiss · heats
 *   6. how winners are decided standings points, tie-breakers, the medal rule
 *
 * plus where it is played (the venue: mat, court, pitch, table, lane) and who
 * officiates. A screen asks the profile instead of naming a sport — so a new
 * sport is one package filling this in, and nothing shared learns its name.
 *
 * Introduced 2026-09-27 (owner: "come up with a generic idea"). The existing
 * types describe what they ALREADY do; nothing behaves differently because a
 * profile exists. Immutable: build it, read it, never change it.
 */
final class CompetitionProfile
{
    /**
     * @param  list<Criterion>  $criteria
     * @param  list<Format>  $formats  what this type can run; the first is the default
     * @param  array{win: int, draw: int, loss: int}|null  $points  standings points, where results feed a table
     * @param  list<string>  $tieBreakers  in order, stable keys
     * @param  list<array{key: string, label: string}>  $officials
     */
    public function __construct(
        public readonly ?string $sport,
        public readonly Entrant $entrant,
        public readonly array $criteria,
        public readonly ?Contest $contest,
        public readonly ResultKind $result,
        public readonly array $formats,
        public readonly ?array $points = null,
        public readonly array $tieBreakers = [],
        public readonly ?string $medals = null,
        public readonly ?array $teamSize = null,
        public readonly string $venue = 'venue',
        public readonly array $officials = [],
    ) {}

    public function uses(Criterion $criterion): bool
    {
        return in_array($criterion, $this->criteria, true);
    }

    public function runs(Format $format): bool
    {
        return in_array($format, $this->formats, true);
    }

    public function defaultFormat(): ?Format
    {
        return $this->formats[0] ?? null;
    }

    /** Is this a competition at all (a contest between entrants)? */
    public function competes(): bool
    {
        return $this->contest !== null;
    }

    /** The shape every consumer reads — the browser, the MCP, a report. */
    public function toArray(): array
    {
        return [
            'sport' => $this->sport,
            'entrant' => $this->entrant->value,
            'team_size' => $this->teamSize,
            'criteria' => array_map(fn (Criterion $c) => $c->value, $this->criteria),
            'contest' => $this->contest?->value,
            'result' => $this->result->value,
            'formats' => array_map(fn (Format $f) => $f->value, $this->formats),
            'points' => $this->points,
            'tie_breakers' => $this->tieBreakers,
            'medals' => $this->medals,
            'venue' => $this->venue,
            'officials' => $this->officials,
        ];
    }

    /* ---------------- The shapes the platform already runs ---------------- */

    /**
     * Anything that is not a sport package — a seminar, a grading day, a club
     * social, or a sport the platform has no package for yet (padel, until it
     * has one). Individuals, grouped by age and gender if at all, and the only
     * result is a finishing place the organiser types in.
     */
    public static function generic(?string $sport = null): self
    {
        return new self(
            sport: $sport,
            entrant: Entrant::Individual,
            criteria: [Criterion::Age, Criterion::Gender],
            contest: null,
            result: ResultKind::Place,
            formats: [Format::Open],
        );
    }

    /**
     * A bracketed, medal-awarding combat championship — BJJ, Karate, Taekwondo
     * and whatever joins them in config/combat.php. Everything that differs
     * between them (the medal rule, the officials) comes from the sport.
     */
    public static function combatChampionship(CombatSport $sport): self
    {
        return new self(
            sport: $sport->key(),
            entrant: Entrant::Individual,
            criteria: [Criterion::Age, Criterion::Gender, Criterion::Weight, Criterion::Rank],
            contest: Contest::HeadToHead,
            result: ResultKind::Score,
            formats: [Format::Knockout, Format::RoundRobin],
            medals: $sport->bronzeRule(),
            venue: 'mat',
            officials: $sport->officialRoles(),
        );
    }

    /**
     * Practice on a mat — sparring, open mat. Head-to-head and scored, but no
     * divisions, no draw and no medals: two people who decided to fight.
     */
    public static function combatPractice(?CombatSport $sport, ?string $sportKey = null): self
    {
        return new self(
            sport: $sport?->key() ?? $sportKey,
            entrant: Entrant::Individual,
            criteria: [],
            contest: Contest::HeadToHead,
            result: ResultKind::Score,
            formats: [Format::Open],
            venue: 'mat',
            officials: $sport?->officialRoles() ?? [],
        );
    }

    /**
     * A league of teams — any sport played by sides (App\Events\League).
     *
     * @param  array{win: int, draw: int, loss: int}  $points
     */
    public static function teamLeague(?string $sport, array $points): self
    {
        return new self(
            sport: $sport,
            entrant: Entrant::Team,
            criteria: [Criterion::Age, Criterion::Gender],
            contest: Contest::HeadToHead,
            result: ResultKind::Score,
            formats: [Format::League],
            points: $points,
            tieBreakers: ['points', 'difference', 'scored', 'head_to_head', 'name'],
            venue: 'pitch',
        );
    }
}
