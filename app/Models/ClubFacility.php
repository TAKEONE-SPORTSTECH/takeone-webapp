<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClubFacility extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('club')
            ->logOnly(['name', 'address', 'is_available', 'maps_url'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'club_facilities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'photo',
        'images',
        'address',
        'gps_lat',
        'gps_long',
        'maps_url',
        'is_available',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'gps_lat' => 'decimal:7',
        'gps_long' => 'decimal:7',
        'images' => 'array',
        'is_available' => 'boolean',
    ];

    /**
     * Get the club that owns the facility.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the activities for the facility.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ClubActivity::class, 'facility_id');
    }
}
