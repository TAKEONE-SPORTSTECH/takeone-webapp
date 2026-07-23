<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCommentRequest;
use App\Http\Requests\JoinClubRequest;
use App\Http\Requests\NearbyClubsRequest;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\ClubPerk;
use App\Models\ClubTimelinePost;
use App\Models\ClubTimelinePostComment;
use App\Models\ClubTimelinePostLike;
use App\Models\Membership;
use App\Models\PerkCollection;
use App\Models\Tenant;
use App\Models\UserRelationship;
use App\Services\RegistrationCostService;
use App\Services\SubscriptionService;
use App\Support\ClubCache;
use App\Traits\HandlesClubAuthorization;
use App\Traits\StoresBase64Images;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlatformController extends Controller
{
    use HandlesClubAuthorization;
    use StoresBase64Images;

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

        // Only users who have explicitly opted in as personal trainers appear on the explore page
        $instructors = \App\Models\User::where('is_personal_trainer', true)
            ->with(['clubInstructors.tenant', 'clubInstructors.reviews'])
            ->get()
            ->map(function ($user) {
                $primaryInstructor = $user->clubInstructors->first();
                $allReviews = $user->clubInstructors->flatMap->reviews;

                return [
                    'id' => $user->id,
                    'name' => $user->full_name ?? $user->name ?? 'Trainer',
                    'role' => $primaryInstructor?->role ?? 'Personal Trainer',
                    'experience_years' => $user->experience_years ?? 0,
                    'bio' => $user->bio,
                    'profile_picture' => $user->profile_picture,
                    'rating' => round($allReviews->avg('rating') ?? 0, 1),
                    'reviews_count' => $allReviews->count(),
                    'club_name' => $primaryInstructor?->tenant->club_name ?? null,
                    'url' => route('trainer.show', $user->id),
                ];
            });

        $isMobile = request()->attributes->get('is_mobile', false);

        // The mobile shell reads $shellTitle to label its header for pages that
        // sit outside the bottom-nav route list (explore is one).
        return view($isMobile ? 'platform.mobile.explore' : 'platform.explore', compact('familyMembers', 'instructors'))
            ->with('shellTitle', __('explore.explore'));
    }

    /**
     * Get nearby clubs based on user's location.
     */
    public function nearby(NearbyClubsRequest $request)
    {

        $userLat = $request->latitude;
        $userLng = $request->longitude;
        $radius = $request->radius ?? 50; // default 50km radius

        // Get all clubs with GPS coordinates
        $clubs = Tenant::whereNotNull('gps_lat')
            ->whereNotNull('gps_long')
            ->with('owner')
            ->withCount(['members', 'packages', 'instructors', 'approvedReviews as reviews_count'])
            ->withAvg('approvedReviews as rating', 'rating')
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
                'members_count' => $club->members_count,
                'packages_count' => $club->packages_count,
                'instructors_count' => $club->instructors_count,
                'rating' => round((float) ($club->rating ?? 0), 1),
                'reviews_count' => (int) $club->reviews_count,
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
    public function show(string $country, string $slug)
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
            'memberships',
            'events',
            'timelinePosts.likes',
            'timelinePosts.comments.user',
            'perks',
            'achievements',
        ])->where('slug', $slug)->firstOrFail();

        // Calculate active members count
        $activeMembersCount = $club->memberships()->where('status', 'active')->count();

        // Get club reviews with average rating
        $reviews = $club->reviews()->with('user')->latest()->get();
        $averageRating = $reviews->avg('rating') ?? 0;

        // Compute member statistics for the Statistics tab — cached for 1 hour.
        $memberStats = Cache::remember(ClubCache::showStats($club->id), ClubCache::TTL_STATS, function () use ($club) {
            $memberIds = $club->members()->pluck('users.id');
            $members = \App\Models\User::whereIn('id', $memberIds)->get();

            // Nationality breakdown — map ISO-2 codes to full country names
            static $countryNames = null;
            if ($countryNames === null) {
                $raw = json_decode(file_get_contents(public_path('data/countries.json')), true) ?? [];
                $countryNames = collect($raw)->pluck('name', 'iso2')->all(); // ['BH' => 'Bahrain', ...]
            }

            $nationalityStats = $members->groupBy('nationality')
                ->map(fn ($group) => $group->count())
                ->sortDesc()
                ->take(4)
                ->mapWithKeys(fn ($count, $code) => [($countryNames[$code] ?? ($code ?: 'Unknown')) => $count]);

            // Age group breakdown
            $ageGroups = ['Kids (5-10)' => 0, 'Juniors (11-15)' => 0, 'Youth (16-21)' => 0, 'Adults (22+)' => 0];
            foreach ($members as $member) {
                if (! $member->birthdate) {
                    continue;
                }
                $age = $member->birthdate->age;
                if ($age >= 5 && $age <= 10) {
                    $ageGroups['Kids (5-10)']++;
                } elseif ($age >= 11 && $age <= 15) {
                    $ageGroups['Juniors (11-15)']++;
                } elseif ($age >= 16 && $age <= 21) {
                    $ageGroups['Youth (16-21)']++;
                } elseif ($age >= 22) {
                    $ageGroups['Adults (22+)']++;
                }
            }

            // Gender breakdown
            $genderStats = $members->groupBy('gender')->map(fn ($group) => $group->count());

            // Horoscope breakdown — individual signs in calendar order
            $horoscopeGroups = array_fill_keys(
                ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'],
                0
            );
            foreach ($members as $member) {
                $sign = $member->horoscope;
                if ($sign && isset($horoscopeGroups[$sign])) {
                    $horoscopeGroups[$sign]++;
                }
            }

            // Blood type breakdown
            $bloodTypeStats = $members->groupBy('blood_type')
                ->map(fn ($group) => $group->count())
                ->filter(fn ($_, $key) => ! empty($key));

            // Member goal status breakdown
            $memberGoals = \App\Models\Goal::whereIn('user_id', $memberIds)->get()->groupBy('user_id');
            $goalStats = ['Achieved' => 0, 'In Progress' => 0, 'Pending' => 0, 'No Goals Set' => 0];
            foreach ($memberIds as $id) {
                if (! isset($memberGoals[$id])) {
                    $goalStats['No Goals Set']++;

                    continue;
                }
                $statuses = $memberGoals[$id]->pluck('status');
                if ($statuses->contains('completed')) {
                    $goalStats['Achieved']++;
                } elseif ($statuses->contains('in_progress')) {
                    $goalStats['In Progress']++;
                } else {
                    $goalStats['Pending']++;
                }
            }

            return compact('nationalityStats', 'ageGroups', 'genderStats', 'horoscopeGroups', 'bloodTypeStats', 'goalStats')
                + ['totalMembers' => $members->count() ?: 1];
        });

        [
            'nationalityStats' => $nationalityStats,
            'ageGroups' => $ageGroups,
            'genderStats' => $genderStats,
            'horoscopeGroups' => $horoscopeGroups,
            'bloodTypeStats' => $bloodTypeStats,
            'goalStats' => $goalStats,
            'totalMembers' => $totalMembers,
        ] = $memberStats;

        // Monthly new members — count of members who joined in each of the last 12 months.
        $monthlyTrend = Cache::remember(ClubCache::showMonthlyTrend($club->id), ClubCache::TTL_STATS, function () use ($club) {
            $trend = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $start = $month->copy()->startOfMonth();
                $end = $month->copy()->endOfMonth();

                $trend[$month->format('M Y')] = $club->memberships()
                    ->whereBetween('created_at', [$start, $end])
                    ->count();
            }

            return $trend;
        });

        // Calculate access hours, days, and distinct class count from package-level schedules.
        // Each packageActivity schedule is an array of day-slots:
        // [{"day":"monday","start_time":"16:00","end_time":"17:00"}, ...]
        $allStartTimes = [];
        $allEndTimes = [];
        $allDays = [];
        $distinctSlots = [];

        foreach ($club->packages as $package) {
            foreach ($package->packageActivities as $pa) {
                try {
                    $slots = is_string($pa->schedule) ? json_decode($pa->schedule, true, 512, JSON_THROW_ON_ERROR) : $pa->schedule;
                } catch (\JsonException) {
                    continue;
                }
                if (! is_array($slots)) {
                    continue;
                }
                foreach ($slots as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }
                    if (! empty($slot['start_time'])) {
                        $allStartTimes[] = $slot['start_time'];
                    }
                    if (! empty($slot['end_time'])) {
                        $allEndTimes[] = $slot['end_time'];
                    }
                    if (! empty($slot['day'])) {
                        $allDays[] = $slot['day'];
                    }
                    $distinctSlots[($slot['day'] ?? '').'|'.($slot['start_time'] ?? '').'|'.($slot['end_time'] ?? '')] = true;
                }
            }
        }

        $accessStat = '24/7';
        $distinctClassCount = count($distinctSlots);

        if (! empty($allStartTimes) && ! empty($allEndTimes)) {
            [$startH, $startM] = explode(':', min($allStartTimes));
            [$endH,   $endM] = explode(':', max($allEndTimes));
            $hours = (int) ceil(((int) $endH * 60 + (int) $endM - ((int) $startH * 60 + (int) $startM)) / 60);
            $uniqueDays = count(array_unique($allDays));
            $accessStat = $hours.'h/'.($uniqueDays ?: 7);
        }

        // Events this user has joined in this club (empty set for guests)
        $joinedEventIds = [];
        $joinedEventRegistrations = []; // event_id => registered_at (Carbon)
        // IDs of timeline posts this user has liked in this club
        $likedPostIds = [];
        if (Auth::check()) {
            $eventIds = $club->events->pluck('id');
            $regs = ClubEventRegistration::where('user_id', Auth::id())
                ->whereIn('event_id', $eventIds)
                ->get(['event_id', 'registered_at']);
            $joinedEventIds = $regs->pluck('event_id')->toArray();
            $joinedEventRegistrations = $regs->keyBy('event_id')->toArray();

            $postIds = $club->timelinePosts->pluck('id');
            $likedPostIds = ClubTimelinePostLike::where('user_id', Auth::id())
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->toArray();
        }

        $activeAchievements = $club->achievements->where('status', 'active')->values();

        // Desktop "Latest Achievements" keeps its 3-up grid.
        $achievements = $activeAchievements->take(3)->values();

        // Mobile shows a swipeable slider of the latest 5 with a "view more" to reveal the
        // rest (capped to keep the inline payload reasonable).
        $achievementsAll = $activeAchievements->take(24)->values();

        // Build family members array (same as explore page)
        $familyMembers = collect();
        if (Auth::check()) {
            $user = Auth::user();
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
        }

        // Mark which family members are already enrolled in this club
        $familyIds = $familyMembers->pluck('id')->toArray();
        $existingMemberIds = Membership::where('tenant_id', $club->id)
            ->whereIn('user_id', $familyIds)
            ->pluck('user_id')
            ->toArray();
        $familyMembers = $familyMembers->map(function ($m) use ($existingMemberIds) {
            $m['is_member'] = in_array($m['id'], $existingMemberIds);

            return $m;
        });

        // Per-package applicability: a package is "applicable" if at least one of
        // me / my family members fits its age + gender rules. Used to disable the
        // non-applicable ones and sort the applicable ones first.
        $eligibleFor = function ($pkg, array $m): bool {
            $age = $m['age'] ?? null;
            if ($pkg->age_min !== null && $age !== null && $age < $pkg->age_min) {
                return false;
            }
            if ($pkg->age_max !== null && $age !== null && $age > $pkg->age_max) {
                return false;
            }
            $g = $pkg->gender;
            if ($g && $g !== 'mixed' && ! empty($m['gender'])) {
                if ($g === 'male' && $m['gender'] !== 'Male') {
                    return false;
                }
                if ($g === 'female' && $m['gender'] !== 'Female') {
                    return false;
                }
            }

            return true;
        };

        // Only gate when we actually know the viewer's family (logged-in). Guests
        // keep every package enabled (they'll be routed to register on join).
        $gateApplicability = Auth::check() && $familyMembers->isNotEmpty();

        foreach ($club->packages as $pkg) {
            $eligible = $gateApplicability
                ? $familyMembers->filter(fn ($m) => $eligibleFor($pkg, $m))
                : $familyMembers;
            $pkg->setAttribute('is_applicable', ! $gateApplicability || $eligible->isNotEmpty());
            $pkg->setAttribute('eligible_names', $eligible->pluck('name')->values()->all());
        }

        // Applicable packages first (stable within each group).
        $club->setRelation('packages', $club->packages->sortByDesc('is_applicable')->values());

        // Whether the current viewer may manage this club (owner / club-admin /
        // super-admin / chain owner) — gates the inline owner controls on the page.
        $canManage = $this->canManageClub($club);

        $isMobile = request()->attributes->get('is_mobile', false);
        $showView = $isMobile && view()->exists('platform.mobile.show') ? 'platform.mobile.show' : 'platform.show';

        return view($showView, compact(
            'club', 'activeMembersCount', 'reviews', 'averageRating',
            'nationalityStats', 'ageGroups', 'genderStats', 'horoscopeGroups',
            'bloodTypeStats', 'monthlyTrend', 'totalMembers', 'accessStat', 'distinctClassCount',
            'goalStats', 'joinedEventIds', 'joinedEventRegistrations', 'likedPostIds', 'achievements',
            'achievementsAll', 'familyMembers', 'canManage'
        ));
    }

    public function showPublic($country, $slug)
    {
        return $this->show($country, $slug);
    }

    /**
     * Get all clubs for the map view.
     * If latitude and longitude are provided, calculate distance and sort by nearest.
     */
    public function all(Request $request)
    {
        $userLat = $request->input('latitude');
        $userLng = $request->input('longitude');

        $clubs = Tenant::with('owner')
            ->withCount(['members', 'packages', 'instructors', 'approvedReviews as reviews_count'])
            ->withAvg('approvedReviews as rating', 'rating')
            ->get();

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
                    'country_code' => $club->country_code,
                    'url' => $club->url,
                    'logo' => $club->logo,
                    'cover_image' => $club->cover_image,
                    'gps_lat' => $club->gps_lat ? (float) $club->gps_lat : null,
                    'gps_long' => $club->gps_long ? (float) $club->gps_long : null,
                    'distance' => $distance !== null ? round($distance, 2) : null,
                    'owner_name' => $club->owner ? $club->owner->full_name : 'N/A',
                    'address' => $club->address,
                    'members_count' => $club->members_count,
                    'packages_count' => $club->packages_count,
                    'instructors_count' => $club->instructors_count,
                    'rating' => round((float) ($club->rating ?? 0), 1),
                    'reviews_count' => (int) $club->reviews_count,
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
                'country_code' => $club->country_code,
                'url' => $club->url,
                'logo' => $club->logo,
                'cover_image' => $club->cover_image,
                'gps_lat' => $club->gps_lat ? (float) $club->gps_lat : null,
                'gps_long' => $club->gps_long ? (float) $club->gps_long : null,
                'distance' => null,
                'owner_name' => $club->owner ? $club->owner->full_name : 'N/A',
                'address' => $club->address,
                'members_count' => $club->members_count,
                'packages_count' => $club->packages_count,
                'instructors_count' => $club->instructors_count,
                'rating' => round((float) ($club->rating ?? 0), 1),
                'reviews_count' => (int) $club->reviews_count,
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
    public function clubPackages(string $country, string $slug, RegistrationCostService $costSvc)
    {
        $club = Tenant::where('slug', $slug)->firstOrFail();
        $packages = ClubPackage::where('tenant_id', $club->id)
            ->with('packageActivities.instructor.user')
            ->get();

        // Attach the activity-scoped equipment catalog to each package (no per-user
        // ownership baked in — ownership is resolved per registrant client-side via
        // the `owned` map below, since the join flow enrols several family members).
        $costSvc->attachEquipmentToPackages($packages, $club->id, null);

        // Per-member equipment ownership for the guardian + their dependents, so the
        // client can pre-untick gear each registrant already has.
        $owned = [];
        if (Auth::check()) {
            $memberIds = collect([Auth::id()])
                ->merge(UserRelationship::where('guardian_user_id', Auth::id())->pluck('dependent_user_id'))
                ->unique()->values();
            foreach ($memberIds as $mid) {
                $owned[$mid] = [
                    'products' => $costSvc->ownedProductIds($club->id, $mid),
                    'variants' => $costSvc->ownedVariantIds($club->id, $mid),
                ];
            }
        }

        return response()->json([
            'packages' => $packages->map(function ($pkg) {
                return [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'price' => $pkg->price,
                    'registration_fee' => $pkg->registration_fee,
                    'duration_months' => $pkg->duration_months,
                    'activity_type' => $pkg->activity_type ?? null,
                    'age_min' => $pkg->age_min,
                    'age_max' => $pkg->age_max,
                    'gender' => $pkg->gender,
                    'equipment' => $pkg->equipment ?? [],
                    'schedules' => collect($pkg->packageActivities)->map(function ($pa) {
                        $slots = is_string($pa->schedule) ? json_decode($pa->schedule, true) : $pa->schedule;
                        if (! is_array($slots) || empty($slots)) {
                            return null;
                        }
                        $days = collect($slots)->pluck('day')->map(fn ($d) => ucfirst($d))->unique()->join(', ');
                        $times = collect($slots)->map(fn ($s) => ($s['start_time'] ?? '').' – '.($s['end_time'] ?? ''))->first();

                        return ['days' => $days, 'time' => $times];
                    })->filter()->values(),
                    'instructors' => collect($pkg->packageActivities)->map(function ($pa) {
                        if (! $pa->instructor?->user) {
                            return null;
                        }

                        return [
                            'name' => $pa->instructor->user->full_name ?? $pa->instructor->user->name,
                            'image_url' => $pa->instructor->user->profile_picture ? asset('storage/'.$pa->instructor->user->profile_picture) : null,
                        ];
                    })->filter()->unique('name')->values(),
                ];
            }),
            'currency' => $club->currency ?? 'USD',
            'registration_fee' => $club->registration_fee ?? 0,
            'enrollment_fee' => $club->enrollment_fee ?? 0,
            'owned' => $owned,
        ]);
    }

    /**
     * Handle club join/registration from the explore page.
     */
    public function joinClub(string $country, JoinClubRequest $request, SubscriptionService $subscriptions)
    {
        $user = Auth::user();
        $club = Tenant::findOrFail($request->club_id);
        $payLater = $request->boolean('pay_later');

        // Store proof of payment image if provided (private disk — not publicly accessible)
        $proofPath = null;
        if (! $payLater && $request->filled('payment_proof_base64')) {
            $proofPath = $this->storeBase64Image(
                $request->input('payment_proof_base64'),
                'payment-proofs',
                'proof_'.time().'_'.uniqid(),
                'local'
            );
        }

        $paymentStatus = $payLater ? 'unpaid' : ($proofPath ? 'pending_approval' : 'unpaid');

        // Validate eligibility for all registrants before creating any subscriptions
        foreach ($request->registrants as $registrant) {
            $package = ClubPackage::where('id', $registrant['package_id'])->where('tenant_id', $club->id)->firstOrFail();
            $memberId = ! empty($registrant['user_id']) ? $registrant['user_id'] : $user->id;
            $age = ! empty($registrant['date_of_birth']) ? \Carbon\Carbon::parse($registrant['date_of_birth'])->age : null;
            $gender = $registrant['gender'] ?? null;

            $error = $subscriptions->checkEligibility($package, $registrant['name'], $age, $gender);
            if ($error) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }

            if ($subscriptions->isDuplicate($club->id, $memberId, $package->id)) {
                return response()->json([
                    'success' => false,
                    'message' => "'{$registrant['name']}' already has an active or pending subscription for '{$package->name}'.",
                ], 422);
            }
        }

        // Identify which registrants are joining the club for the first time
        $registrantIds = collect($request->registrants)
            ->map(fn ($r) => ! empty($r['user_id']) ? $r['user_id'] : $user->id)
            ->unique()->toArray();
        $existingMemberIds = Membership::where('tenant_id', $club->id)
            ->whereIn('user_id', $registrantIds)
            ->pluck('user_id')->toArray();
        $chargedJoiningIds = [];

        $costSvc = app(RegistrationCostService::class);

        foreach ($request->registrants as $registrant) {
            $package = ClubPackage::findOrFail($registrant['package_id']);
            $memberId = ! empty($registrant['user_id']) ? $registrant['user_id'] : $user->id;
            $notes = "Member: {$registrant['name']} ({$registrant['type']}). Registered by: {$user->name}.".($payLater ? ' Pay later requested.' : '');

            $subscription = $subscriptions->createPending($club, $memberId, $package, $paymentStatus, $proofPath, $notes);

            $extra = 0.0;

            // One-time registration fee — charged once per member on their
            // first-time join. The package price itself is the member's enrollment.
            if (! in_array($memberId, $existingMemberIds) && ! in_array($memberId, $chargedJoiningIds)) {
                $regFee = $costSvc->effectiveRegistrationFee($package, $club);
                if ($regFee > 0) {
                    $subscription->update(['registration_fee' => $regFee]);
                    $costSvc->recordRegistrationFee($club, $memberId, $subscription, $regFee, $registrant['name']);
                    $extra += $regFee;
                }
                $chargedJoiningIds[] = $memberId;
            }

            // Equipment — frozen accounting lines + ownership memory (pending until
            // the payment is approved). Gear ticked "I already have it" is recorded
            // as owned and never billed.
            $chargedGear = array_map('intval', $registrant['equipment'] ?? []);
            $extra += $costSvc->snapshotEquipment(
                $club, $memberId, $subscription, $chargedGear, 'pending',
                recordIncome: true, variantMap: $registrant['variants'] ?? []
            );
            $ownedGear = array_values(array_diff(array_map('intval', $registrant['owned_equipment'] ?? []), $chargedGear));
            $costSvc->recordOwnedEquipment($club, $memberId, $subscription, $ownedGear);

            // Fold the joining fees + equipment onto what this subscription owes.
            if ($extra > 0) {
                $subscription->update(['amount_due' => (float) $subscription->amount_due + $extra]);
            }
        }

        activity('membership')
            ->causedBy($user)
            ->performedOn($club)
            ->withProperties(['registrants' => count($request->registrants), 'pay_later' => $payLater])
            ->log('Club join registration submitted');

        // Tell the club owner + staff a new registration came in (bell + MQTT + push
        // + a persistent center-screen popup that stays until they act on it).
        $count = count($request->registrants);
        $primaryName = $request->registrants[0]['name'] ?? $user->name;
        $who = $count === 1 ? $primaryName : $primaryName.' (+'.($count - 1).' more)';

        // Land the admin ON the payment to verify, not on a generic list: financials
        // focused on this registrant's outstanding row (the desktop ledger filters to
        // pending; #collect opens the mobile panel).
        $focusUserId = (int) ($registrantIds[0] ?? $user->id);
        $focusUuid = \App\Models\User::whereKey($focusUserId)->value('uuid');
        $reviewUrl = route('admin.club.financials', $club->slug)
            .($focusUuid ? '?member='.$focusUuid : '').'#collect';

        foreach ($club->staffUserIds() as $staffId) {
            \App\Models\UserNotification::notifyUser($staffId, 'new_member', 'New member registration', [
                'actor_id'     => $user->id,
                'tenant_id'    => $club->id,
                'subject_type' => 'user',
                'subject_id'   => $focusUserId,
                'action_url'   => $reviewUrl,
                'icon'         => 'bi-person-plus-fill',
                'context'      => $club->club_name,
                'body'         => $who.' registered at '.$club->club_name.'. Review the pending payment to approve.',
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Registration submitted successfully!']);
    }

    /**
     * Collect a member exclusive perk (active members only).
     */
    public function collectPerk(string $country, Request $request, string $slug, ClubPerk $perk)
    {
        $user = Auth::user();

        // Build list of eligible members: the user + their dependents who have an active subscription to this club
        $eligibleIds = ClubMemberSubscription::where('tenant_id', $perk->tenant_id)
            ->whereIn('status', ['active', 'pending'])
            ->pluck('user_id')
            ->toArray();

        $familyIds = UserRelationship::where('guardian_user_id', $user->id)
            ->pluck('dependent_user_id')
            ->toArray();

        // Members the logged-in user can collect for: themselves + their dependents — filtered to active subscribers
        $canCollectFor = array_values(array_filter(
            array_unique(array_merge([$user->id], $familyIds)),
            fn ($id) => in_array($id, $eligibleIds)
        ));

        if (empty($canCollectFor)) {
            return response()->json([
                'success' => false,
                'members_only' => true,
                'message' => 'This perk is exclusive to active members of this club.',
            ], 403);
        }

        // If a specific beneficiary was provided, validate it
        $forUserId = $request->input('for_user_id');
        if ($forUserId !== null) {
            $forUserId = (int) $forUserId;
            if (! in_array($forUserId, $canCollectFor)) {
                return response()->json(['success' => false, 'message' => 'Invalid selection.'], 422);
            }
        }

        // Multiple eligible members and no selection yet — return picker data
        if ($forUserId === null && count($canCollectFor) > 1) {
            $members = \App\Models\User::whereIn('id', $canCollectFor)->get(['id', 'full_name', 'name', 'profile_picture']);
            $collected = PerkCollection::where('perk_id', $perk->id)
                ->whereIn('collected_for_user_id', $canCollectFor)
                ->pluck('collected_at', 'collected_for_user_id')
                ->toArray();

            $memberList = $members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->full_name ?? $m->name,
                'profile_picture' => $m->profile_picture,
                'already_collected' => isset($collected[$m->id]),
            ])->values()->toArray();

            return response()->json([
                'success' => false,
                'requires_selection' => true,
                'members' => $memberList,
            ]);
        }

        // Single eligible member — auto-select
        if ($forUserId === null) {
            $forUserId = $canCollectFor[0];
        }

        // Check if already collected for this beneficiary
        $existing = PerkCollection::where('perk_id', $perk->id)
            ->where('collected_for_user_id', $forUserId)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'already_collected' => true,
                'message' => 'This perk has already been collected.',
                'collected_at' => $existing->created_at->format('M d, Y'),
            ]);
        }

        // Record the collection
        PerkCollection::create([
            'perk_id' => $perk->id,
            'tenant_id' => $perk->tenant_id,
            'collected_by_user_id' => $user->id,
            'collected_for_user_id' => $forUserId,
        ]);

        return response()->json([
            'success' => true,
            'perk_type' => $perk->perk_type,
            'perk_value' => $perk->perk_value,
            'title' => $perk->tr('title'),
        ]);
    }

    /**
     * Toggle like on a timeline post.
     */
    public function toggleLike(string $country, Request $request, string $slug, ClubTimelinePost $post)
    {
        $userId = Auth::id();
        $existing = ClubTimelinePostLike::where('post_id', $post->id)->where('user_id', $userId)->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            ClubTimelinePostLike::create(['post_id' => $post->id, 'user_id' => $userId]);
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'count' => ClubTimelinePostLike::where('post_id', $post->id)->count(),
        ]);
    }

    /**
     * Add a comment to a timeline post.
     */
    public function addComment(string $country, AddCommentRequest $request, string $slug, ClubTimelinePost $post)
    {

        $comment = ClubTimelinePostComment::create([
            'post_id' => $post->id,
            'user_id' => Auth::id(),
            'body' => $request->body,
        ]);

        $comment->load('user');

        return response()->json([
            'id' => $comment->id,
            'body' => $comment->body,
            'user_name' => $comment->user->full_name ?? $comment->user->name,
            'avatar' => $comment->user->profile_picture
                                ? asset('storage/'.$comment->user->profile_picture)
                                : null,
            'time_ago' => $comment->created_at->diffForHumans(),
            'is_owner' => true,
            'delete_url' => route('clubs.timeline.comment.delete', [$country, $slug, $post->id, $comment->id]),
        ]);
    }

    /**
     * Delete own comment from a timeline post.
     */
    public function deleteComment(string $country, Request $request, string $slug, ClubTimelinePost $post, ClubTimelinePostComment $comment)
    {
        abort_if($comment->user_id !== Auth::id(), 403);
        $comment->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Join a club event (or waitlist if full).
     */
    public function joinEvent(string $country, Request $request, string $slug, ClubEvent $event)
    {
        $user = Auth::user();

        // Already registered — do nothing
        if (ClubEventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->exists()) {
            return back()->with('info', 'You have already joined this event.');
        }

        $isFull = $event->max_capacity && $event->spots_taken >= $event->max_capacity;
        $status = $isFull ? 'waitlisted' : 'joined';

        ClubEventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => $status,
            'registered_at' => now(),
        ]);

        if ($status === 'joined') {
            DB::table('club_events')->where('id', $event->id)->increment('spots_taken');
        }

        $msg = $status === 'joined' ? 'You have joined the event!' : 'You have been added to the waitlist.';

        return back()->with('success', $msg);
    }

    /**
     * Leave a club event (or remove from waitlist).
     */
    public function leaveEvent(string $country, Request $request, string $slug, ClubEvent $event)
    {
        $user = Auth::user();

        $registration = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $registration) {
            return back()->with('info', 'You are not registered for this event.');
        }

        // Enforce cancellation deadline
        if ($event->cancel_within_days && $registration->registered_at) {
            $deadline = $registration->registered_at->addDays($event->cancel_within_days);
            if (now()->isAfter($deadline)) {
                return back()->with('error', 'The cancellation window for this event has closed. You can no longer leave after '.$event->cancel_within_days.' day(s) of joining.');
            }
        }

        $wasJoined = $registration->status === 'joined';
        $registration->delete();

        if ($wasJoined && $event->spots_taken > 0) {
            DB::table('club_events')->where('id', $event->id)->decrement('spots_taken');
        }

        return back()->with('success', 'You have left the event.');
    }
}
