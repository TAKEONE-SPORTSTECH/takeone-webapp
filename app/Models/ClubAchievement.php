<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubAchievement extends Model
{
    protected $fillable = [
        'tenant_id', 'title', 'short_title', 'type_icon', 'description',
        'location', 'achievement_date', 'date_label',
        'medals_gold', 'medals_silver', 'medals_bronze',
        'bouts_count', 'wins_count', 'category', 'chips', 'athletes',
        'tag', 'tag_icon', 'image_path', 'images', 'bg_from', 'bg_to', 'status', 'sort_order',
    ];

    protected $casts = [
        'images'           => 'array',
        'chips'            => 'array',
        'athletes'         => 'array',
        'achievement_date' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
