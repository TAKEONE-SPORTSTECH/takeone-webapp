<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClubController extends Controller
{
    /**
     * Display the explore clubs page.
     */
    public function index()
    {
        return view('clubs.explore');
    }

    /**
     * Get nearby clubs based on user's location.
     */
    public function nearby(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:100', // radius in kilometers
        ]);

        $userLat = $request->latitude;
        $userLng = $request->longitude;
        $radius = $request->radius ?? 50; // default 50km radius

        // Get all clubs with GPS coordinates
        $clubs = Tenant::whereNotNull('gps_lat')
            ->whereNotNull('gps_long')
            ->with('owner')
            ->get();

        // Calculate distance for each club using Haversine formula
        $clubsWithDistance = $clubs->map(function ($club) use ($userLat, $userLng) {
            $distance = $this->calculateDistance(
                $userLat,
                $userLng,
                $club->gps_lat,
                $club->gps_long
            );

            return [
                'id' => $club->id,
                'club_name' => $club->club_name,
                'slug' => $club->slug,
                'logo' => $club->logo,
                'gps_lat' => (float) $club->gps_lat,
                'gps_long' => (float) $club->gps_long,
                'distance' => round($distance, 2), // distance in kilometers
                'owner_name' => $club->owner->full_name,
                'owner_email' => $club->owner->email,
                'owner_mobile' => $club->owner->mobile,
            ];
        });

        // Filter by radius and sort by distance
        $nearbyClubs = $clubsWithDistance
            ->filter(function ($club) use ($radius) {
                return $club['distance'] <= $radius;
            })
            ->sortBy('distance')
            ->values();

        return response()->json([
            'success' => true,
            'clubs' => $nearbyClubs,
            'total' => $nearbyClubs->count(),
            'user_location' => [
                'latitude' => $userLat,
                'longitude' => $userLng,
            ],
            'radius' => $radius,
        ]);
    }

    /**
     * Calculate distance between two GPS coordinates using Haversine formula.
     * Returns distance in kilometers.
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return $distance;
    }

    /**
     * Get all clubs for the map view.
     */
    public function all()
    {
        $clubs = Tenant::whereNotNull('gps_lat')
            ->whereNotNull('gps_long')
            ->with('owner')
            ->get()
            ->map(function ($club) {
                return [
                    'id' => $club->id,
                    'club_name' => $club->club_name,
                    'slug' => $club->slug,
                    'logo' => $club->logo,
                    'gps_lat' => (float) $club->gps_lat,
                    'gps_long' => (float) $club->gps_long,
                    'owner_name' => $club->owner->full_name,
                ];
            });

        return response()->json([
            'success' => true,
            'clubs' => $clubs,
            'total' => $clubs->count(),
        ]);
    }
}
