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

    public function members(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId        = $club->id;
        $members       = Membership::where('tenant_id', $clubId)->with(['user', 'user.guardians.guardian'])->paginate(20);
        $packages      = ClubPackage::where('tenant_id', $clubId)->get();
        $subscriptions = ClubMemberSubscription::where('tenant_id', $clubId)
            ->with('package')
            ->get()
            ->groupBy('user_id');

        return view('admin.club.members.index', compact('club', 'members', 'packages', 'subscriptions'));
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

            $guardianUser = User::create([
                'full_name'         => $g['name'],
                'name'              => $g['name'],
                'email'             => $g['email'],
                'password'          => Hash::make($g['password']),
                'gender'            => $g['gender'],
                'birthdate'         => $g['dob'],
                'nationality'       => $g['nationality'],
                'mobile'            => ['code' => $g['countryCode'] ?? '+973', 'number' => $g['phone']],
                'email_verified_at' => now(),
            ]);

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

    public function approvePayment(Request $request, Tenant $club, ClubMemberSubscription $subscription, SubscriptionService $subscriptions)
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

        return response()->json(['success' => true, 'message' => 'Payment approved successfully.']);
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

        $financials->recordTransaction($club, [
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

        return response()->json(['success' => true, 'message' => 'Refund processed successfully.']);
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
}
