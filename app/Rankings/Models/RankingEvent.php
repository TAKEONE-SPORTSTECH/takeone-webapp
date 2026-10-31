<?php

namespace App\Rankings\Models;

use Illuminate\Database\Eloquent\Model;

/** One event's ranking coefficient. Written only by App\Rankings\Support\EventFactor. */
class RankingEvent extends Model
{
    protected $fillable = ['event_id', 'factor', 'set_by'];

    protected $casts = ['factor' => 'integer'];
}
