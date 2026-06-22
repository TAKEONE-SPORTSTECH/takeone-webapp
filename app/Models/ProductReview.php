<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    protected $fillable = ['club_product_id', 'order_id', 'user_id', 'rating'];

    protected $casts = ['rating' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ClubProduct::class, 'club_product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
