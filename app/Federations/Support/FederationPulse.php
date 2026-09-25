<?php

namespace App\Federations\Support;

use App\Federations\Models\Federation;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationStaff;
use App\Members\Models\User;
use Takeone\Realtime\Support\Topics;

/**
 * "Something in this federation changed" — told to everyone who has its
 * dashboard open, so their page re-reads instead of waiting for a refresh.
 *
 * A REFRESH SIGNAL, never data: every recipient gets `{action: 'refresh'}` on
 * their own `federation` topic and re-fetches /federation/api/state, which
 * answers with exactly what THAT viewer may see. So the same change can land
 * on a manager, a sport-scoped staff member and a parent body's staff without
 * anybody being pushed a field they are not allowed (CLAUDE.md Part 3 §11).
 *
 * The audience is the whole tree the federation sits in — its ancestors (they
 * see their children's events and clubs) and its descendants — plus the
 * super-admins, who open the top body's dashboard. Best-effort, like every
 * push on the platform: the database is the truth, and a missed signal is
 * only a page that updates at the next one.
 *
 * Once per federation tree per request, however many writes the request made.
 */
final class FederationPulse
{
    /** @var array<int, true> top-ancestor ids already signalled this request */
    private static array $sent = [];

    public static function federation(?Federation $federation): void
    {
        if (! $federation || ! config('federations.enabled')) {
            return;
        }

        try {
            $top = $federation;
            for ($guard = 0; $top->parent_id && $guard < 10; $guard++) {
                $up = Federation::find($top->parent_id);
                if (! $up) {
                    break;
                }
                $top = $up;
            }
            if (isset(self::$sent[$top->id])) {
                return;
            }
            self::$sent[$top->id] = true;

            // The tree, top down.
            $ids = [$top->id];
            $frontier = [$top->id];
            for ($depth = 0; $frontier && $depth < 10; $depth++) {
                $frontier = Federation::whereIn('parent_id', $frontier)->pluck('id')->all();
                $ids = array_merge($ids, $frontier);
            }

            $users = Federation::whereIn('id', $ids)->pluck('owner_user_id')
                ->merge(FederationStaff::whereIn('federation_id', $ids)->pluck('user_id'))
                ->merge(User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->pluck('id'))
                ->map(fn ($id) => (int) $id)->filter()->unique();

            $batch = $users->map(fn (int $uid) => [
                'topic' => Topics::user($uid, 'federation'),
                'payload' => ['action' => 'refresh'],
            ])->values()->all();

            if ($batch !== []) {
                \Realtime()->publishMany($batch);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The federation a signed-in staff member works in. */
    public static function forUser(?User $user): void
    {
        if (! $user) {
            return;
        }
        rescue(function () use ($user) {
            $access = app(\App\Federations\Services\FederationDirectory::class)->access($user);
            self::federation($access['root'] ?? null);
        }, report: true);
    }

    /** The federation that runs an event (the club invitation's doors). */
    public static function forEvent(FederationEvent|string|null $event): void
    {
        if (is_string($event)) {
            $event = FederationEvent::where('uuid', $event)->first();
        }
        self::federation($event?->federation);
    }
}
