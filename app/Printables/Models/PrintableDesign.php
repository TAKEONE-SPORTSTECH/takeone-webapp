<?php

namespace App\Printables\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * One design of one printable kind for one owner — see App\Printables.
 *
 * `document` is the page set the editor draws:
 *   { pages: [ { id, background, elements: [ … ] } ] }
 * with every length in CSS pixels at 96 dpi (1 mm = 3.7795 px). It is written
 * only through App\Printables\Support\DocumentSanitizer, so what is stored has
 * already been checked against the element whitelist.
 *
 * Addressed by `uuid` everywhere outside the database.
 */
class PrintableDesign extends Model
{
    protected $fillable = [
        'kind', 'owner_type', 'owner_id', 'slot', 'title', 'format', 'orientation',
        'width_mm', 'height_mm', 'document', 'revision', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'document' => 'array',
        'width_mm' => 'float',
        'height_mm' => 'float',
        'revision' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $design) {
            $design->uuid ??= (string) Str::uuid();
        });
        // Pictures first, then the row (CLAUDE.md → Delete Files Before Records).
        static::deleting(function (self $design) {
            $design->assets()->get()->each->delete();
        });
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function assets(): HasMany
    {
        return $this->hasMany(PrintableAsset::class, 'design_id');
    }
}
