<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubTimelinePost extends Model
{
    use BelongsToTenant;

    protected $table = 'club_timeline_posts';

    protected $fillable = [
        'tenant_id',
        'body',
        'category',
        'image_path',
        'posted_at',
        'status',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ClubTimelinePostLike::class, 'post_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ClubTimelinePostComment::class, 'post_id')->orderBy('created_at');
    }
}
