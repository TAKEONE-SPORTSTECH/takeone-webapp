<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['type', 'tenant_id', 'dm_key', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->withPivot('last_read_at', 'cleared_at', 'muted', 'banned_until', 'blocked', 'left_at')
            ->withTimestamps();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** The single group room for a club; participants synced to its members. */
    public static function findOrCreateClubRoom(Tenant $club): self
    {
        $room = static::firstOrCreate(
            ['type' => 'club', 'tenant_id' => $club->id],
            ['last_message_at' => now()],
        );
        $room->syncClubMembers($club);

        return $room;
    }

    /** Add any club members not yet in the room (never re-adds those who left/blocked). */
    public function syncClubMembers(Tenant $club): void
    {
        $existing = $this->participants()->pluck('users.id')->all();
        $toAdd    = $club->members()->pluck('users.id')->diff($existing)->all();
        if ($toAdd) {
            $this->participants()->attach($toAdd);
        }
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /** Find (or create) the single 1:1 conversation between two users. */
    public static function findOrCreateDirect(int $a, int $b): self
    {
        $key = 'u' . min($a, $b) . '-u' . max($a, $b);

        $conversation = static::firstOrCreate(
            ['dm_key' => $key],
            ['type' => 'direct'],
        );

        // Attach both participants the first time round (idempotent).
        if ($conversation->wasRecentlyCreated) {
            $conversation->participants()->syncWithoutDetaching([$a, $b]);
        }

        return $conversation;
    }

    /** The other party in a 1:1 conversation, from $userId's perspective. */
    public function otherParticipant(int $userId): ?User
    {
        return $this->participants->firstWhere('id', '!=', $userId);
    }

    /** Count of messages this user hasn't read (excludes their own). */
    public function unreadCountFor(int $userId): int
    {
        $pivot = $this->participants->firstWhere('id', $userId)?->pivot;
        $since = $pivot?->last_read_at;

        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->count();
    }
}
