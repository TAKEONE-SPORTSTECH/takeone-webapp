<?php

namespace App\Forms\Support;

use App\Forms\Models\Form;

/**
 * The pages a paged survey hands to the browser — and nothing else.
 *
 * Private pages and fields (the visitor's cover sheet, observations, visit
 * summary) are left out entirely unless the request came through the visit
 * link, so a club opening the public link never receives them in its page
 * source. Each field is reduced to the keys the renderer reads; anything an
 * editor adds to the schema later stays on the server until it is listed here.
 */
class SurveyPages
{
    private const FIELD_KEYS = [
        'key', 'type', 'label', 'label_ar', 'help', 'help_ar', 'required', 'rows', 'columns', 'options',
        'other', 'note', 'min', 'max', 'low', 'low_ar', 'high', 'high_ar', 'from', 'fromValue', 'private',
    ];

    public static function for(Form $form, bool $visit): array
    {
        $out = [];
        foreach (($form->schema['pages'] ?? []) as $page) {
            $private = ! empty($page['private']);
            if ($private && ! $visit) {
                continue;
            }
            $fields = [];
            foreach (($page['fields'] ?? []) as $field) {
                if (! empty($field['private']) && ! $visit) {
                    continue;
                }
                $fields[] = array_intersect_key($field, array_flip(self::FIELD_KEYS));
            }
            if ($fields === []) {
                continue;
            }
            $out[] = [
                'id' => (string) ($page['id'] ?? count($out)),
                'title' => $page['title'] ?? '',
                'title_ar' => $page['title_ar'] ?? '',
                'intro' => $page['intro'] ?? '',
                'intro_ar' => $page['intro_ar'] ?? '',
                'private' => $private,
                'fields' => $fields,
            ];
        }

        return $out;
    }
}
