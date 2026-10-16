<?php

namespace App\Events\Models;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Traits\TranslatesAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One part of a competition day: a division being run, or a block between them.
 *
 * The organiser arranges `position`; the clock is worked out from it by
 * `App\Events\Support\RunSheet`. Only a PINNED slot carries a time of its own.
 *
 * @property string $uuid
 * @property int $event_id
 * @property int|null $category_id
 * @property int $day
 * @property int $position
 * @property string $kind
 * @property string|null $label
 * @property int|null $duration_minutes
 * @property int|null $pinned_at
 */
class EventRunSlot extends Model
{
    use HasFactory, TranslatesAttributes;

    /** A division being run, and the three shapes of everything else. */
    public const KIND_DIVISION = 'division';

    public const KIND_CEREMONY = 'ceremony';

    public const KIND_BREAK = 'break';

    public const KIND_CUSTOM = 'custom';

    /**
     * A GROUP header: the weight classes listed after it (until the next
     * group) run together, finishing every bout before the next group starts,
     * called by the group's own method (`mode`, App\Events\Support\RunSessions).
     * Takes no time of its own and is not shown on the public running order.
     */
    public const KIND_GROUP = 'group';

    public const KINDS = [self::KIND_DIVISION, self::KIND_CEREMONY, self::KIND_BREAK, self::KIND_CUSTOM, self::KIND_GROUP];

    /** What a block defaults to when the organiser adds one without saying. */
    public const DEFAULT_DURATIONS = [
        self::KIND_CEREMONY => 15,
        self::KIND_BREAK => 30,
        self::KIND_CUSTOM => 15,
        self::KIND_GROUP => 0,
    ];

    protected $fillable = [
        'uuid', 'event_id', 'category_id', 'parts', 'day', 'position',
        'kind', 'label', 'duration_minutes', 'pinned_at',
        // A GROUP's: how its weight classes call their bouts (RunSessions).
        'mode',
        // A CLASS's: the id of the group slot it belongs to (RunSessions).
        'group_id',
    ];

    protected $casts = [
        'day' => 'integer',
        'position' => 'integer',
        'duration_minutes' => 'integer',
        'pinned_at' => 'integer',
        'group_id' => 'integer',
        // Which parts of the division this slot runs (App\Events\Support\BoutPart);
        // null is the whole division.
        'parts' => 'array',
    ];

    protected static function booted(): void
    {
        // The public key is minted here, so no caller can forget it and no
        // route can ever end up binding on the auto-increment id.
        static::creating(function (self $slot) {
            $slot->uuid = $slot->uuid ?: (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    /**
     * The organiser types "التتويج" and an English reader must not be shown
     * "التتويج".
     *
     * The words travel in the EVENT's one translation document rather than a
     * document of their own, like divisions and fee lines do — one job per
     * event, and the agent sees the day's parts alongside the description, so
     * the word it picks for a ceremony here matches the one it picks there.
     *
     * A DIVISION slot has no label of its own: it reads its name from the
     * category, which is already translated by the same document. Only the
     * blocks an organiser named are here.
     */
    protected function translationOwner(): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->event;
    }

    /** @return array<string, string> */
    protected function translatedAttributes(): array
    {
        return ['label' => 'run_order.'.$this->getKey()];
    }

    public function isDivision(): bool
    {
        return $this->kind === self::KIND_DIVISION && $this->category_id !== null;
    }

    /**
     * What this part is called.
     *
     * A division reads its name from the category every time rather than
     * copying it, so renaming a division cannot leave the running order
     * showing a name that no longer exists anywhere else.
     */
    public function displayName(): string
    {
        if ($this->isDivision()) {
            $name = (string) ($this->category?->name ?? __('events::events.run_slot_missing_division'));
            // The whole of it — every bout part and the awarding — is just its name.
            // (Its awarding may be the ceremony for everyone's, not its own.)
            $whole = false;
            if ($this->parts && $this->category) {
                $present = \App\Events\Support\BoutPart::present($this->category);
                $whole = $this->parts == [...$present, \App\Events\Support\BoutPart::AWARDING]
                    || ($this->parts == $present
                        && in_array($this->category_id, app(\App\Events\Support\RunOrder::class)->awardedTogether((int) $this->event_id), true));
            }
            $parts = $whole ? null : \App\Events\Support\BoutPart::labels($this->parts);

            // "8-9 Women · Bracket A — Preliminaries" when only part of it runs here.
            return $parts ? $name.' — '.$parts : $name;
        }

        $label = (string) ($this->label ?: __('events::events.run_slot_untitled'));

        // One medal ceremony for everyone (RunOrder::togetherCeremony): say how many.
        $awards = is_array($this->parts) ? count((array) ($this->parts['awards'] ?? [])) : 0;

        return $awards ? $label.' — '.trans_choice('events::events.run_order_awards_n', $awards, ['n' => $awards]) : $label;
    }
}
