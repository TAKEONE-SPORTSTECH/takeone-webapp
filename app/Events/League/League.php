<?php

namespace App\Events\League;

use App\Events\AbstractEventType;
use App\Events\League\Models\LeagueFixture;
use App\Events\League\Models\LeagueTeam;
use App\Members\Models\User;
use App\Events\Models\ClubEvent;
use Illuminate\Support\Carbon;

/**
 * A league — a season of fixtures and a table, for any sport played by sides.
 *
 * ── Why it is its own package ───────────────────────────────────────────────
 *
 * Everything else this platform runs is a KNOCKOUT: a draw is cut, a winner
 * walks forward, and a division ends when its final does. A league is the other
 * shape of competition entirely —
 *
 *   · its competitor is a TEAM, not a person's entry;
 *   · everybody plays everybody, so there is no draw to cut and nothing to
 *     advance;
 *   · a match can be DRAWN, which no bout on a mat can be;
 *   · it is decided by a TABLE, recomputed from every result, over weeks.
 *
 * None of that is a branch inside a bracket. It is a different domain sharing a
 * calendar entry, which is exactly what an event package is for.
 *
 * ── Cross-sport on purpose ──────────────────────────────────────────────────
 *
 * Football, futsal, basketball, volleyball and a club's own five-a-side all run
 * the same season and read the same table; what differs is the WORD for a goal,
 * and a table does not care. So this claims any sport, and the two combat
 * packages above it in the registry claim their own event types first.
 *
 * ── What replaced what ──────────────────────────────────────────────────────
 *
 * Before this, a league was a JSON blob on `club_events.league` — team names as
 * strings, fixtures as loose rows, standings computed inline in the generic
 * bucket, no fixture generation and nothing that could be queried. That data is
 * still read once, on first use, and turned into real rows (`importLegacy`), so
 * an organiser who typed a season into the old form keeps it.
 *
 * Delete `app/Events/League/`, its two tables and its registry line and leagues
 * are gone; every other event type is untouched.
 */
class League extends AbstractEventType
{
    /** `club_events.event_type` for a season. */
    public const TYPE = 'league';

    public function __construct(private Season $season, private Table $table) {}

    public function key(): string
    {
        return self::TYPE;
    }

    /** The `config/event_schema.php` type this package presents as. */
    protected function schemaType(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('event-league::messages.label');
    }

    public function icon(): string
    {
        return 'bi-table';
    }

    public function color(): string
    {
        return '#0f766e';
    }

    /**
     * A league of TEAMS — and only for a sport that is played by teams.
     *
     * ⚠️ A martial art run "as a league" is a different thing entirely, and it
     * must not land here. A BJJ organiser who wants pools instead of brackets
     * wants their own weight classes run as round robins: the same entrants,
     * the same weigh-in, the same mats, the same medals — only the shape of the
     * play changes, and `event_categories.format` has decided that per class
     * since long before this package existed. Handing them a fixture list of
     * teams over six matchdays would be answering a question nobody asked.
     *
     * So a sport with a combat package keeps it, whatever the organiser types
     * in the event-type box, and this claims the rest.
     */
    public function owns(ClubEvent $event): bool
    {
        if ((string) $event->event_type !== self::TYPE) {
            return false;
        }

        return ! app(\App\Sports\Combat\SportRegistry::class)->has($event->sport);
    }

    /* ---------------- Creation ---------------- */

    public function validationRules(?ClubEvent $event = null): array
    {
        return parent::validationRules($event) + [
            'league' => ['nullable', 'array'],
            // The shared create form types team names straight in; they become
            // real rows in saveRelatedData(). Fixtures typed there are kept for
            // the same reason.
            'league.teams' => ['nullable', 'array', 'max:64'],
            'league.teams.*' => ['nullable', 'string', 'max:80'],
            'league.fixtures' => ['nullable', 'array', 'max:400'],
            'league.fixtures.*.home' => ['nullable', 'string', 'max:80'],
            'league.fixtures.*.away' => ['nullable', 'string', 'max:80'],
            'league.fixtures.*.date' => ['nullable', 'string', 'max:40'],
            'league.fixtures.*.home_score' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'league.fixtures.*.away_score' => ['nullable', 'integer', 'min:0', 'max:1000'],
            // What a result is WORTH. Three for a win is football's convention,
            // not the platform's.
            'league.points_win' => ['nullable', 'integer', 'min:0', 'max:10'],
            'league.points_draw' => ['nullable', 'integer', 'min:0', 'max:10'],
            'league.points_loss' => ['nullable', 'integer', 'min:0', 'max:10'],
            // One leg or two — everybody once, or home and away.
            'league.rounds' => ['nullable', 'integer', 'min:1', 'max:4'],
        ];
    }

