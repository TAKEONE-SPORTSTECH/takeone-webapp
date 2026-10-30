<?php

namespace App\Forms\Support;

use App\Forms\Models\Form;
use Illuminate\Support\Carbon;

/**
 * Turns a form's answers into numbers a person can read: a summary, a chart
 * per question, and — where the form says how — proposed decisions.
 *
 * Works on ANY form built from the schema; nothing here knows about the club
 * questionnaire. A form that wants decisions declares `settings.analysis`:
 *
 *   analysis = {
 *     grid: "grid",                         the Must/Nice/No field to score
 *     must: "must", nice: "nice",           its column values
 *     status: { rowValue: [status, effort] } what exists today, and the work
 *   }
 *
 *   demand   = (Must + 0.4 × Nice) ÷ answers × 100
 *   gap      = missing 1.0 · partial 0.6 · exists 0.2
 *   priority = demand × gap ÷ effort
 *
 * Exists and wanted (≥55) → tell clubs it is there. Missing/partial and
 * wanted (≥45) → build, filled into Now (4 effort points, 3 items), Next (6
 * points), Later. Anything else is kept or parked.
 */
class SurveyAnalytics
{
    private const GAP = ['missing' => 1.0, 'partial' => 0.6, 'exists' => 0.2];

    /**
     * @param  list<array>  $answers  each a submission's `data` plus `_at` (ISO time)
     */
    public static function build(Form $form, array $answers): array
    {
        $n = count($answers);
        $out = [
            'n' => $n,
            'summary' => self::summary($answers),
            'timeline' => self::timeline($answers),
            'pages' => [],
            'decisions' => null,
            'highlights' => [],
        ];
        if ($n === 0) {
            return $out;
        }

        foreach (($form->schema['pages'] ?? []) as $page) {
            $qs = [];
            foreach (($page['fields'] ?? []) as $f) {
                $q = self::question($f, $answers);
                if ($q) {
                    $qs[] = $q;
                }
            }
            if ($qs) {
                $out['pages'][] = [
                    'title' => $page['title'] ?? '', 'title_ar' => $page['title_ar'] ?? '',
                    'private' => ! empty($page['private']), 'questions' => $qs,
                ];
            }
        }

        $out['decisions'] = self::decisions($form, $answers);
        $out['highlights'] = self::highlights($out['pages']);

        return $out;
    }

    private static function summary(array $answers): array
    {
        $n = count($answers);
        $at = array_filter(array_map(fn ($a) => $a['_at'] ?? null, $answers));
        $week = Carbon::now()->subDays(7);

        return [
            'total' => $n,
            'week' => count(array_filter($at, fn ($t) => Carbon::parse($t)->gte($week))),
            'visits' => count(array_filter($answers, fn ($a) => ($a['_mode'] ?? '') === 'visit')),
            'arabic' => count(array_filter($answers, fn ($a) => ($a['_lang'] ?? '') === 'ar')),
            'last' => $at ? max($at) : null,
        ];
    }

    /** Answers per day, the last 30 days, oldest first. */
    private static function timeline(array $answers): array
    {
        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $days[Carbon::now()->subDays($i)->toDateString()] = 0;
        }
        foreach ($answers as $a) {
            $d = isset($a['_at']) ? Carbon::parse($a['_at'])->toDateString() : null;
            if ($d && isset($days[$d])) {
                $days[$d]++;
            }
        }

