<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A node in the family tree — a human being, alive or dead, whether or not
 * they have an app account. Accounts (User) attach optionally via user_id.
 *
 * Relationships are intentionally thin here (just the raw edges); all kinship
 * reasoning lives in App\Services\KinshipService so the graph logic stays in
 * one place.
 */
class Person extends Model
{
    use SoftDeletes;

    /** Eloquent would pluralise "Person" to "people"; the table is "persons". */
    protected $table = 'persons';

    protected $fillable = [
        'user_id',
        'full_name',
        'gender',
        'birth_date',
        'death_date',
        'is_deceased',
        'photo',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'death_date' => 'date',
        'is_deceased' => 'boolean',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Edges where this person is the parent. */
    public function childLinks(): HasMany
    {
        return $this->hasMany(PersonParentLink::class, 'parent_person_id');
    }

    /** Edges where this person is the child. */
    public function parentLinks(): HasMany
    {
        return $this->hasMany(PersonParentLink::class, 'child_person_id');
    }

    /** Union edges where this person sits on the low side. */
    public function unionsAsLow(): HasMany
    {
        return $this->hasMany(PersonUnion::class, 'person_low_id');
    }

    /** Union edges where this person sits on the high side. */
    public function unionsAsHigh(): HasMany
    {
        return $this->hasMany(PersonUnion::class, 'person_high_id');
    }

    // ---------------------------------------------------------------------
    // Convenience accessors — CONFIRMED first-degree relatives only.
    // For anything deeper use KinshipService.
    // ---------------------------------------------------------------------

    /** @return Collection<int,Person> confirmed parents */
    public function parents(): Collection
    {
        $ids = $this->parentLinks()->where('status', 'confirmed')->pluck('parent_person_id');

        return static::whereIn('id', $ids)->get();
    }

    /** @return Collection<int,Person> confirmed children */
    public function children(): Collection
    {
        $ids = $this->childLinks()->where('status', 'confirmed')->pluck('child_person_id');

        return static::whereIn('id', $ids)->get();
    }

    /** @return Collection<int,PersonUnion> confirmed unions touching this person */
    public function unions(): Collection
    {
        return PersonUnion::query()
            ->where('status', 'confirmed')
            ->where(fn ($q) => $q->where('person_low_id', $this->id)
                ->orWhere('person_high_id', $this->id))
            ->get();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    public function isAccountLinked(): bool
    {
        return $this->user_id !== null;
    }
}
