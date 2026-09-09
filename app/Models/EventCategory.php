<?php

namespace App\Models;

use App\Traits\TranslatesAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCategory extends Model
{
    use TranslatesAttributes;

    /**
     * How this division is decided.
     *
     * `knockout` is the only one the engine can actually run today —
     * `DrawEngine` cuts a single-elimination ladder; `GroupEngine` runs an
     * all-play-all group that feeds a knockout final (added 2026-09-07). A
     * format is offered to an organiser only once something can run it: one
     * somebody can choose but the mats cannot honour is worse than one that is
     * missing.
     */
    public const FORMAT_KNOCKOUT = 'knockout';
    public const FORMAT_ROUND_ROBIN = 'round_robin';

    /** Formats an organiser may actually pick right now. */
    public const RUNNABLE_FORMATS = [self::FORMAT_KNOCKOUT, self::FORMAT_ROUND_ROBIN];

    /** Everything the column recognises, runnable or not. */
    public const FORMATS = [self::FORMAT_KNOCKOUT, self::FORMAT_ROUND_ROBIN];

    protected $fillable = [
        'event_id', 'name', 'weight_class', 'format', 'capacity', 'status', 'draw_state', 'draw_count', 'schedule', 'note', 'podium', 'sort_order',
        // A title in the list rather than a division — see isHeading().
        'is_heading',
        // What the division is FOR. Every one is nullable and null means ANY —
        // see App\Events\Support\DivisionRange, and the migration that added
        // them for why they are a guide rather than a gate.
        'gender', 'min_age', 'max_age', 'min_weight', 'max_weight',
    ];

    protected $casts = [
        'is_heading' => 'boolean',
        'podium' => 'array',
        'schedule' => 'array',
        'draw_count' => 'integer',
        'min_age' => 'integer',
        'max_age' => 'integer',
        'min_weight' => 'float',
        'max_weight' => 'float',
    ];

    /**
     * Is this row a TITLE rather than a division?
     *
     * A heading labels the divisions that follow it — "GI", then the five Gi
     * weight classes, then "NO-GI". It holds no entrants, is never drawn, and
     * takes no weight or age range. It sits in the same ordered list because
     * that is the only place it means anything.
     *
     * Everywhere that would otherwise treat it as a division refuses it by
     * name: entering somebody into it, putting people in it, cutting a draw
     * from it. Relying on it merely having no entrants would make it a
     * division that happens to be empty, which is a different thing and would
     * quietly acquire people the first time somebody dragged one in.
     */
    public function isHeading(): bool
    {
        return (bool) $this->is_heading;
    }

    /** Only the rows that are real divisions — the default for anything competitive. */
    public function scopeCompeting($query)
    {
        return $query->where(fn ($q) => $q->where('is_heading', false)->orWhereNull('is_heading'));
    }

    /** Only the titles. */
    public function scopeHeadings($query)
    {
        return $query->where('is_heading', true);
    }

    /** Can the engine actually run this division's format today? */
    public function isRunnableFormat(): bool
    {
        return in_array($this->format ?: self::FORMAT_KNOCKOUT, self::RUNNABLE_FORMATS, true);
    }

    /** What this division is for, and who sits outside it. */
    public function range(): \App\Events\Support\DivisionRange
    {
        return new \App\Events\Support\DivisionRange($this);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(EventMatch::class, 'category_id')->orderBy('slot');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ClubEventRegistration::class, 'category_id');
    }

    /**
     * A division's name, in the reader's language.
     *
     * ⚠️ The words live on the EVENT's translation document, not this row's,
     * under `divisions.{id}`. That is deliberate and is explained in
     * ClubEvent::translatableDocument(): one job per event rather than one per
     * row, and — the reason that matters — the agent sees the divisions and the
     * description together, so the word it picks for a belt rank in one matches
     * the word it picks in the other.
     *
     * `weight_class` is NOT here. It is a measurement ("−70kg"), it appears in
     * the translator's `keep` list as a name to leave alone, and rendering it
     * into another language is how a competitor turns up in the wrong bracket.
     *
     * @return array<string, string>
     */
    protected function translatedAttributes(): array
    {
        return ['name' => 'divisions.'.$this->getKey()];
    }

    /** The event whose document holds this division's words. */
    protected function translationOwner(): ?Model
    {
        return $this->translationOwnerVia('event', ClubEvent::class, $this->getAttributeValue('event_id'));
    }
}
