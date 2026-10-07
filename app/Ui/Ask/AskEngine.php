<?php

namespace App\Ui\Ask;

use App\Ai\AiManager;
use App\Clubs\Models\ClubPackage;
use App\Members\Models\User;
use App\Platform\Models\ActivityCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Ask TAKEONE" — turns what a member types into a search, runs it, and
 * answers with real listings.
 *
 * The language model only READS the request: it returns a small structured
 * intent (what, for whom, ages, budget, gender, times). The search itself is
 * ordinary queries over the same data Explore shows to any signed-in member,
 * so the model never sees the database and can never invent a club, a price
 * or a time. If the model is unreachable, a simple reader stands in.
 */
class AskEngine
{
    private const DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /** @param  list<array{role:string,content:string}>  $history */
    public function answer(User $user, string $message, array $history = []): array
    {
        $intent = $this->understand($message, $history);
        $usedAi = $intent['_ai'] ?? false;

        $coaches = in_array('coach', $intent['looking_for'], true) ? $this->coaches($intent) : null;
        $clubs = $coaches === null || in_array('club', $intent['looking_for'], true) || $intent['children_count'] ? $this->packages($intent) : collect();
        $plan = $intent['children_count'] >= 1 && $clubs->isNotEmpty() ? $this->plan($intent, $clubs) : null;

        $missing = $intent['activities'] && $clubs->isNotEmpty() && ! $clubs->contains(fn ($c) => $c['activity_hit'])
            ? __('ui::ask.missing_activity', ['activity' => implode(', ', $intent['activities'])]) : null;
        $clubs = $clubs->map(fn ($c) => collect($c)->except(['all', 'activity_hit', 'score'])->all());

        return [
            'reply' => trim(($missing ? $missing.' ' : '').$this->reply($intent, $coaches, $clubs, $plan)),
            'question' => $this->question($intent, $coaches, $clubs),
            'clubs' => $clubs->take(6)->values()->all(),
            'coaches' => $coaches?->values()->all() ?? [],
            'plan' => $plan,
            'filter' => $this->filterLabel($intent),
            'ai' => $usedAi,
        ];
    }

    /* ------------------------------------------------------------------ intent */

    private function understand(string $message, array $history): array
    {
        $base = $this->fallback($message);

        // A clear request needs no model: the reader already has what to search for.
        if ($base['activities'] || $base['children_count'] || $base['mixed'] || in_array('coach', $base['looking_for'], true)) {
            return $base;
        }

        try {
            $catalog = ActivityCatalog::where('is_active', true)->pluck('name')->implode(', ');
            $system = 'You extract a sports-search request into JSON. Reply with ONLY one JSON object, no prose. Keys: '
                .'looking_for (array of "coach","club","event"), activities (array of activity names from this list when they match: '.$catalog.'), '
                .'mixed (true if they want several activities / multi-sport / variety), children_count (integer, 0 if not for children), '
                .'children_ages (array of integers, empty if unknown), gender_pref ("female","male" or null — the TRAINER\'s or class gender they prefer), '
                .'budget_min, budget_max (numbers in BHD or null), budget_per ("hour","month" or null), time_from, time_to ("HH:MM" 24h or null), days (array of weekday names or empty).';
            $msgs = [['role' => 'system', 'content' => $system]];
            foreach (array_slice($history, -6) as $h) {
                $msgs[] = ['role' => $h['role'] === 'assistant' ? 'assistant' : 'user', 'content' => Str::limit($h['content'], 600)];
            }
            $msgs[] = ['role' => 'user', 'content' => $message];

            $out = app(AiManager::class)->text()->chat($msgs, [], ['temperature' => 0, 'format' => 'json', 'timeout' => 25]);
            $raw = (string) ($out['content'] ?? '');
            if (preg_match('/\{.*\}/s', $raw, $m) && is_array($j = json_decode($m[0], true))) {
                return $this->normalise($j, $base, $message) + ['_ai' => true];
            }
        } catch (Throwable $e) {
            Log::info('ask: model unavailable, using the built-in reader', ['error' => Str::limit($e->getMessage(), 160)]);
        }

        return $base;
    }

