<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\ClubPackage;
use App\Models\ClubMemberSubscription;
use App\Models\UserRelationship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformController extends Controller
{
    /**
     * Display the explore clubs page.
     */
    public function index()
    {
        $user = Auth::user();

        // Build family members array: guardian (self) + dependents
        $familyMembers = collect();

        // Add the logged-in user as guardian
        $familyMembers->push([
            'id' => $user->id,
            'name' => $user->full_name ?? $user->name,
            'gender' => $user->gender,
            'birthdate' => $user->birthdate?->format('Y-m-d'),
            'age' => $user->birthdate ? $user->birthdate->age : null,
            'profile_picture' => $user->profile_picture,
            'type' => 'guardian',
            'relationship' => 'Self',
        ]);

        // Add dependents (children / family members)
        $dependents = UserRelationship::where('guardian_user_id', $user->id)
            ->with('dependent')
            ->whereHas('dependent')
            ->get();

        foreach ($dependents as $rel) {
            $dep = $rel->dependent;
            $familyMembers->push([
                'id' => $dep->id,
                'name' => $dep->full_name ?? $dep->name,
                'gender' => $dep->gender,
                'birthdate' => $dep->birthdate?->format('Y-m-d'),
                'age' => $dep->birthdate ? $dep->birthdate->age : null,
                'profile_picture' => $dep->profile_picture,
                'type' => 'dependent',
                'relationship' => ucfirst($rel->relationship_type ?? 'Family'),
            ]);
        }

        $instructors = \App\Models\ClubInstructor::with(['user', 'tenant', 'reviews'])
            ->get()
            ->map(function ($instructor) {
                $user = $instructor->user;
                return [
                    'id'               => $instructor->id,
                    'name'             => $user->full_name ?? $user->name ?? 'Instructor',
                    'role'             => $instructor->role ?? 'Instructor',
                    'experience_years' => $instructor->experience_years ?? 0,
                    'bio'              => $instructor->bio,
                    'profile_picture'  => $user->profile_picture,
                    'rating'           => round($instructor->average_rating, 1),
                    'reviews_count'    => $instructor->reviews_count,
                    'club_name'        => $instructor->tenant->club_name ?? null,
                    'url'              => route('trainer.show', $instructor->id),
                ];
            });

        return view('platform.explore', compact('familyMembers', 'instructors'));
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
     * Display a specific club's details page.
     */
    public function show($slug)
    {
        $club = Tenant::with([
            'owner',
            'facilities',
            'instructors.user',
            'instructors.reviews',
            'activities.packages',
            'activities.facility',
            'packages.activities',
            'packages.packageActivities.instructor',
            'packages.packageActivities.activity',
            'galleryImages',
            'reviews.user',
            'socialLinks',
            'memberships'
        ])->where('slug', $slug)->firstOrFail();

        // Calculate active members count
        $activeMembersCount = $club->memberships()->where('status', 'active')->count();

        // Get club reviews with average rating
        $reviews = $club->reviews()->with('user')->latest()->get();
        $averageRating = $reviews->avg('rating') ?? 0;

        // Compute member statistics for the Statistics tab
        $memberIds = $club->members()->pluck('users.id');
        $members = \App\Models\User::whereIn('id', $memberIds)->get();

        // Nationality breakdown
        $nationalityStats = $members->groupBy('nationality')
            ->map(fn($group) => $group->count())
            ->sortDesc()
            ->take(4);

        // Age group breakdown
        $ageGroups = ['Kids (5-10)' => 0, 'Juniors (11-15)' => 0, 'Youth (16-21)' => 0, 'Adults (22+)' => 0];
        foreach ($members as $member) {
            if (!$member->birthdate) continue;
            $age = $member->birthdate->age;
            if ($age >= 5 && $age <= 10) $ageGroups['Kids (5-10)']++;
            elseif ($age >= 11 && $age <= 15) $ageGroups['Juniors (11-15)']++;
            elseif ($age >= 16 && $age <= 21) $ageGroups['Youth (16-21)']++;
            elseif ($age >= 22) $ageGroups['Adults (22+)']++;
        }

        // Gender breakdown
        $genderStats = $members->groupBy('gender')
            ->map(fn($group) => $group->count());

        // Horoscope breakdown
        $horoscopeGroups = ['Fire' => 0, 'Earth' => 0, 'Air' => 0, 'Water' => 0];
        $fireSigns = ['Aries', 'Leo', 'Sagittarius'];
        $earthSigns = ['Taurus', 'Virgo', 'Capricorn'];
        $airSigns = ['Gemini', 'Libra', 'Aquarius'];
        $waterSigns = ['Cancer', 'Scorpio', 'Pisces'];
        foreach ($members as $member) {
            $sign = $member->horoscope;
            if (!$sign) continue;
            if (in_array($sign, $fireSigns)) $horoscopeGroups['Fire']++;
            elseif (in_array($sign, $earthSigns)) $horoscopeGroups['Earth']++;
            elseif (in_array($sign, $airSigns)) $horoscopeGroups['Air']++;
            elseif (in_array($sign, $waterSigns)) $horoscopeGroups['Water']++;
        }

        // Blood type breakdown
        $bloodTypeStats = $members->groupBy('blood_type')
            ->map(fn($group) => $group->count())
            ->filter(fn($_, $key) => !empty($key));

        // Monthly active members trend (last 12 months)
        $monthlyTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyTrend[$date->format('M')] = $club->memberships()
                ->where('created_at', '<=', $date->endOfMonth())
                ->where(function ($q) use ($date) {
                    $q->where('status', 'active')
                      ->orWhere('updated_at', '>=', $date->startOfMonth());
                })
                ->count();
        }

        // Total members count for percentage calculations
        $totalMembers = $members->count() ?: 1;

        return view('platform.show', compact(
            'club', 'activeMembersCount', 'reviews', 'averageRating',
            'nationalityStats', 'ageGroups', 'genderStats', 'horoscopeGroups',
            'bloodTypeStats', 'monthlyTrend', 'totalMembers'
        ));
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

    /**
     * Get club packages as JSON for the join modal.
     */
    public function clubPackages($slug)
    {
        $club = Tenant::where('slug', $slug)->firstOrFail();
        $packages = ClubPackage::where('tenant_id', $club->id)->get();

        return response()->json([
            'packages' => $packages->map(function ($pkg) {
                return [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'price' => $pkg->price,
                    'duration_months' => $pkg->duration_months,
                    'activity_type' => $pkg->activity_type ?? null,
                    'age_min' => $pkg->age_min,
                    'age_max' => $pkg->age_max,
                    'gender_restriction' => $pkg->gender_restriction,
                ];
            }),
            'currency' => $club->currency ?? 'USD',
            'enrollment_fee' => $club->enrollment_fee ?? 0,
        ]);
    }

    /**
     * Handle club join/registration from the explore page.
     */
    public function joinClub(Request $request)
    {
        $request->validate([
            'club_id' => 'required|exists:tenants,id',
            'registrants' => 'required|array|min:1',
            'registrants.*.type' => 'required|in:self,child',
            'registrants.*.name' => 'required|string|max:255',
            'registrants.*.user_id' => 'nullable',
            'registrants.*.package_id' => 'required|exists:club_packages,id',
            'registrants.*.gender' => 'nullable|string',
            'registrants.*.date_of_birth' => 'nullable|date',
            'pay_later' => 'nullable',
            'payment_screenshot' => 'nullable|image|max:5120',
        ]);

        $user = Auth::user();
        $club = Tenant::findOrFail($request->club_id);
        $payLater = $request->boolean('pay_later');

        $paymentNotes = '';
        if ($request->hasFile('payment_screenshot')) {
            $path = $request->file('payment_screenshot')->store('payment-screenshots', 'public');
            $paymentNotes = 'Payment screenshot: ' . $path;
        }

        foreach ($request->registrants as $registrant) {
            $package = ClubPackage::findOrFail($registrant['package_id']);
            $startDate = now();
            $endDate = now()->addMonths($package->duration_months);

            // Use the specific member's user_id if provided, otherwise fall back to the guardian
            $memberId = !empty($registrant['user_id']) ? $registrant['user_id'] : $user->id;

            ClubMemberSubscription::create([
                'tenant_id' => $club->id,
                'user_id' => $memberId,
                'package_id' => $registrant['package_id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'pending',
                'payment_status' => $payLater ? 'unpaid' : 'unpaid',
                'amount_paid' => 0,
                'amount_due' => $package->price,
                'notes' => "Member: {$registrant['name']} ({$registrant['type']}). Registered by: {$user->name}. " . ($paymentNotes ?: ($payLater ? 'Pay later requested.' : '')),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration submitted successfully!',
        ]);
    }
}
