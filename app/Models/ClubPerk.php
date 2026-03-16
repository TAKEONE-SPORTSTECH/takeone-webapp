<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubPerk extends Model
{
    use BelongsToTenant;

    protected $table = 'club_perks';

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'badge',
        'image_path',
        'icon',
        'bg_from',
        'bg_to',
        'perk_type',
        'perk_value',
        'status',
        'sort_order',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