    /** Merge a model's answer over the built-in reading, keeping only well-formed values. */
    private function normalise(array $j, array $base, string $message): array
    {
        // A model only adds what the message itself supports: no money words →
        // no budget; no clock time → no times; no digits → no ages; only
        // activities that exist in the catalogue.
        $t = mb_strtolower($message);
        $saysMoney = (bool) preg_match('/bd|bhd|dinar|budget|price|cost|\$|دينار|ميزانية/u', $t);
        $saysTime = (bool) preg_match('/\d\s*(am|pm)|\d:\d\d|o\'clock|صباح|مساء|ظهر/u', $t);
        $saysDigits = (bool) preg_match('/\d/', $t);
        $known = ActivityCatalog::where('is_active', true)->pluck('name')->mapWithKeys(fn ($n) => [mb_strtolower($n) => $n]);
        $j['activities'] = collect((array) ($j['activities'] ?? []))->map(fn ($a) => $known[mb_strtolower(trim((string) $a))] ?? null)->filter()->values()->all();
        if (! $saysMoney) { $j['budget_min'] = $j['budget_max'] = null; }
        if (! $saysTime) { $j['time_from'] = $j['time_to'] = null; }
        if (! $saysDigits) { $j['children_ages'] = []; }
        $j['looking_for'] = array_values(array_intersect((array) ($j['looking_for'] ?? []), ['coach', 'club']));

        $num = fn ($v) => is_numeric($v) ? (float) $v : null;
        $time = fn ($v) => is_string($v) && preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim($v)) ? str_pad(trim($v), 5, '0', STR_PAD_LEFT) : null;
        $looking = array_values(array_intersect(['coach', 'club', 'event'], array_map('strval', (array) ($j['looking_for'] ?? []))));
        $ages = array_values(array_filter(array_map(fn ($a) => is_numeric($a) ? (int) $a : null, (array) ($j['children_ages'] ?? [])), fn ($a) => $a !== null && $a >= 1 && $a <= 18));

