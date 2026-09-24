<?php

namespace App\Federations\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A file the organiser attached to an event, shown to clubs on the invitation. */
class FederationEventDocument extends Model
{
    use DeletesUploadedFiles;

    protected array $fileUploads = ['path' => 'local'];

    protected $fillable = ['federation_event_id', 'title', 'filename', 'path', 'mime', 'size', 'position'];

    protected static function booted(): void
    {
        static::creating(fn (FederationEventDocument $d) => $d->uuid ??= (string) Str::uuid());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FederationEvent::class, 'federation_event_id');
    }
}