    public function formSections(): array
    {
        return ['league', 'schedule', 'requirements', 'prize'];
    }

    public function columnsFromInput(array $data, ?ClubEvent $event = null): array
    {
        $league = is_array($data['league'] ?? null) ? $data['league'] : [];

        // Settings live on the event; the teams and the fixtures live in their
        // own tables from here on. The typed lists are kept in the column only
        // until saveRelatedData() has turned them into rows.
        return [
            'league' => [
                'points' => [
                    'win' => (int) ($league['points_win'] ?? Table::DEFAULT_POINTS['win']),
                    'draw' => (int) ($league['points_draw'] ?? Table::DEFAULT_POINTS['draw']),
                    'loss' => (int) ($league['points_loss'] ?? Table::DEFAULT_POINTS['loss']),
                ],
                'rounds' => (int) ($league['rounds'] ?? 1),
            ],
            // A league has no weight classes and no medals table.
            'max_capacity' => null,
        ];
    }

    public function saveRelatedData(ClubEvent $event, array $data): void
    {
        $league = is_array($data['league'] ?? null) ? $data['league'] : [];

        foreach ($league['teams'] ?? [] as $i => $name) {
            $this->addTeam($event, (string) $name, $i);
        }

        foreach ($league['fixtures'] ?? [] as $row) {
            $this->importFixture($event, (array) $row);
        }
    }

    /* ---------------- Lifecycle ---------------- */

    public function stages(): array
    {
        return ['draft', 'scheduled', 'running', 'completed'];
    }

    /**
     * Derived from the season itself, never stored: no teams is a draft, no
     * result yet is scheduled, every fixture played is completed.
     */
    public function stage(ClubEvent $event): string
    {
        if ($event->status === 'cancelled') {
            return 'cancelled';
        }

        $fixtures = LeagueFixture::where('event_id', $event->id)->get(['status', 'home_score', 'away_score']);

        if ($fixtures->isEmpty()) {
            return LeagueTeam::where('event_id', $event->id)->exists() ? 'scheduled' : 'draft';
        }

        $live = $fixtures->reject(fn ($f) => in_array($f->status, [LeagueFixture::CANCELLED, LeagueFixture::POSTPONED], true));

        if ($live->isNotEmpty() && $live->every(fn ($f) => $f->status === LeagueFixture::PLAYED)) {
            return 'completed';
        }

        return $live->contains(fn ($f) => $f->status === LeagueFixture::PLAYED) ? 'running' : 'scheduled';
    }

    public function canTransitionTo(ClubEvent $event, string $stage): bool
    {
        return $stage === 'cancelled' && $this->stage($event) !== 'completed';
    }

    /*
     * ---------------- Entry ----------------
     *
     * A league has no weight classes, so nothing classifies an entrant — the
     * base class already answers null and this package adds nothing. People may
     * still register for a league event (a squad list, a spectator ticket);
     * they simply are not what the competition is made of. Its competitors are
     * TEAMS, and they are managed on the league's own screen.
     */

    /* ---------------- Running it ---------------- */

    public function availableActions(ClubEvent $event): array
    {
        $actions = [];

        if (LeagueTeam::where('event_id', $event->id)->whereNull('withdrawn_at')->count() >= 2) {
            $actions[] = [
                'action' => 'generate_fixtures',
                'label' => __('event-league::messages.action_generate'),
                'icon' => 'bi-calendar-week',
            ];
        }

        if (LeagueFixture::where('event_id', $event->id)->exists()) {
            $actions[] = [
                'action' => 'clear_fixtures',
                'label' => __('event-league::messages.action_clear'),
                'icon' => 'bi-eraser',
            ];
        }

        return $actions;
    }

