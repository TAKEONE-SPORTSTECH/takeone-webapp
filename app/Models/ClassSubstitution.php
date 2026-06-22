<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A substitute trainer covering one dated occurrence of a club class slot.
 * See migration create_class_substitutions_table for the data model.
 */
class ClassSubstitution extends Model
{
    protected $fillable = [
        'package_activity_id', 'slot_day', 'slot_start', 'date',
        'original_user_id', 'substitute_user_id', 'assigned_by', 'note',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function packageActivity(): BelongsTo
    {
        return $this->belongsTo(ClubPackageActivity::class, 'package_activity_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
