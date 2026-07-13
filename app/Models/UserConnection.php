<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConnection extends Model
{
    protected $fillable = ['requester_id', 'addressee_id', 'status'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /** A connection row between two users, in either direction. */
    public function scopeBetweenUsers(Builder $query, int $a, int $b): Builder
    {
        return $query->where(function (Builder $q) use ($a, $b) {
            $q->where(['requester_id' => $a, 'addressee_id' => $b])
                ->orWhere(['requester_id' => $b, 'addressee_id' => $a]);
        });
    }
}
