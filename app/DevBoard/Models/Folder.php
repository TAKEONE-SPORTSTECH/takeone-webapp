<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\HasBoardUuid;
use App\DevBoard\Models\Concerns\InNotebook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folder in the notebook. Folders nest (parent_id), the way Trilium's tree
 * does; a note is in one folder or in none. Shared by everyone on the board,
 * like the notes themselves.
 */
class Folder extends Model
{
    use HasBoardUuid, InNotebook;

    /** What a shared folder's fence limits this model by (Concerns\InNotebook). */
    public string $notebookFence = 'id';

    /** How deep folders may nest (a folder in a folder in a folder in a folder). */
    public const MAX_DEPTH = 4;

    protected $table = 'devboard_folders';

    protected $fillable = ['uuid', 'parent_id', 'name', 'color', 'position', 'created_by'];

    protected $casts = ['position' => 'float'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Card::class)->where('kind', 'note');
    }
}
