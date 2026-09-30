<?php

namespace App\Ranks\Models;

use App\Clubs\Models\ClubActivity;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step up (or a correction) in a member's rank in one activity — the history. */
class RankPromotion extends Model
{
    protected $fillable = ['activity_id', 'user_id', 'from_belt', 'from_grade', 'to_belt', 'to_grade', 'promoted_on', 'promoted_by', 'grading_id', 'note'];

    protected $casts = ['promoted_on' => 'date'];

    public function activity(): BelongsTo { return $this->belongsTo(ClubActivity::class, 'activity_id'); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function promoter(): BelongsTo { return $this->belongsTo(User::class, 'promoted_by'); }
}
