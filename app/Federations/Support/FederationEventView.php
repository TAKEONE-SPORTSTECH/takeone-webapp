<?php

namespace App\Federations\Support;

use App\Events\Models\ClubEvent;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationStaff;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * May federation staff READ a platform event? (decided 2026-09-26)
 *
 * The Athletes page lists the events a federation's athletes competed in,
 * and each must open. This is the one door EventAccess::visible() asks, and
 * it answers yes only when ALL hold:
 *
 *   · the viewer runs a usable federation — its owner, or on its staff;
 *   · the event is not a club's internal session;
 *   · someone competing in it belongs to that federation (a member of one of
 *     its clubs, or one of its athletes), sub-federations included;
 *   · a sport-limited staff member sees only their sports.
 *
 * Viewing only. It grants nothing on canManage() — no console, no draw, no
 * money — and nothing outside events its own people competed in.
 */
class FederationEventView
{
    /** @var array<string, bool> memo per request: event:user */
    private static array $memo = [];

    public static function sees(ClubEvent $event, User $user): bool
    {
        if (! config('federations.enabled') || ($event->scope ?? 'internal') === 'internal') {
            return false;
        }

        return self::$memo[$event->id.':'.$user->id] ??= rescue(fn () => self::resolve($event, $user), false, report: true);
    }

    private static function resolve(ClubEvent $event, User $user): bool
    {
        // The federations this user runs, each with the sports they may see
        // ([] = every sport).
        $runs = Federation::approved()->whereNull('suspended_at')->where('owner_user_id', $user->id)->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => []])->all();
        foreach (FederationStaff::where('user_id', $user->id)->get(['federation_id', 'sports']) as $row) {
            $runs[$row->federation_id] = array_values(array_filter((array) $row->sports));
        }
        if ($runs === []) {
            return false;
        }

        $sport = trim((string) $event->sport);
        $ids = [];
        foreach ($runs as $fid => $sports) {
            if ($sports !== [] && ! collect($sports)->contains(fn ($s) => strcasecmp($s, $sport) === 0)) {
                continue;
            }
            $ids = array_merge($ids, self::tree((int) $fid));
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return false;
        }

        $competitors = DB::table('club_event_registrations')->where('event_id', $event->id)
            ->where('status', 'joined')->where('role', 'participant')->pluck('user_id');
        if ($competitors->isEmpty()) {
            return false;
        }

        $tenants = FederationClub::whereIn('federation_id', $ids)->where('status', 'approved')->pluck('tenant_id');
        if ($tenants->isNotEmpty() && DB::table('memberships')->whereIn('tenant_id', $tenants)->where('status', 'active')
            ->whereIn('user_id', $competitors)->exists()) {
            return true;
        }

        return FederationAthleteClub::whereIn('federation_id', $ids)
            ->whereIn('federation_athlete_id', FederationAthlete::whereIn('user_id', $competitors)->pluck('id'))
            ->exists();
    }

    /** A usable federation and every body beneath it. */
    private static function tree(int $rootId): array
    {
        $root = Federation::find($rootId);
        if (! $root || ! $root->isApproved() || $root->isSuspended()) {
            return [];
        }
        $ids = [$root->id];
        $frontier = [$root->id];
        for ($depth = 0; $frontier && $depth < 10; $depth++) {
            $frontier = Federation::whereIn('parent_id', $frontier)->whereNull('suspended_at')->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
