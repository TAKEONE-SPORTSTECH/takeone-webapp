<?php

namespace App\Events\Championship;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventPerformance;
use App\Events\Support\AudienceResolver;
use App\Events\Support\Competition\CompetitionProfile;
use App\Events\Support\Competition\Entrant;
use App\Events\Support\Competition\Entrants;
use App\Events\Support\Competition\Format;
use App\Events\Support\Competition\ResultRules;
use App\Events\Support\Competition\SportRecipe;
use App\Events\Support\Competition\SportRecipes;
use App\Events\Support\Competition\Standings;
use App\Events\Support\Competition\Formats\Heats;
use App\Events\Support\Competition\Formats\Knockout;
use App\Events\Support\Competition\Formats\RoundRobin;
use App\Events\Support\Competition\Formats\Swiss;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Runs a sport championship, one division at a time, by the event's own
 * settings (club_events.competition) and its sport's recipe:
 *
 *   knockout         cut the bracket; results carry winners forward
 *   round_robin      one group, everyone meets everyone
 *   groups_knockout  groups first, then the best of each into a bracket
 *   swiss            one round at a time, paired from the standings
 *   heats            heats by lanes, then the fastest/best into a final
 *
 * Every rule about a RESULT is the sport's (ResultRules); every rule about a
 * FORMAT is the shared engines' (Formats/*). This class only decides which to
 * call, and writes the podium back once a division is decided. Nudges the
 * organisers with a refresh signal after every change.
 */
final class Runner
{
    public const DEFAULTS = ['group_size' => 4, 'advance' => 2, 'swiss_rounds' => 5, 'finalists' => 8];

    public function __construct(
        private SportRecipes $recipes,
        private Knockout $knockout,
        private RoundRobin $roundRobin,
        private Swiss $swiss,
        private Heats $heats,
        private ResultRules $results,
        private Standings $standings,
        private Entrants $entrants,
        private AudienceResolver $audience,
    ) {}

    /** The event's settings, with the recipe's defaults filling any gap. */
    public function settings(ClubEvent $event): array
    {
        $recipe = $this->recipe($event);
        $stored = is_array($event->competition) ? $event->competition : [];

        $format = Format::tryFrom((string) ($stored['format'] ?? '')) ?? $recipe->formats()[0];
        $entrant = Entrant::tryFrom((string) ($stored['entrant'] ?? '')) ?? $recipe->entrants()[0];

        return [
            'format' => $recipe->allowsFormat($format) ? $format : $recipe->formats()[0],
            'entrant' => $recipe->allows($entrant) ? $entrant : $recipe->entrants()[0],
        ] + array_map('intval', array_intersect_key($stored, self::DEFAULTS)) + self::DEFAULTS;
    }

    public function recipe(ClubEvent $event): SportRecipe
    {
        return $this->recipes->get($event->sport)
            ?? throw new \RuntimeException('No sport recipe for '.$event->sport);
    }

    public function profile(ClubEvent $event): CompetitionProfile
    {
        $s = $this->settings($event);

        return $this->recipe($event)->profile($s['entrant'], $s['format']);
    }

    /* ---------------- building ---------------- */

    /** Draw a division: its bracket, its groups, its first Swiss round or its heats. */
    public function build(ClubEvent $event, EventCategory $division): array
    {
        $this->assertOwn($event, $division);
        $s = $this->settings($event);
        $recipe = $this->recipe($event);
        $entrants = $this->entrants->of($division, $s['entrant']);

        if ($entrants->count() < 2) {
            throw ValidationException::withMessages(['division' => __('event-sport_championship::messages.need_two')]);
        }

        if ($this->started($division)) {
            throw ValidationException::withMessages(['division' => __('event-sport_championship::messages.already_started')]);
        }

        DB::transaction(function () use ($division, $s, $recipe, $entrants) {
            EventMatch::where('category_id', $division->id)->delete();
            EventPerformance::where('category_id', $division->id)->delete();

            match ($s['format']) {
                Format::Knockout => $this->knockout->build($division, $entrants, $this->thirdPlace($recipe)),
                Format::RoundRobin, Format::League => $this->roundRobin->build($division, $entrants, null),
                Format::GroupsKnockout => $this->roundRobin->build($division, $entrants, max(2, $s['group_size'])),
                Format::Swiss => $this->swiss->pairNext($division, $entrants, $this->profile($division->event), max(1, $s['swiss_rounds'])),
                Format::Heats => $this->heats->build($division, $entrants, $recipe),
                default => null,
            };

            $division->forceFill(['podium' => null, 'status' => 'running', 'draw_state' => 'final'])->save();
        });

        $this->announce($event);

        return $this->division($event, $division->fresh());
    }

