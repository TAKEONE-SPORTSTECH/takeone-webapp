<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Clubs\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Who hears about an event.
 *
 * Shared across every event type: `scope`, `sport` and the host club's location
 * are core columns, so the rule is identical for a championship, a belt test or
 * a league. Only the geography widens with scope — the "practises this
 * activity" predicate is constant:
 *
 *   internal    → the host club
 *   inter_club  → clubs within `radius_km` of the host
 *   nationwide  → clubs in the host's country
 *   regional    → host country + chosen (or configured neighbouring) countries
 *   worldwide   → chosen countries, or everywhere when none are chosen
 *
 * DENY BY DEFAULT: if the event's activity cannot be matched to anything the
 * platform knows about, a broadcast resolves to NOBODY rather than to everyone.
 * Getting "we couldn't identify the sport" wrong must never mean notifying a
 * whole country.
 */
class AudienceResolver
{
    /**
     * Members to announce this event to — user ids.
     *
     * @param  bool  $announcement  true = broadcast (respects the announcements
     *                              opt-out), false = a reminder about an event
     *                              they already joined
     * @return array<int, int>
     */
    public function forEvent(ClubEvent $event, bool $announcement = true): array
    {
        $clubIds = $this->clubsInScope($event);
        if (! $clubIds) {
            return [];
        }

        return $this->membersPractising($event, $clubIds, $announcement);
    }

