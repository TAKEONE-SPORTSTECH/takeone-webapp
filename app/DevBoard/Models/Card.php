<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\HasBoardUuid;
use App\DevBoard\Models\Concerns\InNotebook;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One captured idea. Deleting puts it in the Bin (soft delete); its items'
 * files are only purged when the Bin is emptied (force delete), and each item
 * is force-deleted one by one so DeletesUploadedFiles runs for it — the
 * pictures placed in its notes too.
 */
class Card extends Model
{
    use HasBoardUuid, InNotebook, SoftDeletes;

    /** What a shared folder's fence limits this model by (Concerns\InNotebook). */
    public string $notebookFence = 'folder_id';

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /** A NOTE lives in the notebook; a CARD is on the action-plan board. A note can become a card, and back. */
    public const KINDS = ['note', 'card'];

    protected $table = 'devboard_cards';

    protected $fillable = [
        'uuid', 'kind', 'in_notebook', 'lane_id', 'folder_id', 'title', 'priority', 'assignee_id', 'due_on',
        'source_url', 'cover_uuid', 'position', 'created_by', 'version',
        'start_on', 'due_time', 'reminder_minutes', 'reminded_at', 'done_at', 'cover_color', 'cover_size', 'archived_at', 'is_template', 'pinned_at',
    ];

    protected $casts = [
        'position' => 'float',
        'due_on' => 'date',
        'start_on' => 'date',
        'reminder_minutes' => 'integer',
        'reminded_at' => 'datetime',
        'done_at' => 'datetime',
        'archived_at' => 'datetime',
        'pinned_at' => 'datetime',
        'is_template' => 'boolean',
        'in_notebook' => 'boolean',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::forceDeleting(function (self $card) {
            $card->items()->withTrashed()->get()->each->forceDelete();
            $card->assets()->get()->each->delete();
            $card->attachments()->get()->each->delete(); // each file before its row
        });
    }

    public function lane(): BelongsTo
    {
        return $this->belongsTo(Lane::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class)->orderBy('position');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function labels(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'devboard_card_label')->orderBy('position');
    }

    public function members(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'devboard_card_members')->withPivot('created_at')->orderBy('devboard_card_members.created_at');
    }

    public function watchers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'devboard_card_watchers');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->orderBy('created_at');
    }

    public function scopeNotes($q)
    {
        return $q->where('kind', 'note');
    }

    /** What the notebook lists: every note, and every card that was a note first (it stays one). */
    public function scopeInNotebook($q)
    {
        return $q->where(fn ($w) => $w->where('kind', 'note')->orWhere('in_notebook', true));
    }

    public function scopeCards($q)
    {
        return $q->where('kind', 'card');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at');
    }

    public function activity(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('created_at');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
