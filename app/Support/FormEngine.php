<?php

namespace App\Support;

use App\Models\Form;

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
