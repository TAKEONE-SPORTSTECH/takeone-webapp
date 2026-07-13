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
use App\Services\FinancialService;
use App\Support\ClubCache;
use App\Traits\HandlesClubAuthorization;
use App\Traits\PersistsTranslations;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClubAdminController extends Controller
{
    use HandlesClubAuthorization, PersistsTranslations, StoresBase64Images;

    /**
     * Dashboard overview
     */
    public function dashboard(Tenant $club, FinancialService $financials)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $stats = Cache::remember(ClubCache::dashboardStats($clubId), ClubCache::TTL_STATS, function () use ($clubId, $club) {
            // Compute access hours and distinct class count from package schedules
            $allStartTimes = [];
            $allEndTimes = [];
            $allDays = [];
            $distinctSlots = [];
            foreach (ClubPackage::where('tenant_id', $clubId)->with('packageActivities')->get() as $package) {
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
            if (! empty($allStartTimes) && ! empty($allEndTimes)) {
                [$startH, $startM] = explode(':', min($allStartTimes));
                [$endH,   $endM] = explode(':', max($allEndTimes));
                $hours = (int) ceil(((int) $endH * 60 + (int) $endM - ((int) $startH * 60 + (int) $startM)) / 60);
                $uniqueDays = count(array_unique($allDays));
                $accessStat = $hours.'h/'.($uniqueDays ?: 7);
            }

            return [
                'members_total' => Membership::where('tenant_id', $clubId)->where('status', 'active')->count(),
                'members' => (function () use ($clubId) {
                    $enrolledIds = ClubMemberSubscription::where('tenant_id', $clubId)
                        ->where(fn ($q) => $q->where('type', 'owner')
                            ->orWhere(fn ($q2) => $q2->where('type', 'regular')->whereIn('status', ['active', 'pending']))
                        )
                        ->pluck('user_id')->unique();

                    return Membership::where('tenant_id', $clubId)->where('status', 'active')
                        ->whereIn('user_id', $enrolledIds)->count();
                })(),
                'activities' => ClubActivity::where('tenant_id', $clubId)->count(),
                'packages' => ClubPackage::where('tenant_id', $clubId)->count(),
                'instructors' => ClubInstructor::where('tenant_id', $clubId)->count(),
                'events' => \App\Models\ClubEvent::where('tenant_id', $clubId)->where('is_archived', false)->get()->filter(fn ($e) => ! $e->hasEnded())->count(),
                'rating' => $club->reviews()->avg('rating') ?? 0,
                'access' => $accessStat,
                'classes' => count($distinctSlots),
            ];
        });

        // Member breakdown stats — reuse the same cache as the public page
        $memberStats = Cache::remember(ClubCache::showStats($clubId), ClubCache::TTL_STATS, function () use ($clubId, $club) {
            $memberIds = $club->members()->pluck('users.id');
            $members = \App\Models\User::whereIn('id', $memberIds)->get();

            static $countryNames = null;
            if ($countryNames === null) {
                $raw = json_decode(file_get_contents(public_path('data/countries.json')), true) ?? [];
                $countryNames = collect($raw)->pluck('name', 'iso2')->all();
            }

            $nationalityStats = $members->groupBy('nationality')
                ->map(fn ($group) => $group->count())
                ->sortDesc()->take(4)
                ->mapWithKeys(fn ($count, $code) => [($countryNames[$code] ?? ($code ?: 'Unknown')) => $count]);

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

            $genderStats = $members->groupBy('gender')->map(fn ($group) => $group->count());

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

            $bloodTypeStats = $members->groupBy('blood_type')
                ->map(fn ($group) => $group->count())
                ->filter(fn ($_, $key) => ! empty($key));

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

        $monthlyTrend = Cache::remember(ClubCache::showMonthlyTrend($clubId), ClubCache::TTL_STATS, function () use ($club) {
            $trend = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $trend[$month->format('M Y')] = $club->memberships()
                    ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                    ->count();
            }

            return $trend;
        });

        $reviews = $club->reviews()->with('user')->latest()->get();
        $averageRating = $reviews->avg('rating') ?? 0;

        $transactions = ClubTransaction::where('tenant_id', $clubId)->with(['subscription.user'])->latest('transaction_date')->get();
        $monthlyData = $financials->getMonthlyData($transactions, $clubId);
        $pendingSubscriptions = $financials->getCashToCollect($clubId);
        $expiringSubscriptions = collect();

        // Dashboard card data
        $packages = ClubPackage::where('tenant_id', $clubId)->take(8)->get();
        $instructors = ClubInstructor::where('tenant_id', $clubId)->with('user')->take(4)->get();
        $activities = ClubActivity::where('tenant_id', $clubId)->take(5)->get();
        $hofMembers = $club->members()->with([
            'clubAffiliations' => fn ($q) => $q->latest()->take(1),
        ])->take(5)->get();

        return view(\App\Support\ClubView::pick('dashboard'), compact(
            'club', 'stats', 'monthlyData', 'transactions', 'pendingSubscriptions', 'expiringSubscriptions',
            'nationalityStats', 'ageGroups', 'genderStats', 'horoscopeGroups',
            'bloodTypeStats', 'goalStats', 'totalMembers', 'monthlyTrend',
            'averageRating', 'reviews',
            'packages', 'instructors', 'activities', 'hofMembers'
        ));
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
        $reviews = $club->reviews()->with('user')->latest()->get();
        $averageRating = $reviews->avg('rating') ?? 0;
        $whatsappSettings = app(\App\Services\ClubWhatsAppManager::class)->adminSettings($club);

        return view(\App\Support\ClubView::pick('details'), compact('club', 'activeMembersCount', 'reviews', 'averageRating', 'whatsappSettings'));
    }

    public function updateWhatsAppSettings(\Illuminate\Http\Request $request, Tenant $club, \App\Services\ClubWhatsAppManager $whatsapp)
    {
        $this->authorizeClub($club);

        $data = $request->validate([
            'enabled'      => 'sometimes|boolean',
            'session_name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);
        $data['enabled'] = $request->boolean('enabled');

        $whatsapp->save($club, $data);

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'WhatsApp settings saved.',
                'settings' => $whatsapp->adminSettings($club),
            ]);
        }

        return back()->with('success', 'WhatsApp settings saved.');
    }

    public function testWhatsAppConnection(Tenant $club, \App\Services\ClubWhatsAppManager $whatsapp)
    {
        $this->authorizeClub($club);

        $result = $whatsapp->probe($club);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function sendTestWhatsAppMessage(\Illuminate\Http\Request $request, Tenant $club, \App\Services\ClubWhatsAppManager $whatsapp)
    {
        $this->authorizeClub($club);

        $data = $request->validate([
            'phone' => 'required|string|max:32',
        ]);

        $result = $whatsapp->sendTestMessage($club, $data['phone']);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Update club details
     */
    public function update(UpdateClubRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $data = $request->only([
            'club_name', 'slogan', 'description', 'enrollment_fee', 'registration_fee',
            'registration_terms', 'registration_requirements',
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

        foreach (['logo', 'favicon', 'cover_image', 'registration_splash_image'] as $field) {
            if ($request->filled($field) && str_starts_with($request->input($field), 'data:image')) {
                if ($club->$field && Storage::disk('public')->exists($club->$field)) {
                    Storage::disk('public')->delete($club->$field);
                }
                $data[$field] = $this->storeBase64Image($request->input($field), 'clubs/'.$clubId.'/branding', $field.'_'.time());
            } elseif ($request->hasFile($field)) {
                // Stored at original resolution (no downscaling) — but must be a real image.
                $request->validate([$field => 'image|mimes:jpg,jpeg,png,webp,gif|max:8192']);
                if ($club->$field && Storage::disk('public')->exists($club->$field)) {
                    Storage::disk('public')->delete($club->$field);
                }
                $data[$field] = $request->file($field)->store('clubs/'.$clubId.'/branding', 'public');
            }
        }

        $club->update($data);

        $this->applyTranslations($club, $request);

        if ($request->has('social_links')) {
            $club->socialLinks()->delete();
            foreach ($request->social_links as $link) {
                if (! empty($link['platform']) && ! empty($link['url'])) {
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

        foreach (['clubs/'.$clubId.'/branding', 'clubs/'.$clubId.'/gallery', 'clubs/'.$clubId.'/facilities', 'clubs/'.$clubId.'/instructors'] as $folder) {
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
            'platform' => $request->platform,
            'url' => $request->url,
            'icon' => $request->icon ?? 'link-45deg',
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
                'full_name' => $request->full_name,
                'name' => $request->full_name,
                'password' => bcrypt($request->password),
                'gender' => $request->gender,
                'birthdate' => $request->birthdate,
                'nationality' => $request->nationality,
                'blood_type' => $request->blood_type,
                'mobile' => $request->mobile ? ['code' => $request->mobile_code ?? '+973', 'number' => $request->mobile] : null,
            ]);
        } elseif ($newOwner) {
            return response()->json(['success' => false, 'message' => 'An active account with this email already exists. Use "Link Existing Member" instead.'], 422);
        } else {
            $newOwner = User::create([
                'full_name' => $request->full_name,
                'name' => $request->full_name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'gender' => $request->gender,
                'birthdate' => $request->birthdate,
                'nationality' => $request->nationality,
                'blood_type' => $request->blood_type,
                'mobile' => $request->mobile ? ['code' => $request->mobile_code ?? '+973', 'number' => $request->mobile] : null,
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
            if (! $alreadyAdmin) {
                $oldOwner->assignRole('club-admin', $club->id);
            }
        }

        $newOwner->assignRole('club-admin', $club->id);

        ClubMemberSubscription::create([
            'tenant_id' => $club->id,
            'type' => 'owner',
            'user_id' => $newOwner->id,
            'package_id' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'status' => 'active',
            'payment_status' => 'paid',
            'amount_paid' => 0,
            'amount_due' => 0,
            'notes' => 'Owner membership',
        ]);

        Membership::firstOrCreate(
            ['tenant_id' => $club->id, 'user_id' => $newOwner->id],
            ['status' => 'active']
        );

        return response()->json([
            'success' => true,
            'message' => 'New owner account created and ownership transferred successfully.',
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
                'name' => $request->full_name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'gender' => 'Male',
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
            if (! $alreadyAdmin) {
                $oldOwner->assignRole('club-admin', $club->id);
            }
        }

        $alreadyAdmin = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $newOwner->id)
            ->where('user_roles.tenant_id', $club->id)
            ->where('roles.slug', 'club-admin')
            ->exists();
        if (! $alreadyAdmin) {
            $newOwner->assignRole('club-admin', $club->id);
        }

        $alreadyOwner = ClubMemberSubscription::where('tenant_id', $club->id)
            ->where('user_id', $newOwner->id)
            ->where('type', 'owner')
            ->exists();

        if (! $alreadyOwner) {
            ClubMemberSubscription::create([
                'tenant_id' => $club->id,
                'type' => 'owner',
                'user_id' => $newOwner->id,
                'package_id' => null,
                'start_date' => now()->toDateString(),
                'end_date' => null,
                'status' => 'active',
                'payment_status' => 'paid',
                'amount_paid' => 0,
                'amount_due' => 0,
                'notes' => 'Owner membership',
            ]);
        }

        Membership::firstOrCreate(
            ['tenant_id' => $club->id, 'user_id' => $newOwner->id],
            ['status' => 'active']
        );

        return response()->json([
            'success' => true,
            'message' => 'Ownership transferred successfully.',
            'owner' => [
                'name' => $newOwner->full_name ?? $newOwner->name,
                'email' => $newOwner->email,
                'mobile' => $newOwner->formatted_mobile ?? '',
            ],
        ]);
    }

    /**
     * Helper: Monthly financial data for dashboard chart.
     */
    private function getMonthlyFinancials($clubId)
    {
        $now = now();
        $start = $now->copy()->subMonths(11)->startOfMonth();

        $rows = ClubTransaction::where('tenant_id', $clubId)
            ->where('transaction_date', '>=', $start)
            ->selectRaw("strftime('%Y-%m', transaction_date) as month_key, type, SUM(amount) as total")
            ->groupBy('month_key', 'type')
            ->get()
            ->groupBy('month_key');

        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $key = $date->format('Y-m');
            $monthRows = $rows->get($key, collect());

            $income = (float) $monthRows->firstWhere('type', 'income')?->total ?? 0;
            $expenses = (float) $monthRows->firstWhere('type', 'expense')?->total ?? 0;
            $refunds = (float) $monthRows->firstWhere('type', 'refund')?->total ?? 0;

            $data[] = [
                'month' => $date->format('M'),
                'income' => $income,
                'expenses' => $expenses,
                'profit' => $income - $expenses - $refunds,
            ];
        }

        return $data;
    }
}
