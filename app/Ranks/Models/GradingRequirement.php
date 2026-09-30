<?php

namespace App\Ranks\Models;

use Illuminate\Database\Eloquent\Model;

/** A club's requirement for one belt in one of its ranked activities. */
class GradingRequirement extends Model
{
    protected $fillable = ['activity_id', 'belt', 'min_classes', 'min_months', 'min_age'];
}
