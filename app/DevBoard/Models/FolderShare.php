<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\HasBoardUuid;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One folder shared with one person (Support\Notebooks decides what it opens).
 * Deliberately NOT notebook-scoped: it is read across notebooks — the owner
 * lists who has their folder, the collaborator lists what is shared with them —
 * and every query names the notebook or the person it is for.
 */
class FolderShare extends Model
{
    use HasBoardUuid;

    protected $table = 'devboard_folder_shares';

    protected $fillable = ['uuid', 'folder_id', 'notebook_id', 'user_id', 'email', 'invited_by', 'revoked_at'];

    protected $casts = ['revoked_at' => 'datetime'];

    public function scopeLive($q)
    {
        return $q->whereNull('revoked_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
