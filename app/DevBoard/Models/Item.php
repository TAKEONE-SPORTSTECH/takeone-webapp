<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\HasBoardUuid;
use App\DevBoard\Models\Concerns\InNotebookThroughCard;
use App\Members\Models\User;
use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One thing inside a card: a note, a picture (with its vector markup layer), a
 * sketch, a checklist or a link. Pictures and previews live on the PRIVATE disk
 * and are served only through the board's authorising controller.
 */
class Item extends Model
{
    use DeletesUploadedFiles, HasBoardUuid, InNotebookThroughCard, SoftDeletes;

    public const TYPES = ['note', 'image', 'sketch', 'checklist', 'link'];

    protected $table = 'devboard_items';

    protected $fillable = [
        'uuid', 'card_id', 'type', 'position', 'body', 'path', 'preview_path',
        'strokes', 'created_by', 'updated_by', 'version',
    ];

    protected $casts = [
        'position' => 'float',
        'strokes' => 'array',
        'version' => 'integer',
    ];

    protected array $fileUploads = [
        'path' => 'local',
        'preview_path' => 'local',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class)->orderBy('position');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
