<?php

namespace App\Forms;

use App\Forms\Models\Form;

/**
 * Turns a Form's JSON schema into Laravel validation rules and evaluates
 * conditional visibility, so the same schema drives both render and submit.
 */
class FormEngine
{
    /** Is a field visible given the current answers? (conditional logic) */
    public static function isVisible(array $field, array $data): bool
    {
        $cond = $field['visibleIf'] ?? null;
        if (! $cond || empty($cond['field'])) {
            return true;
        }
        $val = $data[$cond['field']] ?? null;
        $target = $cond['value'] ?? null;

        return match ($cond['op'] ?? 'equals') {
            'equals' => (string) $val === (string) $target,
            'not_equals' => (string) $val !== (string) $target,
            'in' => is_array($val) ? in_array($target, $val, true) : in_array($val, (array) $target, true),
            'filled' => $val !== null && $val !== '' && $val !== [],
            'not_filled' => $val === null || $val === '' || $val === [],
            default => true,
        };
    }

    /**
     * Rules for the PAGED survey layout, and the exact list of keys it may
     * store. Only what is declared is kept: an extra key in the request is
     * dropped rather than saved, and a private field (the visitor's cover,
     * observations, summary) is not even accepted unless the request carries
     * the visit link.
     *
     * Companion keys ride beside a field: `{key}__other` (the "Other" box on
     * a choice) and `{key}__note` (the note under a one-choice or a scale).
     *
     * @return array{0: array, 1: array, 2: list<string>}
     */
    public static function surveyRules(Form $form, array $data, bool $visit): array
    {
        $rules = [];
        $labels = [];
        $keys = [];

        foreach (($form->schema['pages'] ?? []) as $page) {
            $pagePrivate = ! empty($page['private']);
            foreach (($page['fields'] ?? []) as $field) {
                $key = (string) ($field['key'] ?? '');
                $type = $field['type'] ?? 'text';
                if (! preg_match('/^[a-z0-9_]{1,40}$/', $key) || in_array($type, ['heading', 'paragraph'], true)) {
                    continue;
                }
                if (($pagePrivate || ! empty($field['private'])) && ! $visit) {
                    continue;
                }

                $d = 'fields.'.$key;
                $labels[$d] = $field['label'] ?? $key;
                $keys[] = $key;
                $req = ! empty($field['required']) && self::isVisible($field, $data) ? 'required' : 'nullable';
                $values = fn (string $list) => collect($field[$list] ?? [])->pluck('value')->map(fn ($v) => (string) $v)->all();

                switch ($type) {
                    case 'checkboxes':
                        $rules[$d] = [$req, 'array', 'max:50'];
                        $rules[$d.'.*'] = ['string', 'in:'.implode(',', $values('options'))];
                        if (! empty($field['other'])) {
                            $rules['fields.'.$key.'__other'] = ['nullable', 'string', 'max:300'];
                            $keys[] = $key.'__other';
                        }
                        break;
                    case 'radio':
                    case 'select':
                        $rules[$d] = [$req, 'string', 'in:'.implode(',', $values('options'))];
                        break;
                    case 'scale':
                        $rules[$d] = [$req, 'integer', 'between:'.(int) ($field['min'] ?? 1).','.(int) ($field['max'] ?? 5)];
                        break;
                    case 'numbers':
                        $rules[$d] = ['nullable', 'array:'.implode(',', $values('rows'))];
                        $rules[$d.'.*'] = ['nullable', 'integer', 'between:0,1000000'];
                        break;
                    case 'grid':
                        $rows = $values('rows');
                        $rules[$d] = [$req, 'array:'.implode(',', $rows)];
                        foreach ($rows as $row) {
                            $rules[$d.'.'.$row] = [$req, 'string', 'in:'.implode(',', $values('columns'))];
                        }
                        break;
                    case 'rank':
                        $rules[$d] = [$req, 'array', 'max:'.(int) ($field['max'] ?? 5)];
                        $rules[$d.'.*'] = ['distinct', 'string', 'in:'.implode(',', $values('rows'))];
                        break;
                    case 'checkbox':
                    case 'terms':
                        $rules[$d] = [$req === 'required' ? 'accepted' : 'nullable', 'boolean'];
                        break;
                    case 'date':
                        $rules[$d] = [$req, 'date'];
                        break;
                    case 'number':
                        $rules[$d] = [$req, 'numeric', 'between:-1000000,1000000'];
                        break;
                    default: // text, textarea, phone, email, note
                        $rules[$d] = [$req, 'string', 'max:'.(in_array($type, ['textarea', 'note'], true) ? 5000 : 300)];
                        if ($type === 'email') {
                            $rules[$d][] = 'email';
                        }
                }

                if (! empty($field['note'])) {
                    $rules['fields.'.$key.'__note'] = ['nullable', 'string', 'max:1000'];
                    $keys[] = $key.'__note';
                }
            }
        }

        return [$rules, $labels, $keys];
    }

