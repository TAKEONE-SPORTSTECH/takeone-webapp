<?php

namespace App\Rankings\Support;

use App\Events\Support\CompetitorFace;
use App\Federations\Models\Federation;
use App\Rankings\Models\RankingPoint;
use App\Rankings\Schemes\Wkf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads a ranking off the ledger.
 *
 * Decay is applied HERE, against today's date: a row less than a year old
 * counts in full, one between one and two years old counts half, anything
 * older is not read at all. So a ranking is always current without a nightly
 * job, and the stored rows never change for the passage of time.
 *
 * Ties are broken the WKF way: golds, then silvers, then bronzes, then bouts
 * won, then point difference, then points scored, then fewest conceded.
 */
class Standings
{
    /** Federations that have a ranking at all, with a little about each. */
    public function federations(): Collection
    {
        $live = $this->live(RankingPoint::query())
            ->selectRaw('federation_id, count(distinct user_id) as athletes, count(distinct category_key) as categories')
            ->groupBy('federation_id')->get()->keyBy('federation_id');

        return Federation::whereIn('id', $live->keys())->orderBy('name')->get()
            ->map(fn (Federation $f) => [
                'federation' => $f,
                'athletes' => (int) $live[$f->id]->athletes,
                'categories' => (int) $live[$f->id]->categories,
            ]);
    }

    /**
     * Every category a federation ranks, in the order its divisions are listed,
     * grouped by what comes before "Kumite"/"Kata" in the name (the age group).
     */
    public function categories(Federation $federation): Collection
    {
        $rows = $this->live(RankingPoint::where('federation_id', $federation->id))
            ->get(['category_key', 'category_name', 'category_order', 'discipline', 'user_id', 'event_id', 'points', 'place', 'wins', 'score_for', 'score_against', 'earned_on']);

        $categories = $rows->groupBy('category_key')
            ->map(function (Collection $group, string $key) {
                $leader = $this->rank($group)->first();
                $name = (string) $group->sortByDesc('earned_on')->first()->category_name;

                return [
                    'key' => $key,
                    'name' => $name,
                    'group' => $this->ageGroup($name),
                    'discipline' => $group->first()->discipline,
                    'order' => (int) $group->min('category_order'),
                    'athletes' => $group->pluck('user_id')->unique()->count(),
                    'leader_user_id' => $leader['user_id'] ?? null,
                    'leader_points' => $leader['points'] ?? 0,
                ];
            })
            ->sortBy('order')->values();

        $names = \App\Members\Models\User::whereIn('id', $categories->pluck('leader_user_id')->filter())
            ->get(['id', 'full_name', 'name'])->mapWithKeys(fn ($u) => [$u->id => (string) ($u->full_name ?: $u->name)]);

        return $categories->map(fn (array $c) => $c + ['leader_name' => $names[$c['leader_user_id']] ?? null]);
    }

    /** One category's ranking, best first, ready to draw. */
    public function table(Federation $federation, string $key): Collection
    {
        $rows = $this->live(RankingPoint::where('federation_id', $federation->id)->where('category_key', $key))
            ->with(['user:id,uuid,full_name,name,gender,nationality,profile_picture,profile_picture_is_public,updated_at', 'registration:id,user_id,photo,updated_at', 'registration.user:id,profile_picture,profile_picture_is_public,updated_at'])
            ->get();

        return $this->rank($rows)->map(function (array $r) {
            $latest = $r['latest'];

            return $r + [
                'name' => (string) ($latest->user?->full_name ?: $latest->user?->name),
                'gender' => $latest->user?->gender,
                'uuid' => $latest->user?->uuid,
                'photo' => CompetitorFace::for($latest->registration),
                'club' => $latest->club_name,
                // Their nationality as it stands on their profile today; the
                // ledger's copy only when none is on file.
                'country' => $latest->user?->nationality ?: $latest->country,
            ];
        });
    }

    /**
     * The top of several categories at once — for a page that shows "who leads
     * each weight class" (the federation event's Analytics tab).
     *
     * @param  list<string>  $keys  in the order they should come back
     * @return list<array{key:string, name:string, discipline:string, athletes:int, top:Collection}>
     */
    public function leaders(Federation $federation, array $keys, int $take = 3): array
    {
        $meta = $this->categories($federation)->keyBy('key');

        $out = [];
        foreach (array_unique($keys) as $key) {
            if (! $meta->has($key)) {
                continue;   // a division nobody has ranking points in yet
            }
            $table = $this->table($federation, $key);
            $out[] = [
                'key' => $key,
                'name' => $meta[$key]['name'],
                'discipline' => $meta[$key]['discipline'],
                'athletes' => $table->count(),
                'top' => $table->take($take)->values(),
            ];
        }

        return $out;
    }

