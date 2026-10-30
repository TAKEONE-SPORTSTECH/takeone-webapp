<?php

namespace App\Forms\Support;

use App\Forms\Models\Form;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The builder's input, made safe to store.
 *
 * The browser sends the whole form; nothing it sends is trusted. Every page,
 * question and option is rebuilt here from a whitelist of keys, lengths are
 * capped, types come from a fixed list, and identifiers are decided by the
 * server. A question keeps its `key` for life once it has one — answers are
 * stored by key, so renaming a label never orphans what was already said.
 */
class FormSchema
{
    /** What the paged survey can draw. (heading/paragraph/file/date/select/terms belong to the old card page.) */
    public const BUILDER_TYPES = ['text', 'textarea', 'number', 'email', 'phone', 'radio', 'checkboxes', 'checkbox', 'scale', 'grid', 'rank', 'numbers', 'note'];

    private const KEY = '/^[a-z0-9_]{1,40}$/';

    /** @return array{title:string, type:string, description:?string, schema:array, settings:array} */
    public static function normalise(array $in, ?Form $existing = null): array
    {
        $errors = [];
        $title = self::str($in['title'] ?? '', 150);
        if ($title === '') {
            $errors['title'] = 'Give the form a title.';
        }
        $type = in_array($in['type'] ?? '', Form::TYPES, true) ? $in['type'] : 'survey';

        $pagesIn = array_values(array_filter((array) ($in['pages'] ?? []), 'is_array'));
        if (! $pagesIn) {
            $errors['pages'] = 'A form needs at least one page.';
        }
        if (count($pagesIn) > 30) {
            $errors['pages'] = 'A form can have up to 30 pages.';
        }

        $usedKeys = [];
        $pageIds = [];
        $pages = [];
        $total = 0;
        foreach (array_slice($pagesIn, 0, 30) as $pi => $p) {
            $id = is_string($p['id'] ?? null) && preg_match(self::KEY, $p['id']) && ! isset($pageIds[$p['id']]) ? $p['id'] : 'p_'.Str::lower(Str::random(6));
            $pageIds[$id] = true;
            $fields = [];
            foreach (array_values(array_filter((array) ($p['fields'] ?? []), 'is_array')) as $fi => $f) {
                if (++$total > 400) {
                    $errors['pages'] = 'A form can have up to 400 questions.';
                    break 2;
                }
                $clean = self::field($f, $usedKeys, "pages.$pi.fields.$fi", $errors);
                if ($clean) {
                    $fields[] = $clean;
                }
            }
            $pages[] = [
                'id' => $id,
                'title' => self::str($p['title'] ?? '', 150) ?: 'Page '.($pi + 1),
                'title_ar' => self::str($p['title_ar'] ?? '', 150),
                'intro' => self::str($p['intro'] ?? '', 500),
                'intro_ar' => self::str($p['intro_ar'] ?? '', 500),
                'private' => (bool) ($p['private'] ?? false),
                'fields' => $fields,
            ];
        }

        // A ranking may draw its list from a grid on the same form — only one that exists.
        $grids = [];
        foreach ($pages as $p) {
            foreach ($p['fields'] as $f) {
                if ($f['type'] === 'grid') {
                    $grids[$f['key']] = array_column($f['columns'], 'value');
                }
            }
        }
        foreach ($pages as &$p) {
            foreach ($p['fields'] as &$f) {
                if ($f['type'] === 'rank' && ! empty($f['from'])) {
                    if (! isset($grids[$f['from']])) {
                        unset($f['from'], $f['fromValue']);
                    } elseif (! in_array($f['fromValue'] ?? '', $grids[$f['from']], true)) {
                        $f['fromValue'] = $grids[$f['from']][0] ?? 'must';
                    }
                }
            }
            unset($f);
        }
        unset($p);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $old = $existing?->settings ?? [];
        $settings = array_merge($old, [
            'layout' => 'paged',
            'bilingual' => true,
            'title_ar' => self::str($in['title_ar'] ?? '', 150),
            'description_ar' => self::str($in['description_ar'] ?? '', 2000),
            'successMessage' => self::str($in['successMessage'] ?? '', 300) ?: 'Thank you for your time.',
            'successMessage_ar' => self::str($in['successMessage_ar'] ?? '', 300) ?: 'شكراً لوقتكم.',
            'interviewer' => (bool) ($in['interviewer'] ?? false),
        ]);
        if ($settings['interviewer']) {
            $settings['visitToken'] = $old['visitToken'] ?? Str::random(40);
        } else {
            unset($settings['visitToken']); // the old visit link stops working
        }

        return [
            'title' => $title,
            'type' => $type,
            'description' => self::str($in['description'] ?? '', 2000) ?: null,
            'schema' => ['pages' => $pages],
            'settings' => $settings,
        ];
    }

