<?php

namespace App\Clubs\Support;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Members\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Club mode — a manager who came in through /club stays inside their club.
 *
 * Engaged by ClubEntryController, held in the session, and ended only by
 * signing out (the session is invalidated). A super-admin is never locked.
 *
 * This is a NAVIGATION seal, not an access rule: it decides which pages a
 * locked manager may land on, and every one of those pages still authorises
 * the request itself. Two halves:
 *   - App\Clubs\Middleware\KeepInClub turns any other full page into a
 *     redirect back to the club (typed URL, notification, stray link);
 *   - the shared layouts ask active() and swap their platform navigation for
 *     the club bar, so the way out is not drawn in the first place.
 *
 * Reachable while locked: the club admin panel of a locked club, /club
 * itself, that club's own public page and QR posters, and the three "work"
 * pages the panel links to — a person who belongs to the club, an event the
 * club hosts, a trainer who coaches there. Sign-in / 2FA / verification
 * pages are always reachable, so the lock can never trap anyone mid-login.
 */
final class ClubLock
{
    private const KEY = 'club_lock';

    /** @param  Collection<int, Tenant>  $clubs */
    public static function engage(User $user, Collection $clubs): void
    {
        if ($user->isSuperAdmin() || $clubs->isEmpty()) {
            return;
        }

        session()->put(self::KEY, [
            'user' => $user->id,
            'clubs' => $clubs->mapWithKeys(fn (Tenant $c) => [$c->id => [
                'slug' => $c->slug,
                'name' => $c->club_name,
                'logo' => $c->logo,
            ]])->all(),
        ]);
    }

    public static function active(): bool
    {
        $lock = session(self::KEY);

        return is_array($lock) && Auth::id() !== null && (int) ($lock['user'] ?? 0) === (int) Auth::id();
    }

    /** @return array<int, array{slug: string, name: string, logo: ?string}> */
    public static function clubs(): array
    {
        return self::active() ? (array) session(self::KEY.'.clubs', []) : [];
    }

    /** The club the page is about, or the first locked club. */
    public static function current(?Request $request = null): ?array
    {
        $clubs = self::clubs();
        $club = ($request ?? request())->route('club');
        if ($club instanceof Tenant && isset($clubs[$club->id])) {
            return $clubs[$club->id] + ['id' => $club->id];
        }
        $first = array_key_first($clubs);

        return $first === null ? null : $clubs[$first] + ['id' => $first];
    }

    /** Where "back to the club" goes: the only club's dashboard, or the picker. */
    public static function homeUrl(?Request $request = null): string
    {
        $clubs = self::clubs();
        $club = ($request ?? request())->route('club');
        if ($club instanceof Tenant && isset($clubs[$club->id])) {
            return route('admin.club.dashboard', $club->slug);
        }
        if (count($clubs) === 1) {
            return route('admin.club.dashboard', reset($clubs)['slug']);
        }

        return route('club.entry');
    }

    public static function permits(Request $request): bool
    {
        $route = $request->route();
        $name = $route?->getName();
        if ($name === null) {
            return false;
        }

        if (in_array($name, ['club.entry', 'login', 'logout'], true)
            || str_starts_with($name, 'two-factor.')
            || str_starts_with($name, 'verification.')
            || str_starts_with($name, 'password.')) {
            return true;
        }

        $ids = array_keys(self::clubs());
        if ($ids === []) {
            return false;
        }

        if (str_starts_with($name, 'admin.club.') || str_starts_with($name, 'qr.club.')) {
            $club = $route->parameter('club');

            return $club instanceof Tenant && in_array($club->id, $ids, true);
        }

        if ($name === 'clubs.show' || $name === 'clubs.show.public') {
            $slugs = array_column(self::clubs(), 'slug');

            return in_array((string) $route->parameter('slug'), $slugs, true);
        }

        if (str_starts_with($name, 'me.events.')) {
            $event = $route->parameter('event');
            $tenantId = $event instanceof ClubEvent
                ? $event->tenant_id
                : (is_string($event) ? ClubEvent::where('uuid', $event)->value('tenant_id') : null);

            return $tenantId !== null && in_array((int) $tenantId, $ids, true);
        }

        if (str_starts_with($name, 'member.')) {
            $key = $route->parameter('uuid') ?? $route->parameter('id');
            $userId = match (true) {
                $key instanceof User => $key->id,
                $name === 'member.show' => User::where('uuid', (string) $key)->value('id'),
                is_numeric($key) => (int) $key,
                default => null,
            };

            return $userId !== null && self::belongsToClub((int) $userId, $ids);
        }

        if ($name === 'trainer.show' || $name === 'trainer.show.public') {
            $user = $route->parameter('user');
            $userId = $user instanceof User ? $user->id : (is_numeric($user) ? (int) $user : null);

            return $userId !== null
                && DB::table('club_instructors')->where('user_id', $userId)->whereIn('tenant_id', $ids)->exists();
        }

        return false;
    }

    /** A member, a former subscriber or an instructor of one of these clubs. */
    private static function belongsToClub(int $userId, array $ids): bool
    {
        return DB::table('memberships')->where('user_id', $userId)->whereIn('tenant_id', $ids)->exists()
            || DB::table('club_member_subscriptions')->where('user_id', $userId)->whereIn('tenant_id', $ids)->exists()
            || DB::table('club_instructors')->where('user_id', $userId)->whereIn('tenant_id', $ids)->exists();
    }
}
