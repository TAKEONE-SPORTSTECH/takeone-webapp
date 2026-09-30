<?php

namespace App\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * An organiser's own category system or belt ladder (2026-09-28), owned by
 * its HOST — a club or a federation — and reusable across that host's events.
 * Public key: uuid. Read through App\Events\Support\CategorySystems.
 */
class CategorySystemRecord extends Model
{
    protected $table = 'category_systems';

    // Every field is set by CategorySystems, which validates the shape first.
    protected $guarded = ['*'];

    protected $casts = ['data' => 'array'];

    protected static function booted(): void
    {
        static::creating(fn (self $r) => $r->uuid ??= (string) Str::uuid());
    }

    public function key(): string
    {
        return 'custom:'.$this->uuid;
    }
}
