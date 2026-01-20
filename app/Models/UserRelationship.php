<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRelationship extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'guardian_user_id',
        'dependent_user_id',
        'relationship_type',
        'is_billing_contact',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_billing_contact' => 'boolean',
    ];

    /**
     * Get the guardian user that owns the relationship.
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    /**
     * Get the dependent user that belongs to the relationship.
     */
    public function dependent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dependent_user_id');
    }
}
