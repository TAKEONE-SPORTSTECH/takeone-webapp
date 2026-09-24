<?php

namespace App\Members\Models;

use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\Surfaces\ContributesMessengerThreads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['type', 'dm_key', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->withPivot('last_read_at', 'cleared_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * The module that owns this thread, when it is not a 1:1 chat — see
     * ContributesMessengerThreads. Null for every direct conversation.
     */
    public function threadModule(): ?ContributesMessengerThreads
    {
        if ($this->type === 'direct' || $this->type === null) {
            return null;
        }

        return app(ModuleRegistry::class)->contributingTo(ContributesMessengerThreads::class)
            ->first(fn (ContributesMessengerThreads $m) => in_array($this->type, $m->messengerThreadTypes(), true));
    }

    /** Find (or create) the single 1:1 conversation between two users. */
    public static function findOrCreateDirect(int $a, int $b): self
    {
        $key = 'u'.min($a, $b).'-u'.max($a, $b);

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
