<?php

namespace App\Events\Models;

use Illuminate\Database\Eloquent\Model;

/** One desk-issued sign-in code (App\Events\Support\DeskSignInCode). Only its HMAC is kept. */
class EventSignInCode extends Model
{
    protected $fillable = ['event_id', 'user_id', 'code_hash', 'issued_by', 'expires_at', 'used_at', 'attempts'];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime', 'attempts' => 'integer'];

    public function scopeLive($q)
    {
        return $q->whereNull('used_at')->where('expires_at', '>', now())->where('attempts', '<', \App\Events\Support\DeskSignInCode::MAX_ATTEMPTS);
    }
}