        return [
            'looking_for' => $looking ?: $base['looking_for'],
            'activities' => array_values(array_filter(array_map(fn ($a) => Str::limit(trim((string) $a), 40, ''), (array) ($j['activities'] ?? [])))) ?: $base['activities'],
            'mixed' => (bool) ($j['mixed'] ?? $base['mixed']),
            'children_count' => max((int) ($j['children_count'] ?? 0), count($ages), $base['children_count']),
            'children_ages' => $ages ?: $base['children_ages'],
            'gender_pref' => in_array($j['gender_pref'] ?? null, ['female', 'male'], true) ? $j['gender_pref'] : $base['gender_pref'],
            'budget_min' => $num($j['budget_min'] ?? null) ?? $base['budget_min'],
            'budget_max' => $num($j['budget_max'] ?? null) ?? $base['budget_max'],
            'budget_per' => in_array($j['budget_per'] ?? null, ['hour', 'month'], true) ? $j['budget_per'] : $base['budget_per'],
            'time_from' => $time($j['time_from'] ?? null) ?? $base['time_from'],
            'time_to' => $time($j['time_to'] ?? null) ?? $base['time_to'],
            'days' => array_values(array_intersect(self::DAYS, array_map(fn ($d) => mb_strtolower((string) $d), (array) ($j['days'] ?? [])))) ?: $base['days'],
        ];
    }

    /** The built-in reader: plain keyword and number spotting. */
    public function fallback(string $text): array
    {
        $t = mb_strtolower($text);
        $looking = [];
        if (preg_match('/trainer|coach|personal training|مدرب/u', $t)) $looking[] = 'coach';
        if (preg_match('/club|academy|class|package|نادي|أندية/u', $t)) $looking[] = 'club';

        $words = ['swim' => 'Swimming', 'سباح' => 'Swimming', 'karate' => 'Karate', 'كاراتيه' => 'Karate', 'taekwondo' => 'Taekwondo', 'judo' => 'Judo',
            'jiu' => 'Brazilian Jiu-Jitsu', 'bjj' => 'Brazilian Jiu-Jitsu', 'box' => 'Boxing', 'padel' => 'Padel', 'tennis' => 'Tennis', 'football' => 'Football',
            'basket' => 'Basketball', 'yoga' => 'Yoga', 'pilates' => 'Pilates', 'fitness' => 'Fitness Training', 'gym' => 'Fitness Training', 'gymnast' => 'Gymnastics'];
        $acts = [];
        foreach ($words as $w => $name) {
            if (str_contains($t, $w)) $acts[$name] = $name;
        }

        $kids = 0;
        if (preg_match('/(\d+|two|three|four|five)\s+(kids|children|sons|daughters|boys|girls)/u', $t, $m)) {
            $kids = is_numeric($m[1]) ? (int) $m[1] : ['two' => 2, 'three' => 3, 'four' => 4, 'five' => 5][$m[1]];
        } elseif (preg_match('/\b(kid|kids|child|children|son|daughter)\b|أطفال|أولاد/u', $t)) {
            $kids = 1;
        }
        $ages = [];
        if (preg_match_all('/\b(\d{1,2})\s*(?:years?|yrs?|y\.?o\.?|-year)/u', $t, $m)) {
            $ages = array_map('intval', $m[1]);
        }
        // "aged 6, 9 and 12" / "ages 6 & 9" / "أعمار 6 و9"
        if (preg_match('/(?:aged?|ages|أعمار|بأعمار)\s+((?:\d{1,2}\s*(?:,|and|&|و)?\s*)+)/u', $t, $m) && preg_match_all('/\d{1,2}/', $m[1], $n)) {
            $ages = array_merge($ages, array_map('intval', $n[0]));
        }
        $ages = array_values(array_unique(array_filter($ages, fn ($a) => $a >= 1 && $a <= 18)));

        $bmin = $bmax = null; $per = null;
        // Money only where the message talks about money — "aged 9 and 12" is not a budget.
        if (preg_match('/bd|bhd|dinar|budget|price|cost|دينار|ميزانية/u', $t)) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*(?:to|-|–|and)\s*(\d+(?:\.\d+)?)\s*(?:bd|bhd|dinars?|per|a\b|an\b|\/)/u', $t, $m)
                || preg_match('/(?:budget|price|cost)[^\d]{0,20}(\d+(?:\.\d+)?)\s*(?:to|-|–|and)\s*(\d+(?:\.\d+)?)/u', $t, $m)) { $bmin = (float) $m[1]; $bmax = (float) $m[2]; }
            elseif (preg_match('/(?:under|below|max|up to|less than)\s*(\d+(?:\.\d+)?)/u', $t, $m)) { $bmax = (float) $m[1]; }
        }
        if (str_contains($t, 'hour')) $per = 'hour'; elseif (str_contains($t, 'month')) $per = 'month';

        $from = $to = null;
        if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s*(?:to|-|–|until|till)\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)/u', $t, $m)) {
            $h1 = (int) $m[1] % 12 + ((($m[3] ?: $m[6]) === 'pm') ? 12 : 0);
            $h2 = (int) $m[4] % 12 + ($m[6] === 'pm' ? 12 : 0);
            $from = sprintf('%02d:%02d', $h1, (int) ($m[2] ?: 0)); $to = sprintf('%02d:%02d', $h2, (int) ($m[5] ?: 0));
        }

        return [
            'looking_for' => $looking ?: ($kids ? ['club'] : ['club']),
            'activities' => array_values($acts),
            'mixed' => (bool) preg_match('/mix|multi|variety|different|several|كل/u', $t),
            'children_count' => max($kids, count($ages)),
            'children_ages' => $ages,
            'gender_pref' => preg_match('/female|woman|women|lady|ladies|سيدة|مدربة/u', $t) ? 'female' : (preg_match('/\bmale trainer|\bman\b/u', $t) ? 'male' : null),
            'budget_min' => $bmin, 'budget_max' => $bmax, 'budget_per' => $per,
            'time_from' => $from, 'time_to' => $to,
            'days' => array_values(array_filter(self::DAYS, fn ($d) => str_contains($t, $d))),
        ];
    }

    /* ------------------------------------------------------------------ search */

    /** Personal trainers who opted in. Rates and hours are not on file yet (NEW-UI.md, phase 8). */
    private function coaches(array $i): Collection
    {
        return User::where('is_personal_trainer', true)
            ->when($i['gender_pref'], fn ($q, $g) => $q->where('gender', $g === 'female' ? 'Female' : 'Male'))
            ->limit(12)->get()
            ->map(fn (User $u) => [
                'name' => (string) ($u->full_name ?? $u->name),
                'url' => route('trainer.show', $u->id),
                'photo' => ($u->profile_picture && $u->profile_picture_is_public) ? file_url($u->profile_picture) : null,
                'gender' => $u->gender,
                'why' => collect([$u->gender, $u->experience_years ? $u->experience_years.' yrs' : null])->filter()->implode(' · '),
            ]);
    }

    /** Clubs with the packages that fit, best first. */
    private function packages(array $i): Collection
    {
        $wanted = array_map(fn ($a) => mb_strtolower($a), $i['activities']);
        $stems = array_map(fn ($a) => mb_substr(preg_replace('/[^a-z]/', '', $a) ?: $a, 0, 5), $wanted);
        $ages = $i['children_ages'];
        $forKids = $i['children_count'] > 0;

        $sportsPerClub = \App\Clubs\Models\ClubActivity::query()->selectRaw('tenant_id, COUNT(DISTINCT LOWER(name)) as c')->groupBy('tenant_id')->pluck('c', 'tenant_id');

        $rows = ClubPackage::where('is_active', true)
            ->with(['tenant:id,club_name,slug,logo,cover_image,address,country,currency', 'activities:club_activities.id,club_activities.name,club_activities.schedule'])
            ->get()
            ->map(function (ClubPackage $p) use ($i, $stems, $ages, $forKids, $sportsPerClub) {
                if (! $p->tenant) return null;
                $names = mb_strtolower($p->name.' '.$p->activities->pluck('name')->implode(' '));
                $actHit = $stems === [] || collect($stems)->contains(fn ($s) => $s !== '' && str_contains(preg_replace('/[^a-z ]/', '', $names), $s));
                $multiClub = $i['mixed'] && ($p->type === 'multi' || (int) ($sportsPerClub[$p->tenant_id] ?? 0) >= 3);
                if (! $actHit && ! $multiClub) return null;

                $fitsAge = function (?int $a) use ($p) { return $a === null || (($p->age_min === null || $a >= $p->age_min) && ($p->age_max === null || $a <= $p->age_max)); };
                if ($ages) { if (! collect($ages)->contains(fn ($a) => $fitsAge($a))) return null; }
                elseif ($forKids && $p->age_min !== null && $p->age_min > 12) return null; // children, ages not given: 12 and under

                if ($i['gender_pref'] === 'female' && ! in_array($p->gender, ['female', 'mixed', null], true)) return null;
                if ($i['gender_pref'] === 'male' && ! in_array($p->gender, ['male', 'mixed', null], true)) return null;

                $monthly = (float) $p->price / max(1, (int) ($p->duration_months ?: 1));
                if ($i['budget_per'] !== 'hour') {
                    if ($i['budget_max'] !== null && $monthly > $i['budget_max']) return null;
                    if ($i['budget_min'] !== null && $monthly < $i['budget_min']) return null;
                }

                $slots = $p->activities->flatMap(fn ($a) => collect(is_array($a->schedule) ? $a->schedule : (json_decode((string) $a->schedule, true) ?: [])))
                    ->filter(fn ($s) => isset($s['day'], $s['time']))->values();
                if ($i['time_from'] && $slots->isNotEmpty()) {
                    $slots = $slots->filter(fn ($s) => $s['time'] >= $i['time_from'] && $s['time'] < ($i['time_to'] ?? '24:00'))->values();
                    if ($slots->isEmpty()) return null;
                }
                if ($i['days'] && $slots->isNotEmpty()) {
                    $slots = $slots->filter(fn ($s) => in_array(mb_strtolower($s['day']), $i['days'], true))->values();
                    if ($slots->isEmpty()) return null;
                }

                $score = ($actHit && $stems ? 3 : 0) + ($i['mixed'] && $p->type === 'multi' ? 3 : 0) + ($ages ? 2 : 0);

                return ['p' => $p, 'monthly' => $monthly, 'slots' => $slots, 'score' => $score, 'fitsAge' => $fitsAge, 'actHit' => $actHit && $stems !== []];
            })->filter();

        return $rows->groupBy(fn ($r) => $r['p']->tenant_id)->map(function ($list) {
            $club = $list->first()['p']->tenant;

            return [
                'key' => $club->slug,
                'name' => (string) $club->club_name,
                'url' => $club->url,
                'logo' => $club->logo ? file_url($club->logo) : null,
                'cover' => $club->cover_image ? file_url($club->cover_image) : null,
                'area' => $club->address ? Str::limit(trim(explode(',', $club->address)[1] ?? explode(',', $club->address)[0]), 28) : null,
                'score' => $list->max('score'),
                'activity_hit' => $list->contains(fn ($r) => $r['actHit']),
                'all' => $list->sortByDesc('score')->map(fn ($r) => [
                    'id' => $r['p']->id,
                    'name' => (string) $r['p']->name,
                    'type' => $r['p']->type,
                    'ages' => $this->ages($r['p']->age_min, $r['p']->age_max),
                    'age_min' => $r['p']->age_min, 'age_max' => $r['p']->age_max,
                    'price' => $this->money($r['monthly'], (string) ($club->currency ?: 'BHD')),
                    'monthly' => round($r['monthly'], 3),
                    'when' => $r['slots']->map(fn ($s) => mb_substr(__('ui::ask.day_'.mb_strtolower($s['day'])), 0, 3).' '.$s['time'])->unique()->take(4)->implode(' · '),
                ])->values()->all(),
            ];
        })->map(fn ($c) => $c + ['packages' => array_slice($c['all'], 0, 3)])->sortByDesc('score')->values();
    }

    /** One package per child, preferring one club so a parent is not driving across the island. */
    private function plan(array $i, Collection $clubs): ?array
    {
        $n = max(1, $i['children_count']);
        $ages = $i['children_ages'];
        $all = $clubs->flatMap(fn ($c) => collect($c['all'])->map(fn ($p) => $p + ['club' => $c['name'], 'club_key' => $c['key']]));
        if ($all->isEmpty()) return null;

        $kids = []; $total = 0.0; $prevClub = null; $used = [];
        for ($k = 0; $k < $n; $k++) {
            $age = $ages[$k] ?? null;
            $pick = $all->map(function ($p) use ($age, $prevClub, $i, $used) {
                $fits = $age === null || (($p['age_min'] === null || $age >= $p['age_min']) && ($p['age_max'] === null || $age <= $p['age_max']));
                return $p + ['_s' => ($fits ? 5 : -10) + ($i['mixed'] && $p['type'] === 'multi' ? 3 : 0) + ($p['club_key'] === $prevClub ? 2 : 0)
                    - ($i['mixed'] && in_array($p['id'], $used, true) ? 4 : 0)];
            })->sortByDesc('_s')->first();
            if (! $pick || $pick['_s'] < 0) continue;
            $prevClub = $pick['club_key'];
            $used[] = $pick['id'];
            $total += $pick['monthly'];
            $kids[] = ['label' => __('ui::ask.child_n', ['n' => $k + 1]).($age ? ' · '.__('ui::ask.age_n', ['n' => $age]) : ''),
                'club' => $pick['club'], 'package' => $pick['name'], 'price' => $pick['price'], 'when' => $pick['when']];
        }

        return $kids ? ['children' => $kids, 'total' => $this->money($total, 'BHD')] : null;
    }

    /* ------------------------------------------------------------------ words */

    private function reply(array $i, ?Collection $coaches, Collection $clubs, ?array $plan): string
    {
        $parts = [];
        if ($coaches !== null) {
            $parts[] = $coaches->isEmpty()
                ? __('ui::ask.no_coaches'.($i['gender_pref'] ? '_'.$i['gender_pref'] : ''))
                : trans_choice('ui::ask.found_coaches', $coaches->count(), ['count' => $coaches->count()]);
            if ($coaches->isNotEmpty() && ($i['budget_max'] || $i['time_from'])) $parts[] = __('ui::ask.coach_rates_missing');
        }
        if ($coaches === null || $clubs->isNotEmpty()) {
            $parts[] = $clubs->isEmpty() ? __('ui::ask.no_clubs') : trans_choice('ui::ask.found_clubs', $clubs->count(), ['count' => $clubs->count()]);
        }
        if ($plan) $parts[] = __('ui::ask.plan_intro', ['total' => $plan['total']]);

        return implode(' ', $parts);
    }

    private function question(array $i, ?Collection $coaches, Collection $clubs): ?string
    {
        if ($i['children_count'] > 0 && count($i['children_ages']) < $i['children_count']) return __('ui::ask.q_ages');
        if ($coaches !== null && ! $i['days']) return __('ui::ask.q_days');
        if ($clubs->isEmpty() && $i['budget_max']) return __('ui::ask.q_budget');

        return null;
    }

    private function filterLabel(array $i): string
    {
        return collect([
            implode(', ', $i['activities']),
            $i['mixed'] ? __('ui::ask.mixed') : null,
            $i['children_count'] ? trans_choice('ui::ask.kids', $i['children_count'], ['count' => $i['children_count']]) : null,
            $i['gender_pref'] ? __('ui::ask.pref_'.$i['gender_pref']) : null,
            $i['budget_max'] ? 'BD '.($i['budget_min'] ? (float) $i['budget_min'].'–' : '≤ ').(float) $i['budget_max'] : null,
            $i['time_from'] ? $i['time_from'].'–'.($i['time_to'] ?? '') : null,
        ])->filter()->implode(' · ');
    }

    private function ages(?int $min, ?int $max): string
    {
        return $min && $max ? $min.'–'.$max : ($min ? $min.'+' : ($max ? '≤ '.$max : ''));
    }

    private function money(float $v, string $cur): string
    {
        return (strtoupper($cur) === 'BHD' ? 'BD' : strtoupper($cur)).' '.(fmod($v, 1.0) == 0.0 ? number_format($v, 0) : number_format($v, 3));
    }
}
