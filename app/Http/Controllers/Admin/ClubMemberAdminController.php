<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMembersRequest;
use App\Http\Requests\Admin\WalkInRegistrationRequest;
use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\ClubTransaction;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelationship;
use App\Services\FinancialService;
use App\Services\SubscriptionService;
use App\Traits\HandlesClubAuthorization;
use App\Traits\StoresBase64Images;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClubMemberAdminController extends Controller
{
    use HandlesClubAuthorization, StoresBase64Images;

    public function members(Tenant $club, Request $request)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $filter = $request->input('filter', 'active');

        // Enrolled user IDs — owners OR members with active/pending subscriptions
        $enrolledUserIds = ClubMemberSubscription::where('tenant_id', $clubId)
            ->whereIn('user_id', function ($q) use ($clubId) {
                $q->select('user_id')->from('memberships')
                  ->where('tenant_id', $clubId)->where('status', 'active');
            })
            ->where(fn ($q) => $q->where('type', 'owner')
                ->orWhere(fn ($q2) => $q2->where('type', 'regular')->whereIn('status', ['active', 'pending']))
            )
            ->pluck('user_id')
            ->unique();

        // All counts in one query
        $counts = DB::table('memberships')
            ->where('tenant_id', $clubId)
            ->selectRaw("
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as all_active,
                SUM(CASE WHEN status = 'former' THEN 1 ELSE 0 END) as former_count
            ")
            ->first();

        $allCount       = (int) ($counts->all_active ?? 0);
        $formerCount    = (int) ($counts->former_count ?? 0);
        $activeCount    = Membership::where('tenant_id', $clubId)
                            ->where('status', 'active')
                            ->whereIn('user_id', $enrolledUserIds)
                            ->count();
        $notActiveCount = $allCount - $activeCount;
        $statusCounts   = ['all' => $allCount, 'active' => $activeCount, 'not_active' => $notActiveCount];

        // Demographics — gender + birthdate only
        $activeUsers = DB::table('memberships as m')
            ->join('users as u', 'm.user_id', '=', 'u.id')
            ->where('m.tenant_id', $clubId)->where('m.status', 'active')
            ->select('u.gender', 'u.birthdate')
            ->get();

        $maleCount   = $activeUsers->filter(fn ($u) => strtolower($u->gender ?? '') === 'male')->count();
        $femaleCount = $activeUsers->filter(fn ($u) => strtolower($u->gender ?? '') === 'female')->count();

        $ageGroupCounts = ['Kids' => 0, 'Cadet' => 0, 'Junior' => 0, 'Senior' => 0, 'Masters' => 0];
        foreach ($activeUsers as $u) {
            if (!$u->birthdate) continue;
            $age = \Carbon\Carbon::parse($u->birthdate)->age;
            if      ($age >= 6  && $age < 12) $ageGroupCounts['Kids']++;
            elseif  ($age >= 12 && $age < 15) $ageGroupCounts['Cadet']++;
            elseif  ($age >= 15 && $age < 18) $ageGroupCounts['Junior']++;
            elseif  ($age >= 18 && $age < 31) $ageGroupCounts['Senior']++;
            elseif  ($age >= 31)              $ageGroupCounts['Masters']++;
        }

        $packages = ClubPackage::where('tenant_id', $clubId)->get();

        // Monthly new-member registrations + demographic breakdown — last 12 months (sparkline data)
        $recentMembers = DB::table('memberships as m')
            ->join('users as u', 'm.user_id', '=', 'u.id')
            ->where('m.tenant_id', $clubId)
            ->where('m.status', 'active')
            ->where('m.created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("strftime('%Y-%m', m.created_at) as ym, u.gender, u.birthdate")
            ->get();

        $monthlyLabels = [];
        $slots = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $monthlyLabels[] = now()->subMonths($i)->format('M Y');
            $slots[$key] = ['total' => 0, 'male' => 0, 'female' => 0,
                            'Kids' => 0, 'Cadet' => 0, 'Junior' => 0, 'Senior' => 0, 'Masters' => 0];
        }

        foreach ($recentMembers as $row) {
            $ym = $row->ym;
            if (!isset($slots[$ym])) continue;
            $slots[$ym]['total']++;
            $g = strtolower($row->gender ?? '');
            if ($g === 'male')   $slots[$ym]['male']++;
            if ($g === 'female') $slots[$ym]['female']++;
            if ($row->birthdate) {
                $age = \Carbon\Carbon::parse($row->birthdate)->age;
                if      ($age >= 6  && $age < 12) $slots[$ym]['Kids']++;
                elseif  ($age >= 12 && $age < 15) $slots[$ym]['Cadet']++;
                elseif  ($age >= 15 && $age < 18) $slots[$ym]['Junior']++;
                elseif  ($age >= 18 && $age < 31) $slots[$ym]['Senior']++;
                elseif  ($age >= 31)              $slots[$ym]['Masters']++;
            }
        }

        $monthlyNewMembers  = array_column(array_values($slots), 'total');
        $monthlyMale        = array_column(array_values($slots), 'male');
        $monthlyFemale      = array_column(array_values($slots), 'female');
        $monthlyKids        = array_column(array_values($slots), 'Kids');
        $monthlyCadet       = array_column(array_values($slots), 'Cadet');
        $monthlyJunior      = array_column(array_values($slots), 'Junior');
        $monthlySenior      = array_column(array_values($slots), 'Senior');
        $monthlyMasters     = array_column(array_values($slots), 'Masters');

        // On mobile we render the roster server-side (desktop loads it via AJAX).
        $mobileMembers = collect();
        $mobileSubscriptions = collect();
        if ($request->attributes->get('is_mobile')) {
            $rosterQuery = Membership::where('tenant_id', $clubId)
                ->where('status', 'active')
                ->with(['user:id,uuid,full_name,name,profile_picture,gender,birthdate,nationality,updated_at']);
            if ($filter === 'active') {
                $rosterQuery->whereIn('user_id', $enrolledUserIds);
            } elseif ($filter === 'not_active') {
                $rosterQuery->whereNotIn('user_id', $enrolledUserIds);
            }
            $mobileMembers = $rosterQuery->get();
            $mobileSubscriptions = ClubMemberSubscription::where('tenant_id', $clubId)
                ->whereIn('user_id', $mobileMembers->pluck('user_id'))
                ->where(fn ($q) => $q->where('type', 'owner')
                    ->orWhere(fn ($q2) => $q2->where('type', 'regular')->whereIn('status', ['active', 'pending']))
                )
                ->with(['package:id,name'])
                ->get()
                ->groupBy('user_id');
        }

        return view(\App\Support\ClubView::pick('members'), compact(
            'club', 'packages', 'statusCounts', 'filter',
            'mobileMembers', 'mobileSubscriptions',
            'allCount', 'activeCount', 'notActiveCount', 'formerCount',
            'maleCount', 'femaleCount', 'ageGroupCounts',
            'monthlyNewMembers', 'monthlyLabels',
            'monthlyMale', 'monthlyFemale',
            'monthlyKids', 'monthlyCadet', 'monthlyJunior', 'monthlySenior', 'monthlyMasters'
        ));
    }

    public function membersCards(Tenant $club, Request $request)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $filter = $request->input('filter', 'active');

        if ($filter === 'former') {
            $formerMembers = Membership::where('tenant_id', $clubId)
                ->where('status', 'former')
                ->with([
                    'user:id,uuid,full_name,name,first_name,last_name,profile_picture,gender,birthdate,mobile,email,nationality,updated_at',
                    'user.guardians.guardian:id,first_name,last_name,profile_picture,updated_at',
                ])
                ->paginate(20, ['*'], 'former_page');

            $memberUserIds = $formerMembers->pluck('user_id');
            $subscriptions = ClubMemberSubscription::where('tenant_id', $clubId)
                ->whereIn('user_id', $memberUserIds)
                ->where(fn ($q) => $q->where('type', 'owner')
                    ->orWhere(fn ($q2) => $q2->where('type', 'regular')->whereIn('status', ['active', 'pending']))
                )
                ->with(['package:id,name'])
                ->get()
                ->groupBy('user_id');

            return view('admin.club.members.partials.former-cards', compact('club', 'formerMembers', 'subscriptions'));
        }

        // Active/not-active/all members
        $enrolledUserIds = ClubMemberSubscription::where('tenant_id', $clubId)
            ->whereIn('user_id', function ($q) use ($clubId) {
                $q->select('user_id')->from('memberships')
                  ->where('tenant_id', $clubId)->where('status', 'active');
            })
            ->where(fn ($q) => $q->where('type', 'owner')
                ->orWhere(fn ($q2) => $q2->where('type', 'regular')->whereIn('status', ['active', 'pending']))
            )
            ->pluck('user_id')
            ->unique();

        $query = Membership::where('tenant_id', $clubId)
            ->where('status', 'active')
            ->with([
                'user:id,uuid,full_name,name,first_name,last_name,profile_picture,gender,birthdate,mobile,email,nationality,updated_at',
                'user.guardians.guardian:id,first_name,last_name,profile_picture,updated_at',
                'user.latestHealthRecord',
            ]);

        if ($filter === 'active') {
            $query->whereIn('user_id', $enrolledUserIds);
        } elseif ($filter === 'not_active') {
            $query->whereNotIn('user_id', $enrolledUserIds);
        }

        $members = $query->get();

        $subscriptions = ClubMemberSubscription::where('tenant_id', $clubId)
            ->whereIn('user_id', $members->pluck('user_id'))
            ->where(fn ($q) => $q->where('type', 'owner')
                ->orWhere(fn ($q2) => $q2->where('type', 'regular')->whereIn('status', ['active', 'pending']))
            )
            ->with(['package:id,name'])
            ->get()
            ->groupBy('user_id');

        return view('admin.club.members.partials.cards', compact('club', 'members', 'subscriptions'));
    }

    public function memberPopupDemo(Tenant $club)
    {
        $this->authorizeClub($club);

        // Pick the first available member to demo with, or null for skeleton
        $membership = Membership::where('tenant_id', $club->id)->with('user')->first();

        return view('admin.club.members.partials.member-popup-demo', compact('club', 'membership'));
    }

    public function memberPopup(Tenant $club, User $user)
    {
        $this->authorizeClub($club);

        $membership = Membership::where('tenant_id', $club->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $subscriptions = ClubMemberSubscription::where('tenant_id', $club->id)
            ->where('user_id', $user->id)
            ->where('type', 'regular')
            ->with('package')
            ->latest()
            ->get()
            ->map(function ($sub) use ($club) {
                return [
                    'id'             => $sub->id,
                    'package'        => $sub->package?->name ?? 'N/A',
                    'currency'       => $sub->package?->currency ?? 'BHD',
                    'start_date'     => $sub->start_date?->format('M d, Y') ?? 'N/A',
                    'end_date'       => $sub->end_date?->format('M d, Y') ?? 'Ongoing',
                    'payment_status' => $sub->payment_status ?? 'pending',
                    'amount_due'     => number_format((float) ($sub->amount_due ?? 0), 2),
                    'amount_paid'    => number_format((float) ($sub->amount_paid ?? 0), 2),
                    'status'         => $sub->status,
                    'is_active'      => in_array($sub->status, ['active', 'pending']),
                    'has_proof'      => (bool) $sub->proof_of_payment,
                    'approve_url'    => route('admin.club.subscriptions.approve-payment', [$club->slug, $sub->id]),
                    'proof_url'      => $sub->proof_of_payment
                        ? route('admin.club.subscriptions.payment-proof', [$club->slug, $sub->id])
                        : null,
                ];
            });

        $phone = is_array($user->mobile)
            ? trim(($user->mobile['code'] ?? '') . ' ' . ($user->mobile['number'] ?? ''))
            : ($user->mobile ?? '');

        return response()->json([
            'id'          => $user->id,
            'name'        => $user->full_name,
            'initial'     => mb_strtoupper(mb_substr($user->full_name ?? 'M', 0, 1, 'UTF-8'), 'UTF-8'),
            'has_picture' => (bool) $user->profile_picture,
            'picture_url' => $user->profile_picture
                ? asset('storage/' . $user->profile_picture) . '?v=' . $user->updated_at->timestamp
                : null,
            'gender'        => $user->gender ?? 'Male',
            'phone'         => $phone ?: 'N/A',
            'email'         => $user->email ?? 'N/A',
            'age'           => $user->age ? $user->age . ' years' : 'N/A',
            'since'         => $membership->created_at->format('d/m/Y'),
            'profile_url'         => route('member.show', $user->uuid),
            'remove_url'          => route('admin.club.members.remove', [$club->slug, $user->id]),
            'subscriptions'       => $subscriptions,
            'context'             => 'club',
            'enroll_packages_url' => route('admin.club.members.enroll-packages', [$club->slug, $user->id]),
            'enroll_url'          => route('admin.club.members.enroll', [$club->slug, $user->id]),
        ]);
    }

    public function enrollPackages(Tenant $club, User $user)
    {
        $this->authorizeClub($club);

        $age    = $user->age;
        $gender = $user->gender;

        $activePackageIds = ClubMemberSubscription::where('tenant_id', $club->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->pluck('package_id');

        $packages = ClubPackage::where('tenant_id', $club->id)
            ->where('is_active', true)
            ->whereNotIn('id', $activePackageIds)
            ->get()
            ->filter(function ($pkg) use ($age, $gender) {
                if ($pkg->age_min !== null && $age !== null && $age < $pkg->age_min) return false;
                if ($pkg->age_max !== null && $age !== null && $age > $pkg->age_max) return false;
                if ($pkg->gender && $pkg->gender !== 'mixed' && $gender) {
                    $match = ($pkg->gender === 'male' && $gender === 'Male')
                          || ($pkg->gender === 'female' && $gender === 'Female');
                    if (!$match) return false;
                }
                return true;
            })
            ->values()
            ->map(fn($pkg) => [
                'id'              => $pkg->id,
                'name'            => $pkg->name,
                'price'           => number_format((float) $pkg->price, 2),
                'currency'        => $club->currency ?? 'BHD',
                'duration_months' => $pkg->duration_months,
                'description'     => $pkg->description,
            ]);

        return response()->json(['packages' => $packages]);
    }

    public function enrollMember(Request $request, Tenant $club, User $user, SubscriptionService $subscriptions)
    {
        $this->authorizeClub($club);

        $request->validate(['package_id' => 'required|integer']);

        $package = ClubPackage::where('tenant_id', $club->id)
            ->where('id', $request->package_id)
            ->where('is_active', true)
            ->firstOrFail();

        if ($subscriptions->isDuplicate($club->id, $user->id, $package->id)) {
            return response()->json(['success' => false, 'message' => 'Member is already enrolled in this package.'], 422);
        }

        $error = $subscriptions->checkEligibility($package, $user->full_name, $user->age, $user->gender);
        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        $subscriptions->createEnrollment(
            $club,
            $user->id,
            $package,
            "Admin enrollment: {$user->full_name} — {$package->name}"
        );

        return response()->json(['success' => true, 'message' => 'Member enrolled successfully.']);
    }

    public function storeMember(StoreMembersRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $addedCount = 0;
        foreach ($request->user_ids as $userId) {
            $existingMembership = Membership::where('tenant_id', $clubId)->where('user_id', $userId)->first();

            if (!$existingMembership) {
                Membership::create(['tenant_id' => $clubId, 'user_id' => $userId, 'status' => 'active']);
                $addedCount++;
            }
        }

        if ($request->expectsJson() || $request->ajax()) {
            if ($addedCount > 0) {
                return response()->json(['success' => true, 'message' => "{$addedCount} member(s) added successfully.", 'count' => $addedCount]);
            }
            return response()->json(['success' => true, 'message' => 'Selected users are already members of this club.', 'count' => 0]);
        }

        if ($addedCount > 0) {
            return back()->with('success', "{$addedCount} member(s) added successfully.");
        }

        return back()->with('info', 'Selected users are already members of this club.');
    }

    public function walkInRegistration(WalkInRegistrationRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        DB::beginTransaction();
        try {
            $g = $request->guardian;

            $guardianData = [
                'full_name'         => $g['name'],
                'name'              => $g['name'],
                'email'             => $g['email'],
                'password'          => Hash::make($g['password']),
                'gender'            => $g['gender'],
                'birthdate'         => $g['dob'],
                'nationality'       => $g['nationality'],
                'mobile'            => ['code' => $g['countryCode'] ?? '+973', 'number' => $g['phone']],
                'email_verified_at' => null,
            ];

            $softDeletedGuardian = User::withTrashed()->where('email', $g['email'])->whereNotNull('deleted_at')->first();
            if ($softDeletedGuardian) {
                $softDeletedGuardian->restore();
                $softDeletedGuardian->update($guardianData);
                $guardianUser = $softDeletedGuardian;
            } else {
                $guardianUser = User::create($guardianData);
            }

            $childUsers = [];
            foreach ($request->people as $person) {
                if ($person['type'] === 'child') {
                    $childUser = User::create([
                        'full_name'   => $person['name'],
                        'name'        => $person['name'],
                        'gender'      => $person['gender'],
                        'birthdate'   => $person['dob'],
                        'nationality' => $person['nationality'] ?? null,
                        'password'    => Hash::make(Str::random(16)),
                    ]);
                    UserRelationship::create([
                        'guardian_user_id'  => $guardianUser->id,
                        'dependent_user_id' => $childUser->id,
                        'relationship_type' => 'child',
                    ]);
                    $childUsers[] = $childUser;
                }
            }

            $validPkgIds = ClubPackage::where('tenant_id', $club->id)->pluck('id')->flip();
            $childIdx    = 0;

            foreach ($request->people as $person) {
                $user = $person['type'] === 'guardian'
                    ? $guardianUser
                    : ($childUsers[$childIdx++] ?? null);
                if (!$user) continue;

                Membership::firstOrCreate(
                    ['tenant_id' => $club->id, 'user_id' => $user->id],
                    ['status' => 'active']
                );

                if ($club->enrollment_fee > 0) {
                    ClubTransaction::create([
                        'tenant_id'        => $club->id,
                        'user_id'          => $user->id,
                        'type'             => 'income',
                        'category'         => 'enrollment',
                        'amount'           => $club->enrollment_fee,
                        'description'      => "Walk-in enrollment: {$user->full_name}",
                        'transaction_date' => now(),
                    ]);
                }

                foreach (($person['selectedPackageIds'] ?? []) as $pkgId) {
                    if (!isset($validPkgIds[$pkgId])) continue;
                    $package = ClubPackage::find($pkgId);
                    if (!$package) continue;

                    if (app(SubscriptionService::class)->isDuplicate($club->id, $user->id, $pkgId)) continue;

                    app(SubscriptionService::class)->createActive(
                        $club,
                        $user->id,
                        $package,
                        "Walk-in: {$user->full_name} — {$package->name}"
                    );
                }
            }

            DB::commit();

            // Send verification email so the user can activate their account and log in.
            $guardianUser->sendEmailVerificationNotification();

            activity('membership')
                ->causedBy(auth()->user())
                ->performedOn($club)
                ->withProperties(['guardian_email' => $guardianUser->email, 'people_count' => count($request->people)])
                ->log('Walk-in registration completed');

            return response()->json(['success' => true, 'message' => 'Walk-in registration completed successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Registration failed. Please try again.'], 500);
        }
    }

    public function searchUsers(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $query  = $request->input('query');

        if (empty($query) || strlen($query) < 2) {
            return response()->json(['users' => []]);
        }

        $users = User::where(function ($q) use ($query) {
            $q->where('email', 'like', "%{$query}%")
              ->orWhere('name', 'like', "%{$query}%")
              ->orWhere('full_name', 'like', "%{$query}%")
              ->orWhere('mobile', 'like', "%{$query}%");
        })
        ->limit(20)
        ->get()
        ->map(function ($user) use ($clubId) {
            $isMember = Membership::where('tenant_id', $clubId)->where('user_id', $user->id)->exists();

            $dependents = $user->dependents()->with('dependent')->get()->map(function ($relationship) use ($clubId, $user) {
                $dep = $relationship->dependent;
                if (!$dep) return null;

                $isDepMember      = Membership::where('tenant_id', $clubId)->where('user_id', $dep->id)->exists();
                $relationshipType = $relationship->relationship_type;
                $isChild          = in_array($relationshipType, ['son', 'daughter', 'child']);

                return [
                    'id'                => $dep->id,
                    'name'              => $dep->full_name ?? $dep->name,
                    'profile_picture'   => $dep->profile_picture ? asset('storage/' . $dep->profile_picture) : null,
                    'gender'            => $dep->gender,
                    'age'               => $dep->birthdate ? \Carbon\Carbon::parse($dep->birthdate)->age : null,
                    'is_member'         => $isDepMember,
                    'relationship_type' => ucfirst($relationshipType),
                    'is_child'          => $isChild,
                    'guardian_name'     => $isChild ? ($user->full_name ?? $user->name) : null,
                    'email'             => $dep->email ?: ($isChild ? $user->email : null),
                    'mobile'            => $dep->mobile ?: ($isChild ? $user->mobile : null),
                ];
            })->filter();

            return [
                'id'              => $user->id,
                'name'            => $user->full_name ?? $user->name,
                'email'           => $user->email,
                'mobile'          => $user->mobile,
                'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                'gender'          => $user->gender,
                'age'             => $user->birthdate ? \Carbon\Carbon::parse($user->birthdate)->age : null,
                'is_member'       => $isMember,
                'dependents'      => $dependents,
            ];
        });

        return response()->json(['users' => $users]);
    }

    public function approvePayment(Request $request, Tenant $club, ClubMemberSubscription $subscription, SubscriptionService $subscriptions, FinancialService $financials)
    {
        $this->authorizeClub($club);

        if ($subscription->tenant_id !== $club->id) {
            abort(403);
        }

        $proofPath = null;
        if ($request->filled('admin_proof_base64')) {
            $proofPath = $this->storeBase64Image(
                $request->input('admin_proof_base64'),
                'payment-proofs',
                'admin_proof_' . $subscription->id . '_' . time(),
                'local'
            );
        }

        $subscriptions->approvePayment($subscription, $proofPath, auth()->user());

        return response()->json([
            'success'         => true,
            'message'         => 'Payment approved successfully.',
            'subscription_id' => $subscription->id,
            'payment_status'  => $subscription->fresh()->payment_status,
            'financials'      => $this->financialsPayload($club, $financials),
        ]);
    }

    /**
     * Recompute the financials KPI summary + 12-month chart data for live UI updates.
     */
    private function financialsPayload(Tenant $club, FinancialService $financials): array
    {
        $transactions = ClubTransaction::where('tenant_id', $club->id)->latest('transaction_date')->get();

        return [
            'summary' => $financials->getSummary($club->id, $transactions),
            'monthly' => $financials->getMonthlyData($transactions, $club->id),
        ];
    }

    public function servePaymentProof(Tenant $club, ClubMemberSubscription $subscription)
    {
        $this->authorizeClub($club);

        if ($subscription->tenant_id !== $club->id || !$subscription->proof_of_payment) {
            abort(404);
        }

        $path = $subscription->proof_of_payment;

        if (!Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($path),
            ['Content-Type' => Storage::disk('local')->mimeType($path)]
        );
    }

    public function refundPayment(Request $request, Tenant $club, ClubMemberSubscription $subscription, FinancialService $financials)
    {
        $this->authorizeClub($club);

        if ($subscription->tenant_id !== $club->id) {
            abort(403);
        }

        if ($subscription->payment_status !== 'paid') {
            return response()->json(['success' => false, 'message' => 'Subscription is not paid.'], 422);
        }

        $refundProofPath = null;
        if ($request->filled('refund_proof_base64')) {
            $refundProofPath = $this->storeBase64Image(
                $request->input('refund_proof_base64'),
                'payment-proofs',
                'refund_proof_' . $subscription->id . '_' . time(),
                'local'
            );
        }

        $refundTxn = $financials->recordTransaction($club, [
            'type'             => 'refund',
            'amount'           => $subscription->amount_paid,
            'description'      => 'Refund - ' . ($subscription->package?->name ?? 'Subscription'),
            'category'         => 'refund',
            'payment_method'   => 'bank_transfer',
            'transaction_date' => now()->toDateString(),
            'subscription_id'  => $subscription->id,
        ]);

        $subscription->update([
            'payment_status' => 'refunded',
            'refund_proof'   => $refundProofPath,
        ]);

        return response()->json([
            'success'         => true,
            'message'         => 'Refund processed successfully.',
            'subscription_id' => $subscription->id,
            'payment_status'  => 'refunded',
            'transaction'     => [
                'id'               => $refundTxn->id,
                'type'             => 'refund',
                'description'      => $refundTxn->description,
                'category'         => $refundTxn->category,
                'amount'           => (float) $refundTxn->amount,
                'payment_method'   => $refundTxn->payment_method,
                'reference_number' => $refundTxn->reference_number,
                'transaction_date' => $refundTxn->transaction_date?->format('d M Y'),
            ],
            'financials'      => $this->financialsPayload($club, $financials),
        ]);
    }

    public function serveRefundProof(Tenant $club, ClubMemberSubscription $subscription)
    {
        $this->authorizeClub($club);

        if ($subscription->tenant_id !== $club->id || !$subscription->refund_proof) {
            abort(404);
        }

        $path = $subscription->refund_proof;

        if (!Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($path),
            ['Content-Type' => Storage::disk('local')->mimeType($path)]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function removeMember(Tenant $club, User $user)
    {
        $this->authorizeClub($club);

        $membership = Membership::where('tenant_id', $club->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json(['success' => false, 'message' => 'Member not found in this club.'], 404);
        }

        // End the club affiliation — profile and all history remain intact.
        $membership->update(['status' => 'former']);

        \App\Support\ClubCache::flushStats($club->id);

        return response()->json([
            'success' => true,
            'message' => $user->full_name . '\'s membership has been ended. Their profile and history are preserved.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Import Members
    // ─────────────────────────────────────────────────────────────────────────

    public function importTemplate(Tenant $club)
    {
        $this->authorizeClub($club);

        $templatePath = public_path('files/member-import-template.xlsx');

        if (!file_exists($templatePath)) {
            abort(404, 'Import template not found. Please contact support.');
        }

        return response()->download($templatePath, 'member-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importMembers(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $file = $request->file('import_file');

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Could not read the file. Please use the provided template.']);
        }

        $sheet      = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        // Detect header row (row 3 in the template, but allow row 1 for plain CSV)
        $headerRow  = $this->detectImportHeaderRow($sheet);
        if ($headerRow === null) {
            return response()->json(['success' => false, 'message' => 'Could not find the header row. Please use the official import template.']);
        }

        $colMap = $this->mapImportColumns($sheet, $headerRow);

        if (!isset($colMap['first_name']) || !isset($colMap['last_name'])) {
            return response()->json(['success' => false, 'message' => 'Required columns "First Name" and "Last Name" not found. Please use the official import template.']);
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        DB::beginTransaction();
        try {
            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $data = [];
                foreach ($colMap as $field => $colIdx) {
                    $data[$field] = trim((string) $sheet->getCellByColumnAndRow($colIdx, $row)->getValue());
                }

                // Skip empty rows
                if (empty($data['first_name']) && empty($data['last_name'])) {
                    continue;
                }
                // Skip sample/note rows
                if (in_array(strtolower($data['first_name'] ?? ''), ['first name', 'example', 'sample', '★'])) {
                    continue;
                }

                // Validate required fields
                $missing = [];
                foreach (['first_name', 'last_name', 'gender', 'date_of_birth', 'phone'] as $req) {
                    if (isset($colMap[$req]) && empty($data[$req])) {
                        $missing[] = $req;
                    }
                }
                if (!empty($missing)) {
                    $errors[] = "Row $row skipped — missing: " . implode(', ', $missing);
                    $skipped++;
                    continue;
                }

                // Validate gender
                $gender = ucfirst(strtolower($data['gender'] ?? ''));
                if (!in_array($gender, ['Male', 'Female'])) {
                    $errors[] = "Row $row skipped — invalid gender '{$data['gender']}' (use Male or Female).";
                    $skipped++;
                    continue;
                }

                // Build name
                $fullName = trim(implode(' ', array_filter([
                    $data['first_name'] ?? '',
                    $data['middle_name'] ?? '',
                    $data['last_name'] ?? '',
                ])));

                // Parse phone
                $rawPhone = preg_replace('/\s+/', '', $data['phone'] ?? '');
                $phoneCode   = '+973';
                $phoneNumber = $rawPhone;
                if (preg_match('/^(\+\d{1,4})(\d{6,})$/', $rawPhone, $pm)) {
                    $phoneCode   = $pm[1];
                    $phoneNumber = $pm[2];
                } elseif (preg_match('/^(00\d{1,4})(\d{6,})$/', $rawPhone, $pm)) {
                    $phoneCode   = '+' . ltrim($pm[1], '0');
                    $phoneNumber = $pm[2];
                }

                // Parse DOB
                $dob = null;
                if (!empty($data['date_of_birth'])) {
                    try {
                        $dob = \Carbon\Carbon::parse($data['date_of_birth'])->format('Y-m-d');
                    } catch (\Throwable) {
                        // leave null
                    }
                }

                // Duplicate check by email
                $email = !empty($data['email']) ? strtolower(trim($data['email'])) : null;
                if ($email) {
                    $existing = User::where('email', $email)->first();
                    if ($existing) {
                        // Add to club if not already a member
                        Membership::firstOrCreate(
                            ['tenant_id' => $club->id, 'user_id' => $existing->id],
                            ['status' => 'active']
                        );
                        $imported++;
                        continue;
                    }
                }

                // Create user
                $userData = [
                    'full_name' => $fullName,
                    'name'      => $fullName,
                    'gender'    => $gender,
                    'email'     => $email,
                    'birthdate' => $dob,
                    'mobile'    => ['code' => $phoneCode, 'number' => $phoneNumber],
                    'password'  => Hash::make(Str::random(16)),
                ];

                if (!empty($data['cpr_id'])) {
                    // Store CPR in address notes — adapt if there's a dedicated field
                }

                $user = User::create($userData);

                // Create membership
                Membership::create([
                    'tenant_id' => $club->id,
                    'user_id'   => $user->id,
                    'status'    => 'active',
                ]);

                // Enroll in package if specified
                if (!empty($data['package_name'])) {
                    $package = ClubPackage::where('tenant_id', $club->id)
                        ->whereRaw('LOWER(name) = ?', [strtolower($data['package_name'])])
                        ->first();
                    if ($package && !app(SubscriptionService::class)->isDuplicate($club->id, $user->id, $package->id)) {
                        app(SubscriptionService::class)->createActive($club, $user->id, $package, "Bulk import: {$user->full_name}");
                    }
                }

                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
        }

        $message = "Import complete — $imported member(s) added.";
        if ($skipped) {
            $message .= " $skipped row(s) skipped.";
        }

        return response()->json([
            'success'  => true,
            'message'  => $message,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 10), // cap at 10 to avoid huge payloads
        ]);
    }

    private function detectImportHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): ?int
    {
        // Look for a row containing "first" or "first name" in any cell (rows 1-6)
        for ($r = 1; $r <= 6; $r++) {
            $highest = $sheet->getHighestDataColumn($r);
            $lastIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highest);
            for ($c = 1; $c <= $lastIdx; $c++) {
                $val = strtolower(trim((string) $sheet->getCellByColumnAndRow($c, $r)->getValue()));
                // Match "first name" or just "first" (template has "First Name *")
                if (str_contains($val, 'first') && (str_contains($val, 'name') || $val === 'first')) {
                    return $r;
                }
            }
        }
        return null;
    }

    private function mapImportColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $headerRow): array
    {
        $map = [];
        $highest = $sheet->getHighestDataColumn($headerRow);
        $lastIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highest);

        $keyMap = [
            'first_name'    => ['first name', 'first'],
            'middle_name'   => ['middle name', 'middle'],
            'last_name'     => ['last name', 'last'],
            'gender'        => ['gender'],
            'date_of_birth' => ['date of birth', 'dob', 'birth', 'birthdate'],
            'phone'         => ['phone', 'mobile', 'telephone', 'tel'],
            'email'         => ['email'],
            'cpr_id'        => ['cpr', 'id number', 'cpr / id', 'cpr/id'],
            'height_cm'     => ['height'],
            'weight_kg'     => ['weight'],
            'health_notes'  => ['health', 'condition', 'health condition'],
            'emergency_1'   => ['emergency contact 1', 'emergency 1', 'emergency number 1'],
            'emergency_2'   => ['emergency contact 2', 'emergency 2', 'emergency number 2'],
            'package_name'  => ['package', 'package name'],
        ];

        for ($c = 1; $c <= $lastIdx; $c++) {
            $header = strtolower(trim(preg_replace('/[*★\x{0600}-\x{06FF}]/u', '', (string)
                $sheet->getCellByColumnAndRow($c, $headerRow)->getValue())));
            $header = trim($header);

            foreach ($keyMap as $field => $variants) {
                if (isset($map[$field])) continue;
                foreach ($variants as $variant) {
                    if (str_contains($header, $variant)) {
                        $map[$field] = $c;
                        break;
                    }
                }
            }
        }

        return $map;
    }
}
