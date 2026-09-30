<?php

namespace App\Events\Championship;

use App\Events\AbstractEventType;
use App\Events\Contracts\AwardsDivisions;
use App\Events\Contracts\ProvidesWorkspace;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventPerformance;
use App\Events\Support\Competition\CompetitionProfile;
use App\Events\Support\Competition\EventSides;
use App\Events\Support\Competition\Format;
use App\Events\Support\Competition\SportRecipes;
use App\Events\Support\SyncsDivisions;
use App\Members\Models\User;
use Illuminate\Validation\Rule;

/**
 * A championship in ANY sport that has a recipe — padel, tennis, table tennis,
 * football, basketball, volleyball, swimming, athletics, weightlifting,
 * gymnastics, chess, esports, judo… (app/Events/Sports/<Sport>/recipe.php).
 *
 * The generic competition engine (steps 4–6 of the generic-sports plan,
 * 2026-09-27): one package, every sport, because what differs between them is
 * DATA — who enters (people, pairs, teams), what a contest is, how it is won,
 * how contests chain — and the recipe says it. The shared engines do the rest
 * (App\Events\Support\Competition): Knockout, RoundRobin, Swiss, Heats,
 * Standings, ResultRules, EventSides.
 *
 * ⚠️ Claims ONLY events it created: an event carrying `club_events.competition`
 * (its marker), or a brand-new one being created. Every existing tournament of
 * these sports stays with whatever type ran it — nothing changes hands. And
 * only while `events.sport_championships` is on (OFF by default). A sport with
 * a combat package of its own (BJJ, Karate, Taekwondo) is never claimed.
 */
class Championship extends AbstractEventType implements AwardsDivisions, ProvidesWorkspace
{
    use SyncsDivisions;

    public const KEY = 'sport_championship';

    /** The event kinds it runs, in the platform's own vocabulary (event_schema.types). */
    public const TYPES = ['tournament', 'championship'];

    public function __construct(private SportRecipes $recipes, private Runner $runner) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function owns(ClubEvent $event): bool
    {
        if (! config('events.sport_championships', false)
            || ! in_array($event->event_type, self::TYPES, true)
            || ! $this->recipes->has($event->sport)
            || app(\App\Sports\Combat\SportRegistry::class)->has($event->sport)) {
            return false;
        }

        // Only what it made: a new event, or one carrying its marker.
        return ! $event->exists || is_array($event->competition);
    }

    protected function schemaType(): string
    {
        return 'championship';
    }

    public function profile(?ClubEvent $event = null): CompetitionProfile
    {
        if ($event && $this->recipes->has($event->sport)) {
            return $this->runner->profile($event);
        }

        return CompetitionProfile::generic($event?->sport);
    }

    /* ---------------- creation ---------------- */

    /**
     * What the create form offers per sport — the recipe's formats and who may
     * enter — so the "How it runs" card only ever shows choices this sport
     * accepts. Empty while the switch is off, which keeps the card hidden.
     */
    public function formCatalog(): array
    {
        if (! config('events.sport_championships', false)) {
            return [];
        }

        $combat = app(\App\Sports\Combat\SportRegistry::class);

        return ['competition' => collect($this->recipes->all())
            ->reject(fn (\App\Events\Support\Competition\SportRecipe $r, string $key) => $combat->has($key))
            ->map(fn (\App\Events\Support\Competition\SportRecipe $r) => [
                'formats' => array_map(fn (Format $f) => $f->value, $r->formats()),
                'entrants' => array_map(fn ($e) => $e->value, $r->entrants()),
                'result' => $r->result()->value,
            ])->all()];
    }

    public function formSections(): array
    {
        return ['competition', 'divisions', 'schedule', 'requirements', 'prize'];
    }

    public function validationRules(?ClubEvent $event = null): array
    {
        $recipe = $this->recipes->get(request('sport') ?? $event?->sport);

        return [
            'competition' => ['nullable', 'array'],
            'competition.format' => ['nullable', 'string', Rule::in($recipe ? array_map(fn ($f) => $f->value, $recipe->formats()) : [])],
            'competition.entrant' => ['nullable', 'string', Rule::in($recipe ? array_map(fn ($e) => $e->value, $recipe->entrants()) : [])],
            'competition.group_size' => ['nullable', 'integer', 'min:2', 'max:16'],
            'competition.advance' => ['nullable', 'integer', 'min:1', 'max:4'],
            'competition.swiss_rounds' => ['nullable', 'integer', 'min:1', 'max:15'],
            'competition.finalists' => ['nullable', 'integer', 'min:2', 'max:16'],
            // The same timetable fields a generic event takes.
            'break_start' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'break_end' => ['nullable', 'date_format:H:i', 'after:break_start', 'before_or_equal:end_time'],
            'courts' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sport' => ['nullable', Rule::in(array_keys(config('event_schema.sports', [])))],
        ] + parent::validationRules($event) + $this->divisionRules();
    }

