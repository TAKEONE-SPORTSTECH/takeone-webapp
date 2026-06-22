<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'club_notification_id',
        'user_id',
        'actor_user_id',
        'tenant_id',
        'type',
        'subject_type',
        'subject_id',
        'title',
        'body',
        'action_url',
        'icon',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read'  => 'boolean',
        'read_at'  => 'datetime',
    ];

    public function clubNotification(): BelongsTo
    {
        return $this->belongsTo(ClubNotification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Unified view model for the bell — resolves content from either a club
     * broadcast (clubNotification) or a self-contained row (social/billing).
     */
    public function display(): array
    {
        if ($this->club_notification_id && $this->clubNotification) {
            $cn = $this->clubNotification;
            return [
                'title'   => $cn->subject,
                'body'    => $cn->message,
                'url'     => $cn->action_url,
                'icon'    => 'bi-megaphone-fill',
                'context' => $cn->tenant->club_name ?? 'Club',
                'avatar'  => null,
            ];
        }

        $actor = $this->actor;

        // Re-render the title in the recipient's locale from the notification
        // type (the stored $this->title is an English fallback for unknown
        // types and legacy rows). Actor names are data and stay as-is.
        $actorName = $actor?->full_name ?? '';
        $title = match ($this->type) {
            'follow', 'post', 'like', 'comment'     => __('notifications.' . $this->type, ['actor' => $actorName]),
            'payment_approved', 'payment_refunded'  => __('notifications.' . $this->type),
            default                                 => $this->title,
        };

        return [
            'title'   => $title,
            'body'    => $this->body,
            'url'     => $this->action_url,
            'icon'    => $this->icon ?: 'bi-bell-fill',
            'context' => $actor?->full_name ?? $this->tenant?->club_name,
            'avatar'  => $actor && $actor->profile_picture
                ? asset('storage/' . $actor->profile_picture) . '?v=' . optional($actor->updated_at)->timestamp
                : null,
        ];
    }

    /**
     * Create a self-contained notification for a user and best-effort push it
     * over realtime so the bell updates without a reload.
     */
    public static function notifyUser(int $userId, string $type, string $title, array $opts = []): ?self
    {
        // Never notify someone about their own action.
        if (! empty($opts['actor_id']) && (int) $opts['actor_id'] === $userId) {
            return null;
        }

        $n = static::create([
            'user_id'       => $userId,
            'actor_user_id' => $opts['actor_id']     ?? null,
            'tenant_id'     => $opts['tenant_id']    ?? null,
            'type'          => $type,
            'subject_type'  => $opts['subject_type'] ?? null,
            'subject_id'    => $opts['subject_id']   ?? null,
            'title'         => $title,
            'body'          => $opts['body']       ?? null,
            'action_url'    => $opts['action_url'] ?? null,
            'icon'          => $opts['icon']       ?? null,
            'is_read'       => false,
        ]);

        try {
            if (function_exists('Realtime') && Realtime()->enabled()) {
                $d = $n->display();
                Realtime()->publishToUser($userId, 'notifications', [
                    'id'               => $n->id,
                    'subject'          => $title,
                    'body'             => $d['body'],
                    'icon'             => $d['icon'],
                    'avatar'           => $d['avatar'],          // actor's profile picture
                    'context'          => $d['context'] ?? ($opts['context'] ?? null),
                    'club_name'        => $opts['context'] ?? null,
                    'action_url'       => $opts['action_url'] ?? null,
                    'created_at_human' => 'just now',
                ]);
            }
        } catch (\Throwable $e) {
            // Best-effort; the DB row is the source of truth.
        }

        return $n;
    }

    /**
     * Delete every notification raised for a given subject (e.g. a deleted
     * post) and best-effort push a live "remove" event to each affected user's
     * bell so the entry disappears without a reload.
     */
    public static function removeForSubject(string $subjectType, int $subjectId): void
    {
        $rows = static::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->get(['id', 'user_id']);

        if ($rows->isEmpty()) {
            return;
        }

        static::whereIn('id', $rows->pluck('id'))->delete();

        try {
            if (! (function_exists('Realtime') && Realtime()->enabled())) {
                return;
            }
            $batch = $rows->groupBy('user_id')->map(function ($userRows, $userId) {
                return [
                    'topic'   => Realtime()->userTopic((int) $userId, 'notifications'),
                    'payload' => ['action' => 'remove', 'ids' => $userRows->pluck('id')->map(fn ($i) => (int) $i)->all()],
                ];
            })->values()->all();

            if ($batch) {
                Realtime()->publishMany($batch);
            }
        } catch (\Throwable $e) {
            // Best-effort; the rows are already gone from the DB.
        }
    }
}
