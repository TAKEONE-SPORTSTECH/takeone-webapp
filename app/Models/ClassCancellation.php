<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassCancellation extends Model
{
    protected $fillable = [
        'package_activity_id', 'slot_day', 'slot_start', 'date', 'reason', 'creditable', 'cancelled_by',
    ];

    protected $casts = ['date' => 'date', 'creditable' => 'boolean'];

    public function packageActivity(): BelongsTo
    {
        return $this->belongsTo(ClubPackageActivity::class, 'package_activity_id');
    }
}
