<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateOwnerRequest;
use App\Http\Requests\Admin\StoreSocialLinkRequest;
use App\Http\Requests\Admin\TransferOwnershipRequest;
use App\Http\Requests\Admin\UpdateClubRequest;
use App\Models\ClubActivity;
use App\Models\ClubInstructor;
use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\ClubTransaction;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelationship;
use App\Services\FinancialService;
use App\Support\ClubCache;
use App\Traits\HandlesClubAuthorization;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClubAdminController extends Controller
{
    use HandlesClubAuthorization, StoresBase64Images;

    /**
     * Dashboard overview
     */
    public function dashboard(Tenant $club, FinancialService $financials)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $stats = Cache::remember(ClubCache::dashboardStats($clubId), ClubCache::TTL_STATS, function () use ($clubId, $club) {
            return [
                'members'     => Membership::where('tenant_id', $clubId)->where('status', 'active')->count(),
                'activities'  => ClubActivity::where('tenant_id', $clubId)->count(),
                'packages'    => ClubPackage::where('tenant_id', $clubId)->count(),
                'instructors' => ClubInstructor::where('tenant_id', $clubId)->count(),
                'events'      => \App\Models\ClubEvent::where('tenant_id', $clubId)->where('is_archived', false)->count(),
                'rating'      => $club->reviews()->avg('rating') ?? 0,
            ];
        });

        $transactions  = ClubTransaction::where('tenant_id', $clubId)->with(['subscription.user'])->latest('transaction_date')->get();
        $monthlyData   = $financials->getMonthlyData($transactions, $clubId);
        $expiringSubscriptions = collect();

        return view('admin.club.dashboard.index', compact('club', 'stats', 'monthlyData', 'transactions', 'expiringSubscriptions'));
    }

    /**
     * Club details
     */
    public function details(Tenant $club)
    {
        $this->authorizeClub($club);

        $club->load([
            'owner', 'facilities', 'instructors', 'activities',
            'packages.activities', 'galleryImages', 'reviews.user',
            'socialLinks', 'subscriptions',
        ]);

        $activeMembersCount = $club->subscriptions()->where('status', 'active')->count();
        $reviews            = $club->reviews()->with('user')->latest()->get();
        $averageRating      = $reviews->avg('rating') ?? 0;

        return view('admin.club.details.index', compact('club', 'activeMembersCount', 'reviews', 'averageRating'));
    }

    /**
     * Update club details
     */
    public function update(UpdateClubRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $data = $request->only([
            'club_name', 'slogan', 'description', 'enrollment_fee',
            'commercial_reg_number', 'vat_reg_number', 'vat_percentage',
            'email', 'country', 'currency', 'timezone', 'slug', 'address',
            'gps_lat', 'gps_long', 'maps_url', 'owner_name', 'owner_email',
        ]);

        if ($request->filled('phone_code') || $request->filled('phone_number')) {
            $data['phone'] = ['code' => $request->phone_code, 'number' => $request->phone_number];
        }

        if ($request->has('settings')) {
            $data['settings'] = array_merge($club->settings ?? [], $request->settings);
        }

        foreach (['logo', 'favicon', 'cover_image'] as $field) {
            if ($request->filled($field) && str_starts_with($request->input($field), 'data:image')) {
                if ($club->$field && Storage::disk('public')->exists($club->$field)) {
                    Storage::disk('public')->delete($club->$field);
                }
                $data[$field] = $this->storeBase64Image($request->input($field), 'clubs/' . $clubId . '/branding', $field . '_' . time());
            } elseif ($request->hasFile($field)) {
                if ($club->$field && Storage::disk('public')->exists($club->$field)) {
                    Storage::disk('public')->delete($club->$field);
                }
                $data[$field] = $request->file($field)->store('clubs/' . $clubId . '/branding', 'public');
            }
        }

        $club->update($data);

        if ($request->has('social_links')) {
            $club->socialLinks()->delete();
            foreach ($request->social_links as $link) {
                if (!empty($link['platform']) && !empty($link['url'])) {
                    $club->socialLinks()->create(['platform' => $link['platform'], 'url' => $link['url']]);
                }
            }
        } else {
            $club->socialLinks()->delete();
        }

        return back()->with('success', 'Club details updated successfully.');
    }

    /**
     * Delete club
     */
    public function destroy(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        foreach (['clubs/' . $clubId . '/branding', 'clubs/' . $clubId . '/gallery', 'clubs/' . $clubId . '/facilities', 'clubs/' . $clubId . '/instructors'] as $folder) {
            if (Storage::disk('public')->exists($folder)) {
                Storage::disk('public')->deleteDirectory($folder);
            }
        }

        $club->delete();

        return redirect()->route('admin.platform.clubs')->with('success', 'Club deleted successfully.');
    }

    /**
     * Store social link
     */
    public function storeSocialLink(StoreSocialLinkRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $club->socialLinks()->create([
            'tenant_id' => $club->id,
            'platform'  => $request->platform,
            'url'       => $request->url,
            'icon'      => $request->icon ?? 'link-45deg',
        ]);

        return back()->with('success', 'Social link added successfully.');
    }

    /**
     * Delete social link
     */
    public function destroySocialLink(Tenant $club, $linkId)
    {
        $this->authorizeClub($club);

        $link = $club->socialLinks()->findOrFail($linkId);
        $link->delete();

        return back()->with('success', 'Social link deleted successfully.');
    }

    /**
     * Create a new platform user and immediately transfer ownership to them.
     */
    public function createOwner(CreateOwnerRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $newOwner = null;
        if ($request->filled('email')) {
            $newOwner = User::withTrashed()->where('email', $request->email)->first();
        }

        if ($newOwner && $newOwner->trashed()) {
            $newOwner->restore();
            $newOwner->update([
                'full_name'   => $request->full_name,
                'name'        => $request->full_name,
                'password'    => bcrypt($request->password),
                'gender'      => $request->gender,
                'birthdate'   => $request->birthdate,
                'nationality' => $request->nationality,
                'blood_type'  => $request->blood_type,
                'mobile'      => $request->mobile ? ['code' => $request->mobile_code ?? '+973', 'number' => $request->mobile] : null,
            ]);
        } elseif ($newOwner) {
            return response()->json(['success' => false, 'message' => 'An active account with this email already exists. Use "Link Existing Member" instead.'], 422);
        } else {
            $newOwner = User::create([
                'full_name'   => $request->full_name,
                'name'        => $request->full_name,
                'email'       => $request->email,
                'password'    => bcrypt($request->password),
                'gender'      => $request->gender,
                'birthdate'   => $request->birthdate,
                'nationality' => $request->nationality,
                'blood_type'  => $request->blood_type,
                'mobile'      => $request->mobile ? ['code' => $request->mobile_code ?? '+973', 'number' => $request->mobile] : null,
            ]);
        }

        $oldOwner = $club->owner;
        $club->update(['owner_user_id' => $newOwner->id]);

        if ($oldOwner && $oldOwner->id !== $newOwner->id) {
            $alreadyAdmin = DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $oldOwner->id)
                ->where('user_roles.tenant_id', $club->id)
                ->where('roles.slug', 'club-admin')
                ->exists();
            if (!$alreadyAdmin) {
                $oldOwner->assignRole('club-admin', $club->id);
            }
        }

        $newOwner->assignRole('club-admin', $club->id);

        ClubMemberSubscription::create([
            'tenant_id'      => $club->id,
            'type'           => 'owner',
            'user_id'        => $newOwner->id,
            'package_id'     => null,
            'start_date'     => now()->toDateString(),
            'end_date'       => null,
            'status'         => 'active',
            'payment_status' => 'paid',
            'amount_paid'    => 0,
            'amount_due'     => 0,
            'notes'          => 'Owner membership',
        ]);

        Membership::firstOrCreate(
            ['tenant_id' => $club->id, 'user_id' => $newOwner->id],
            ['status' => 'active']
        );

        return response()->json([
            'success'  => true,
            'message'  => 'New owner account created and ownership transferred successfully.',
            'redirect' => route('admin.club.details', $club->slug),
        ]);
    }

    /**
     * Transfer club ownership to an existing member or a newly created user.
     */
    public function transferOwnership(TransferOwnershipRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $mode = $request->input('mode');

        if ($mode === 'existing') {
            $newOwner = User::findOrFail($request->user_id);
        } else {
            $newOwner = User::create([
                'full_name' => $request->full_name,
                'name'      => $request->full_name,
                'email'     => $request->email,
                'password'  => bcrypt($request->password),
                'gender'    => 'm',
            ]);
        }

        $oldOwner = $club->owner;
        $club->update(['owner_user_id' => $newOwner->id]);

        if ($oldOwner && $oldOwner->id !== $newOwner->id) {
            $alreadyAdmin = DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $oldOwner->id)
                ->where('user_roles.tenant_id', $club->id)
                ->where('roles.slug', 'club-admin')
                ->exists();
            if (!$alreadyAdmin) {
                $oldOwner->assignRole('club-admin', $club->id);
            }
        }

        $alreadyAdmin = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $newOwner->id)
            ->where('user_roles.tenant_id', $club->id)
            ->where('roles.slug', 'club-admin')
            ->exists();
        if (!$alreadyAdmin) {
            $newOwner->assignRole('club-admin', $club->id);
        }

        $alreadyOwner = ClubMemberSubscription::where('tenant_id', $club->id)
            ->where('user_id', $newOwner->id)
            ->where('type', 'owner')
            ->exists();

        if (!$alreadyOwner) {
            ClubMemberSubscription::create([
                'tenant_id'      => $club->id,
                'type'           => 'owner',
                'user_id'        => $newOwner->id,
                'package_id'     => null,
                'start_date'     => now()->toDateString(),
                'end_date'       => null,
                'status'         => 'active',
                'payment_status' => 'paid',
                'amount_paid'    => 0,
                'amount_due'     => 0,
                'notes'          => 'Owner membership',
            ]);
        }

        Membership::firstOrCreate(
            ['tenant_id' => $club->id, 'user_id' => $newOwner->id],
            ['status' => 'active']
        );

        return response()->json([
            'success' => true,
            'message' => 'Ownership transferred successfully.',
            'owner'   => [
                'name'   => $newOwner->full_name ?? $newOwner->name,
                'email'  => $newOwner->email,
                'mobile' => $newOwner->formatted_mobile ?? '',
            ],
        ]);
    }

    /**
     * Helper: Monthly financial data for dashboard chart.
     */
    private function getMonthlyFinancials($clubId)
    {
        $now   = now();
        $start = $now->copy()->subMonths(11)->startOfMonth();

        $rows = ClubTransaction::where('tenant_id', $clubId)
            ->where('transaction_date', '>=', $start)
            ->selectRaw("strftime('%Y-%m', transaction_date) as month_key, type, SUM(amount) as total")
            ->groupBy('month_key', 'type')
            ->get()
            ->groupBy('month_key');

        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date      = $now->copy()->subMonths($i);
            $key       = $date->format('Y-m');
            $monthRows = $rows->get($key, collect());

            $income   = (float) $monthRows->firstWhere('type', 'income')?->total  ?? 0;
            $expenses = (float) $monthRows->firstWhere('type', 'expense')?->total ?? 0;
            $refunds  = (float) $monthRows->firstWhere('type', 'refund')?->total  ?? 0;

            $data[] = [
                'month'    => $date->format('M'),
                'income'   => $income,
                'expenses' => $expenses,
                'profit'   => $income - $expenses - $refunds,
            ];
        }

        return $data;
    }
}