    /** Build Laravel validation rules + attribute labels for a submission. */
    public static function rules(Form $form, array $data): array
    {
        $rules = [];
        $labels = [];

        foreach ($form->fields() as $field) {
            $key = $field['key'] ?? null;
            if (! $key) {
                continue;
            }
            $dataKey = 'fields.'.$key;
            $labels[$dataKey] = $field['label'] ?? $key;

            // A field hidden by conditional logic is neither required nor validated.
            if (! self::isVisible($field, $data)) {
                $rules[$dataKey] = ['nullable'];

                continue;
            }

            $type = $field['type'] ?? 'text';
            $required = ! empty($field['required']);
            $v = $field['validation'] ?? [];
            $opts = collect($field['options'] ?? [])->pluck('value')->map(fn ($x) => (string) $x)->all();

            $r = [];
            switch ($type) {
                case 'terms':
                case 'checkbox':
                    $r[] = $required ? 'accepted' : 'nullable';
                    break;
                case 'checkboxes':
                    $r[] = $required ? 'required' : 'nullable';
                    $r[] = 'array';
                    $rules[$dataKey.'.*'] = $opts ? ['in:'.implode(',', $opts)] : ['string'];
                    break;
                case 'select':
                case 'radio':
                    $r[] = $required ? 'required' : 'nullable';
                    if ($opts) {
                        $r[] = 'in:'.implode(',', $opts);
                    }
                    break;
                case 'number':
                    $r[] = $required ? 'required' : 'nullable';
                    $r[] = 'numeric';
                    if (isset($v['min']) && $v['min'] !== '') {
                        $r[] = 'min:'.$v['min'];
                    }
                    if (isset($v['max']) && $v['max'] !== '') {
                        $r[] = 'max:'.$v['max'];
                    }
                    break;
                case 'email':
                    $r[] = $required ? 'required' : 'nullable';
                    $r[] = 'email';
                    break;
                case 'date':
                    $r[] = $required ? 'required' : 'nullable';
                    $r[] = 'date';
                    break;
                case 'file':
                    $r[] = $required ? 'required' : 'nullable';
                    $r[] = 'file';
                    $r[] = 'max:10240';
                    $r[] = 'mimes:jpg,jpeg,png,webp,pdf';
                    break;
                case 'phone':
                case 'text':
                case 'textarea':
                default:
                    $r[] = $required ? 'required' : 'nullable';
                    $r[] = 'string';
                    if (isset($v['minLength']) && $v['minLength'] !== '') {
                        $r[] = 'min:'.$v['minLength'];
                    }
                    $r[] = 'max:'.($v['maxLength'] ?? ($type === 'textarea' ? 5000 : 500));
                    if (! empty($v['pattern'])) {
                        $r[] = 'regex:/'.str_replace('/', '\/', $v['pattern']).'/';
                    }
                    break;
            }

            $rules[$dataKey] = $r;
        }

        return [$rules, $labels];
    }
}
