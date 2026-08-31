<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A category in a club's Shop (e.g. Gear, Nutrition). Per club.
 */
class ClubProductCategory extends Model
{
    use BelongsToTenant;

    protected $table = 'club_product_categories';

    protected $fillable = ['tenant_id', 'key', 'label', 'icon', 'sort'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
