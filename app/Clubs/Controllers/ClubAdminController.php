<?php

namespace App\Clubs\Controllers;

use App\Http\Controllers\Controller;
use App\Clubs\Requests\CreateOwnerRequest;
use App\Clubs\Requests\StoreSocialLinkRequest;
use App\Clubs\Requests\TransferOwnershipRequest;
use App\Clubs\Requests\UpdateClubRequest;
use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\ClubInstructor;
use App\Clubs\Models\ClubMemberSubscription;
use App\Clubs\Models\ClubPackage;
use App\Clubs\Models\ClubTransaction;
use App\Members\Models\Membership;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Clubs\Support\FinancialService;
use App\Clubs\Support\ClubCache;
use App\Clubs\Concerns\HandlesClubAuthorization;
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
                'instructors' => ClubInstructor::where('tenant_id', $clubId)->where('is_active', true)->count(),
                'events' => \App\Events\Models\ClubEvent::where('tenant_id', $clubId)->where('is_archived', false)->get()->filter(fn ($e) => ! $e->hasEnded())->count(),
                'rating' => $club->reviews()->avg('rating') ?? 0,
                'access' => $accessStat,
                'classes' => count($distinctSlots),
            ];
        });

        // Member breakdown stats — reuse the same cache as the public page
        $memberStats = Cache::remember(ClubCache::showStats($clubId), ClubCache::TTL_STATS, function () use ($clubId, $club) {
            $memberIds = $club->members()->pluck('users.id');
            $members = \App\Members\Models\User::whereIn('id', $memberIds)->get();

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

            $memberGoals = \App\Members\Models\Goal::whereIn('user_id', $memberIds)->get()->groupBy('user_id');
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

        $transactions = ClubTransaction::where('tenant_id', $clubId)->with(['subscription.user', 'user'])->latest('transaction_date')->get();
        $monthlyData = $financials->getMonthlyData($transactions, $clubId);
        $pendingSubscriptions = $financials->getCashToCollect($clubId);
        $expiringSubscriptions = collect();

        // Dashboard card data
        $packages = ClubPackage::where('tenant_id', $clubId)->take(8)->get();
        $instructors = ClubInstructor::where('tenant_id', $clubId)->where('is_active', true)->with('user')->take(4)->get();
        $activities = ClubActivity::where('tenant_id', $clubId)->take(5)->get();
        $hofMembers = $club->members()->with([
            'clubAffiliations' => fn ($q) => $q->latest()->take(1),
        ])->take(5)->get();

        return view(\App\Clubs\Support\ClubView::pick('dashboard', 'clubs'), compact(
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
        $whatsappSettings = app(\App\Clubs\Services\ClubWhatsAppManager::class)->adminSettings($club);

        // Null for anyone who may run the club but not give it away: the views
        // then draw no transfer control at all (the endpoints refuse anyway).
        $canActAsOwner = $this->canActAsClubOwner($club);
        // A co-owned club changes hands through its owners' vote (the owners
        // panel), never through the single-owner transfer.
        $transferOwnershipUrl = $canActAsOwner && ! $this->isCoOwned($club) ? route('admin.club.transfer-ownership', $club->slug) : null;
        $deleteClubUrl = $canActAsOwner ? route('admin.club.destroy', $club->slug) : null;

        return view(\App\Clubs\Support\ClubView::pick('details', 'clubs'), compact('club', 'activeMembersCount', 'reviews', 'averageRating', 'whatsappSettings', 'transferOwnershipUrl', 'deleteClubUrl'));
    }

    public function updateWhatsAppSettings(\Illuminate\Http\Request $request, Tenant $club, \App\Clubs\Services\ClubWhatsAppManager $whatsapp)
    {
        $this->authorizeClub($club);

        $data = $request->validate([
            'enabled'      => 'sometimes|boolean',
            'session_name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);
        $data['enabled'] = $request->boolean('enabled');

        // A session is one paired phone: it belongs to ONE club. Another club's
        // session, or the platform's own, can never be claimed from here.
        if (! $whatsapp->sessionIsFree($club, $data['session_name'])) {
            $message = __('admin.whatsapp_session_taken');

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message, 'errors' => ['session_name' => [$message]]], 422)
                : back()->withErrors(['session_name' => $message]);
        }

        $whatsapp->save($club, $data);

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => __('admin.whatsapp_saved'),
                'settings' => $whatsapp->adminSettings($club),
            ]);
        }

        return back()->with('success', __('admin.whatsapp_saved'));
    }

    public function testWhatsAppConnection(Tenant $club, \App\Clubs\Services\ClubWhatsAppManager $whatsapp)
    {
        $this->authorizeClub($club);

        $result = $whatsapp->probe($club);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function sendTestWhatsAppMessage(\Illuminate\Http\Request $request, Tenant $club, \App\Clubs\Services\ClubWhatsAppManager $whatsapp)
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
        $oldSlug = $club->slug;

        // owner_name / owner_email are not editable here: the owner is a member
        // (ownership transfer), and federation matching reads these columns.
        $data = $request->only([
            'club_name', 'slogan', 'description', 'enrollment_fee', 'registration_fee',
            'registration_terms', 'registration_requirements',
            'commercial_reg_number', 'vat_reg_number', 'vat_percentage',
            'email', 'country', 'currency', 'timezone', 'slug', 'address',
            'gps_lat', 'gps_long', 'maps_url',
        ]);

        // Money columns are NOT NULL: a cleared field means zero, not a crash.
        foreach (['enrollment_fee', 'registration_fee', 'vat_percentage'] as $money) {
            if (array_key_exists($money, $data) && blank($data[$money])) {
                $data[$money] = 0;
            }
        }

        // A Google Maps link with no pin behind it: the form moves the pin from the
        // link in the browser, but when that script did not run the link was saved
        // and the club stayed off every map. Read the link here as well — when no
        // pin was sent, or the link changed and the pin did not move with it.
        $pinUnread = false;
        if (! empty($data['maps_url'])) {
            $noPin = blank($data['gps_lat'] ?? null) || blank($data['gps_long'] ?? null);
            $pinStale = $data['maps_url'] !== $club->maps_url
                && (string) ($data['gps_lat'] ?? '') === (string) $club->gps_lat
                && (string) ($data['gps_long'] ?? '') === (string) $club->gps_long;
            if ($noPin || $pinStale) {
                if ($at = \App\Support\MapLink::resolve($data['maps_url'])) {
                    $data['gps_lat'] = $at['lat'];
                    $data['gps_long'] = $at['lng'];
                } elseif ($noPin) {
                    $pinUnread = true;
                }
            }
        }

        if ($request->filled('phone_code') || $request->filled('phone_number')) {
            $data['phone'] = ['code' => $request->phone_code, 'number' => $request->phone_number];
        }

        // Email-verification switch — only touched when the details form actually
        // submits it, so other partial updates never flip it. An unchecked toggle
        // posts nothing, hence the hidden-field-backed boolean() read.
        if ($request->has('require_email_verification')) {
            $data['require_email_verification'] = $request->boolean('require_email_verification');
        }

        // Only the keys the request allows (UpdateClubRequest) — never whatsapp.
        if ($request->has('settings')) {
            $data['settings'] = array_merge($club->settings ?? [], (array) $request->validated('settings', []));
        }

        // Contact source switch — only touched when the details form submits it.
        // The club's own email/phone are kept either way, so switching back
        // restores them.
        if ($request->has('use_owner_contact')) {
            $data['settings'] = array_merge($data['settings'] ?? $club->settings ?? [], [
                'contact_source' => $request->boolean('use_owner_contact') ? 'owner' : 'club',
            ]);
        }

        // Pictures: every file was validated by UpdateClubRequest before we got
        // here. New files are written first; the old ones are deleted only after
        // the row points at the new ones — a failure never leaves a club with a
        // path to a file that is gone.
        $stored = [];
        $retire = [];
        foreach (['logo', 'favicon', 'cover_image', 'registration_splash_image'] as $field) {
            $path = null;
            if ($request->filled($field) && is_string($request->input($field)) && str_starts_with($request->input($field), 'data:image')) {
                $path = $this->storeBase64Image($request->input($field), 'clubs/'.$clubId.'/branding', $field.'_'.time());
            } elseif ($request->hasFile($field)) {
                // Stored at original resolution (no downscaling).
                $path = $request->file($field)->store('clubs/'.$clubId.'/branding', 'public') ?: null;
            } elseif ($request->boolean('remove_'.$field) && $club->$field) {
                $data[$field] = null;
                $retire[] = $club->$field;

                continue;
            }
            if ($path) {
                $stored[] = $path;
                $data[$field] = $path;
                if ($club->$field && $club->$field !== $path) {
                    $retire[] = $club->$field;
                }
            }
        }

        try {
            DB::transaction(function () use ($club, $data, $request) {
                $club->update($data);

                $this->applyTranslations($club, $request);

                // Social links are synced only by a form that rendered them
                // (social_links_present=1). A request that never showed the rows
                // must not wipe them.
                if ($request->boolean('social_links_present')) {
                    $icons = $club->socialLinks()->pluck('icon', 'platform');
                    $club->socialLinks()->delete();
                    foreach (array_values((array) $request->input('social_links', [])) as $i => $link) {
                        if (! empty($link['platform']) && ! empty($link['url'])) {
                            $club->socialLinks()->create([
                                'platform' => $link['platform'],
                                'url' => $link['url'],
                                'icon' => $icons[$link['platform']] ?? null,
                                'display_order' => $i,
                            ]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            // Nothing was saved: the files written for this attempt go too.
            foreach ($stored as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        foreach ($retire as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        // A renamed slug moves the page itself: go to the new address, never back
        // to the old one (which no longer exists). The form may name the panel or
        // tab it was saved from, so the admin lands where they were.
        $club->refresh();

        // The details pages save in place (No-Reload): they get the saved club
        // back, and a new address only when the slug moved the page.
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.club_details_updated'),
                'warning' => $pinUnread ? __('shared.map_link_not_found') : null,
                'redirect' => $club->slug !== $oldSlug ? route('admin.club.details', $club->slug) : null,
                'club' => [
                    'slug' => $club->slug,
                    'club_name' => $club->club_name,
                    'logo' => $club->logo ? file_url($club->logo) : null,
                    'favicon' => $club->favicon ? file_url($club->favicon) : null,
                    'cover_image' => $club->cover_image ? file_url($club->cover_image) : null,
                    'registration_splash_image' => $club->registration_splash_image ? file_url($club->registration_splash_image) : null,
                    'gps_lat' => $club->gps_lat,
                    'gps_long' => $club->gps_long,
                    'maps_url' => $club->maps_url,
                ],
            ]);
        }

        $panel = preg_match('/^[a-z0-9-]{1,30}$/', (string) $request->input('return_panel')) ? '#'.$request->input('return_panel') : '';
        $to = $club->slug !== $oldSlug || $panel !== ''
            ? redirect()->to(route('admin.club.details', $club->slug).$panel)
            : back();

        $to = $to->with('success', __('admin.club_details_updated'));
        if ($pinUnread) {
            $to = $to->with('warning', __('shared.map_link_not_found'));
        }

        return $to;
    }

    /**
     * Delete club
     */
    public function destroy(\Illuminate\Http\Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        // Deleting the club is the owner's call, not a club admin's.
        $this->authorizeClubOwnerAction($club);

        // A co-owned club is every owner's to give up, not one owner's.
        if ($this->isCoOwned($club)) {
            return back()->with('error', __('admin.club_co_owned_delete'));
        }

        // The typed-name check is enforced here too, not only in the browser.
        if (trim((string) $request->input('confirm_name')) !== trim((string) $club->club_name)) {
            return back()->with('error', __('admin.club_delete_name_mismatch'));
        }

        // The club is SOFT-deleted (it can be restored), so its files stay: a
        // restored club with its pictures gone would be a broken club. Purging
        // the files belongs to a force-delete.
        $club->delete();

        // Only the platform admin has the platform club list; an owner goes home.
        $home = \Illuminate\Support\Facades\Auth::user()?->isSuperAdmin() ? route('admin.platform.clubs') : route('me.home');

        return redirect()->to($home)->with('success', __('admin.club_deleted'));
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
        $this->authorizeClubOwnerAction($club);
        if ($refusal = $this->refuseIfCoOwned($club)) {
            return $refusal;
        }

        $newOwner = null;
        if ($request->filled('email')) {
            $newOwner = User::withTrashed()->where('email', $request->email)->first();
        }

        // A deleted account is never revived (and its password reset) from here:
        // that would let a club owner take over somebody else's old account.
        if ($newOwner && $newOwner->trashed()) {
            return response()->json(['success' => false, 'message' => __('admin.owner_email_deleted_account')], 422);
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
                'blood_type' => $request->blood_type ?? 'Unknown',   // column is NOT NULL (default 'Unknown'); the form makes it optional
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

        ClubMemberSubscription::firstOrCreate([
            'tenant_id' => $club->id,
            'type' => 'owner',
            'user_id' => $newOwner->id,
        ], [
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
        $this->authorizeClubOwnerAction($club);
        if ($refusal = $this->refuseIfCoOwned($club)) {
            return $refusal;
        }

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

    /** More than one owner on record (App\Parties)? Always false while PARTIES_ENABLED is off. */
    private function isCoOwned(Tenant $club): bool
    {
        // Never the reason a page fails: if the records cannot be read, the club
        // is treated as single-owned, exactly as before the records existed.
        return config('parties.enabled')
            && rescue(fn () => app(\App\Parties\Relations::class)->owners($club)->count() > 1, false, report: true);
    }

    /**
     * The single-owner transfer would flatten a co-owned club to one owner and
     * silently take everyone else's share away — so it refuses, and the owners
     * decide through the owners panel instead.
     */
    private function refuseIfCoOwned(Tenant $club): ?\Illuminate\Http\JsonResponse
    {
        return $this->isCoOwned($club)
            ? response()->json(['success' => false, 'message' => __('parties::parties.err_co_owned')], 422)
            : null;
    }
}
