<?php

namespace App\DevBoard\Models\Concerns;

use App\DevBoard\Support\Notebooks;
use Illuminate\Database\Eloquent\Builder;

/**
 * A row that belongs to one notebook (cards, folders, lanes, labels): every
 * query sees only the current notebook's rows — route bindings included, so a
 * uuid from another notebook is a 404 — and a new row is born in it.
 * Support\Notebooks decides which notebook that is.
 */
trait InNotebook
{
    public static function bootInNotebook(): void
    {
        static::addGlobalScope('notebook', function (Builder $q) {
            $id = Notebooks::scopeId();
            if ($id !== null) {
                $model = $q->getModel();
                $q->where($model->getTable().'.notebook_id', $id);
                // a shared folder: only that folder's tree (cards by their folder, folders by themselves)
                if (($col = $model->notebookFence ?? null) && ($fence = Notebooks::fence()) !== null) {
                    $q->whereIn($model->getTable().'.'.$col, $fence);
                }
            }
        });

        static::creating(function ($model) {
            // born in the notebook in use; off the web (a command, a seeder) in the main one, as on the old single board
            if (! $model->notebook_id && ($id = Notebooks::currentId() ?? Notebooks::main()?->id)) {
                $model->notebook_id = $id;
            }
        });

        // In a shared folder nothing leaves the fence: a note or card made, or filed, outside it lands in the
        // shared folder itself, and so does a folder put at the top level.
        static::saving(function ($model) {
            $col = $model->notebookFence ?? null;
            $fence = $col ? Notebooks::fence() : null;
            $share = $fence !== null ? Notebooks::share() : null;
            // (saving runs before creating, so a new row has no notebook yet: it is going into the current one)
            if (! $share || (int) ($model->notebook_id ?: Notebooks::currentId()) !== (int) $share->notebook_id) {
                return;
            }
            if ($col === 'folder_id' && ! in_array((int) $model->folder_id, $fence, true)) {
                $model->folder_id = (int) $share->folder_id;
            }
            if ($col === 'id' && (int) $model->id !== (int) $share->folder_id && ! in_array((int) $model->parent_id, $fence, true)) {
                $model->parent_id = (int) $share->folder_id;
            }
        });
    }
}
