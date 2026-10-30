<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\HasBoardUuid;
use App\DevBoard\Models\Concerns\InNotebookThroughCard;
use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something placed in a note: a picture, a voice note or a short video. Owned
 * by the card, re-encoded on the way in (Services\Pictures, Services\Clips),
 * kept on the private disk and served only through the board's authorising
 * controller.
 */
class Asset extends Model
{
    use DeletesUploadedFiles, HasBoardUuid, InNotebookThroughCard;

    protected $table = 'devboard_assets';

    public const KINDS = ['image', 'audio', 'video'];

    protected $fillable = ['uuid', 'card_id', 'kind', 'status', 'mime', 'path', 'preview_path', 'source_path',
        'width', 'height', 'bytes', 'duration_ms', 'meta', 'created_by'];

    protected $casts = ['width' => 'integer', 'height' => 'integer', 'bytes' => 'integer', 'duration_ms' => 'integer', 'meta' => 'array'];

    protected $attributes = ['kind' => 'image', 'status' => 'ready'];

    protected array $fileUploads = [
        'path' => 'local',
        'preview_path' => 'local',
        'source_path' => 'local',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
