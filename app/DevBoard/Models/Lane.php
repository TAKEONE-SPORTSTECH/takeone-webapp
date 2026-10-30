<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\HasBoardUuid;
use App\DevBoard\Models\Concerns\InNotebook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One stage of the pipeline: Inbox, Planned, Doing… */
class Lane extends Model
{
    use HasBoardUuid, InNotebook;

    protected $table = 'devboard_lanes';

    protected $fillable = ['uuid', 'name', 'color', 'position'];

    protected $casts = ['position' => 'float'];

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->orderBy('position');
    }
}
