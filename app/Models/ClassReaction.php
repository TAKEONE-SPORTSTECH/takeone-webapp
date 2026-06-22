<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassReaction extends Model
{
    protected $fillable = ['package_activity_id', 'slot_day', 'slot_start', 'date', 'user_id', 'emoji'];
    protected $casts = ['date' => 'date'];
}
