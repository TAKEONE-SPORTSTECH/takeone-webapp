<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClubPackage;
use App\Models\Tenant;
use App\Support\ClubCache;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Support\Facades\Cache;

class ClubAnalyticsController extends Controller
{
    use HandlesClubAuthorization;

    public function analytics(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $analytics = [
            'new_members'       => 0,
            'new_members_change'=> 0,
            'retention_rate'    => 0,
            'retention_change'  => 0,
            'avg_revenue'       => 0,
            'total_checkins'    => 0,
            'checkins_change'   => 0,
            'monthly_members'   => array_fill(0, 12, 0),
            'activity_labels'   => ['No data'],
            'activity_data'     => [100],
            'hourly_checkins'   => array_fill(0, 9, 0),
        ];

        $popularPackages = Cache::remember(ClubCache::analyticsPopularPackages($clubId), ClubCache::TTL_ANALYTICS, function () use ($clubId) {
            return ClubPackage::where('tenant_id', $clubId)
                ->withCount('subscriptions')
                ->orderByDesc('subscriptions_count')
                ->take(5)
                ->get();
        });

        return view('admin.club.analytics.index', compact('club', 'analytics', 'popularPackages'));
    }
}
