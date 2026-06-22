<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassMakeupCredit extends Model
{
    protected $fillable = [
        'package_activity_id', 'user_id', 'subscription_id',
        'source_date', 'credit_days', 'status', 'used_at', 'created_by',
    ];

    protected $casts = ['source_date' => 'date', 'used_at' => 'datetime'];

    public function packageActivity(): BelongsTo
    {
        return $this->belongsTo(ClubPackageActivity::class, 'package_activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
