<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single "parent_of" edge in the family tree: parent_person → child_person.
 * The backbone of the tree. Every other vertical relationship (grandparent,
 * ancestor, descendant…) is derived by walking these edges.
 */
class PersonParentLink extends Model
{
    protected $fillable = [
        'parent_person_id',
        'child_person_id',
        'status',
        'created_by_user_id',
        'confirmed_by_user_id',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'parent_person_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'child_person_id');
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}
