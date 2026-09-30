<?php

namespace App\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One event's certificate choices. Absent row = the defaults below. */
class EventCertificateSetting extends Model
{
    public const TRIGGERS = ['division', 'event', 'manual'];

    protected $fillable = ['event_id', 'participation', 'podium', 'trigger'];

    protected $attributes = ['participation' => true, 'podium' => true, 'trigger' => 'division'];

    protected $casts = ['participation' => 'boolean', 'podium' => 'boolean'];

    public static function for(ClubEvent $event): self
    {
        return static::firstOrNew(['event_id' => $event->id]);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }
}
