<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_user_id',
        'club_name',
        'slug',
        'logo',
        'gps_lat',
        'gps_long',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'gps_lat' => 'decimal:7',
        'gps_long' => 'decimal:7',
    ];

    /**
     * Get the owner user that owns the tenant.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Get the members for the tenant.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    /**
     * Get the invoices for the tenant.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
