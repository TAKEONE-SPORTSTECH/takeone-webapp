<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubAchievement extends Model
{
    protected $fillable = [
        'tenant_id', 'title', 'description', 'tag', 'tag_icon',
        'image_path', 'images', 'bg_from', 'bg_to', 'status', 'sort_order',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
