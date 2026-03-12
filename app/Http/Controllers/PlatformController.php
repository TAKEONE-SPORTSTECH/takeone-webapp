<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\ClubPackage;
use App\Models\ClubMemberSubscription;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\ClubTimelinePost;
use App\Models\ClubTimelinePostLike;
use App\Models\ClubTimelinePostComment;
use App\Models\ClubPerk;
use App\Models\UserRelationship;
use App\Models\ClubTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // Only users who have explicitly opted in as personal trainers appear on the explore page
        $instructors = \App\Models\User::where('is_personal_trainer', true)
            ->with(['clubInstructors.tenant', 'clubInstructors.reviews'])
            ->get()
            ->map(function ($user) {
                $primaryInstructor = $user->clubInstructors->first();
                $allReviews        = $user->clubInstructors->flatMap->reviews;
                return [
                    'id'               => $user->id,
                    'name'             => $user->full_name ?? $user->name ?? 'Trainer',
                    'role'             => $primaryInstructor?->role ?? 'Personal Trainer',
                    'experience_years' => $user->experience_years ?? 0,
                    'bio'              => $user->bio,
                    'profile_picture'  => $user->profile_picture,
                    'rating'           => round($allReviews->avg('rating') ?? 0, 1),
                    'reviews_count'    => $allReviews->count(),
                    'club_name'        => $primaryInstructor?->tenant->club_name ?? null,
                    'url'              => route('trainer.show', $user->id),
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
            ->withCount(['members', 'packages', 'instructors'])
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

        // Member goal status breakdown
        $memberGoals = \App\Models\Goal::whereIn('user_id', $memberIds)->get()->groupBy('user_id');
        $goalStats   = ['Achieved' => 0, 'In Progress' => 0, 'Pending' => 0, 'No Goals Set' => 0];
        foreach ($memberIds as $id) {
            if (!isset($memberGoals[$id])) { $goalStats['No Goals Set']++; continue; }
            $statuses = $memberGoals[$id]->pluck('status');
            if ($statuses->contains('completed'))       $goalStats['Achieved']++;
            elseif ($statuses->contains('in_progress')) $goalStats['In Progress']++;
            else                                        $goalStats['Pending']++;
        }

        // Total members count for percentage calculations
        $totalMembers = $members->count() ?: 1;

        // Calculate access hours, days, and distinct class count from package-level schedules.
        // Each packageActivity schedule is an array of day-slots:
        // [{"day":"monday","start_time":"16:00","end_time":"17:00"}, ...]
        $allStartTimes  = [];
        $allEndTimes    = [];
        $allDays        = [];
        $distinctSlots  = [];

        foreach ($club->packages as $package) {
            foreach ($package->packageActivities as $pa) {
                $slots = is_string($pa->schedule) ? json_decode($pa->schedule, true) : $pa->schedule;
                if (!is_array($slots)) continue;
                foreach ($slots as $slot) {
                    if (!is_array($slot)) continue;
                    if (!empty($slot['start_time'])) $allStartTimes[] = $slot['start_time'];
                    if (!empty($slot['end_time']))   $allEndTimes[]   = $slot['end_time'];
                    if (!empty($slot['day']))        $allDays[]       = $slot['day'];
                    $distinctSlots[($slot['day'] ?? '') . '|' . ($slot['start_time'] ?? '') . '|' . ($slot['end_time'] ?? '')] = true;
                }
            }
        }

        $accessStat        = '24/7';
        $distinctClassCount = count($distinctSlots);

        if (!empty($allStartTimes) && !empty($allEndTimes)) {
            [$startH, $startM] = explode(':', min($allStartTimes));
            [$endH,   $endM]   = explode(':', max($allEndTimes));
            $hours      = (int) ceil(((int)$endH * 60 + (int)$endM - ((int)$startH * 60 + (int)$startM)) / 60);
            $uniqueDays = count(array_unique($allDays));
            $accessStat = $hours . 'h/' . ($uniqueDays ?: 7);
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

        $achievements = $club->achievements
            ->where('status', 'active')
            ->take(3)
            ->values();

        return view('platform.show', compact(
            'club', 'activeMembersCount', 'reviews', 'averageRating',
            'nationalityStats', 'ageGroups', 'genderStats', 'horoscopeGroups',
            'bloodTypeStats', 'monthlyTrend', 'totalMembers', 'accessStat', 'distinctClassCount',
            'goalStats', 'joinedEventIds', 'joinedEventRegistrations', 'likedPostIds', 'achievements'
        ));
    }

    public function showPublic($slug)
    {
        return $this->show($slug);
    }

    /**
     * Get all clubs for the map view.
     * If latitude and longitude are provided, calculate distance and sort by nearest.
     */
    public function all(Request $request)
    {
        $userLat = $request->input('latitude');
        $userLng = $request->input('longitude');

        $clubs = Tenant::with('owner')->withCount(['members', 'packages', 'instructors'])->get();

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
                    'members_count' => $club->members_count,
                    'packages_count' => $club->packages_count,
                    'instructors_count' => $club->instructors_count,
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
                'members_count' => $club->members_count,
                'packages_count' => $club->packages_count,
                'instructors_count' => $club->instructors_count,
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
                    'gender' => $pkg->gender,
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
            'payment_proof_base64' => 'nullable|string',
        ]);

        $user = Auth::user();
        $club = Tenant::findOrFail($request->club_id);
        $payLater = $request->boolean('pay_later');

        // Store proof of payment image if provided
        $proofPath = null;
        if (!$payLater && $request->filled('payment_proof_base64')) {
            $base64 = $request->input('payment_proof_base64');
            if (str_starts_with($base64, 'data:image')) {
                $parts    = explode(';base64,', $base64);
                $ext      = explode('image/', $parts[0])[1] ?? 'png';
                $binary   = base64_decode($parts[1]);
                $filename = 'payment-proofs/proof_' . time() . '_' . uniqid() . '.' . $ext;
                \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $binary);
                $proofPath = $filename;
            }
        }

        $paymentStatus = $payLater ? 'unpaid' : ($proofPath ? 'pending_approval' : 'unpaid');

        foreach ($request->registrants as $registrant) {
            $package = ClubPackage::findOrFail($registrant['package_id']);
            $startDate = now();
            $endDate = now()->addMonths($package->duration_months);

            // Use the specific member's user_id if provided, otherwise fall back to the guardian
            $memberId = !empty($registrant['user_id']) ? $registrant['user_id'] : $user->id;

            $subscription = ClubMemberSubscription::create([
                'tenant_id'        => $club->id,
                'type'             => 'regular',
                'user_id'          => $memberId,
                'package_id'       => $registrant['package_id'],
                'start_date'       => $startDate,
                'end_date'         => $endDate,
                'status'           => 'pending',
                'payment_status'   => $paymentStatus,
                'amount_paid'      => 0,
                'amount_due'       => $package->price,
                'proof_of_payment' => $proofPath,
                'notes'            => "Member: {$registrant['name']} ({$registrant['type']}). Registered by: {$user->name}." . ($payLater ? ' Pay later requested.' : ''),
            ]);

            ClubTransaction::create([
                'tenant_id' => $club->id,
                'user_id' => $memberId,
                'subscription_id' => $subscription->id,
                'type' => 'income',
                'category' => 'subscription',
                'amount' => $package->price,
                'payment_method' => null,
                'description' => 'Package: ' . $package->name . ' – ' . $registrant['name'],
                'transaction_date' => now(),
                'reference_number' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration submitted successfully!',
        ]);
    }

    /**
     * Collect a member exclusive perk (active members only).
     */
    public function collectPerk(Request $request, string $slug, ClubPerk $perk)
    {
        $user = Auth::user();

        // Check the user has an active subscription to this club
        $isActiveMember = ClubMemberSubscription::where('user_id', $user->id)
            ->where('tenant_id', $perk->tenant_id)
            ->where('status', 'active')
            ->exists();

        if (!$isActiveMember) {
            return response()->json([
                'success' => false,
                'members_only' => true,
                'message' => 'This perk is exclusive to active members.',
            ], 403);
        }

        return response()->json([
            'success'    => true,
            'perk_type'  => $perk->perk_type,
            'perk_value' => $perk->perk_value,
            'title'      => $perk->title,
        ]);
    }

    /**
     * Toggle like on a timeline post.
     */
    public function toggleLike(Request $request, string $slug, ClubTimelinePost $post)
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
    public function addComment(Request $request, string $slug, ClubTimelinePost $post)
    {
        $request->validate(['body' => 'required|string|max:1000']);

        $comment = ClubTimelinePostComment::create([
            'post_id' => $post->id,
            'user_id' => Auth::id(),
            'body'    => $request->body,
        ]);

        $comment->load('user');

        return response()->json([
            'id'         => $comment->id,
            'body'       => $comment->body,
            'user_name'  => $comment->user->full_name ?? $comment->user->name,
            'avatar'     => $comment->user->profile_picture
                                ? asset('storage/' . $comment->user->profile_picture)
                                : null,
            'time_ago'   => $comment->created_at->diffForHumans(),
            'is_owner'   => true,
            'delete_url' => route('clubs.timeline.comment.delete', [$slug, $post->id, $comment->id]),
        ]);
    }

    /**
     * Delete own comment from a timeline post.
     */
    public function deleteComment(Request $request, string $slug, ClubTimelinePost $post, ClubTimelinePostComment $comment)
    {
        abort_if($comment->user_id !== Auth::id(), 403);
        $comment->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Join a club event (or waitlist if full).
     */
    public function joinEvent(Request $request, string $slug, ClubEvent $event)
    {
        $user = Auth::user();

        // Already registered — do nothing
        if (ClubEventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->exists()) {
            return back()->with('info', 'You have already joined this event.');
        }

        $isFull = $event->max_capacity && $event->spots_taken >= $event->max_capacity;
        $status = $isFull ? 'waitlisted' : 'joined';

        ClubEventRegistration::create([
            'event_id'      => $event->id,
            'user_id'       => $user->id,
            'status'        => $status,
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
    public function leaveEvent(Request $request, string $slug, ClubEvent $event)
    {
        $user = Auth::user();

        $registration = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$registration) {
            return back()->with('info', 'You are not registered for this event.');
        }

        // Enforce cancellation deadline
        if ($event->cancel_within_days && $registration->registered_at) {
            $deadline = $registration->registered_at->addDays($event->cancel_within_days);
            if (now()->isAfter($deadline)) {
                return back()->with('error', 'The cancellation window for this event has closed. You can no longer leave after ' . $event->cancel_within_days . ' day(s) of joining.');
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
