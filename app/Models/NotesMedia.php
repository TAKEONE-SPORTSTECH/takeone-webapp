<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotesMedia extends Model
{
    protected $fillable = [
        'tournament_event_id',
        'note_text',
        'media_link',
    ];

    public function tournamentEvent(): BelongsTo
    {
        return $this->belongsTo(TournamentEvent::class);
    }
}
