<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A stored file, and the only row that knows where its bytes are.
 *
 * `vault_id` NULL means local disk, which is the platform's default state and
 * not a fallback. Everything that owns media (a clip, a bout recording) points
 * here and never learns about storage.
 */
class MediaFile extends Model
{
    use HasUuids;

    protected $fillable = [
        'vault_id', 'owner_type', 'owner_id', 'kind', 'rel_path', 'hls_rel_path',
        'original_name', 'mime', 'bytes', 'duration_seconds', 'width', 'height',
        'status', 'error', 'checksum', 'meta', 'created_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'bytes' => 'integer',
        'duration_seconds' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public const KIND_CLIP = 'clip';

    public const KIND_RECORDING = 'recording';

    public const KIND_THUMB = 'thumb';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_STORED = 'stored';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_MISSING = 'missing';

    public const STATUS_FAILED = 'failed';

    /**
     * The people in this file. Several, which is why they are rows and not a
     * folder — see MediaFileSubject.
     */
    public function subjects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MediaFileSubject::class);
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(MediaVault::class, 'vault_id');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** Streamable — the ladder exists and the source was found. */
    public function isPlayable(): bool
    {
        return in_array($this->status, [self::STATUS_READY, self::STATUS_STORED], true);
    }

    /** Where these bytes live, in words an operator can act on. */
    public function getVaultLabelAttribute(): string
    {
        return $this->vault?->name ?? 'Local disk';
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->bytes;

        if ($bytes <= 0) {
            return '—';
        }

        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return round($bytes, 1).' PB';
    }
}
