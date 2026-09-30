<?php

namespace App\Ranks\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One member being graded, and what they are being graded for. */
class GradingCandidate extends Model
{
    public const RESULTS = ['pending', 'passed', 'failed', 'absent'];

    protected $fillable = ['grading_id', 'user_id', 'target_belt', 'target_grade', 'result', 'note'];

    public function grading(): BelongsTo { return $this->belongsTo(Grading::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
