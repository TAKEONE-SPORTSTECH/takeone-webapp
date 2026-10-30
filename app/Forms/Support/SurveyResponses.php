<?php

namespace App\Forms\Support;

use App\Forms\Models\Form;
use Illuminate\Support\Carbon;

/**
 * A form's answers in the three shapes the results page needs: the raw rows
 * the analytics read, a readable list of responses, and a flat table for the
 * spreadsheet export. Also the bundled EXAMPLE answers a template may ship
 * (app/Forms/templates/{name}.example.json) — read from disk, never stored,
 * so a preview can never be mistaken for, or mixed into, real answers.
 */
class SurveyResponses
{
    /** @return list<array> each submission's data plus `_at` */
    public static function rows(Form $form): array
    {
        return $form->submissions()->oldest('id')->limit(5000)->get(['id', 'data', 'created_at'])
            ->map(fn ($s) => (array) $s->data + ['_at' => $s->created_at?->toIso8601String(), '_id' => $s->id])
            ->all();
    }

    public static function hasExample(Form $form): bool
    {
        return self::exampleFile($form) !== null;
    }

    public static function example(Form $form): array
    {
        $file = self::exampleFile($form);
        $rows = $file ? (json_decode((string) file_get_contents($file), true) ?: []) : [];

        return array_map(fn ($r, $i) => $r + [
            '_at' => Carbon::now()->subDays((int) ($r['_days_ago'] ?? 0))->setTime(10 + $i % 8, 15)->toIso8601String(),
            '_id' => 'example-'.($i + 1),
        ], $rows, array_keys($rows));
    }

    private static function exampleFile(Form $form): ?string
    {
        $name = (string) ($form->settings['template'] ?? '');
        if (! preg_match('/^[a-z0-9-]{1,60}$/', $name)) {
            return null;
        }
        $file = app_path("Forms/templates/{$name}.example.json");

        return is_file($file) ? $file : null;
    }

    /**
     * Readable responses, newest first: who, when, how complete, and every
     * answer as label → text, ready to draw with x-text (never as HTML).
     */
    public static function list(Form $form, array $rows, int $limit = 100): array
    {
        $fields = [];
        foreach (($form->schema['pages'] ?? []) as $page) {
            foreach (($page['fields'] ?? []) as $f) {
                if (! empty($f['key']) && ! in_array($f['type'] ?? '', ['heading', 'paragraph'], true)) {
                    $fields[] = $f + ['_page' => $page['title'] ?? '', '_page_ar' => $page['title_ar'] ?? ''];
                }
            }
        }
        $countable = array_filter($fields, fn ($f) => ($f['type'] ?? '') !== 'note');

        $out = [];
        foreach (array_slice(array_reverse($rows), 0, $limit) as $r) {
            $answers = [];
            $done = 0;
            foreach ($fields as $f) {
                $text = self::readable($f, $r);
                if ($text === '') {
                    continue;
                }
                if (($f['type'] ?? '') !== 'note') {
                    $done++;
                }
                $answers[] = ['page' => $f['_page'], 'page_ar' => $f['_page_ar'], 'q' => $f['label'] ?? $f['key'],
                    'q_ar' => $f['label_ar'] ?? '', 'a' => $text, 'private' => ! empty($f['private'])];
            }
            $out[] = [
                'id' => (string) ($r['_id'] ?? ''),
                'who' => $r['cover_club'] ?? null,
                'area' => $r['cover_area'] ?? null,
                'at' => $r['_at'] ?? null,
                'lang' => $r['_lang'] ?? 'en',
                'mode' => $r['_mode'] ?? 'self',
                'complete' => $countable ? (int) round($done / count($countable) * 100) : 0,
                'answers' => $answers,
            ];
        }

        return $out;
    }

    /** @return array{0: list<string>, 1: list<list<string>>} */
    public static function table(Form $form, array $rows): array
    {
        $cols = [];
        foreach ($form->fields() as $f) {
            if (! empty($f['key'])) {
                $cols[] = $f;
            }
        }
        $head = array_merge(['answered_at', 'mode', 'language'], array_map(fn ($f) => ($f['key']).' — '.($f['label'] ?? ''), $cols));
        $lines = [];
        foreach ($rows as $r) {
            $lines[] = array_merge(
                [$r['_at'] ?? '', $r['_mode'] ?? '', $r['_lang'] ?? ''],
                array_map(fn ($f) => self::readable($f, $r, ' | '), $cols)
            );
        }

        return [$head, $lines];
    }

    /** One answer as plain text, in English option labels. */
    public static function readable(array $f, array $r, string $sep = ' · '): string
    {
        $key = $f['key'];
        $v = $r[$key] ?? null;
        $lab = function (string $list, $value) use ($f) {
            foreach (($f[$list] ?? []) as $o) {
                if ((string) $o['value'] === (string) $value) {
                    return $o['label'] ?? (string) $value;
                }
            }

            return (string) $value;
        };
        $text = match ($f['type'] ?? 'text') {
            'radio', 'select' => $v === null || $v === '' ? '' : $lab('options', $v),
            'checkboxes' => is_array($v) ? implode($sep, array_map(fn ($x) => $lab('options', $x), $v)) : '',
            'scale' => $v === null || $v === '' ? '' : $v.' / '.($f['max'] ?? 5),
            'numbers' => is_array($v) ? implode($sep, array_filter(array_map(fn ($row) => isset($v[$row['value']]) && $v[$row['value']] !== '' ? $row['label'].': '.$v[$row['value']] : null, $f['rows'] ?? []))) : '',
            'grid' => is_array($v) ? implode($sep, array_filter(array_map(fn ($row) => isset($v[$row['value']]) ? $row['label'].': '.$lab('columns', $v[$row['value']]) : null, $f['rows'] ?? []))) : '',
            'rank' => is_array($v) ? implode($sep, array_map(fn ($x, $i) => ($i + 1).'. '.$lab('rows', $x), array_values($v), array_keys(array_values($v)))) : '',
            'checkbox', 'terms' => $v ? 'Yes' : '',
            default => is_scalar($v) ? (string) $v : '',
        };
        foreach (['__other' => 'Other: ', '__note' => 'Note: '] as $suffix => $prefix) {
            if (! empty($r[$key.$suffix]) && is_scalar($r[$key.$suffix])) {
                $text .= ($text !== '' ? $sep : '').$prefix.$r[$key.$suffix];
            }
        }

        return $text;
    }
}
