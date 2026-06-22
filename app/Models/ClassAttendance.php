<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One attendee marked present for a dated occurrence of a club class slot.
 * See migration create_class_attendances_table.
 */
class ClassAttendance extends Model
{
    protected $fillable = [
        'package_activity_id', 'slot_day', 'slot_start', 'date', 'user_id', 'marked_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
