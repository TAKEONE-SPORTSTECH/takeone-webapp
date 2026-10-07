<?php

namespace App\Federations\Services;

use App\Events\Models\ClubEvent;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationClub;
use App\Members\Models\Membership;
use Illuminate\Support\Collection;

/**
 * Everything the federation dashboard shows, in a fixed number of grouped
 * queries however many clubs are affiliated. Reads only APPROVED clubs —
 * a club that has merely applied is listed by name, nothing more.
 */
class FederationDashboard
{
    private const UPCOMING_LIMIT = 6;

    public function build(Federation $federation): array
    {
        $clubs = $federation->clubs()
            ->select('tenants.id', 'tenants.club_name', 'tenants.slug', 'tenants.logo', 'tenants.country')
            ->orderBy('tenants.club_name')
            ->get();

        $clubIds = $clubs->pluck('id')->all();

        $pending = FederationClub::where('federation_id', $federation->id)
            ->where('status', FederationClub::STATUS_PENDING)
            ->with('club:id,club_name,logo')
            ->latest()
            ->get()
            ->map(fn (FederationClub $l) => [
                'name' => $l->club?->club_name,
                'logo' => $l->club?->logo,
                'since' => $l->created_at,
            ])
            ->filter(fn ($r) => $r['name'] !== null)
            ->values();

        if ($clubIds === []) {
            return [
                'clubs' => collect(),
                'pending' => $pending,
                'upcoming' => collect(),
                'totals' => ['clubs' => 0, 'athletes' => 0, 'pending' => $pending->count(), 'upcoming' => 0],
            ];
        }

        $athletes = Membership::whereIn('tenant_id', $clubIds)
            ->where('status', 'active')
            ->selectRaw('tenant_id, COUNT(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');

        // A person in two affiliated clubs is one athlete of the federation.
        $distinctAthletes = Membership::whereIn('tenant_id', $clubIds)
            ->where('status', 'active')
            ->distinct()
            ->count('user_id');

        $upcomingQuery = ClubEvent::whereIn('tenant_id', $clubIds)
            // A club's HIDDEN event is not the federation's to see (ClubEvent::isHidden).
            ->listed()
            ->where('date', '>=', now()->startOfDay())
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNull('is_archived')->orWhere('is_archived', false));

        $upcomingByClub = (clone $upcomingQuery)
            ->selectRaw('tenant_id, COUNT(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');

        $clubNames = $clubs->pluck('club_name', 'id');

        $upcoming = (clone $upcomingQuery)
            ->orderBy('date')
            ->limit(self::UPCOMING_LIMIT)
            ->get()
            ->map(fn (ClubEvent $e) => [
                'title' => $e->title,
                'date' => $e->date,
                'club' => $clubNames[$e->tenant_id] ?? null,
                'color' => $e->color,
            ]);

        $rows = $clubs->map(fn ($c) => [
            'name' => $c->club_name,
            'logo' => $c->logo,
            'country' => $c->country,
            'athletes' => (int) ($athletes[$c->id] ?? 0),
            'upcoming' => (int) ($upcomingByClub[$c->id] ?? 0),
            'joined' => $c->pivot->decided_at,
        ]);

        return [
            'clubs' => $rows,
            'pending' => $pending,
            'upcoming' => $upcoming,
            'totals' => [
                'clubs' => $rows->count(),
                'athletes' => $distinctAthletes,
                'pending' => $pending->count(),
                'upcoming' => (int) $upcomingByClub->sum(),
            ],
        ];
    }
}
