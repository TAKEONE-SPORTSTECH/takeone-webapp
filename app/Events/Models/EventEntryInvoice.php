<?php

namespace App\Events\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A club's payment for its athletes, with its proof (PlatformClubEntry). Written
 * by the server only. Its proof file is deleted before the row (Delete Files
 * Before Records).
 */
class EventEntryInvoice extends Model
{
    use DeletesUploadedFiles;

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $guarded = ['*'];

    protected array $fileUploads = ['proof_path' => 'local'];

    protected $casts = [
        'registration_ids' => 'array',
        'amount' => 'float',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $i) => $i->uuid ??= (string) Str::uuid());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }
}
