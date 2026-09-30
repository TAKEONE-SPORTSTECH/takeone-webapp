<?php

namespace App\Clubs\Controllers;

use App\Http\Controllers\Controller;
use App\Clubs\Models\ClubPackage;
use App\Clubs\Models\Tenant;
use App\Clubs\Support\ClubAnalytics;
use App\Clubs\Support\ClubCache;
use App\Clubs\Concerns\HandlesClubAuthorization;
use Illuminate\Support\Facades\Cache;

class ClubAnalyticsController extends Controller
{
    use HandlesClubAuthorization;

    public function analytics(Tenant $club, ClubAnalytics $analytics)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $analytics = $analytics->build($club);

        $popularPackages = Cache::remember(ClubCache::analyticsPopularPackages($clubId), ClubCache::TTL_ANALYTICS, function () use ($clubId) {
            return ClubPackage::where('tenant_id', $clubId)
                ->withCount('subscriptions')
                ->orderByDesc('subscriptions_count')
                ->take(5)
                ->get();
        });

        // Each package's share of the subscriptions, for the progress bars.
        $totalSubs = max(1, (int) $popularPackages->sum('subscriptions_count'));
        $popularPackages->each(fn ($p) => $p->percentage = (int) round($p->subscriptions_count / $totalSubs * 100));

        return view(\App\Clubs\Support\ClubView::pick('analytics', 'clubs'), compact('club', 'analytics', 'popularPackages'));
    }
}
