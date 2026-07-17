<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassProgramOverride extends Model
{
    protected $fillable = [
        'package_activity_id', 'slot_day', 'slot_start', 'date',
        'intensity', 'focus', 'notes', 'workout', 'set_by',
    ];

    protected $casts = [
        'date' => 'date',
        'focus' => 'array',
        'workout' => 'array',
    ];

    public function packageActivity(): BelongsTo
    {
        return $this->belongsTo(ClubPackageActivity::class, 'package_activity_id');
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
