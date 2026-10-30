<?php

namespace App\DevBoard\Models\Concerns;

use Illuminate\Support\Str;

/** Every board record is addressed by a uuid, never its numeric id. */
trait HasBoardUuid
{
    public static function bootHasBoardUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
