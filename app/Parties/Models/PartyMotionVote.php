<?php

namespace App\Parties\Models;

use Illuminate\Database\Eloquent\Model;

/** One owner's vote on a motion, with the share they held when they cast it. */
class PartyMotionVote extends Model
{
    protected $table = 'party_motion_votes';

    protected $fillable = ['motion_id', 'user_id', 'share_bp', 'approve'];

    protected $casts = ['motion_id' => 'integer', 'user_id' => 'integer', 'share_bp' => 'integer', 'approve' => 'boolean'];
}
