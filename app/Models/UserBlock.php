<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBlock extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];

    /** IDs of everyone blocked by, or who has blocked, the given user — plus the user's own id. */
    public static function idsBlockedEitherWayWith(int $userId): \Illuminate\Support\Collection
    {
        return static::where('blocker_id', $userId)->pluck('blocked_id')
            ->merge(static::where('blocked_id', $userId)->pluck('blocker_id'))
            ->push($userId)
            ->unique();
    }
}