    public function performAction(ClubEvent $event, string $action, array $payload = []): array
    {
        return match ($action) {
            'generate_fixtures' => $this->generateFixtures($event, $payload),
            'clear_fixtures' => $this->clearFixtures($event),
            default => ['success' => false, 'message' => __('event-league::messages.action_unknown')],
        };
    }

    /** @return array{success: bool, message: string} */
    public function generateFixtures(ClubEvent $event, array $payload = []): array
    {
        $rounds = (int) ($payload['rounds'] ?? $this->settings($event)['rounds'] ?? 1);
        $result = $this->season->generate($event, $rounds);

        if ($result['created'] === 0 && $result['kept'] === 0) {
            return ['success' => false, 'message' => __('event-league::messages.need_two_teams')];
        }

        $this->broadcast($event, ['action' => 'league']);

        return [
            'success' => true,
            'message' => __('event-league::messages.fixtures_made', [
                'count' => $result['created'],
                'matchdays' => $result['matchdays'],
            ]),
        ] + $result;
    }

    /** Only the unplayed ones — a result outlives a change of plan. */
    public function clearFixtures(ClubEvent $event): array
    {
        $removed = LeagueFixture::where('event_id', $event->id)
            ->where('status', '!=', LeagueFixture::PLAYED)->delete();

        return ['success' => true, 'message' => __('event-league::messages.fixtures_cleared', ['count' => $removed])];
    }

    /**
     * A result, from the shared outcome door.
     *
     * `$unitId` is a fixture, and the payload carries two scores rather than a
     * winner — a league match is 2-2 as often as it is won.
     */
    public function recordOutcome(ClubEvent $event, int $unitId, array $payload): array
    {
        $fixture = LeagueFixture::where('event_id', $event->id)->whereKey($unitId)->first();

        if (! $fixture) {
            return ['success' => false, 'ok' => false, 'message' => __('event-league::messages.no_fixture')];
        }

        /*
         * ⚠️ ABSENT IS NOT "CLEAR".
         *
         * A league match is two scores, and this door is also reachable from
         * the SHARED outcome endpoint, whose payload speaks a bracket's
         * language (`winner`, `a_score`, `b_score`). Reading those absent keys
         * as "clear the result" meant a generic call erased a played fixture
         * and reported success. Saying nothing about the score now changes
         * nothing; clearing stays available and stays explicit — send the keys
         * with an empty value.
         */
        if (! array_key_exists('home_score', $payload) && ! array_key_exists('away_score', $payload)) {
            return ['success' => false, 'ok' => false, 'message' => __('event-league::messages.result_needs_scores')];
        }

        $home = $payload['home_score'] ?? null;
        $away = $payload['away_score'] ?? null;

        if ($home === null || $away === null || $home === '' || $away === '') {
            // Clearing a result is a legitimate act: a score entered against
            // the wrong fixture has to be removable, and the table must forget
            // it the moment it is.
            $fixture->update([
                'home_score' => null, 'away_score' => null,
                'status' => LeagueFixture::SCHEDULED, 'recorded_by' => null, 'recorded_at' => null,
            ]);

            return ['success' => true, 'message' => __('event-league::messages.result_cleared'), 'fixture' => $this->fixtureRow($fixture->fresh())];
        }

        $fixture->update([
            'home_score' => max(0, (int) $home),
            'away_score' => max(0, (int) $away),
            'status' => LeagueFixture::PLAYED,
            'recorded_by' => auth()->id(),
            'recorded_at' => now(),
        ]);

        /*
         * Everybody else looking at this league is looking at a table that just
         * moved. A refresh signal rather than the table itself: what each
         * reader may see differs (an organiser has the score controls, a
         * stranger does not), so each re-reads its own (CLAUDE.md → Realtime).
         */
        $this->broadcast($event, ['action' => 'league', 'matchday' => $fixture->matchday]);

        return [
            'success' => true,
            'message' => __('event-league::messages.result_saved'),
            'fixture' => $this->fixtureRow($fixture->fresh()),
            // The actor's own screen patches from here, without a round trip.
            'table' => $this->table->build($event, $this->settings($event)['points']),
        ];
    }

    /* ---------------- Outputs ---------------- */

