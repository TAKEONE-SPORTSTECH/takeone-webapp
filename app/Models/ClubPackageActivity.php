<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubPackageActivity extends Model
{
    protected $fillable = [
        'package_id',
        'activity_id',
        'instructor_id',
    ];

    /**
     * Get the package that owns this activity assignment.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ClubPackage::class, 'package_id');
    }

    /**
     * Get the activity.
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'activity_id');
    }

    /**
     * Get the instructor assigned to this activity.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(ClubInstructor::class, 'instructor_id');
    }
}
