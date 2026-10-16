<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Members\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Merging divisions that are too small to be a fair competition.
 *
 * ── Safety first, then fairness ─────────────────────────────────────────────
 *
 * A division of one hands out a gold medal for no bout. The cure is a merge,
 * and a careless merge is worse than the disease: a child put up against a far
 * heavier or far older opponent. So this class does two separate jobs and
 * keeps them apart:
 *
 *   judge()   — grades ANY set of divisions as green (safe), amber (only with
 *               coach + guardian consent) or red (never), and says WHY in words.
 *   suggest() — finds the small divisions and, for each, the best neighbour
 *               judge() will allow. A red merge is never suggested; the lone
 *               athlete is listed with the reason nothing is safe.
 *   merge()   — re-judges on the server (the client's grade is never trusted)
 *               and only then moves the entrants and retires the emptied
 *               groups, the same way deleting a division does.
 *
 * The limits and the published rules each one comes from are in
 * Documentation/DIVISION-MERGING.md and config('events.division_merge').
 *
 * Shared by every sport (CLAUDE.md → Shared Stays Shared): nothing here knows
 * which sport it is merging. The draw is rebuilt through the event's own
 * package (EventType::onEntrantsChanged).
 *
 * ── Measuring ───────────────────────────────────────────────────────────────
 *
 * Weight and age gaps are measured between the athletes who MOVE and the
 * athletes already in the group they join — not across the whole pool, because
 * a weight class with an open bottom already spans a range its organiser
 * accepted. The gap is the moved athlete against the opponent furthest from
 * them, because in a knockout any two may meet.
 *
 * Age is measured only when a merge crosses an age group: inside one group the
 * group's own band is the accepted spread. A minor is never merged with an
 * adult, whatever the band says.
 */
class DivisionMerge
{
    public const GREEN = 'green';

    public const AMBER = 'amber';

    public const RED = 'red';

    private const RANK = [self::GREEN => 0, self::AMBER => 1, self::RED => 2];

    public static function enabled(): bool
    {
        return (bool) config('events.division_merge.enabled', false);
    }

    /** The limits in force, with floors so a bad .env cannot loosen them into nonsense. */
    public function limits(): array
    {
        $c = (array) config('events.division_merge', []);

        return [
            'below' => max(2, (int) ($c['below'] ?? 3)),
            'consent_gap' => max(0.0, (float) ($c['consent_gap'] ?? 12)),
            'max_gap' => max(0.0, (float) ($c['max_gap'] ?? 15)),
            'max_months' => max(0, (int) ($c['max_age_months'] ?? 24)),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Reading the event                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Every competing division of the event, in the event's own order, with
     * the people in it. Headings are not divisions and are never merged.
     *
     * @return Collection<int, array>
     */
    public function divisions(ClubEvent $event): Collection
    {
        $cats = $event->categories()->competing()->orderBy('sort_order')->orderBy('id')->get();
        $ids = $cats->pluck('id');

        $regs = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->whereIn('category_id', $ids)
            ->with('user:id,name,full_name,gender,birthdate')
            ->get()
            ->groupBy('category_id');

        // Anything that happened — a contested bout or footage of one — makes
        // a group part of the record; the same test deleting a group uses.
        $boutIds = EventMatch::whereIn('category_id', $ids)->pluck('category_id', 'id');
        $fought = EventMatch::whereIn('category_id', $ids)->contested()->pluck('category_id')->all();
        if ($boutIds->isNotEmpty()) {
            $filmed = DB::table('event_recordings')->whereIn('match_id', $boutIds->keys())->pluck('match_id')
                ->merge(DB::table('event_camera_clips')->whereIn('match_id', $boutIds->keys())->pluck('match_id'))
                ->map(fn ($m) => $boutIds[$m] ?? null)->filter()->all();
            $fought = array_merge($fought, $filmed);
        }
        $fought = array_flip(array_map('intval', $fought));

        $on = $event->date ? Carbon::parse($event->date) : Carbon::now();

        return $cats->values()->map(function (EventCategory $c, int $i) use ($regs, $fought, $on) {
            $people = ($regs[$c->id] ?? collect())->map(function (ClubEventRegistration $r) use ($on) {
                $born = $r->user?->birthdate ? Carbon::parse($r->user->birthdate) : null;
                $age = $born ? (int) floor($born->diffInYears($on)) : ($r->declared_age !== null ? (int) $r->declared_age : null);

                return [
                    'reg' => (int) $r->id,
                    'user' => (int) $r->user_id,
                    'name' => (string) ($r->user?->full_name ?: $r->user?->name ?: '—'),
                    'gender' => $this->gender($r->user?->gender),
                    'weight' => $r->weight !== null && (float) $r->weight > 0 ? (float) $r->weight : null,
                    'weighed' => $r->weighed_in_at !== null,
                    'born' => $born,
                    'age' => $age,
                ];
            })->values()->all();

            $num = fn ($v) => $v === null || $v === '' ? null : (float) $v;

            return [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'order' => $i,
                'gender' => $this->gender($c->gender),
                'min_age' => $c->min_age === null || $c->min_age === '' ? null : (int) $c->min_age,
                'max_age' => $c->max_age === null || $c->max_age === '' ? null : (int) $c->max_age,
                'min_weight' => $num($c->min_weight),
                'max_weight' => $num($c->max_weight),
                'people' => $people,
                'count' => count($people),
                'fought' => isset($fought[(int) $c->id]),
            ];
        });
    }

    private function gender($g): ?string
    {
        $g = strtolower(trim((string) $g));

        return in_array($g, ['male', 'female'], true) ? $g : null;
    }

    /* ------------------------------------------------------------------ */
    /*  Judging a set                                                      */
    /* ------------------------------------------------------------------ */

    /** The group the others move INTO: the biggest, the earliest on a tie. */
    public function targetOf(array $set): int
    {
        $best = null;
        foreach ($set as $d) {
            if ($best === null || $d['count'] > $best['count'] || ($d['count'] === $best['count'] && $d['order'] < $best['order'])) {
                $best = $d;
            }
        }

        return (int) $best['id'];
    }

    /**
     * Grade a set of divisions merged into one.
     *
     * @param  array  $set  division arrays from divisions()
     * @return array{grade:string, reasons:array, into:int, into_name:string, moving:int, after:int, gap:?float, headline:string}
     */
    public function judge(ClubEvent $event, array $set, ?int $into = null): array
    {
        $L = $this->limits();
        $set = array_values($set);
        $into = $into && collect($set)->contains('id', $into) ? $into : $this->targetOf($set);
        $target = collect($set)->firstWhere('id', $into);
        $movers = collect($set)->where('id', '!=', $into)->flatMap(fn ($d) => $d['people'])->values();
        $stay = collect($target['people']);
        $everyone = $movers->merge($stay);

        $why = [];
        $say = function (string $level, string $key, array $p = []) use (&$why) {
            $why[] = ['level' => $level, 'text' => __('events::events.'.$key, $p)];
        };

        // ── The event and the groups themselves ───────────────────────────
        if ($event->hasStarted() || $event->hasEnded()) {
            $say(self::RED, 'merge_started');
        }
        foreach ($set as $d) {
            if ($d['fought']) {
                $say(self::RED, 'merge_fought', ['name' => $d['name']]);
            }
        }
        $twice = $everyone->groupBy('user')->filter(fn ($g) => $g->count() > 1);
        if ($twice->isNotEmpty()) {
            $say(self::RED, 'merge_twice', ['names' => $twice->map(fn ($g) => $g->first()['name'])->take(3)->implode(', ')]);
        }

        // ── Gender: never mixed ──────────────────────────────────────────
        $genders = collect($set)->pluck('gender')->merge($everyone->pluck('gender'))->filter()->unique();
        if ($genders->count() > 1) {
            $say(self::RED, 'merge_mixed_gender');
        } else {
            $say(self::GREEN, 'merge_same_gender');
        }
        $noGender = $everyone->whereNull('gender')->count();
        if ($noGender > 0 && collect($set)->pluck('gender')->filter()->isEmpty()) {
            $say(self::AMBER, 'merge_gender_unknown', ['count' => $noGender]);
        }

        // ── Age: same group, or one adjacent group with consent ──────────
        $bands = collect($set)->map(fn ($d) => [$d['min_age'], $d['max_age']])
            ->unique(fn ($b) => ($b[0] ?? '').'-'.($b[1] ?? ''))->values();
        $crosses = $bands->count() > 1;
        if (! $crosses) {
            $say(self::GREEN, 'merge_same_age_group');
        } elseif ($bands->count() === 2 && $this->adjacent($bands[0], $bands[1])) {
            $say(self::AMBER, 'merge_crosses_age_group', ['a' => $this->band($bands[0]), 'b' => $this->band($bands[1])]);
        } else {
            $say(self::RED, 'merge_age_groups_far');
        }

        $ages = $everyone->pluck('age')->filter(fn ($a) => $a !== null);
        if ($ages->isNotEmpty() && $ages->min() < 18 && $ages->max() >= 18) {
            $say(self::RED, 'merge_adult_minor');
        }

        if ($crosses) {
            $months = $this->maxMonths($movers, $stay);
            if ($months !== null) {
                $say($months > $L['max_months'] ? self::RED : self::GREEN,
                    $months > $L['max_months'] ? 'merge_age_gap_red' : 'merge_age_gap_ok',
                    ['months' => $months, 'limit' => $L['max_months']]);
            }
            $noBirth = $everyone->whereNull('born')->count();
            if ($noBirth > 0) {
                $say(self::AMBER, 'merge_age_unknown', ['count' => $noBirth]);
            }
        }

        // ── Weight: the mover against the opponent furthest from them ────
        $gap = null;
        $pair = null;
        foreach ($movers as $m) {
            foreach ($stay as $s) {
                if ($m['weight'] === null || $s['weight'] === null) {
                    continue;
                }
                $lo = min($m['weight'], $s['weight']);
                $hi = max($m['weight'], $s['weight']);
                $g = $lo > 0 ? ($hi - $lo) / $lo * 100 : 0;
                if ($gap === null || $g > $gap) {
                    $gap = $g;
                    $pair = [$lo, $hi];
                }
            }
        }
        if ($gap !== null) {
            $p = ['pct' => $this->num($gap), 'light' => $this->num($pair[0]), 'heavy' => $this->num($pair[1]),
                'consent' => $this->num($L['consent_gap']), 'limit' => $this->num($L['max_gap'])];
            if ($gap > $L['max_gap']) {
                $say(self::RED, 'merge_weight_gap_red', $p);
            } elseif ($gap > $L['consent_gap']) {
                $say(self::AMBER, 'merge_weight_gap_amber', $p);
            } else {
                $say(self::GREEN, 'merge_weight_gap_ok', $p);
            }
        }
        $noWeight = $everyone->whereNull('weight')->count();
        if ($noWeight > 0) {
            $say(self::AMBER, 'merge_weight_unknown', ['count' => $noWeight]);
        }
        if ($everyone->whereNotNull('weight')->contains('weighed', false)) {
            $why[] = ['level' => 'info', 'text' => __('events::events.merge_declared')];
        }

        $grade = self::GREEN;
        foreach ($why as $w) {
            if (isset(self::RANK[$w['level']]) && self::RANK[$w['level']] > self::RANK[$grade]) {
                $grade = $w['level'];
            }
        }

        $first = collect($why)->firstWhere('level', $grade === self::GREEN ? 'none' : $grade);
        $from = collect($set)->where('id', '!=', $into)->pluck('name')->implode(', ');

        return [
            'grade' => $grade,
            'consent' => $grade === self::AMBER,
            'reasons' => $why,
            'into' => (int) $into,
            'into_name' => (string) $target['name'],
            'from' => collect($set)->where('id', '!=', $into)->pluck('id')->values()->all(),
            'from_names' => $from,
            'moving' => $movers->count(),
            'after' => $everyone->count(),
            'gap' => $gap === null ? null : round($gap, 1),
            'crosses' => $crosses,
            'headline' => $first['text'] ?? __('events::events.merge_headline_safe', ['count' => $everyone->count()]),
        ];
    }

    /** Two age bands that meet or overlap — one step apart, not two. */
    private function adjacent(array $a, array $b): bool
    {
        [$a, $b] = ($a[0] ?? 0) <= ($b[0] ?? 0) ? [$a, $b] : [$b, $a];

        // An open band ("any age") touches everything.
        if ($a[1] === null || $b[0] === null) {
            return true;
        }

        return $b[0] <= $a[1] + 1;
    }

    private function band(array $b): string
    {
        [$lo, $hi] = $b;
        if ($lo === null && $hi === null) {
            return __('events::events.merge_any_age');
        }
        if ($lo === null) {
            return '≤'.$hi;
        }
        if ($hi === null) {
            return $lo.'+';
        }

        return $lo.'–'.$hi;
    }

    /** Most months apart between anyone moving and anyone already there. */
    private function maxMonths(Collection $movers, Collection $stay): ?int
    {
        $max = null;
        foreach ($movers as $m) {
            foreach ($stay as $s) {
                if (! $m['born'] || ! $s['born']) {
                    continue;
                }
                $mo = (int) floor(abs($m['born']->diffInMonths($s['born'])));
                $max = $max === null ? $mo : max($max, $mo);
            }
        }

        return $max;
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }

    /* ------------------------------------------------------------------ */
    /*  Suggesting                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * The small divisions and what to do with each.
     *
     * @return array{limits:array, suggestions:array, alone:array, wide:array, small:int}
     */
    public function suggest(ClubEvent $event): array
    {
        $L = $this->limits();
        $divs = $this->divisions($event);
        $small = $divs->filter(fn ($d) => $d['count'] >= 1 && $d['count'] < $L['below']);

        $suggestions = [];
        $alone = [];
        $seen = [];

        foreach ($small as $s) {
            $best = null;
            foreach ($divs as $t) {
                if ($t['id'] === $s['id'] || $t['count'] === 0 || ! $this->neighbour($s, $t)) {
                    continue;
                }
                $j = $this->judge($event, [$s, $t]);
                if ($best === null || $this->better($j, $best)) {
                    $best = $j;
                }
            }

            if ($best && $best['grade'] !== self::RED) {
                $key = collect([$best['into']])->merge($best['from'])->sort()->implode('-');
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $suggestions[] = $best + ['ids' => array_merge([$best['into']], $best['from'])];
                }

                continue;
            }

            // Two people already make a final; only a lone athlete is a problem
            // worth a line when nothing safe exists.
            if ($s['count'] === 1) {
                $alone[] = [
                    'id' => $s['id'],
                    'name' => $s['name'],
                    'count' => $s['count'],
                    'why' => $best
                        ? (collect($best['reasons'])->firstWhere('level', self::RED)['text'] ?? $best['headline'])
                        : __('events::events.merge_no_neighbour'),
                ];
            }
        }

        usort($suggestions, fn ($a, $b) => [self::RANK[$a['grade']], $a['gap'] ?? 0] <=> [self::RANK[$b['grade']], $b['gap'] ?? 0]);

        // Groups that are already wider than any merge would be allowed to make
        // them. Nothing to merge — something for the organiser to look at.
        $wide = $divs->map(function ($d) use ($L) {
            $w = collect($d['people'])->pluck('weight')->filter();
            if ($w->count() < 2 || $w->min() <= 0) {
                return null;
            }
            $pct = ($w->max() - $w->min()) / $w->min() * 100;

            return $pct > $L['max_gap'] ? ['id' => $d['id'], 'name' => $d['name'], 'pct' => round($pct, 1),
                'light' => $w->min(), 'heavy' => $w->max()] : null;
        })->filter()->values()->all();

        return [
            'limits' => $L,
            'small' => $small->count(),
            // Every group, for the "pick them yourself" list.
            'divisions' => $divs->map(fn ($d) => ['id' => $d['id'], 'name' => $d['name'], 'count' => $d['count'], 'gender' => $d['gender']])->values()->all(),
            'suggestions' => array_values($suggestions),
            'alone' => $alone,
            'wide' => $wide,
        ];
    }

    /**
     * Could these two sensibly be one group at all? Same (or unknown) gender,
     * and the same or an adjacent age band. Everything finer is judge()'s.
     */
    private function neighbour(array $s, array $t): bool
    {
        if ($s['gender'] && $t['gender'] && $s['gender'] !== $t['gender']) {
            return false;
        }
        $a = [$s['min_age'], $s['max_age']];
        $b = [$t['min_age'], $t['max_age']];

        return $a === $b || $this->adjacent($a, $b);
    }

    private function better(array $a, array $b): bool
    {
        $ra = self::RANK[$a['grade']];
        $rb = self::RANK[$b['grade']];
        if ($ra !== $rb) {
            return $ra < $rb;
        }
        // Between equals, the natural neighbour first: the same age group,
        // then the closest in weight. This is also the merge whose refusal is
        // shown to the organiser when nothing is safe, so it should be the one
        // they would have tried.
        if ($a['crosses'] !== $b['crosses']) {
            return ! $a['crosses'];
        }

        return ($a['gap'] ?? INF) < ($b['gap'] ?? INF);
    }

    /* ------------------------------------------------------------------ */
    /*  Merging                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Merge the given divisions into one. The server judges again — a grade
     * from the client is never trusted — and refuses red outright, amber
     * without the organiser's confirmation that consent was given.
     *
     * @param  int[]  $ids
     * @return array{ok:bool, status:int, message:string, judgement?:array, needs_consent?:bool}
     */
    public function merge(ClubEvent $event, array $ids, ?int $into, User $by, bool $consent): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $divs = $this->divisions($event)->keyBy('id');

        $set = [];
        foreach ($ids as $id) {
            if (! isset($divs[$id])) {
                return ['ok' => false, 'status' => 422, 'message' => __('events::events.merge_gone')];
            }
            $set[] = $divs[$id];
        }
        if (count($set) < 2) {
            return ['ok' => false, 'status' => 422, 'message' => __('events::events.merge_need_two')];
        }

        $j = $this->judge($event, $set, $into);

        if ($j['grade'] === self::RED) {
            return ['ok' => false, 'status' => 422, 'judgement' => $j,
                'message' => collect($j['reasons'])->firstWhere('level', self::RED)['text'] ?? __('events::events.merge_refused')];
        }
        if ($j['grade'] === self::AMBER && ! $consent) {
            return ['ok' => false, 'status' => 409, 'needs_consent' => true, 'judgement' => $j,
                'message' => __('events::events.merge_needs_consent')];
        }

        $target = $event->categories()->competing()->findOrFail($j['into']);
        $sources = $j['from'];

        // The unique index (event_id, user_id, category_id) covers every role,
        // not just competitors — one more look at the raw rows.
        $clash = ClubEventRegistration::where('event_id', $event->id)->whereIn('category_id', $sources)
            ->whereIn('user_id', ClubEventRegistration::where('event_id', $event->id)->where('category_id', $target->id)->select('user_id'))
            ->exists();
        if ($clash) {
            return ['ok' => false, 'status' => 422, 'message' => __('events::events.merge_twice', ['names' => '—'])];
        }

        DB::transaction(function () use ($event, $sources, $target) {
            foreach ($sources as $sid) {
                // Moved as an organiser's deliberate placement, so the weigh-in
                // desk and a later re-sort never undo it.
                ClubEventRegistration::where('event_id', $event->id)->where('category_id', $sid)
                    ->update(['category_id' => $target->id, 'category_source' => ClubEventRegistration::PLACED_MANUAL]);

                // The emptied group goes the way a deleted one does: its
                // unfought draw with it (judge() refused anything fought).
                EventMatch::where('category_id', $sid)->delete();
                EventCategory::where('event_id', $event->id)->whereKey($sid)->delete();
            }
        });

        $target->setRelation('event', $event);
        rescue(fn () => app(EventTypeRegistry::class)->for($event)->onEntrantsChanged($event, $target), null, true);

        rescue(fn () => activity()->causedBy($by)->performedOn($target)->withProperties([
            'event' => $event->uuid,
            'into' => $target->id,
            'into_name' => $j['into_name'],
            'merged' => $sources,
            'merged_names' => $j['from_names'],
            'grade' => $j['grade'],
            'consent_confirmed' => $j['grade'] === self::AMBER,
            'reasons' => collect($j['reasons'])->pluck('text')->all(),
            'moved' => $j['moving'],
        ])->log('events.divisions_merged'), null, true);

        $this->pushRefresh($event);

        return ['ok' => true, 'status' => 200, 'judgement' => $j, 'into' => $target->id, 'removed' => $sources,
            'message' => __('events::events.merge_done', ['into' => $j['into_name'], 'count' => $j['after']])];
    }

    /**
     * Every open board and console re-reads. A refresh signal, not the data —
     * what each viewer may see differs (same shape and audience as
     * PersonalEventController::pushDivisionsChanged).
     */
    private function pushRefresh(ClubEvent $event): void
    {
        rescue(function () use ($event) {
            if (! \Realtime()->enabled()) {
                return;
            }
            $ids = app(AudienceResolver::class)->forEvent($event);
            if (! $ids) {
                return;
            }
            \Realtime()->publishMany(array_map(fn (int $uid) => [
                'topic' => \Realtime()->userTopic($uid, 'events'),
                'payload' => ['action' => 'draw', 'event' => $event->uuid],
            ], $ids));
        }, null, false);
    }
}
