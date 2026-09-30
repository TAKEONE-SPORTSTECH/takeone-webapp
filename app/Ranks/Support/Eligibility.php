<?php

namespace App\Ranks\Support;

use App\Ranks\Models\ActivityPackage;
use App\Ranks\Models\GradingRequirement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Is a member ready for their next belt?
 *
 * Measured against the activity's requirements for THAT belt — classes attended
 * in this activity since the last promotion, months at the current rank, and
 * age — each only when the club (or, later, the federation's published rule
 * book) has set it. Attendance is what the club already records
 * (class_attendances); nothing here counts anything by hand.
 *
 * Status: eligible (every requirement met) · nearly (80 % or more of the way)
 * · not_yet · no_rules (nothing set for that belt) · unranked · top (nothing
 * above).
 */
final class Eligibility
{
    public const NEARLY = 0.8;

    /** @return array<string, array{min_classes: ?int, min_months: ?int, min_age: ?int}> keyed by belt */
    public function requirements(ActivityPackage $link): array
    {
        return GradingRequirement::where('activity_id', $link->activity_id)->get()
            ->mapWithKeys(fn ($r) => [$r->belt => ['min_classes' => $r->min_classes, 'min_months' => $r->min_months, 'min_age' => $r->min_age]])
            ->all();
    }

    /**
     * Save the club's requirements. Only belts on the ladder; empty = not asked.
     *
     * @param  array<string, array{min_classes?: ?int, min_months?: ?int, min_age?: ?int}>  $rows
     */
    public function save(ActivityPackage $link, array $ladder, array $rows): void
    {
        $belts = collect($ladder)->pluck('value')->all();
        DB::transaction(function () use ($link, $belts, $rows) {
            foreach ($rows as $belt => $r) {
                if (! in_array($belt, $belts, true)) {
                    continue;
                }
                $clean = collect(['min_classes' => 999, 'min_months' => 240, 'min_age' => 99])
                    ->map(fn ($max, $k) => isset($r[$k]) && $r[$k] !== '' && $r[$k] !== null ? max(0, min($max, (int) $r[$k])) : null)->all();
                if (array_filter($clean, fn ($v) => $v !== null) === []) {
                    GradingRequirement::where('activity_id', $link->activity_id)->where('belt', $belt)->delete();

                    continue;
                }
                GradingRequirement::updateOrCreate(['activity_id' => $link->activity_id, 'belt' => $belt], $clean);
            }
        });
    }

    /**
     * Annotate roster rows with where each member stands.
     *
     * @param  array<int, array>  $rows   MemberRanks::roster() rows
     * @return array<int, array>
     */
    public function annotate(ActivityPackage $link, array $ladder, array $rows): array
    {
        $req = $this->requirements($link);
        $index = collect($ladder)->pluck('value')->flip();
        $users = \App\Members\Models\User::whereIn('uuid', collect($rows)->pluck('uuid'))->pluck('id', 'uuid');
        $ranks = \App\Ranks\Models\MemberRank::where('activity_id', $link->activity_id)->get()->keyBy('user_id');

        // Classes attended in THIS activity since each member's current rank, in one query.
        $attended = DB::table('class_attendances as ca')
            ->join('club_package_activities as pa', 'pa.id', '=', 'ca.package_activity_id')
            ->where('pa.activity_id', $link->activity_id)
            ->whereIn('ca.user_id', $users->values())
            ->get(['ca.user_id', 'ca.date']);

        return array_map(function ($row) use ($req, $index, $ladder, $users, $ranks, $attended) {
            $uid = $users[$row['uuid']] ?? null;
            $rank = $uid ? ($ranks[$uid] ?? null) : null;
            if (! $rank) {
                return $row + ['status' => 'unranked', 'next' => null, 'progress' => null];
            }
            $i = $index[$rank->belt] ?? null;
            $next = $i !== null ? ($ladder[$i + 1] ?? null) : null;
            if (! $next) {
                return $row + ['status' => 'top', 'next' => null, 'progress' => null];
            }
            $r = $req[$next['value']] ?? null;
            $nextOut = ['belt' => $next['value'], 'label' => $next['label'], 'icon' => $next['icon'] ?? null];
            if (! $r || array_filter($r, fn ($v) => $v !== null) === []) {
                return $row + ['status' => 'no_rules', 'next' => $nextOut, 'progress' => null];
            }

            $since = $rank->since ?? Carbon::now();
            $classes = $attended->where('user_id', $uid)->filter(fn ($a) => (string) $a->date >= $since->toDateString())->count();
            $months = (int) floor($since->diffInMonths(now()));
            $progress = [];
            $ratios = [];
            if ($r['min_classes']) { $progress['classes'] = [$classes, $r['min_classes']]; $ratios[] = $classes / $r['min_classes']; }
            if ($r['min_months']) { $progress['months'] = [$months, $r['min_months']]; $ratios[] = $months / $r['min_months']; }
            if ($r['min_age'] !== null && $r['min_age'] > 0) {
                $age = $row['age'];
                $progress['age'] = [$age, $r['min_age']];
                $ratios[] = $age === null ? 0 : $age / $r['min_age'];
            }
            $worst = $ratios === [] ? 1 : min($ratios);
            $status = $worst >= 1 ? 'eligible' : ($worst >= self::NEARLY ? 'nearly' : 'not_yet');

            return $row + ['status' => $status, 'next' => $nextOut, 'progress' => $progress];
        }, $rows);
    }
}
