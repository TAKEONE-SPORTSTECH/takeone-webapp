<?php

namespace App\Federations\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** A competition a federation runs. Public key: uuid. */
class FederationEvent extends Model
{
    use DeletesUploadedFiles, SoftDeletes;

    public const STATUSES = ['Planning', 'Registration open', 'Live', 'Completed'];

    protected array $fileUploads = ['logo' => 'public', 'art_portrait' => 'public', 'art_landscape' => 'public'];

    protected $fillable = [
        'federation_id', 'created_by', 'name', 'sport', 'status', 'capacity', 'starts_on', 'ends_on',
        'details', 'logo', 'logo_shape', 'art_portrait', 'art_landscape',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn (FederationEvent $e) => $e->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FederationEventDocument::class)->orderBy('position');
    }
}
