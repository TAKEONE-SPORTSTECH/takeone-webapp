<?php

namespace App\Federations\Models;

use App\Members\Models\Conversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Which club affiliation a federation Messenger thread belongs to. */
class FederationConversation extends Model
{
    protected $fillable = ['federation_club_id', 'conversation_id'];

    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(FederationClub::class, 'federation_club_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
