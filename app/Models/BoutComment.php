<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A comment under a bout video. See the migration for the shape and why. */
class BoutComment extends Model
{
    use HasUuids;

    protected $fillable = [
        'event_id', 'match_id', 'user_id', 'parent_id', 'body', 'stamp_seconds',
    ];

    protected $casts = [
        'stamp_seconds' => 'float',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(BoutCommentLike::class, 'comment_id');
    }

    /**
     * The comment as the page draws it.
     *
     * `initials` and `bg` exist because the draft's avatar is a two-letter tile
     * with a colour — derived from the name so the same person is always the
     * same colour, rather than stored.
     */
    public function present(?User $viewer = null): array
    {
        $name = $this->author?->full_name ?: ($this->author?->name ?: __('shared.unknown'));

        return [
            'key' => $this->uuid,
            'name' => $name,
            'initials' => self::initials($name),
            'bg' => self::tint($name),
            'when' => $this->created_at?->diffForHumans(short: true),
            'stamp' => $this->stamp_seconds !== null ? self::clock((float) $this->stamp_seconds) : null,
            'secs' => (int) ($this->stamp_seconds ?? 0),
            'text' => (string) $this->body,
            'likes' => $this->likes_count ?? $this->likes()->count(),
            'liked' => $viewer !== null && $this->likes->contains('user_id', $viewer->id),
            'mine' => $viewer !== null && $this->user_id === $viewer->id,
        ];
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = Str::upper(Str::substr($parts[0] ?? '', 0, 1));
        $last = count($parts) > 1 ? Str::upper(Str::substr(end($parts), 0, 1)) : '';

        return ($first.$last) ?: '?';
    }

    /** A stable colour per person: the same name always gets the same tile. */
    public static function tint(string $name): string
    {
        $pairs = [
            ['#7c2d12', '#431407'], ['#1e3a8a', '#172554'], ['#14532d', '#052e16'],
            ['#7f1212', '#450a0a'], ['#4c1d95', '#2e1065'], ['#134e4a', '#042f2e'],
        ];
        [$a, $b] = $pairs[crc32($name) % count($pairs)];

        return "linear-gradient(135deg,{$a},{$b})";
    }

    private static function clock(float $seconds): string
    {
        $whole = max(0, (int) floor($seconds));

        return sprintf('%02d:%02d', intdiv($whole, 60), $whole % 60);
    }
}
