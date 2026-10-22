<?php

namespace App\Printables\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A picture uploaded into one design. The file is private (`local` disk), named
 * by the server, and only ever served through the designer's own route, which
 * re-checks that the viewer may design its owner. A document refers to it by
 * `uuid`, never by path.
 */
class PrintableAsset extends Model
{
    use DeletesUploadedFiles;

    protected $fillable = ['design_id', 'disk', 'path', 'mime', 'width', 'height', 'bytes', 'created_by'];

    protected array $fileUploads = ['path' => 'local'];

    protected static function booted(): void
    {
        static::creating(function (self $asset) {
            $asset->uuid ??= (string) Str::uuid();
        });
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(PrintableDesign::class, 'design_id');
    }
}