    /** The finishing order, once every fixture has been played. */
    public function results(ClubEvent $event): array
    {
        if ($this->stage($event) !== 'completed') {
            return [];
        }

        $table = $this->table->build($event, $this->settings($event)['points']);

        return [[
            'division' => $event->title,
            'class' => null,
            'medals' => collect($table)->take(4)->map(fn (array $row) => [
                'place' => $row['position'],
                'name' => $row['name'],
                'competitor_id' => null,
            ])->all(),
        ]];
    }

    public function allowsManualResults(): bool
    {
        return false;   // the table decides, and it is derived from the fixtures
    }

    /** Matchdays, with the date each falls on. */
    public function timeline(ClubEvent $event): array
    {
        return LeagueFixture::where('event_id', $event->id)
            ->orderBy('matchday')->get()
            ->groupBy('matchday')
            ->map(fn ($day, $no) => [
                'label' => __('event-league::messages.matchday', ['no' => $no]),
                'date' => optional($day->first()->kickoff_at)->toDateString(),
                'note' => __('event-league::messages.matchday_note', ['count' => $day->count()]),
            ])->values()->all();
    }

    /** The next fixture a member's club is playing in. */
    public function nextUp(ClubEvent $event, User $athlete): ?array
    {
        $teamIds = LeagueTeam::where('event_id', $event->id)
            ->whereIn('tenant_id', $athlete->memberClubs()->pluck('tenants.id'))
            ->pluck('id');

        if ($teamIds->isEmpty()) {
            return null;
        }

        $fixture = LeagueFixture::where('event_id', $event->id)
            ->where('status', LeagueFixture::SCHEDULED)
            ->where(fn ($q) => $q->whereIn('home_team_id', $teamIds)->orWhereIn('away_team_id', $teamIds))
            ->orderBy('matchday')->with(['home', 'away'])->first();

        return $fixture ? [
            'label' => __('event-league::messages.matchday', ['no' => $fixture->matchday]),
            'title' => $fixture->home?->name.' v '.$fixture->away?->name,
            'at' => $fixture->kickoff_at?->toIso8601String(),
        ] : null;
    }

    /* ---------------- Screens ---------------- */

    /**
     * The league takes over the BOARD — the screen every event links to as its
     * draw. There is no bracket to show and a table is what that door is for
     * here, so the tile a reader already knows leads to the right place without
     * one shared view being edited.
     */
    /**
     * A league is its own app, in the organiser's name — the same decision the
     * championships made.
     *
     * It also settles the WIDTH question: a branded surface is served from one
     * blade at every width (App\Events\Middleware\BrandEventPage), which is what
     * the public poster does too. So `/me/events/{uuid}` and `/e/{uuid}` are
     * the same page in the same shape on a phone and on a laptop, rather than
     * three layouts of one event.
     */
    public function brandedSurface(): bool
    {
        return true;
    }

    public function views(): array
    {
        return [
            'bracket' => [
                'mobile' => 'event-league::mobile.table',
                'desktop' => 'event-league::desktop.table',
            ],
        ];
    }

    public function bracketView(ClubEvent $event, ?User $viewer = null): array
    {
        return [];   // nothing here is a bracket
    }

    public function viewData(ClubEvent $event, User $viewer): array
    {
        $this->hydrateLegacy($event);

        $settings = $this->settings($event);
        $table = $this->table->build($event, $settings['points']);

        return ['league' => [
            'settings' => $settings,
            'teams' => $this->teamRows($event),
            // The package's own rows — ids, crests, statuses — for its board.
            'matches' => $this->fixtureRows($event),
            'table' => $table,
            'stage' => $this->stage($event),

            /*
             * The SHARED event page has rendered a league's standings and
             * fixtures since long before this package existed, in its own
             * shape. It still does, and it must keep working — so the same
             * table is handed over in the words that page speaks rather than a
             * second table being written for it. One reading of the season,
             * two readers.
             */
            'standings' => collect($table)->map(fn (array $row) => [
                'team' => $row['name'],
                'p' => $row['played'], 'w' => $row['won'], 'd' => $row['drawn'], 'l' => $row['lost'],
                'gf' => $row['scored'], 'ga' => $row['conceded'], 'gd' => $row['difference'], 'pts' => $row['points'],
            ])->all(),

            /*
             * ...and the fixtures in that page's words too: two NAMES and a
             * date, not the package's own rows. It renders `$f['home']` into
             * the markup directly, so handing it a team object there is a 500
             * on a page that has nothing to do with this package. The rich rows
             * are `matches`, above.
             */
            'fixtures' => collect($this->fixtureRows($event))->map(fn (array $row) => [
                'home' => $row['home']['name'],
                'away' => $row['away']['name'],
                'date' => $row['kickoff'],
                'home_score' => $row['home_score'],
                'away_score' => $row['away_score'],
            ])->all(),
        ]];
    }