    /**
     * Everyone who may MANAGE this event — user ids.
     *
     * The realtime twin of EventAccess::canManage(), and it exists because the
     * two kept drifting apart. `canManage` was widened (2026-09-03) to the host
     * club's owner, its club-admins and every appointed organiser, but the
     * push audiences were not: `pushEventRefresh` still sent to registrations +
     * officials + creator, and `AbstractEventType::audienceFor` to
     * registrations + creator alone. So a club owner with the console open, or
     * an appointed organiser cutting the draw, was never nudged — the single
     * most visible instance of "it does not update by itself".
     *
     * Kept HERE rather than on EventAccess because that class answers a
     * yes/no about ONE person (and memoises per person); this enumerates. Same
     * rule, two shapes — so when the rule changes, both places are one file
     * apart.
     *
     * Cheap: three indexed lookups, no model hydration.
     *
     * @return array<int, int>
     */
    public function managers(ClubEvent $event): array
    {
        $ids = [];

        if ($event->created_by) {
            $ids[] = (int) $event->created_by;
        }

        $tenantId = (int) ($event->tenant_id ?? 0);

        if ($tenantId !== 0) {
            // The club's owner. Owning a club and being a member of it are
            // different things, so this asks the tenant directly — exactly as
            // EventAccess::resolveCanManage() does.
            $owner = Tenant::whereKey($tenantId)->value('owner_user_id');
            if ($owner) {
                $ids[] = (int) $owner;
            }

            // Its club-admins, by the role rows rather than by membership.
            $ids = array_merge($ids, DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.tenant_id', $tenantId)
                ->whereIn('roles.slug', ['club-admin', 'owner'])
                ->pluck('user_roles.user_id')->map('intval')->all());
        }

        // Appointed to THIS event as the person running it.
        $ids = array_merge($ids, $event->officials()
            ->where('role', 'organiser')
            ->pluck('user_id')->map('intval')->all());

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Everyone who should be told when one entry changes: the athlete, everyone
     * who may manage the event, and every official whose desk reads the entry
     * list (weigh-in, payments, jury arranging a draw).
     *
     * @return array<int, int>
     */
    public function entryWatchers(ClubEvent $event, int $athleteId): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [$athleteId],
            $this->managers($event),
            $event->officials()->pluck('user_id')->map('intval')->all(),
        ))));
    }

    /** Everyone already registered for the event, plus its organiser. */
    public function registrants(ClubEvent $event): array
    {
        $ids = $event->registrations()->pluck('user_id')->all();
        if ($event->created_by) {
            $ids[] = $event->created_by;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** Only the competitors (not spectators) — for run-day reminders. */
    public function participants(ClubEvent $event): array
    {
        return $event->participantRegistrations()
            ->pluck('user_id')->map('intval')->unique()->values()->all();
    }

    /* ---------------- Geography ---------------- */

    /**
     * Clubs the event's scope reaches.
     *
     * @return array<int, int> tenant ids
     */
    public function clubsInScope(ClubEvent $event): array
    {
        $host = $event->tenant ?: Tenant::find($event->tenant_id);
        if (! $host) {
            return [];
        }

        $scope = $event->scope ?: 'internal';

        return match ($scope) {
            'inter_club' => $this->clubsWithinRadius($host, (int) config('event_notifications.radius_km', 50)),
            'nationwide' => $this->clubsInCountries([$host->country]),
            'regional' => $this->clubsInCountries($this->regionCountries($event, $host)),
            'worldwide' => $this->clubsInCountries($this->selectedCountries($event)),   // empty list = everywhere
            default => [(int) $host->id],   // internal
        };
    }

    /**
     * Clubs within `radius` km of the host, host included.
     *
     * A bounding box narrows the rows in SQL (portable — SQLite has no spatial
     * functions), then haversine gives the true distance. Clubs with no
     * coordinates are excluded: an unknown location must not be assumed near.
     */
    private function clubsWithinRadius(Tenant $host, int $radius): array
    {
        $lat = $host->gps_lat !== null ? (float) $host->gps_lat : null;
        $lng = $host->gps_long !== null ? (float) $host->gps_long : null;

        // Host has no coordinates — we cannot say what is "within 50 km", so we
        // fall back to the host club alone rather than guessing wider.
        if ($lat === null || $lng === null) {
            return [(int) $host->id];
        }

        $latDelta = $radius / 111.0;                                   // ~111 km per degree of latitude
        $lngDelta = $radius / max(1e-6, 111.0 * cos(deg2rad($lat)));   // narrows toward the poles

        $candidates = Tenant::query()
            ->whereNotNull('gps_lat')->whereNotNull('gps_long')
            ->whereBetween('gps_lat', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('gps_long', [$lng - $lngDelta, $lng + $lngDelta])
            ->get(['id', 'gps_lat', 'gps_long']);

        $ids = $candidates
            ->filter(fn ($c) => $this->haversine($lat, $lng, (float) $c->gps_lat, (float) $c->gps_long) <= $radius)
            ->pluck('id')->map('intval')->all();

        $ids[] = (int) $host->id;

        return array_values(array_unique($ids));
    }

    /** Great-circle distance in kilometres. */
    public function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param  array<int, ?string>  $countries  empty = every country
     * @return array<int, int>
     */
    private function clubsInCountries(array $countries): array
    {
        $countries = array_values(array_filter(array_map(
            fn ($c) => $c ? strtolower(trim($c)) : null,
            $countries,
        )));

        return Tenant::query()
            ->when($countries, fn ($q) => $q->whereIn(DB::raw('LOWER(country)'), $countries))
            ->pluck('id')->map('intval')->all();
    }

    /** Host country + the organiser's chosen countries, else the configured neighbours. */
    private function regionCountries(ClubEvent $event, Tenant $host): array
    {
        $chosen = $this->selectedCountries($event);
        if ($chosen) {
            return array_unique(array_merge([$host->country], $chosen));
        }

        $neighbours = config('event_notifications.neighbours.'.strtolower((string) $host->country), []);

        return array_unique(array_merge([$host->country], $neighbours));
    }

    /** @return array<int, string> */
    private function selectedCountries(ClubEvent $event): array
    {
        return collect($event->notify_countries ?? [])
            ->map(fn ($c) => strtolower(trim((string) $c)))
            ->filter()->unique()->values()->all();
    }

    /* ---------------- "Practises this activity" ---------------- */

    /**
     * Active members of the given clubs who practise the event's activity.
     *
     * A member qualifies when EITHER
     *   (a) they hold a skill/affiliation record for the activity, or
     *   (b) their club runs an activity of that name.
     *
     * ⚠️ This is the definition most likely to need tuning after testing — it is
     * deliberately confined to this one method. Narrow it (to members actually
     * enrolled in a package of that activity) or widen it here, nowhere else.
     *
     * @param  array<int, int>  $clubIds
     * @return array<int, int>
     */
    private function membersPractising(ClubEvent $event, array $clubIds, bool $announcement): array
    {
        $terms = $this->activityTerms($event);

        $members = DB::table('memberships')
            ->whereIn('tenant_id', $clubIds)
            ->where('status', 'active')
            ->pluck('user_id')->map('intval')->unique();

        // An internal event is simply "our club" — no activity filter needed, the
        // membership already scopes it.
        if (($event->scope ?: 'internal') !== 'internal' && $terms) {
            $byClubActivity = DB::table('memberships')
                ->join('club_activities', 'club_activities.tenant_id', '=', 'memberships.tenant_id')
                ->whereIn('memberships.tenant_id', $clubIds)
                ->where('memberships.status', 'active')
                ->where(fn ($q) => $this->matchAny($q, 'club_activities.name', $terms))
                ->pluck('memberships.user_id');

            $bySkill = DB::table('skill_acquisitions')
                ->whereNotNull('user_id')
                ->where(fn ($q) => $this->matchAny($q, 'skill_acquisitions.activity_name', $terms)
                    ->orWhere(fn ($w) => $this->matchAny($w, 'skill_acquisitions.skill_name', $terms)))
                ->pluck('user_id');

            $practising = $byClubActivity->concat($bySkill)->map('intval')->unique();

            $members = $members->intersect($practising);
        } elseif (($event->scope ?: 'internal') !== 'internal' && ! $terms) {
            // Deny by default: a broadcast whose activity we cannot identify
            // reaches nobody rather than everybody.
            return [];
        }

        return $this->applyPreferences($members->values()->all(), $announcement);
    }

    /** Words that identify this event's activity (sport key + its label). */
    private function activityTerms(ClubEvent $event): array
    {
        if (! $event->sport) {
            return [];
        }

        $label = config('event_schema.sports.'.$event->sport.'.label');

        return collect([$event->sport, $label])
            ->filter()
            ->map(fn ($t) => strtolower(str_replace('_', ' ', (string) $t)))
            ->unique()->values()->all();
    }

    /** OR-together a LIKE for each term on the given column. */
    private function matchAny($query, string $column, array $terms)
    {
        foreach ($terms as $i => $term) {
            $method = $i === 0 ? 'where' : 'orWhere';
            $query->{$method}(DB::raw('LOWER('.$column.')'), 'like', '%'.Str::lower($term).'%');
        }

        return $query;
    }

    /**
     * Drop members who have opted out of this kind of notification.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int>
     */
    private function applyPreferences(array $userIds, bool $announcement): array
    {
        if (! $userIds) {
            return [];
        }

        $column = $announcement ? 'notify_event_announcements' : 'notify_event_reminders';

        return DB::table('users')
            ->whereIn('id', $userIds)
            ->whereNull('deleted_at')
            ->where($column, true)
            ->pluck('id')->map('intval')->values()->all();
    }
}
