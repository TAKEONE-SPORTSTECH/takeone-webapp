<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_id', 'body', 'edited_at', 'deleted_at',
        'attachment_path', 'attachment_name', 'attachment_mime',
        'attachment_size', 'attachment_kind', 'attachment_expires_at',
    ];

    protected $casts = [
        // Encrypted at rest (AES-256-GCM via APP_KEY). Transparent to the app:
        // reads decrypt, writes encrypt. A raw DB dump exposes only ciphertext.
        'body' => 'encrypted',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'attachment_expires_at' => 'datetime',
    ];

    /** A delete-for-everyone tombstone — body cleared, marker kept. */
    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /** Does this message still carry a (live, non-deleted) attachment? */
    public function hasAttachment(): bool
    {
        return $this->attachment_kind !== null && ! $this->isDeleted();
    }

    /** Attachment present but its bytes were pruned after expiry. */
    public function attachmentExpired(): bool
    {
        return $this->attachment_kind !== null && $this->attachment_path === null;
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
