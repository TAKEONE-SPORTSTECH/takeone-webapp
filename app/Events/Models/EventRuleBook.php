<?php

namespace App\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An organiser's explicit choice of rule-book version for one event.
 * No row = the version in force when the event was created
 * (App\Sports\Package\SportPackages::editionFor()).
 */
class EventRuleBook extends Model
{
    protected $table = 'event_rulebooks';

    protected $fillable = ['event_id', 'edition', 'chosen_by'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }
}
