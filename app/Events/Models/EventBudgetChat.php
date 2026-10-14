<?php

namespace App\Events\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One organiser's running conversation with the budget advisor for one event —
 * the agent's memory. `messages` is a window of recent turns:
 * [{role: user|assistant, content, at, actions?}].
 */
class EventBudgetChat extends Model
{
    /** How many turns the memory keeps. */
    public const WINDOW = 30;

    protected $fillable = ['event_id', 'user_id', 'messages'];

    protected $casts = ['messages' => 'array'];

    public static function for(ClubEvent $event, int $userId): self
    {
        return self::firstOrNew(['event_id' => $event->id, 'user_id' => $userId], ['messages' => []]);
    }

    /** Add a turn and keep only the window. */
    public function remember(string $role, string $content, array $extra = []): void
    {
        $messages = $this->messages ?? [];
        $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000), 'at' => now()->toIso8601String()] + $extra;
        $this->messages = array_slice($messages, -self::WINDOW);
    }
}
