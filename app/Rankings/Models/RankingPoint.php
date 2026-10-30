<?php

namespace App\Rankings\Models;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One athlete's points from one event in one category. Written only by Ledger. */
class RankingPoint extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'earned_on' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ClubEventRegistration::class, 'registration_id');
    }
}