    /**
     * A league that was typed into the old form, brought across once.
     *
     * Before this package a season lived as JSON on `club_events.league`: team
     * names as strings, fixtures as loose rows, a table computed inline and
     * nothing that could be queried. Those events still exist and their
     * organisers still open them, so the first time one is read the names
     * become teams and the rows become fixtures — after which the tables are
     * the only truth and the column is left alone.
     *
     * Guarded three ways so it can only ever run once: there must be no teams
     * yet, there must be something to import, and the event is stamped when it
     * is done.
     */
    public function hydrateLegacy(ClubEvent $event): void
    {
        $legacy = is_array($event->league) ? $event->league : [];

        if (($legacy['imported_at'] ?? null) !== null) {
            return;
        }

        if ($legacy === [] || (empty($legacy['teams']) && empty($legacy['fixtures']))) {
            return;
        }

        if (LeagueTeam::where('event_id', $event->id)->exists()) {
            return;
        }

        foreach ($legacy['teams'] ?? [] as $i => $name) {
            $this->addTeam($event, (string) $name, (int) $i);
        }

        foreach ($legacy['fixtures'] ?? [] as $row) {
            $this->importFixture($event, (array) $row);
        }

        $event->forceFill(['league' => $legacy + ['imported_at' => now()->toIso8601String()]])->save();
    }

    public function runData(ClubEvent $event, User $viewer): array
    {
        return $this->viewData($event, $viewer);
    }

    /**
     * The league on its PUBLIC page: the table, and the fixtures it came from.
     *
     * A season's table is the most public thing about it — it is what a parent
     * follows a link to find out, and every club in it posts a photograph of
     * the same eight rows. So it is published in full.
     *
     * Narrowed to names and numbers on the way out: no team ids, no crest
     * paths, no kick-off addresses beyond the venue the organiser already
     * printed on the poster. Nothing here identifies a person at all — a league
     * is played by sides, and the sides are what is on show.
     */
    public function publicView(ClubEvent $event): array
    {
        $this->hydrateLegacy($event);

        $settings = $this->settings($event);
        $table = $this->table->build($event, $settings['points']);

        if ($table === []) {
            return [];   // no teams yet: no door, rather than an empty one
        }

        $played = collect($this->fixtureRows($event))->where('played', true)->count();

        return [
            'key' => 'table',
            'icon' => 'bi-table',
            'label' => __('event-league::messages.title'),
            'sub' => trans_choice('event-league::messages.public_played', $played, ['count' => $played]),
            'data' => ['tables' => [[
                'division' => null,          // a league is one table, and it is the event
                'points' => $settings['points'],
                'rows' => collect($table)->map(fn (array $row) => [
                    'position' => $row['position'],
                    'name' => $row['name'],
                    'played' => $row['played'],
                    'won' => $row['won'],
                    'drawn' => $row['drawn'],
                    'lost' => $row['lost'],
                    'difference' => $row['difference'],
                    'points' => $row['points'],
                    'form' => $row['form'],
                ])->all(),
                'fixtures' => collect($this->fixtureRows($event))->map(fn (array $row) => [
                    'matchday' => $row['matchday'],
                    'home' => $row['home']['name'],
                    'away' => $row['away']['name'],
                    'home_score' => $row['home_score'],
                    'away_score' => $row['away_score'],
                    'played' => $row['played'],
                    'kickoff' => $row['kickoff'],
                ])->all(),
            ]]],
        ];
    }

    /* ---------------- The package's own helpers ---------------- */

    /** @return array{points: array{win: int, draw: int, loss: int}, rounds: int} */
    /** A league of teams; the points are the organiser's, where an event is given. */
    public function profile(?ClubEvent $event = null): \App\Events\Support\Competition\CompetitionProfile
    {
        return \App\Events\Support\Competition\CompetitionProfile::teamLeague(
            $event?->sport,
            $event ? $this->settings($event)['points'] : Table::DEFAULT_POINTS,
        );
    }

