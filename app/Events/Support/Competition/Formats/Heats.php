<?php

namespace App\Events\Support\Competition\Formats;

use App\Events\Models\EventCategory;
use App\Events\Models\EventPerformance;
use App\Events\Support\Competition\SportRecipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Field, measured and judged contests — everybody competes at once or in
 * turn, and a number ranks them: a race, a swim, a jump, a lift, a routine.
 *
 *   heats      the field split into heats of the venue's lanes (8 by default),
 *              seeded serpentine so the fastest are spread across them; a
 *              field that fits in one heat is simply a final
 *   final      the best `finalists` across the heats, by value
 *   the value  time: lower wins · distance / weight: higher wins, best
 *              attempt (lifting: best of each lift, totalled) · marks: the
 *              panel's marks, highest and lowest dropped, × difficulty
 *
 * One EventPerformance row per entrant per round; `attempts` holds what was
 * typed (each attempt, each judge's mark), `value` the number that ranks.
 */
final class Heats
{
    public function build(EventCategory $division, Collection $entrants, SportRecipe $recipe, string $round = 'heats'): int
    {
        return DB::transaction(function () use ($division, $entrants, $recipe, $round) {
            EventPerformance::where('category_id', $division->id)->where('round', $round)->delete();

            $lanes = max(2, (int) ($recipe->scoring()['lanes'] ?? 8));
            $n = $entrants->count();
            $count = max(1, (int) ceil($n / $lanes));

            // A field that fits in one heat IS the final.
            if ($round === 'heats' && $count === 1) {
                $round = 'final';
                EventPerformance::where('category_id', $division->id)->where('round', 'final')->delete();
            }

            foreach ($entrants->values() as $i => $e) {
                $lap = intdiv($i, $count);
                $heat = $lap % 2 === 0 ? $i % $count : $count - 1 - ($i % $count);

                EventPerformance::create([
                    'event_id' => $division->event_id,
                    'category_id' => $division->id,
                    'round' => $round,
                    'heat' => $heat + 1,
                    'lane' => $lap + 1,
                    'registration_id' => $e['registration_id'],
                    'side_id' => $e['side_id'],
                ]);
            }

            return $count;
        });
    }

    /** Record one entrant's result in a round. */
    public function record(EventPerformance $p, SportRecipe $recipe, array $payload): EventPerformance
    {
        $status = in_array($payload['status'] ?? 'ok', EventPerformance::STATUSES, true) ? ($payload['status'] ?? 'ok') : 'ok';
        $attempts = array_values(array_map(
            fn ($v) => $v === null || $v === '' ? null : (is_numeric($v) ? (float) $v : null),
            (array) ($payload['attempts'] ?? (isset($payload['value']) ? [$payload['value']] : [])),
        ));

        $value = $status === 'ok' ? $this->value($recipe, $attempts, $payload) : null;

        if ($status === 'ok' && $value === null) {
            throw ValidationException::withMessages(['value' => __('events::competition.result_value_required')]);
        }

        $p->forceFill(['attempts' => $attempts + (isset($payload['difficulty']) ? ['dd' => (float) $payload['difficulty']] : []), 'value' => $value, 'status' => $status])->save();
        $this->rank($p->category, $p->round, $recipe);

        return $p->fresh();
    }

    /** Rank a round: by value, the sport's better direction; DNS/DNF/DQ last. */
    public function rank(EventCategory $division, string $round, SportRecipe $recipe): void
    {
        $lower = ($recipe->scoring()['better'] ?? 'lower') === 'lower';
        $rows = EventPerformance::where('category_id', $division->id)->where('round', $round)->get();

        $ranked = $rows->filter(fn ($p) => $p->status === 'ok' && $p->value !== null)
            ->sortBy(fn ($p) => $lower ? $p->value : -$p->value)->values();

        $rank = 0; $prev = null; $shown = 0;
        foreach ($ranked as $p) {
            $shown++;
            if ($p->value !== $prev) { $rank = $shown; $prev = $p->value; }   // ties share a place
            $p->forceFill(['rank' => $rank])->save();
        }

        $rows->reject(fn ($p) => $p->status === 'ok' && $p->value !== null)->each(fn ($p) => $p->forceFill(['rank' => null])->save());
    }

    /**
     * The best `$finalists` of the heats, into a final (lanes drawn best in
     * the middle — seeded order is enough for a club event).
     */
    public function toFinal(EventCategory $division, SportRecipe $recipe, ?int $finalists = null): int
    {
        $finalists ??= max(2, (int) ($recipe->scoring()['lanes'] ?? 8));
        $lower = ($recipe->scoring()['better'] ?? 'lower') === 'lower';

        $best = EventPerformance::where('category_id', $division->id)->where('round', 'heats')
            ->where('status', 'ok')->whereNotNull('value')->get()
            ->sortBy(fn ($p) => $lower ? $p->value : -$p->value)->take($finalists)->values()
            ->map(fn ($p) => ['registration_id' => $p->registration_id, 'side_id' => $p->side_id]);

        return $this->build($division, $best, $recipe, 'final');
    }

    /** Every entrant of a round has a result (a value, or DNS/DNF/DQ). */
    public function complete(EventCategory $division, string $round): bool
    {
        $rows = EventPerformance::where('category_id', $division->id)->where('round', $round)->get();

        return $rows->isNotEmpty() && $rows->every(fn ($p) => $p->status !== 'ok' || $p->value !== null);
    }

    /**
     * The podium of the deciding round — once that round is complete. A field
     * split into several heats is decided by its FINAL, never by the heats.
     */
    public function podium(EventCategory $division): array
    {
        $hasFinal = EventPerformance::where('category_id', $division->id)->where('round', 'final')->exists();
        $round = $hasFinal ? 'final' : 'heats';

        if (! $hasFinal && EventPerformance::where('category_id', $division->id)->where('round', 'heats')->distinct()->count('heat') > 1) {
            return [];
        }

        if (! $this->complete($division, $round)) {
            return [];
        }

        return EventPerformance::where('category_id', $division->id)->where('round', $round)
            ->whereNotNull('rank')->where('rank', '<=', 3)->orderBy('rank')
            ->with(['registration.user:id,full_name,name', 'side'])->get()
            ->map(fn (EventPerformance $p) => [
                'place' => $p->rank,
                'name' => $p->side?->displayName() ?? ($p->registration?->user?->full_name ?: $p->registration?->user?->name),
                'value' => $p->value,
                'key' => $p->side_id ? 's'.$p->side_id : 'r'.$p->registration_id,
            ])->all();
    }

    /** The number that ranks, from what was typed, by the sport's rules. */
    private function value(SportRecipe $recipe, array $attempts, array $payload): ?float
    {
        $rules = $recipe->scoring();
        $vals = array_values(array_filter($attempts, fn ($v) => $v !== null));

        if ($vals === []) {
            return null;
        }

        return match ($recipe->result()->value) {
            // A time is typed as seconds (or m:ss.xx, converted by the screen).
            'time' => (float) $vals[0],
            'distance' => ($rules['counts'] ?? 'best') === 'best_total'
                ? round(array_sum(array_map(fn ($lift) => max($lift ?: [0]), array_chunk($attempts, 3))), 3)
                : max($vals),
            'marks' => $this->marks($rules, $vals, (float) ($payload['difficulty'] ?? 1)),
            default => (float) $vals[0],
        };
    }

    private function marks(array $rules, array $marks, float $difficulty): float
    {
        sort($marks);

        if (($rules['drop'] ?? null) === 'high_low' && count($marks) >= 3) {
            $marks = array_slice($marks, 1, -1);
        }

        $sum = array_sum($marks);

        return round(! empty($rules['difficulty']) ? $sum * max(0.1, $difficulty) : $sum, 3);
    }
}
