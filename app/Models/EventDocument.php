<?php

namespace App\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A file attached to an event so people can download it.
 *
 * Stored on the private disk and reached only through EventDocumentController,
 * which re-runs EventAccess::visible() on every download — the path is never
 * exposed and the file is never web-reachable.
 */
class EventDocument extends Model
{
    use DeletesUploadedFiles, HasFactory;

    /**
     * The file is purged before the row, so a deleted document can never leave
     * an orphan on disk (CLAUDE.md → "Delete Files Before Records").
     */
    protected array $fileUploads = [
        'path' => 'local',
    ];

    protected $fillable = [
        'event_id',
        'title',
        'path',
        'mime',
        'extension',
        'size',
        'uploaded_by',
        'sort_order',
        'uuid',
    ];

    protected $casts = [
        'size' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $doc) {
            if (empty($doc->uuid)) {
                $doc->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** A Bootstrap-icon name for the file kind — used by the download list. */
    public function icon(): string
    {
        return match (true) {
            $this->mime === 'application/pdf' => 'bi-file-earmark-pdf',
            str_starts_with($this->mime, 'image/') => 'bi-file-earmark-image',
            str_contains($this->mime, 'word') => 'bi-file-earmark-word',
            str_contains($this->mime, 'sheet') || str_contains($this->mime, 'excel') => 'bi-file-earmark-spreadsheet',
            default => 'bi-file-earmark',
        };
    }

    /** Human-readable size, e.g. "1.4 MB". */
    public function readableSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }

    /**
     * The filename the browser should save it as: the human title, stripped of
     * anything that could steer a path or fake an extension, plus the extension
     * the server assigned when the bytes were verified.
     */
    public function downloadName(): string
    {
        $base = preg_replace('/[^\p{L}\p{N} _-]+/u', '', $this->title) ?: 'document';

        return trim(mb_substr($base, 0, 80)).'.'.$this->extension;
    }
}
