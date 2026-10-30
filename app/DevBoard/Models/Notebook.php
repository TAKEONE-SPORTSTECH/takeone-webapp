<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\HasBoardUuid;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's TakeNote: their notes, folders, board lists and labels.
 *
 * The MAIN notebook is the owner's, shared — as it always was — with the
 * people listed in devboard_members and the super-admins. Every other
 * notebook belongs to one invited friend and to nobody else. An invitation
 * is a notebook waiting for its owner: `email` is set and `owner_id` is not,
 * until a signed-in, verified account with that email opens TakeNote. An
 * invite LINK is the same wait without an email: whoever opens the link first
 * claims it (Controllers\TakeNoteJoinController), and TakeNote then opens for
 * them before their email is confirmed (`via_link`).
 */
class Notebook extends Model
{
    use HasBoardUuid;

    protected $table = 'devboard_notebooks';

    protected $fillable = ['uuid', 'is_main', 'owner_id', 'email', 'invited_by', 'joined_at', 'revoked_at', 'label', 'via_link'];

    /** the link's secret, as a SHA-256 — never readable back, never sent anywhere */
    protected $hidden = ['invite_hash'];

    protected $casts = [
        'is_main' => 'boolean',
        'via_link' => 'boolean',
        'joined_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeLive($q)
    {
        return $q->whereNull('revoked_at');
    }
}
