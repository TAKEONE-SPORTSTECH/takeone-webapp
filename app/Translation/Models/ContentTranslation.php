<?php

namespace App\Translation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One field, of one record, in one language.
 *
 * @property string $translatable_type morph alias, never a class name
 * @property int    $translatable_id
 * @property string $locale
 * @property string $field   'title', 'requirements.2'
 * @property string $value
 * @property string $source_hash sha256 of the text this was translated FROM
 * @property string $origin  'machine' | 'human'
 */
class ContentTranslation extends Model
{
    public const ORIGIN_MACHINE = 'machine';

    public const ORIGIN_HUMAN = 'human';

    protected $fillable = [
        'translatable_type', 'translatable_id', 'locale', 'field',
        'value', 'source_hash', 'origin', 'provider', 'model',
    ];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A human wrote or corrected this, so no machine may replace it.
     *
     * The single most important predicate in the module: it is what makes the
     * organiser's review real. Without it, an edit to the event's title would
     * re-run the agent and silently reinstate the wording a person had already
     * rejected.
     */
    public function isHuman(): bool
    {
        return $this->origin === self::ORIGIN_HUMAN;
    }

    /**
     * True when the source text has changed since this was written.
     *
     * A stale MACHINE row is not shown and is queued to be replaced. A stale
     * HUMAN row is still shown — a person's words do not become wrong because
     * the English moved, and throwing them away over a typo fix in the source
     * would be worse than showing a slightly dated sentence. It is flagged for
     * review instead.
     */
    public function isStale(string $currentSourceHash): bool
    {
        return $this->source_hash !== $currentSourceHash;
    }

    public function scopeFor($query, string $type, int $id, string $locale)
    {
        return $query->where('translatable_type', $type)
            ->where('translatable_id', $id)
            ->where('locale', $locale);
    }
}
