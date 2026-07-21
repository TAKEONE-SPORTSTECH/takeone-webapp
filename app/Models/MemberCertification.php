<?php

namespace App\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCertification extends Model
{
    use DeletesUploadedFiles;

    protected $fillable = [
        'user_id',
        'title',
        'issuer',
        'issue_date',
        'expiry_date',
        'credential_id',
        'credential_url',
        'image_path',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
    ];

    /** Purge the certificate photo before the row is deleted. */
    protected array $fileUploads = [
        'image_path',   // bare entry → 'public' disk
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** True when the certificate carries an expiry that is already past. */
    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }
}
