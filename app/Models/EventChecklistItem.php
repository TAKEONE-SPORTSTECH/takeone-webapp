<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TranslatesAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Members\Models\User;

/**
 * One thing that must be true before an event can begin.
 *
 * The organiser writes the list; any appointed official can clear an item.
 * Clearing records who and when — an unsigned checklist is a to-do list, a
 * signed one is a record of who said the hall was ready.
 */
class EventChecklistItem extends Model
{
    use HasFactory;
    use TranslatesAttributes;

    protected $fillable = [
        'event_id',
        'label',
        'sort_order',
        'checked_at',
        'checked_by',
        'uuid',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'checked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function isChecked(): bool
    {
        return $this->checked_at !== null;
    }

    /**
     * The item's text, in the reader's language.
     *
     * ⚠️ These were not translatable AT ALL until 2026-09-09 — not missing a
     * language, absent from the document, so there was nothing to look up and
     * no amount of translating the event would ever have reached them. An
     * organiser's readiness list is the first thing an official reads on the
     * morning of a competition, and "الميزان جاهز" is not a useful instruction
     * to a Chinese-speaking official standing at the scale.
     *
     * Stored on the EVENT's document under `checklist.{id}`, with the divisions
     * and the fee lines, so one job translates the whole event.
     *
     * @return array<string, string>
     */
    protected function translatedAttributes(): array
    {
        return ['label' => 'checklist.'.$this->getKey()];
    }

    /** The event whose document holds this item's words. */
    protected function translationOwner(): ?Model
    {
        return $this->translationOwnerVia('event', ClubEvent::class, $this->getAttributeValue('event_id'));
    }
}