    public function columnsFromInput(array $data, ?ClubEvent $event = null): array
    {
        $recipe = $this->recipes->get($data['sport'] ?? $event?->sport);
        $in = (array) ($data['competition'] ?? []);
        $had = is_array($event?->competition) ? $event->competition : [];

        // ALWAYS written — it is the marker that makes this event ours.
        $competition = array_filter([
            'format' => $in['format'] ?? $had['format'] ?? $recipe?->formats()[0]->value,
            'entrant' => $in['entrant'] ?? $had['entrant'] ?? $recipe?->entrants()[0]->value,
            'group_size' => isset($in['group_size']) ? (int) $in['group_size'] : ($had['group_size'] ?? null),
            'advance' => isset($in['advance']) ? (int) $in['advance'] : ($had['advance'] ?? null),
            'swiss_rounds' => isset($in['swiss_rounds']) ? (int) $in['swiss_rounds'] : ($had['swiss_rounds'] ?? null),
            'finalists' => isset($in['finalists']) ? (int) $in['finalists'] : ($had['finalists'] ?? null),
        ], fn ($v) => $v !== null);

        return [
            'competition' => $competition,
            'sport' => $data['sport'] ?? $event?->sport,
            'courts' => isset($data['courts']) && $data['courts'] !== '' ? (int) $data['courts'] : ($event?->courts),
        ] + parent::columnsFromInput($data, $event);
    }

    public function saveRelatedData(ClubEvent $event, array $data): void
    {
        $this->syncDivisions($event, $data['divisions'] ?? []);
    }

    /* ---------------- run ---------------- */

    public function recordOutcome(ClubEvent $event, int $unitId, array $payload): array
    {
        $contest = EventMatch::where('event_id', $event->id)->findOrFail($unitId);

        return ['ok' => true, 'division' => $this->runner->record($event, $contest, $payload)];
    }

    public function awardDivision(EventCategory $category): array
    {
        return $this->runner->award($category->event, $category);
    }

    public function allowsManualResults(): bool
    {
        return false;   // results are contests, not a winners list typed in
    }

    public function results(ClubEvent $event): array
    {
        return $event->categories()->whereNotNull('podium')->get()
            ->map(fn (EventCategory $c) => ['division' => $c->name, 'medals' => $c->podium])
            ->filter(fn ($r) => ! empty($r['medals']))->values()->all();
    }

    public function brandedSurface(): bool
    {
        return true;
    }

    /* ---------------- the organiser's workspace ---------------- */

    public function workspaceView(): string
    {
        return 'event-sport_championship::mobile.workspace';
    }

    public function workspaceData(ClubEvent $event, User $viewer): array
    {
        $settings = $this->runner->settings($event);
        $recipe = $this->runner->recipe($event);
        $sides = app(EventSides::class);

        return [
            'competition' => [
                'sport' => $event->sport,
                'format' => $settings['format']->value,
                'entrant' => $settings['entrant']->value,
                'settings' => array_intersect_key($settings, Runner::DEFAULTS),
                'result' => $recipe->result()->value,
                'contest' => $recipe->contest()->value,
                'scoring' => $recipe->scoring(),
                'draws' => $recipe->drawsAllowed(),
                'profile' => $this->runner->profile($event)->toArray(),
                'divisions' => $event->categories()->orderBy('sort_order')->orderBy('id')->get()
                    ->reject(fn (EventCategory $c) => $c->isHeading())
                    ->map(fn (EventCategory $c) => $this->runner->division($event, $c))->values()->all(),
                'sides' => $sides->kindFor($event) ? $sides->list($event) : [],
                'entries' => ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')
                    ->with('user:id,full_name,name')->get()
                    ->map(fn ($r) => ['id' => $r->id, 'name' => $r->user?->full_name ?: $r->user?->name, 'category_id' => $r->category_id])->all(),
            ],
        ];
    }

    /* ---------------- the public page ---------------- */

    /**
     * Group tables, round robins and Swiss standings on the public page's
     * `table` section — the SAME shape the league publishes, so the page draws
     * them with no new public screen. Brackets come through the draw section
     * (BracketView reads the contest rows as it reads a combat bracket).
     */
    public function publicView(ClubEvent $event): array
    {
        $tables = [];

        foreach ($event->categories()->orderBy('sort_order')->get()->reject(fn ($c) => $c->isHeading()) as $division) {
            $view = $this->runner->division($event, $division);

            foreach ($view['tables'] as $t) {
                $tables[] = [
                    'division' => trim($division->name.($t['group'] ? ' · '.$t['group'] : '')),
                    'points' => $this->runner->profile($event)->points,
                    'rows' => array_map(fn ($r) => array_intersect_key($r, array_flip(['position', 'name', 'played', 'won', 'drawn', 'lost', 'difference', 'points', 'form'])), $t['rows']),
                    'fixtures' => collect($view['contests'])->where('phase', '!=', null)
                        ->filter(fn ($c) => str_starts_with((string) $c['phase'], 'rr:') || str_starts_with((string) $c['phase'], 'sw:'))
                        ->when($t['group'], fn ($c) => $c->where('group', (int) (ord(substr($t['group'], -1)) - 64)))
                        ->map(fn ($c) => ['matchday' => $c['round'], 'home' => $c['a'], 'away' => $c['b'] ?? '—', 'home_score' => $c['a_score'], 'away_score' => $c['b_score'], 'played' => $c['status'] === 'done', 'kickoff' => $c['time']])
                        ->values()->all(),
                ];
            }
        }

        if ($tables === []) {
            return [];
        }

        return [
            'key' => 'table',
            'icon' => 'bi-table',
            'label' => __('event-sport_championship::messages.standings'),
            'sub' => trans_choice('event-sport_championship::messages.tables_count', count($tables), ['n' => count($tables)]),
            'data' => ['tables' => $tables],
        ];
    }
}