    /** Groups decided → the best `advance` of each into a bracket. */
    public function toKnockout(ClubEvent $event, EventCategory $division): array
    {
        $this->assertOwn($event, $division);
        $s = $this->settings($event);
        $entrants = $this->entrants->of($division, $s['entrant']);
        $groups = $this->roundRobin->membersOf($division, $entrants);
        $pool = EventMatch::where('category_id', $division->id)->where('phase', 'like', RoundRobin::PHASE.':%')->get();

        if ($pool->isEmpty() || $pool->contains(fn ($m) => $m->status !== 'done')) {
            throw ValidationException::withMessages(['division' => __('event-sport_championship::messages.groups_unfinished')]);
        }

        // Winners of every group first, then runners-up in reverse group
        // order, and so on — so two teams from one group meet as late as the
        // bracket allows.
        $byPlace = [];
        foreach ($groups as $g => $members) {
            $table = $this->standings->build($members, $pool->where('group_no', $g), $this->profile($event));
            foreach (array_slice($table, 0, max(1, $s['advance'])) as $i => $row) {
                $byPlace[$i][] = $members->firstWhere('key', $row['key']);
            }
        }

        $qualifiers = collect();
        foreach ($byPlace as $i => $rows) {
            $qualifiers = $qualifiers->merge($i % 2 === 0 ? $rows : array_reverse($rows));
        }

        $this->knockout->build($division, $qualifiers->filter()->values(), $this->thirdPlace($this->recipe($event)));
        $this->announce($event);

        return $this->division($event, $division->fresh());
    }

    public function nextSwissRound(ClubEvent $event, EventCategory $division): array
    {
        $this->assertOwn($event, $division);
        $s = $this->settings($event);

        $round = $this->swiss->pairNext($division, $this->entrants->of($division, $s['entrant']), $this->profile($event), max(1, $s['swiss_rounds']));

        if ($round === null) {
            throw ValidationException::withMessages(['division' => __('event-sport_championship::messages.round_unfinished')]);
        }

        $this->announce($event);

        return $this->division($event, $division->fresh());
    }

    public function toFinal(ClubEvent $event, EventCategory $division): array
    {
        $this->assertOwn($event, $division);
        $s = $this->settings($event);

        if (! $this->heats->complete($division, 'heats')
            || EventPerformance::where('category_id', $division->id)->where('round', 'final')->exists()) {
            throw ValidationException::withMessages(['division' => __('event-sport_championship::messages.heats_unfinished')]);
        }

        $this->heats->toFinal($division, $this->recipe($event), max(2, $s['finalists']));
        $this->announce($event);

        return $this->division($event, $division->fresh());
    }

    /* ---------------- results ---------------- */

    public function record(ClubEvent $event, EventMatch $contest, array $payload): array
    {
        abort_unless((int) $contest->event_id === (int) $event->id, 404);

        if ($contest->won_by_bye || ! $contest->a_name || ! $contest->b_name) {
            throw ValidationException::withMessages(['contest' => __('event-sport_championship::messages.contest_not_ready')]);
        }

        $recipe = $this->recipe($event);
        $knockout = str_starts_with((string) $contest->phase, Knockout::PHASE.':');
        $out = $this->results->apply($recipe, $payload, $recipe->drawsAllowed() && ! $knockout);

        DB::transaction(function () use ($contest, $out, $knockout) {
            if ($knockout && $contest->winner) {
                $this->knockout->retract($contest);   // a correction: undo what it carried
            }

            $contest->forceFill([
                'winner' => $out['winner'],
                'a_score' => $out['a_score'],
                'b_score' => $out['b_score'],
                'detail' => $out['detail'] ?: null,
                'status' => 'done',
            ])->save();

            if ($knockout) {
                $this->knockout->advance($contest->fresh());
            }
        });

        $this->settle($event, $contest->category);
        $this->announce($event);

        return $this->division($event, $contest->category->fresh());
    }

    public function recordPerformance(ClubEvent $event, EventPerformance $performance, array $payload): array
    {
        abort_unless((int) $performance->event_id === (int) $event->id, 404);

        $this->heats->record($performance, $this->recipe($event), $payload);
        $this->settle($event, $performance->category);
        $this->announce($event);

        return $this->division($event, $performance->category->fresh());
    }

    /* ---------------- reading ---------------- */

