<?php

namespace App\Federations\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A file that backs up a federation's record. Private; public key: uuid. */
class FederationDocument extends Model
{
    use DeletesUploadedFiles;

    public const KINDS = ['registration_certificate', 'statutes', 'international_letter', 'olympic_letter'];

    protected array $fileUploads = ['path' => 'local'];

    protected $fillable = ['federation_id', 'kind', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    protected static function booted(): void
    {
        static::creating(fn (FederationDocument $d) => $d->uuid ??= (string) Str::uuid());
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }
}
