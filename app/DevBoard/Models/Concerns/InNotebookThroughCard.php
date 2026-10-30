<?php

namespace App\DevBoard\Models\Concerns;

use App\DevBoard\Support\Notebooks;
use Illuminate\Database\Eloquent\Builder;

/**
 * A row that lives under a card (its text, pictures, clips, files, comments,
 * activity): it belongs to its card's notebook, and every query — a picture
 * fetched by its own uuid included — sees only the current notebook's.
 * A model whose rows hang under an ITEM (a checklist step) sets
 * $notebookThrough = 'item'.
 */
trait InNotebookThroughCard
{
    public static function bootInNotebookThroughCard(): void
    {
        static::addGlobalScope('notebook', function (Builder $q) {
            $id = Notebooks::scopeId();
            if ($id === null) {
                return;
            }
            $model = $q->getModel();
            $fence = Notebooks::fence(); // a shared folder: only the cards inside it
            $cards = fn ($s) => $s->select('id')->from('devboard_cards')->where('notebook_id', $id)
                ->when($fence !== null, fn ($s) => $s->whereIn('folder_id', $fence));
            if (($model->notebookThrough ?? 'card') === 'item') {
                $q->whereIn($model->getTable().'.item_id', fn ($s) => $s->select('id')->from('devboard_items')->whereIn('card_id', $cards));
            } else {
                $q->whereIn($model->getTable().'.card_id', $cards);
            }
        });
    }
}
