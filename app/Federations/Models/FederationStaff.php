<?php

namespace App\Federations\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's access to one federation (and everything below it). `sports`
 * set = sees only those sports, and no settings. The federation's owner needs
 * no row: they are always its manager.
 */
class FederationStaff extends Model
{
    protected $table = 'federation_staff';

    protected $fillable = ['federation_id', 'user_id', 'role', 'sports', 'invited_by'];

    protected $casts = [
        'sports' => 'array',
    ];

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