    /** Everything a screen draws for one division. */
    public function division(ClubEvent $event, EventCategory $division): array
    {
        $s = $this->settings($event);
        $profile = $this->profile($event);
        $entrants = $this->entrants->of($division, $s['entrant']);
        $contests = EventMatch::where('category_id', $division->id)->orderBy('slot')->get();
        $pool = $contests->filter(fn ($m) => str_starts_with((string) $m->phase, RoundRobin::PHASE.':'));

        $tables = [];
        if ($pool->isNotEmpty()) {
            foreach ($this->roundRobin->membersOf($division, $entrants) as $g => $members) {
                $tables[] = [
                    'group' => $pool->max('group_no') > 1 ? 'Group '.chr(64 + $g) : null,
                    'rows' => $this->standings->build($members, $pool->where('group_no', $g), $profile),
                ];
            }
        } elseif ($s['format'] === Format::Swiss && $contests->isNotEmpty()) {
            $tables[] = ['group' => null, 'rows' => $this->standings->build($entrants, $contests, $profile)];
        }

        return [
            'id' => $division->id,
            'name' => $division->name,
            'format' => $s['format']->value,
            'entrants' => $entrants->count(),
            'started' => $this->started($division),
            'contests' => $contests->map(fn (EventMatch $m) => $this->contest($m))->values()->all(),
            'tables' => $tables,
            'performances' => EventPerformance::where('category_id', $division->id)
                ->with(['registration.user:id,full_name,name', 'side'])
                ->orderBy('round')->orderBy('heat')->orderBy('lane')->get()
                ->map(fn (EventPerformance $p) => [
                    'id' => $p->id, 'round' => $p->round, 'heat' => $p->heat, 'lane' => $p->lane,
                    'name' => $p->side?->displayName() ?? ($p->registration?->user?->full_name ?: $p->registration?->user?->name),
                    'attempts' => $p->attempts, 'value' => $p->value, 'status' => $p->status, 'rank' => $p->rank,
                ])->all(),
            'can' => [
                'knockout' => $s['format'] === Format::GroupsKnockout && $pool->isNotEmpty()
                    && $pool->every(fn ($m) => $m->status === 'done')
                    && ! $contests->contains(fn ($m) => str_starts_with((string) $m->phase, Knockout::PHASE.':')),
                'next_round' => $s['format'] === Format::Swiss && $contests->isNotEmpty()
                    && $contests->every(fn ($m) => $m->status === 'done')
                    && $this->swiss->roundsPlayed($division) < $s['swiss_rounds'],
                'final' => $s['format'] === Format::Heats
                    && $this->heats->complete($division, 'heats')
                    && EventPerformance::where('category_id', $division->id)->where('round', 'heats')->distinct()->count('heat') > 1
                    && ! EventPerformance::where('category_id', $division->id)->where('round', 'final')->exists(),
            ],
            'podium' => $division->podium ?: [],
        ];
    }

    public function contest(EventMatch $m): array
    {
        return [
            'id' => $m->id, 'no' => $m->match_no, 'round' => $m->round, 'phase' => $m->phase, 'group' => $m->group_no,
            'a' => $m->a_name, 'b' => $m->b_name, 'a_score' => $m->a_score, 'b_score' => $m->b_score,
            'winner' => $m->winner, 'status' => $m->status, 'bye' => (bool) $m->won_by_bye,
            'detail' => $m->detail, 'court' => $m->court, 'time' => $m->scheduled_time,
        ];
    }

    /** Re-derive and store a division's podium (AwardsDivisions). */
    public function award(ClubEvent $event, EventCategory $division): array
    {
        $this->settle($event, $division);

        return $division->fresh()->podium ?: [];
    }

    /* ---------------- internals ---------------- */

    /** Once a division is decided, its podium is written back — once. */
    private function settle(ClubEvent $event, EventCategory $division): void
    {
        $s = $this->settings($event);
        $recipe = $this->recipe($event);
        $contests = EventMatch::where('category_id', $division->id)->get();

        $podium = match (true) {
            $s['format'] === Format::Heats => $this->heats->podium($division),
            $contests->contains(fn ($m) => str_starts_with((string) $m->phase, Knockout::PHASE.':')) => $this->knockout->podium($division, $recipe->data['medals'] ?? 'both_sf_losers'),
            in_array($s['format'], [Format::RoundRobin, Format::League, Format::Swiss], true)
                && $contests->isNotEmpty() && $contests->every(fn ($m) => $m->status === 'done')
                && ($s['format'] !== Format::Swiss || $this->swiss->roundsPlayed($division) >= $s['swiss_rounds'])
                => array_map(fn ($r) => ['place' => $r['position'], 'name' => $r['name'], 'country' => $r['country'], 'key' => $r['key']],
                    array_slice($this->standings->build($this->entrants->of($division, $s['entrant']), $contests, $this->profile($event)), 0, 3)),
            default => [],
        };

        $division->forceFill(['podium' => $podium ?: null, 'status' => $podium ? 'completed' : 'running'])->save();
    }

    private function thirdPlace(SportRecipe $recipe): bool
    {
        return ($recipe->data['medals'] ?? null) === 'third_place_match';
    }

    private function started(EventCategory $division): bool
    {
        return EventMatch::where('category_id', $division->id)->where('status', 'done')->where('won_by_bye', false)->exists()
            || EventPerformance::where('category_id', $division->id)->whereNotNull('value')->exists();
    }

    private function assertOwn(ClubEvent $event, EventCategory $division): void
    {
        abort_unless((int) $division->event_id === (int) $event->id && ! $division->isHeading(), 404);
    }

    private function announce(ClubEvent $event): void
    {
        rescue(fn () => \Realtime()->publishMany(array_map(
            fn (int $id) => ['topic' => \Realtime()->userTopic($id, 'events'), 'payload' => ['action' => 'competition', 'event' => $event->uuid]],
            $this->audience->managers($event),
        )), null, false);
    }
}