    private static function field(array $f, array &$used, string $path, array &$errors): ?array
    {
        $type = in_array($f['type'] ?? '', self::BUILDER_TYPES, true) ? $f['type'] : null;
        if (! $type) {
            return null;
        }
        $key = is_string($f['key'] ?? null) && preg_match(self::KEY, $f['key']) && ! isset($used[$f['key']]) ? $f['key'] : null;
        while (! $key || isset($used[$key])) {
            $key = 'q_'.Str::lower(Str::random(6));
        }
        $used[$key] = true;

        $label = self::str($f['label'] ?? '', 500);
        if ($label === '') {
            $errors[$path.'.label'] = 'Every question needs its English wording.';
        }
        $out = [
            'id' => $key, 'key' => $key, 'type' => $type,
            'label' => $label, 'label_ar' => self::str($f['label_ar'] ?? '', 500),
            'help' => self::str($f['help'] ?? '', 500), 'help_ar' => self::str($f['help_ar'] ?? '', 500),
            'required' => (bool) ($f['required'] ?? false),
        ];
        if (! empty($f['private'])) {
            $out['private'] = true;
        }

        switch ($type) {
            case 'radio':
            case 'checkboxes':
                $out['options'] = self::list($f['options'] ?? [], 40, $path.'.options', $errors);
                if (! $out['options']) {
                    $errors[$path.'.options'] = 'A choice question needs at least one answer to pick.';
                }
                if ($type === 'checkboxes' && ! empty($f['other'])) {
                    $out['other'] = true;
                }
                break;
            case 'scale':
                $out += ['min' => 1, 'max' => 5,
                    'low' => self::str($f['low'] ?? '', 60), 'low_ar' => self::str($f['low_ar'] ?? '', 60),
                    'high' => self::str($f['high'] ?? '', 60), 'high_ar' => self::str($f['high_ar'] ?? '', 60)];
                break;
            case 'grid':
                $out['rows'] = self::list($f['rows'] ?? [], 60, $path.'.rows', $errors);
                $out['columns'] = self::list($f['columns'] ?? [], 5, $path.'.columns', $errors);
                if (! $out['rows'] || count($out['columns']) < 2) {
                    $errors[$path.'.rows'] = 'A grid needs lines and at least two answers per line.';
                }
                break;
            case 'rank':
                $out['rows'] = self::list($f['rows'] ?? [], 60, $path.'.rows', $errors);
                $out['max'] = max(1, min(10, (int) ($f['max'] ?? 5)));
                if (is_string($f['from'] ?? null) && preg_match(self::KEY, $f['from'])) {
                    $out['from'] = $f['from'];
                    $out['fromValue'] = is_string($f['fromValue'] ?? null) ? Str::limit($f['fromValue'], 40, '') : 'must';
                }
                if (! $out['rows']) {
                    $errors[$path.'.rows'] = 'A ranking needs things to rank.';
                }
                break;
            case 'numbers':
                $out['rows'] = self::list($f['rows'] ?? [], 20, $path.'.rows', $errors);
                if (! $out['rows']) {
                    $errors[$path.'.rows'] = 'Add at least one number to ask for.';
                }
                break;
            case 'note':
                $out['private'] = true; // the visitor's own notes are never shown to a club
                break;
        }
        if (in_array($type, ['radio', 'scale'], true) && ! empty($f['note'])) {
            $out['note'] = true;
        }

        return $out;
    }

    /** Options, rows or columns: labels capped, values server-decided and unique. */
    private static function list($items, int $max, string $path, array &$errors): array
    {
        $out = [];
        $seen = [];
        foreach (array_slice(array_values(array_filter((array) $items, 'is_array')), 0, $max) as $o) {
            $label = self::str($o['label'] ?? '', 150);
            if ($label === '') {
                continue;
            }
            $v = is_string($o['value'] ?? null) && preg_match('/^[a-z0-9-]{1,40}$/', $o['value']) ? $o['value'] : (Str::slug(Str::limit($label, 36, ''), '-') ?: 'o');
            $base = $v;
            $i = 2;
            while (isset($seen[$v])) {
                $v = Str::limit($base, 36, '').'-'.$i++;
            }
            $seen[$v] = true;
            $out[] = ['value' => $v, 'label' => $label, 'label_ar' => self::str($o['label_ar'] ?? '', 150)];
        }

        return $out;
    }

    private static function str($v, int $max): string
    {
        return is_scalar($v) ? Str::limit(trim(strip_tags((string) $v)), $max, '') : '';
    }
}
