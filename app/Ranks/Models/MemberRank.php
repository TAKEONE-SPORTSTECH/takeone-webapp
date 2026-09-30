<?php

namespace App\Ranks\Models;

use App\Clubs\Models\ClubActivity;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A member's current rank in one activity. */
class MemberRank extends Model
{
    protected $fillable = ['activity_id', 'user_id', 'belt', 'grade', 'since', 'awarded_by'];

    protected $casts = ['since' => 'date'];

    public function activity(): BelongsTo { return $this->belongsTo(ClubActivity::class, 'activity_id'); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
