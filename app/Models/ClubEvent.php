<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubEvent extends Model
{
    use HasFactory;

    protected $table = 'club_events';

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'date',
        'start_time',
        'end_time',
        'location',
        'level',
        'max_capacity',
        'spots_taken',
        'ribbon_label',
        'ribbon_type',
        'tags',
        'color',
        'cta_text',
        'status',
    ];

    protected $casts = [
        'date'         => 'date',
        'tags'         => 'array',
        'max_capacity' => 'integer',
        'spots_taken'  => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ClubEventRegistration::class, 'event_id');
    }
}
