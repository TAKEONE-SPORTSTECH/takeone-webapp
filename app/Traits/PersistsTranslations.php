<?php

namespace App\Traits;

/**
 * Controller-side helper to persist translation input onto a model that uses
 * the HasTranslations trait.
 *
 * Expects request input named `translations[<field>][<locale>]`, i.e.
 *   translations => ['name' => ['ar' => '...'], 'description' => ['ar' => '...']]
 * which maps 1:1 onto HasTranslations::setTranslations().
 */
trait PersistsTranslations
{
    protected function applyTranslations($model, $request): void
    {
        $input = $request->input('translations', []);

        if (! is_array($input)) {
            return;
        }

        $model->setTranslations($input)->save();
    }
}
