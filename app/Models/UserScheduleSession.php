<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member-authored personal training session (weekly recurring). Renders on
 * /me/schedule next to the read-only sessions synced from enrolled club
 * packages. Holds every field the schedule card + detail show.
 */
class UserScheduleSession extends Model
{
    protected $fillable = [
        'user_id', 'subject_user_id',
        'day', 'start_time', 'end_time',
        'title', 'discipline', 'icon', 'color', 'coach', 'location', 'location_meta', 'intensity',
        'focus', 'notes', 'workout',
    ];

    protected $casts = [
        'focus' => 'array',
        'workout' => 'array',
        'location_meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /** "06:30" → "6:30 AM" for display; passes odd strings through. */
    private function fmt(?string $t): ?string
    {
        if (! $t) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($t)->format('g:i A');
        } catch (\Throwable) {
            return $t;
        }
    }

    /** Human duration ("60 min") derived from start/end, best-effort. */
    public function durationLabel(): string
    {
        try {
            $s = \Carbon\Carbon::parse($this->start_time);
            $e = \Carbon\Carbon::parse($this->end_time);
            $mins = $s->diffInMinutes($e);

            return $mins > 0 ? $mins.' min' : '—';
        } catch (\Throwable) {
            return '—';
        }
    }

    /**
     * The card/detail array used by the schedule views — identical shape to the
     * synced sessions so both render through the same Blade. `who` is the subject
     * key; the controller supplies the matching member entry.
     */
    public function toCardArray(string $whoKey): array
    {
        $workout = $this->workout ?: ['warmup' => [], 'main' => [], 'cooldown' => []];

        return [
            'id' => $this->id,
            'source' => 'personal',
            'editable' => true,
            'who' => $whoKey,
            'day' => $this->day,
            'start' => $this->fmt($this->start_time),
            'end' => $this->fmt($this->end_time),
            'start_raw' => $this->start_time,
            'end_raw' => $this->end_time,
            'duration' => $this->durationLabel(),
            'title' => $this->title,
            'discipline' => $this->discipline,
            'icon' => $this->icon ?: 'bi-calendar-check',
            'color' => $this->color ?: '#7c3aed',
            'coach' => $this->coach,
            'location' => $this->location,
            'location_type' => $this->location_meta['type'] ?? ($this->location ? 'text' : null),
            'location_lat' => $this->location_meta['lat'] ?? null,
            'location_lng' => $this->location_meta['lng'] ?? null,
            'location_address' => $this->location_meta['address'] ?? null,
            'intensity' => $this->intensity,
            'focus' => $this->focus ?: [],
            'notes' => $this->notes,
            'workout' => [
                'warmup' => $workout['warmup'] ?? [],
                'main' => $workout['main'] ?? [],
                'cooldown' => $workout['cooldown'] ?? [],
            ],
        ];
    }
}