    public function settings(ClubEvent $event): array
    {
        $stored = is_array($event->league) ? $event->league : [];

        return [
            'points' => [
                'win' => (int) ($stored['points']['win'] ?? Table::DEFAULT_POINTS['win']),
                'draw' => (int) ($stored['points']['draw'] ?? Table::DEFAULT_POINTS['draw']),
                'loss' => (int) ($stored['points']['loss'] ?? Table::DEFAULT_POINTS['loss']),
            ],
            'rounds' => (int) ($stored['rounds'] ?? 1),
        ];
    }

    /** Add a side, or return the one already carrying that name. */
    public function addTeam(ClubEvent $event, string $name, int $sort = 0, ?int $tenantId = null): ?LeagueTeam
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return LeagueTeam::firstOrCreate(
            ['event_id' => $event->id, 'name' => $name],
            ['sort_order' => $sort, 'tenant_id' => $tenantId],
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function teamRows(ClubEvent $event): array
    {
        return LeagueTeam::where('event_id', $event->id)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (LeagueTeam $t) => [
                'id' => $t->id,
                'uuid' => $t->uuid,
                'name' => $t->name,
                'short_name' => $t->short_name,
                'logo' => $t->logo ? file_url($t->logo) : null,
                'country' => $t->country,
                'club_id' => $t->tenant_id,
                'withdrawn' => $t->withdrawn_at !== null,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function fixtureRows(ClubEvent $event): array
    {
        return LeagueFixture::where('event_id', $event->id)
            ->with(['home:id,name,short_name,logo', 'away:id,name,short_name,logo'])
            ->orderBy('matchday')->orderBy('id')->get()
            ->map(fn (LeagueFixture $f) => $this->fixtureRow($f))
            ->all();
    }

    /** @return array<string, mixed> */
    public function fixtureRow(LeagueFixture $fixture): array
    {
        $fixture->loadMissing(['home:id,name,short_name,logo', 'away:id,name,short_name,logo']);

        return [
            'id' => $fixture->id,
            'uuid' => $fixture->uuid,
            'matchday' => $fixture->matchday,
            'home' => ['id' => $fixture->home_team_id, 'name' => $fixture->home?->name, 'logo' => $fixture->home?->logo ? file_url($fixture->home->logo) : null],
            'away' => ['id' => $fixture->away_team_id, 'name' => $fixture->away?->name, 'logo' => $fixture->away?->logo ? file_url($fixture->away->logo) : null],
            'home_score' => $fixture->home_score,
            'away_score' => $fixture->away_score,
            'status' => $fixture->status,
            'played' => $fixture->isPlayed(),
            'kickoff_at' => $fixture->kickoff_at?->toIso8601String(),
            'kickoff' => $fixture->kickoff_at ? \App\Support\Cldr::shortDate($fixture->kickoff_at) : null,
            'venue' => $fixture->venue,
        ];
    }

    /** A fixture typed into the create form, by team NAME. */
    private function importFixture(ClubEvent $event, array $row): void
    {
        $home = $this->addTeam($event, (string) ($row['home'] ?? ''));
        $away = $this->addTeam($event, (string) ($row['away'] ?? ''));

        if (! $home || ! $away || $home->id === $away->id) {
            return;
        }

        $scored = isset($row['home_score'], $row['away_score'])
            && $row['home_score'] !== null && $row['away_score'] !== null
            && $row['home_score'] !== '' && $row['away_score'] !== '';

        LeagueFixture::firstOrCreate([
            'event_id' => $event->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'matchday' => 0,     // typed by hand, outside the generated season
        ], [
            'kickoff_at' => $this->parseDate($row['date'] ?? null),
            'home_score' => $scored ? (int) $row['home_score'] : null,
            'away_score' => $scored ? (int) $row['away_score'] : null,
            'status' => $scored ? LeagueFixture::PLAYED : LeagueFixture::SCHEDULED,
        ]);
    }

    private function parseDate(?string $raw): ?Carbon
    {
        if (! $raw) {
            return null;
        }

        return rescue(fn () => Carbon::parse($raw), null, false);
    }
}
