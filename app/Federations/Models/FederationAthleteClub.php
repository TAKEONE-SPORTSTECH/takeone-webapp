<?php

namespace App\Federations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Which club an athlete competes for in a federation, and when. `to_ym` null = now. */
class FederationAthleteClub extends Model
{
    protected $fillable = ['federation_athlete_id', 'federation_id', 'federation_club_id', 'club_application_id', 'from_ym', 'to_ym'];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(FederationAthlete::class, 'federation_athlete_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(FederationClub::class, 'federation_club_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(FederationClubApplication::class, 'club_application_id');
    }
}
