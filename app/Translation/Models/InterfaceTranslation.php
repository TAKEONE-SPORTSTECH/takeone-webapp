<?php

namespace App\Translation\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One interface string, in one language.
 *
 * The product's own words — buttons, headings, empty states — as rows rather
 * than as PHP files. See the migration for why that move was made; the short
 * version is that a deploy was going to delete eleven generated languages, and
 * that a UI cannot be allowed to write executable PHP.
 *
 * `origin` carries the same invariant as the content translations: `human`
 * outranks `machine` and is never overwritten by a run.
 */
class InterfaceTranslation extends Model
{
    public const MACHINE = 'machine';

    public const HUMAN = 'human';

    protected $table = 'interface_translations';

    protected $fillable = [
        'locale', 'namespace', 'group', 'key', 'value', 'origin', 'model', 'source_hash',
    ];

    /** The id a lang file is known by — "events", "scoreboard::bjj_messages". */
    public function fileId(): string
    {
        return $this->namespace ? $this->namespace.'::'.$this->group : $this->group;
    }

    /**
     * Split a file id back into its namespace and group.
     *
     * @return array{0: ?string, 1: string}
     */
    public static function splitFileId(string $id): array
    {
        return str_contains($id, '::')
            ? [strtok($id, ':'), substr($id, strpos($id, '::') + 2)]
            : [null, $id];
    }
}