        return array_map(fn ($d, $c) => ['date' => $d, 'count' => $c], array_keys($days), $days);
    }

    private static function question(array $f, array $answers): ?array
    {
        $key = $f['key'] ?? null;
        $type = $f['type'] ?? 'text';
        if (! $key || in_array($type, ['heading', 'paragraph', 'checkbox', 'terms', 'file'], true)) {
            return null;
        }
        $vals = array_map(fn ($a) => $a[$key] ?? null, $answers);
        $given = array_values(array_filter($vals, fn ($v) => $v !== null && $v !== '' && $v !== []));
        $base = [
            'key' => $key, 'type' => $type, 'label' => $f['label'] ?? $key, 'label_ar' => $f['label_ar'] ?? '',
            'answered' => count($given), 'of' => count($answers), 'private' => ! empty($f['private']),
        ];

        switch ($type) {
            case 'radio':
            case 'select':
            case 'checkboxes':
                $rows = [];
                foreach (($f['options'] ?? []) as $o) {
                    $c = count(array_filter($given, fn ($v) => is_array($v) ? in_array($o['value'], $v, true) : (string) $v === (string) $o['value']));
                    $rows[] = ['label' => $o['label'] ?? $o['value'], 'label_ar' => $o['label_ar'] ?? '', 'count' => $c,
                        'pct' => $base['answered'] ? (int) round($c / $base['answered'] * 100) : 0];
                }
                usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);

                return $base + ['chart' => 'bars', 'many' => $type === 'checkboxes', 'rows' => $rows,
                    'texts' => self::texts($answers, $key.'__other', 6), 'notes' => self::texts($answers, $key.'__note', 6)];

            case 'scale':
                $min = (int) ($f['min'] ?? 1);
                $max = (int) ($f['max'] ?? 5);
                $dist = [];
                for ($i = $min; $i <= $max; $i++) {
                    $dist[] = ['value' => $i, 'count' => count(array_filter($given, fn ($v) => (int) $v === $i))];
                }
                $nums = array_map('intval', $given);

                return $base + ['chart' => 'scale', 'min' => $min, 'max' => $max, 'dist' => $dist,
                    'mean' => $nums ? round(array_sum($nums) / count($nums), 1) : null,
                    'low' => $f['low'] ?? '', 'low_ar' => $f['low_ar'] ?? '', 'high' => $f['high'] ?? '', 'high_ar' => $f['high_ar'] ?? '',
                    'notes' => self::texts($answers, $key.'__note', 6)];

            case 'numbers':
                $rows = [];
                foreach (($f['rows'] ?? []) as $r) {
                    $xs = array_values(array_filter(array_map(fn ($v) => is_array($v) && isset($v[$r['value']]) && $v[$r['value']] !== '' ? (float) $v[$r['value']] : null, $given), fn ($x) => $x !== null));
                    sort($xs);
                    $rows[] = ['label' => $r['label'], 'label_ar' => $r['label_ar'] ?? '', 'n' => count($xs),
                        'median' => $xs ? self::median($xs) : null, 'min' => $xs ? $xs[0] : null, 'max' => $xs ? end($xs) : null,
                        'total' => $xs ? array_sum($xs) : null];
                }

                return $base + ['chart' => 'numbers', 'rows' => $rows];

            case 'grid':
                $cols = $f['columns'] ?? [];
                $rows = [];
                foreach (($f['rows'] ?? []) as $r) {
                    $counts = [];
                    foreach ($cols as $c) {
                        $counts[$c['value']] = count(array_filter($given, fn ($v) => is_array($v) && ($v[$r['value']] ?? null) === $c['value']));
                    }
                    $first = $cols[0]['value'] ?? null;
                    $second = $cols[1]['value'] ?? null;
                    $score = $base['answered'] ? (int) round((($counts[$first] ?? 0) + 0.4 * ($counts[$second] ?? 0)) / $base['answered'] * 100) : 0;
                    $rows[] = ['value' => $r['value'], 'label' => $r['label'], 'label_ar' => $r['label_ar'] ?? '', 'counts' => $counts, 'score' => $score];
                }
                usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);

                return $base + ['chart' => 'grid', 'columns' => $cols, 'rows' => $rows];

            case 'rank':
                $max = (int) ($f['max'] ?? 5);
                $pts = [];
                foreach ($given as $list) {
                    foreach (array_values((array) $list) as $i => $v) {
                        $pts[$v] = ($pts[$v] ?? 0) + ($max - $i);
                    }
                }
                $rows = [];
                foreach (($f['rows'] ?? []) as $r) {
                    if (! empty($pts[$r['value']])) {
                        $rows[] = ['label' => $r['label'], 'label_ar' => $r['label_ar'] ?? '', 'points' => $pts[$r['value']],
                            'firsts' => count(array_filter($given, fn ($l) => (array_values((array) $l)[0] ?? null) === $r['value']))];
                    }
                }
                usort($rows, fn ($a, $b) => $b['points'] <=> $a['points']);

                return $base + ['chart' => 'rank', 'rows' => array_slice($rows, 0, 10)];

            default: // text, textarea, phone, email, number, date, note
                return $base + ['chart' => 'texts', 'texts' => self::texts($answers, $key, 4)];
        }
    }

    /** The latest non-empty answers to a free-text key, newest first. */
    private static function texts(array $answers, string $key, int $limit): array
    {
        $out = [];
        foreach (array_reverse($answers) as $a) {
            $v = $a[$key] ?? null;
            if (is_scalar($v) && trim((string) $v) !== '') {
                $who = $key === 'cover_club' ? null : ($a['cover_club'] ?? null);
                $out[] = ['text' => mb_strimwidth((string) $v, 0, 600, '…'), 'who' => $who, 'lang' => $a['_lang'] ?? 'en'];
                if (count($out) >= $limit) {
                    break;
                }
            }
        }

        return $out;
    }

    private static function median(array $xs): float
    {
        $c = count($xs);
        $m = intdiv($c, 2);

        return $c % 2 ? $xs[$m] : ($xs[$m - 1] + $xs[$m]) / 2;
    }

    private static function decisions(Form $form, array $answers): ?array
    {
        $cfg = $form->settings['analysis'] ?? null;
        if (! is_array($cfg) || empty($cfg['grid'])) {
            return null;
        }
        $field = collect($form->fields())->firstWhere('key', $cfg['grid']);
        if (! $field || ($field['type'] ?? '') !== 'grid') {
            return null;
        }
        $must = $cfg['must'] ?? 'must';
        $nice = $cfg['nice'] ?? 'nice';
        $given = array_values(array_filter(array_map(fn ($a) => $a[$cfg['grid']] ?? null, $answers), 'is_array'));
        $n = count($given);
        if ($n === 0) {
            return null;
        }

        $items = [];
        foreach (($field['rows'] ?? []) as $r) {
            $m = count(array_filter($given, fn ($g) => ($g[$r['value']] ?? null) === $must));
            $ni = count(array_filter($given, fn ($g) => ($g[$r['value']] ?? null) === $nice));
            [$status, $effort] = array_pad((array) ($cfg['status'][$r['value']] ?? ['missing', 2]), 2, 2);
            $status = isset(self::GAP[$status]) ? $status : 'missing';
            $effort = max(1, min(3, (int) $effort));
            $demand = (int) round(($m + 0.4 * $ni) / $n * 100);
            $opp = $demand * self::GAP[$status];
            $items[] = [
                'value' => $r['value'], 'label' => $r['label'], 'label_ar' => $r['label_ar'] ?? '',
                'must' => $m, 'nice' => $ni, 'demand' => $demand, 'status' => $status, 'effort' => $effort,
                'priority' => round($opp / $effort, 1),
                'lane' => $status === 'exists' ? ($demand >= 55 ? 'show' : 'keep') : ($demand >= 45 ? 'build' : 'park'),
            ];
        }

        $build = array_values(array_filter($items, fn ($i) => $i['lane'] === 'build'));
        usort($build, fn ($a, $b) => $b['priority'] <=> $a['priority']);
        $lanes = ['now' => [], 'next' => [], 'later' => []];
        $used = 0;
        $nextUsed = 0;
        foreach ($build as $b) {
            if ($used + $b['effort'] <= 4 && count($lanes['now']) < 3) {
                $lanes['now'][] = $b;
                $used += $b['effort'];
            } elseif ($nextUsed + $b['effort'] <= 6) {
                $lanes['next'][] = $b;
                $nextUsed += $b['effort'];
            } else {
                $lanes['later'][] = $b;
            }
        }
        $show = array_values(array_filter($items, fn ($i) => $i['lane'] === 'show'));
        usort($show, fn ($a, $b) => $b['demand'] <=> $a['demand']);
        $lanes['show'] = $show;
        usort($items, fn ($a, $b) => $b['demand'] <=> $a['demand']);

        return ['n' => $n, 'items' => $items, 'lanes' => $lanes];
    }

    /** A few plain sentences: the strongest agreement, the sharpest pain, the top wish. */
    private static function highlights(array $pages): array
    {
        $out = [];
        foreach ($pages as $p) {
            foreach ($p['questions'] as $q) {
                // Only what enough people answered: one club's tick is not a trend.
                if ($q['answered'] < max(2, (int) ceil($q['of'] * 0.5))) {
                    continue;
                }
                if ($q['chart'] === 'scale' && $q['mean'] !== null) {
                    $out[] = ['kind' => 'scale', 'q' => $q, 'value' => $q['mean'], 'weight' => $q['mean'] / max(1, $q['max'])];
                }
                if ($q['chart'] === 'bars' && ($q['rows'][0]['count'] ?? 0) > 0) {
                    $out[] = ['kind' => 'consensus', 'q' => $q, 'row' => $q['rows'][0], 'weight' => $q['rows'][0]['pct'] / 100];
                }
            }
        }
        usort($out, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        return array_slice($out, 0, 6);
    }
}
