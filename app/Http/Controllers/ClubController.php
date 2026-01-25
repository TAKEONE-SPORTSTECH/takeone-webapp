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
                'cover_image' => $club->cover_image,
                'gps_lat' => (float) $club->gps_lat,
                'gps_long' => (float) $club->gps_long,
                'distance' => round($distance, 2), // distance in kilometers
                'owner_name' => $club->owner ? $club->owner->full_name : 'N/A',
                'owner_email' => $club->owner ? $club->owner->email : null,
                'owner_mobile' => $club->owner ? $club->owner->mobile : null,
                'address' => $club->address,
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
     * If latitude and longitude are provided, calculate distance and sort by nearest.
     */
    public function all(Request $request)
    {
        $userLat = $request->input('latitude');
        $userLng = $request->input('longitude');

        $clubs = Tenant::with('owner')->get();

        // If user location is provided, calculate distance for each club
        if ($userLat !== null && $userLng !== null) {
            $clubsWithDistance = $clubs->map(function ($club) use ($userLat, $userLng) {
                $distance = null;

                // Calculate distance only if club has GPS coordinates
                if ($club->gps_lat && $club->gps_long) {
                    $distance = $this->calculateDistance(
                        $userLat,
                        $userLng,
                        $club->gps_lat,
                        $club->gps_long
                    );
                }

                return [
                    'id' => $club->id,
                    'club_name' => $club->club_name,
                    'slug' => $club->slug,
                    'logo' => $club->logo,
                    'cover_image' => $club->cover_image,
                    'gps_lat' => $club->gps_lat ? (float) $club->gps_lat : null,
                    'gps_long' => $club->gps_long ? (float) $club->gps_long : null,
                    'distance' => $distance !== null ? round($distance, 2) : null,
                    'owner_name' => $club->owner ? $club->owner->full_name : 'N/A',
                    'address' => $club->address,
                ];
            });

            // Sort by distance (nearest first), clubs without GPS coordinates go to the end
            $clubsWithDistance = $clubsWithDistance->sort(function ($a, $b) {
                // If both have distance, sort by distance
                if ($a['distance'] !== null && $b['distance'] !== null) {
                    return $a['distance'] <=> $b['distance'];
                }
                // If only one has distance, prioritize it
                if ($a['distance'] !== null) {
                    return -1;
                }
                if ($b['distance'] !== null) {
                    return 1;
                }
                // If neither has distance, maintain original order
                return 0;
            })->values();

            return response()->json([
                'success' => true,
                'clubs' => $clubsWithDistance,
                'total' => $clubsWithDistance->count(),
                'user_location' => [
                    'latitude' => $userLat,
                    'longitude' => $userLng,
                ],
            ]);
        }

        // If no location provided, return clubs without distance calculation
        $clubsData = $clubs->map(function ($club) {
            return [
                'id' => $club->id,
                'club_name' => $club->club_name,
                'slug' => $club->slug,
                'logo' => $club->logo,
                'cover_image' => $club->cover_image,
                'gps_lat' => $club->gps_lat ? (float) $club->gps_lat : null,
                'gps_long' => $club->gps_long ? (float) $club->gps_long : null,
                'distance' => null,
                'owner_name' => $club->owner ? $club->owner->full_name : 'N/A',
                'address' => $club->address,
            ];
        });

        return response()->json([
            'success' => true,
            'clubs' => $clubsData,
            'total' => $clubsData->count(),
        ]);
    }
}
