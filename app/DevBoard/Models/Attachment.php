<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\HasBoardUuid;
use App\DevBoard\Models\Concerns\InNotebookThroughCard;
use App\Members\Models\User;
use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something attached to a card: a FILE (kept on the private disk under a
 * server-made name, checked by its bytes, served only through the board's
 * authorising controller as a download) or a LINK (an http/https address and
 * a name). Its file goes before its row (DeletesUploadedFiles).
 */
class Attachment extends Model
{
    use DeletesUploadedFiles, HasBoardUuid, InNotebookThroughCard;

    public const KINDS = ['file', 'link'];

    protected $table = 'devboard_attachments';

    protected $fillable = ['uuid', 'card_id', 'kind', 'name', 'url', 'path', 'ext', 'mime', 'size', 'created_by'];

    protected $casts = ['size' => 'integer'];

    protected array $fileUploads = ['path' => 'local'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