    /**
     * Where one athlete stands in each category they are ranked in.
     *
     * @return Collection<int, array{key:string, name:string, position:int, of:int, points:float}>
     */
    public function forAthlete(Federation $federation, int $userId): Collection
    {
        $keys = $this->live(RankingPoint::where('federation_id', $federation->id)->where('user_id', $userId))
            ->distinct()->pluck('category_key');

        return $keys->map(function (string $key) use ($federation, $userId) {
            $rows = $this->live(RankingPoint::where('federation_id', $federation->id)->where('category_key', $key))
                ->get(['category_name', 'user_id', 'event_id', 'points', 'place', 'wins', 'score_for', 'score_against', 'earned_on']);
            $ranked = $this->rank($rows);
            $me = $ranked->firstWhere('user_id', $userId);

            return $me ? [
                'key' => $key,
                'name' => (string) $rows->sortByDesc('earned_on')->first()->category_name,
                'position' => $me['position'],
                'of' => $ranked->count(),
                'points' => $me['points'],
            ] : null;
        })->filter()->sortBy('position')->values();
    }

    /** The federation's public logo address (the one its own tab icon uses), or null. */
    public static function logo(Federation $federation): ?string
    {
        return $federation->logo && $federation->isApproved()
            ? route('federations.logo', $federation->uuid).'?v='.substr(md5((string) $federation->logo), 0, 8)
            : null;
    }

    /** 1,250 — or 612.5 when decay has halved an odd total. */
    public static function format(float|int $points): string
    {
        return fmod((float) $points, 1.0) == 0.0 ? number_format($points) : number_format($points, 1);
    }

    /** "Under 10 Years Kumite Male -25 kg" in group "Under 10 Years" → "Kumite Male -25 kg". */
    public static function shortName(string $name, string $group): string
    {
        return $group !== '' && str_starts_with($name, $group) ? trim(substr($name, strlen($group))) : $name;
    }

    /** Ledger rows young enough to count. */
    private function live($query)
    {
        return $query->where('earned_on', '>', now()->subYears(count(Wkf::DECAY))->toDateString());
    }

    /** What a row is worth today. */
    private function decayed(RankingPoint $row): float
    {
        $earned = Carbon::parse($row->earned_on);
        $year = $earned->gt(now()->subYear()) ? 1 : 2;

        return $row->points * (Wkf::DECAY[$year] ?? 0);
    }

    /**
     * Rows → one line per athlete, ordered, with positions (shared on a full tie).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rank(Collection $rows): Collection
    {
        $lines = $rows->groupBy('user_id')->map(function (Collection $mine, $userId) {
            $for = (int) $mine->sum('score_for');
            $against = (int) $mine->sum('score_against');

            return [
                'user_id' => (int) $userId,
                'points' => round($mine->sum(fn ($r) => $this->decayed($r)), 1),
                'gold' => $mine->where('place', 1)->count(),
                'silver' => $mine->where('place', 2)->count(),
                'bronze' => $mine->where('place', 3)->count(),
                'wins' => (int) $mine->sum('wins'),
                'events' => $mine->pluck('event_id')->unique()->count(),
                'diff' => $for - $against,
                'for' => $for,
                'against' => $against,
                'latest' => $mine->sortByDesc('earned_on')->first(),
            ];
        })->values();

        $sortKey = fn (array $l) => [-$l['points'], -$l['gold'], -$l['silver'], -$l['bronze'], -$l['wins'], -$l['diff'], -$l['for'], $l['against']];
        $sorted = $lines->sort(fn ($a, $b) => $sortKey($a) <=> $sortKey($b))->values();

        $position = 0;
        $previous = null;

        return $sorted->map(function (array $l, int $i) use (&$position, &$previous, $sortKey) {
            if ($sortKey($l) !== $previous) {
                $position = $i + 1;
                $previous = $sortKey($l);
            }

            return $l + ['position' => $position];
        });
    }

    /** "Under 10 Years Kumite Male -25 kg" → "Under 10 Years". */
    private function ageGroup(string $name): string
    {
        $group = trim((string) preg_replace('/\s+(kumite|kata)\b.*$/i', '', $name));

        return $group !== '' && $group !== $name ? $group : '';
    }
}
