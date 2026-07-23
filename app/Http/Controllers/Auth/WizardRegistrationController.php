<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Mail\WizardOtpMail;
use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\ClubTransaction;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelationship;
use App\Services\SubscriptionService;
use App\Support\ClubCache;
use App\Traits\StoresBase64Images;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WizardRegistrationController extends Controller
{
    use StoresBase64Images;

    public function packages(Request $request)
    {
        $request->validate([
            'club_slug' => 'required|string',
            'birthdate' => 'required|date',
            'gender' => 'required|in:Male,Female',
        ]);

        $tenant = Tenant::where('slug', $request->club_slug)->firstOrFail();
        // Parse from the birthdate direction so the result is always a positive age.
        $age = \Carbon\Carbon::parse($request->birthdate)->age;

        // Map single-char codes used by the wizard ('m'/'f') to the full words stored in the DB.
        $genderValue = match ($request->gender) {
            'm' => 'male',
            'f' => 'female',
            default => $request->gender,
        };

        $packages = ClubPackage::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where(function ($q) use ($age) {
                $q->whereNull('age_min')->orWhere('age_min', '<=', $age);
            })
            ->where(function ($q) use ($age) {
                $q->whereNull('age_max')->orWhere('age_max', '>=', $age);
            })
            ->where(function ($q) use ($genderValue) {
                // null   → no restriction (open to all)
                // mixed  → open to all genders
                // male / female → specific gender only
                $q->whereNull('gender')
                    ->orWhere('gender', 'mixed')
                    ->orWhere('gender', $genderValue);
            })
            ->with('activities.equipment')
            ->get(['id', 'tenant_id', 'name', 'price', 'registration_fee', 'duration_months', 'description', 'type', 'gender', 'age_min', 'age_max', 'updated_at']);

        // Equipment ownership is only known once the registrant has proven who they
        // are (returning members via OTP). For brand-new signups nothing is owned.
        $userId = null;
        if ($request->filled('user_id') && $this->sessionHasVerified((int) $request->user_id)) {
            $userId = (int) $request->user_id;
        }
        app(\App\Services\RegistrationCostService::class)->attachEquipmentToPackages($packages, $tenant->id, $userId);

        return response()->json([
            'packages' => $packages,
            'enrollment_fee' => (float) ($tenant->enrollment_fee ?? 0),
            'registration_fee' => (float) ($tenant->registration_fee ?? 0),
            'currency' => $tenant->currency,
        ]);
    }

    public function uploadTemp(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,heic,webp,pdf',
            'type' => 'required|in:profile_photo,cpr_image',
        ]);

        $sessionId = session()->getId();
        $dir = "temp/wizard/{$sessionId}";
        $ext = $request->file('file')->getClientOriginalExtension();
        $filename = Str::uuid().'.'.$ext;

        $request->file('file')->storeAs($dir, $filename, 'public');

        return response()->json([
            'success' => true,
            'path' => "{$dir}/{$filename}",
            'url' => Storage::disk('public')->url("{$dir}/{$filename}"),
        ]);
    }

    /**
     * Smart pre-check used between step 2 → step 3 of the wizard.
     *
     * SECURITY: this endpoint is fully unauthenticated. It must NEVER return the
     * linked relatives' details (names / birthdates of minors) based only on a
     * matching email or phone — that is an IDOR + account-enumeration oracle that
     * leaks PII of children to anyone who guesses a contact. Disclosing family or
     * reusing an existing account is gated behind proof of control of the
     * email/phone (OTP / magic link); until that gate is implemented the
     * returning-member import is disabled and this returns nothing identifying.
     *
     * See submit(): an unauthenticated request can likewise never enrol under or
     * reuse an existing account.
     */
    public function lookup(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'mobile_code' => 'nullable|string|max:10',
            'mobile_number' => 'nullable|string|max:20',
            'club_slug' => 'nullable|string',
        ]);

        $user = $this->findExistingUser($request->email, $request->mobile_code, $request->mobile_number);

        // Returning-member flow is only offered when we can prove control by emailing
        // the account's OWN email. No match (or no email on file) → behave exactly
        // like a brand-new signup and disclose nothing.
        if (! $user || ! $user->email) {
            return response()->json(['found' => false]);
        }

        // Issue a short-lived one-time code to the account's verified-on-file email.
        $code = (string) random_int(100000, 999999);
        Cache::put($this->otpKey($user->id), ['code' => $code, 'attempts' => 0], now()->addMinutes(10));

        try {
            Mail::to($user->email)->queue(new WizardOtpMail($code, $user));
        } catch (\Exception $e) {
            \Log::error('Wizard OTP email failed: '.$e->getMessage());

            // Don't leak that the account exists if we couldn't send — fail closed.
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'verification_required' => true,
            'email_hint' => $this->maskEmail($user->email),
        ]);
    }

    /**
     * Verify the emailed OTP. Only on success do we (a) mark the email/phone as
     * proven for this session and (b) disclose the account's linked relatives.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'mobile_code' => 'nullable|string|max:10',
            'mobile_number' => 'nullable|string|max:20',
            'code' => 'required|string|max:10',
            'club_slug' => 'nullable|string',
        ]);

        $user = $this->findExistingUser($request->email, $request->mobile_code, $request->mobile_number);
        if (! $user || ! $user->email) {
            return response()->json(['verified' => false, 'message' => 'Verification failed.'], 422);
        }

        $key = $this->otpKey($user->id);
        $entry = Cache::get($key);
        if (! $entry) {
            return response()->json(['verified' => false, 'message' => 'Your code expired. Please request a new one.'], 422);
        }
        if (($entry['attempts'] ?? 0) >= 5) {
            Cache::forget($key);

            return response()->json(['verified' => false, 'message' => 'Too many attempts. Please request a new code.'], 429);
        }
        if (! hash_equals((string) $entry['code'], trim($request->code))) {
            $entry['attempts'] = ($entry['attempts'] ?? 0) + 1;
            Cache::put($key, $entry, now()->addMinutes(10));

            return response()->json(['verified' => false, 'message' => 'Incorrect code. Please try again.'], 422);
        }

        // Proven. Burn the code and record proof-of-control for this session so
        // submit() may reuse this account.
        Cache::forget($key);
        $verified = session('wizard.verified', []);
        $verified[$user->id] = now()->timestamp;
        session(['wizard.verified' => $verified]);

        $tenant = $request->filled('club_slug')
            ? Tenant::where('slug', $request->club_slug)->first()
            : null;

        $dependents = UserRelationship::where('guardian_user_id', $user->id)
            ->with('dependent:id,name,full_name,gender,birthdate')
            ->get()
            ->map(function ($rel) use ($tenant) {
                $d = $rel->dependent;
                if (! $d) {
                    return null;
                }

                $alreadyMember = $tenant
                    ? ClubMemberSubscription::where('tenant_id', $tenant->id)
                        ->where('user_id', $d->id)
                        ->whereIn('status', ['active', 'pending'])
                        ->exists()
                    : false;

                return [
                    'id' => $d->id,
                    'full_name' => $d->full_name ?: $d->name,
                    'gender' => $d->gender,
                    'birthdate' => $d->birthdate?->format('Y-m-d'),
                    'relationship_type' => $rel->relationship_type,
                    'already_member' => $alreadyMember,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'verified' => true,
            'name' => $user->full_name ?: $user->name,
            'dependents' => $dependents,
        ]);
    }

    /** Per-session cache key for an account's pending OTP. */
    private function otpKey(int $userId): string
    {
        return 'wizard_otp:'.session()->getId().':'.$userId;
    }

    /** Whether THIS session has proven control of the given account (within TTL). */
    private function sessionHasVerified(int $userId): bool
    {
        $ts = session('wizard.verified', [])[$userId] ?? null;

        return $ts && (now()->timestamp - $ts) < 1800;   // 30 min
    }

    /** a***@gmail.com — never reveal the full address to the client. */
    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $masked = mb_substr($name, 0, 1).str_repeat('*', max(1, mb_strlen($name) - 1));

        return $masked.'@'.$domain;
    }

    /**
     * Resolve an existing, non-deleted account by EMAIL only.
     *
     * Deliberately email-keyed, NOT phone-keyed: (1) email is the channel we can
     * actually prove control of via the OTP; (2) phone numbers are NOT unique in
     * the system — families routinely share one — so a phone match would both
     * dead-end legitimate new registrations and risk emailing a code to an
     * unrelated account. The `$code`/`$number` params are accepted for signature
     * compatibility but intentionally ignored for matching.
     */
    private function findExistingUser(?string $email, ?string $code = null, ?string $number = null): ?User
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();
    }

    public function submit(Request $request)
    {
        // Unified flow: the registrant creates (or, if returning, reuses) their own
        // account + profile, then OPTIONALLY adds children. Packages are optional.
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255'],
            'mobile_code' => 'required|string|max:10',
            'mobile_number' => 'required|string|max:20',
            'nationality' => 'required|string|max:255',
            'club_slug' => 'required|string',
            'lang' => 'nullable|in:en,ar',
            'self_gender' => 'required|in:Male,Female',
            'self_birthdate' => 'required|date|before:today',
            'self_packages' => 'nullable|array',
            'self_equipment' => 'nullable|array',
            'self_equipment.*' => 'integer|exists:club_activity_equipment,id',
            'self_equipment_variants' => 'nullable|array',
            'self_equipment_variants.*' => 'nullable|integer|exists:club_product_variants,id',
            'self_owned_equipment' => 'nullable|array',
            'self_owned_equipment.*' => 'integer|exists:club_activity_equipment,id',
            'children' => 'nullable|array',
            'children.*.full_name' => 'required|string|max:255',
            'children.*.gender' => 'required|in:Male,Female',
            'children.*.birthdate' => 'required|date|before:today',
            'children.*.relationship' => 'nullable|in:son,daughter,spouse,sponsor,other',
            'children.*.packages' => 'nullable|array',
            'children.*.equipment' => 'nullable|array',
            'children.*.equipment.*' => 'integer|exists:club_activity_equipment,id',
            'children.*.equipment_variants' => 'nullable|array',
            'children.*.equipment_variants.*' => 'nullable|integer|exists:club_product_variants,id',
            'children.*.owned_equipment' => 'nullable|array',
            'children.*.owned_equipment.*' => 'integer|exists:club_activity_equipment,id',
            'existing_dependents' => 'nullable|array',
            'existing_dependents.*.id' => 'required|integer',
            'existing_dependents.*.packages' => 'nullable|array',
            'existing_dependents.*.equipment' => 'nullable|array',
            'existing_dependents.*.equipment.*' => 'integer|exists:club_activity_equipment,id',
            'existing_dependents.*.equipment_variants' => 'nullable|array',
            'existing_dependents.*.equipment_variants.*' => 'nullable|integer|exists:club_product_variants,id',
            'existing_dependents.*.owned_equipment' => 'nullable|array',
            'existing_dependents.*.owned_equipment.*' => 'integer|exists:club_activity_equipment,id',
            'pay_later' => 'nullable|boolean',
            'payment_proof_base64' => 'nullable|string',
        ]);

        // SECURITY: an existing account may only be reused when THIS session has
        // proven control of its email via the OTP gate (see lookup()/verifyOtp()).
        // Otherwise an unauthenticated request could enrol relatives / create
        // subscriptions under a victim's account just by knowing their email/phone.
        $existingUser = $this->findExistingUser($request->email, $request->mobile_code, $request->mobile_number);
        if ($existingUser && ! $this->sessionHasVerified($existingUser->id)) {
            return response()->json([
                'success' => false,
                'existing_account' => true,
                'message' => 'An account with this email already exists. Please verify it or log in to continue.',
            ], 422);
        }

        // Brand-new account → enforce email uniqueness as usual.
        if (! $existingUser) {
            $request->validate([
                'email' => [\Illuminate\Validation\Rule::unique('users', 'email')->whereNull('deleted_at')],
            ]);
        }

        $tenant = Tenant::where('slug', $request->club_slug)->firstOrFail();

        // Payment proof is uploaded at the end of registration. The member may
        // instead tick "I'll pay later" and continue — in which case the
        // subscriptions stay 'unpaid' until the club collects payment.
        $payLater = $request->boolean('pay_later');
        $proofPath = null;
        if (! $payLater && $request->filled('payment_proof_base64')) {
            // Private disk — proof of payment must never be publicly accessible.
            $proofPath = $this->storeBase64Image(
                $request->input('payment_proof_base64'),
                'payment-proofs',
                'proof_'.time().'_'.uniqid(),
                'local'
            );
        }
        $paymentStatus = (! $payLater && $proofPath) ? 'pending_approval' : 'unpaid';

        DB::beginTransaction();
        try {
            $isReturning = (bool) $existingUser;

            if ($isReturning) {
                // Reuse the existing account — never overwrite their stored profile
                // from a public form, and never log an unauthenticated visitor in.
                $parentUser = $existingUser;
            } else {
                $parentUser = User::create([
                    'name' => $request->full_name,
                    'full_name' => $request->full_name,
                    'email' => $request->email,
                    // Passwordless: no password is collected — accounts authenticate via
                    // email verification + magic login links. Store a random unusable hash
                    // to satisfy the NOT NULL column.
                    'password' => Hash::make(Str::random(40)),
                    'mobile' => ['code' => $request->mobile_code, 'number' => $request->mobile_number],
                    'nationality' => $request->nationality,
                    'gender' => $request->self_gender,
                    'birthdate' => $request->self_birthdate,
                    'health_conditions' => $this->parseHealthConditions($request->self_health_conditions),
                    // Persist the language chosen in the wizard so the whole system
                    // (after they verify / log in via the email link) is in that locale.
                    'locale' => $request->input('lang') ?: 'en',
                ]);
            }

            // One group id ties this whole family submission together.
            $groupId = (string) Str::uuid();

            // The registrant joins the club themselves only if they picked package(s).
            if ($request->self_packages) {
                $this->createSubscriptions($tenant, $parentUser, $request->self_packages, $paymentStatus, $proofPath, $request->input('self_equipment', []), $groupId, $request->input('self_equipment_variants', []), $request->input('self_owned_equipment', []));
            }

            // Enrol existing relatives the registrant chose to include. Re-validate
            // each id against THIS guardian's real relationships — a spoofed id from
            // the client can never enrol someone else's child.
            if ($request->filled('existing_dependents')) {
                $ownDependentIds = UserRelationship::where('guardian_user_id', $parentUser->id)
                    ->pluck('dependent_user_id')
                    ->all();

                foreach ($request->input('existing_dependents') as $dep) {
                    if (! in_array($dep['id'], $ownDependentIds)) {
                        continue;
                    }       // not theirs → ignore
                    if (empty($dep['packages'])) {
                        continue;
                    }
                    $depUser = User::find($dep['id']);
                    if ($depUser) {
                        $this->createSubscriptions($tenant, $depUser, $dep['packages'], $paymentStatus, $proofPath, $dep['equipment'] ?? [], $groupId, $dep['equipment_variants'] ?? [], $dep['owned_equipment'] ?? []);
                    }
                }
            }

            // Optionally register NEW children as dependents under this account.
            foreach (($request->children ?? []) as $childData) {
                $child = User::create([
                    'name' => $childData['full_name'],
                    'full_name' => $childData['full_name'],
                    'email' => null,
                    'password' => Hash::make(Str::random(32)),
                    'gender' => $childData['gender'],
                    'birthdate' => $childData['birthdate'],
                    'nationality' => $childData['nationality'] ?? null,
                    'health_conditions' => $this->parseHealthConditions($childData['health_conditions'] ?? null),
                ]);

                UserRelationship::create([
                    'guardian_user_id' => $parentUser->id,
                    'dependent_user_id' => $child->id,
                    'relationship_type' => $childData['relationship'] ?? 'parent',
                    'is_billing_contact' => true,
                ]);

                if (! empty($childData['packages'])) {
                    $this->createSubscriptions($tenant, $child, $childData['packages'], $paymentStatus, $proofPath, $childData['equipment'] ?? [], $groupId, $childData['equipment_variants'] ?? [], $childData['owned_equipment'] ?? []);
                }
            }

            // NOTE: never grant roles from public registration. The first
            // super-admin is provisioned out-of-band (SuperAdminSeeder /
            // `php artisan` command) during deployment — not here.

            $intended = session('url.intended') ?: route('clubs.explore');

            // A club may switch OFF email verification (escape hatch for a down
            // mail service). For a brand-new account under such a club, mark the
            // email verified on creation and skip the verification email.
            $skipVerification = (! $isReturning) && ! $tenant->require_email_verification;

            if (! $isReturning) {
                if ($skipVerification) {
                    $parentUser->markEmailAsVerified();   // straight in, no email needed
                    $redirect = $intended;
                } else {
                    // Normal path: send the verification email and gate on it.
                    event(new Registered($parentUser));
                    $redirect = route('verification.notice');
                }
                Auth::login($parentUser, true);
            } else {
                // Returning member: NEVER auto-login an unauthenticated visitor into
                // an existing account. Just point them at the login page.
                $redirect = route('login');
            }

            // The welcome email carries the verification link — skip it when this
            // club isn't verifying (it would just be undeliverable noise).
            if ($parentUser->email && ! $skipVerification) {
                try {
                    Mail::to($parentUser->email)->queue(new WelcomeEmail($parentUser, $parentUser, null, $intended));
                } catch (\Exception $e) {
                    \Log::error('Wizard welcome email failed: '.$e->getMessage());
                }
            }

            DB::commit();

            // Tell the club owner + staff a new registration came in (bell + MQTT +
            // push). Only when someone actually enrolled in a package.
            $enrolledCount = ($request->self_packages ? 1 : 0)
                + collect($request->input('existing_dependents', []))->filter(fn ($d) => ! empty($d['packages']))->count()
                + collect($request->children ?? [])->filter(fn ($c) => ! empty($c['packages']))->count();

            if ($enrolledCount > 0) {
                $who = $enrolledCount === 1 ? $parentUser->name : $parentUser->name.' (+'.($enrolledCount - 1).' more)';
                foreach ($tenant->staffUserIds() as $staffId) {
                    \App\Models\UserNotification::notifyUser($staffId, 'new_member', 'New member registration', [
                        'actor_id'     => $parentUser->id,
                        'tenant_id'    => $tenant->id,
                        'subject_type' => 'user',
                        'subject_id'   => $parentUser->id,
                        // Land the admin ON the payment to verify, not on a generic list:
                        // financials focused on this member's outstanding row (desktop
                        // ledger filters to pending; #collect opens the mobile panel).
                        'action_url'   => route('admin.club.financials', $tenant->slug).'?member='.$parentUser->uuid.'#collect',
                        'icon'         => 'bi-person-plus-fill',
                        'context'      => $tenant->club_name,
                        'body'         => $who.' registered at '.$tenant->club_name.'. Review the pending payment to approve.',
                    ]);
                }
            }

            Storage::disk('public')->deleteDirectory('temp/wizard/'.session()->getId());
            session()->forget('wizard.verified');   // one-time: consume the proof

            return response()->json([
                'success' => true,
                'returning' => $isReturning,
                'redirect' => $redirect,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Wizard registration failed: '.$e->getMessage()."\n".$e->getTraceAsString());

            return response()->json(['success' => false, 'message' => 'Registration failed. Please try again.'], 500);
        }
    }

    /**
     * Enrol one person: create their (pending) package subscriptions and fold the
     * first-time registration fee + chosen equipment into the bill.
     *
     * Each package subscription posts an income ClubTransaction at enrolment
     * (category 'subscription', amount = package price) — identical to
     * SubscriptionService::createPending — so wizard registrations appear in the
     * club ledger/financials just like every other join path. Money still owed is
     * tracked separately via amount_due; the registration fee is snapshotted on the
     * subscription and equipment lines are stored as 'pending' (which still counts
     * as "owned" so a later registration auto-skips them).
     *
     * @param  array<int>  $packageIds
     * @param  array<int>  $equipmentIds
     * @param  array<int>  $ownedEquipmentIds  gear ticked "I already have it" — recorded as owned, never billed
     */
    private function createSubscriptions(
        Tenant $tenant,
        User $user,
        array $packageIds,
        string $paymentStatus = 'unpaid',
        ?string $proofPath = null,
        array $equipmentIds = [],
        ?string $groupId = null,
        array $variantMap = [],
        array $ownedEquipmentIds = []
    ): void {
        $costSvc = app(\App\Services\RegistrationCostService::class);
        $subscriptions = app(SubscriptionService::class);

        // Capture first-time status BEFORE the membership row exists.
        $isFirstTime = ! $costSvc->isReturningMember($tenant->id, $user->id);

        // Membership starts 'inactive' — the club activates it on approval.
        // (The memberships.status CHECK constraint only allows active/inactive;
        // approval state itself is tracked on the ClubMemberSubscription below.)
        Membership::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            ['status' => 'inactive']
        );

        $firstSub = null;
        foreach ($packageIds as $packageId) {
            $package = ClubPackage::where('id', $packageId)->where('tenant_id', $tenant->id)->first();
            if (! $package) {
                continue;
            }

            // Returning members may already hold this package — never double-enrol.
            $alreadyEnrolled = ClubMemberSubscription::where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('package_id', $package->id)
                ->whereIn('status', ['active', 'pending'])
                ->exists();
            if ($alreadyEnrolled) {
                continue;
            }

            $sub = ClubMemberSubscription::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'package_id' => $package->id,
                'status' => 'pending',
                'payment_status' => $paymentStatus,
                'amount_due' => $package->price,
                'amount_paid' => 0,
                'start_date' => now()->toDateString(),
                'proof_of_payment' => $proofPath,
                'registration_group_id' => $groupId,
            ]);

            // Post the income line for this package so wizard registrations show up
            // in the club ledger/financials, consistent with every other join path
            // (SubscriptionService::createPending). Payment state lives on the
            // subscription; this row is the billed revenue for the package.
            ClubTransaction::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'subscription_id' => $sub->id,
                'type' => 'income',
                'category' => 'subscription',
                'amount' => $package->price,
                'description' => 'Package: '.$package->name,
                'transaction_date' => now(),
            ]);

            // Affiliation + skills, exactly as SubscriptionService does on every other
            // join path. Without this a club-page registration created no
            // ClubAffiliation at all, so the club never appeared on the member's
            // profile and none of the package's activities became skills.
            $subscriptions->syncAffiliation($tenant, $user->id, $sub, $package);

            $firstSub ??= $sub;
        }

        if (! $firstSub) {
            return;   // nothing enrolled (no valid/new packages) → no fee or gear
        }

        ClubCache::flushStats($tenant->id);
        ClubCache::flushFinancials($tenant->id);

        // Equipment — frozen lines + ownership memory (pending until approved).
        $equipTotal = $costSvc->snapshotEquipment(
            $tenant,
            $user->id,
            $firstSub,
            array_map('intval', $equipmentIds),
            'pending',
            recordIncome: false,
            variantMap: $variantMap
        );

        // Gear the registrant ticked "I already have it" — recorded as owned,
        // never billed. Exclude anything that is also on the charged list.
        $charged = array_map('intval', $equipmentIds);
        $owned = array_values(array_diff(array_map('intval', $ownedEquipmentIds), $charged));
        $costSvc->recordOwnedEquipment($tenant, $user->id, $firstSub, $owned);

        // One-time registration fee (the package price is the enrollment).
        $regFee = 0.0;
        if ($isFirstTime) {
            $firstPkg = ClubPackage::find($packageIds[0] ?? null);
            $regFee = $firstPkg ? $costSvc->effectiveRegistrationFee($firstPkg, $tenant) : 0.0;
        }

        // Fold the joining fee + equipment into what's owed; keep the registration
        // fee snapshotted on the subscription for the itemised breakdown.
        $extra = $regFee + $equipTotal;
        if ($extra > 0) {
            $firstSub->update([
                'registration_fee' => $regFee,
                'amount_due' => (float) $firstSub->amount_due + $extra,
            ]);
        }
    }

    private function moveTempFile(string $tempPath, string $destDir): string
    {
        // The path must be one this session uploaded via uploadTemp() — never
        // trust a client-supplied path that could traverse out of temp/wizard.
        $expectedPrefix = 'temp/wizard/'.session()->getId().'/';
        if (! str_starts_with($tempPath, $expectedPrefix)
            || str_contains($tempPath, '..')
            || str_contains($tempPath, '\\')) {
            abort(422, 'Invalid upload reference.');
        }

        $ext = strtolower(pathinfo($tempPath, PATHINFO_EXTENSION));
        $filename = Str::uuid().'.'.$ext;
        $destPath = "{$destDir}/{$filename}";

        if (Storage::disk('public')->exists($tempPath)) {
            Storage::disk('public')->move($tempPath, $destPath);
        }

        return $destPath;
    }

    private function parseHealthConditions(?string $text): ?array
    {
        if (empty(trim($text ?? ''))) {
            return null;
        }

        return [['condition' => trim($text), 'notes' => '']];
    }
}
