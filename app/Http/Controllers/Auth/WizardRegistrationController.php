<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class WizardRegistrationController extends Controller
{
    public function packages(Request $request)
    {
        $request->validate([
            'club_slug' => 'required|string',
            'birthdate'  => 'required|date',
            'gender'     => 'required|in:Male,Female',
        ]);

        $tenant = Tenant::where('slug', $request->club_slug)->firstOrFail();
        // Parse from the birthdate direction so the result is always a positive age.
        $age    = \Carbon\Carbon::parse($request->birthdate)->age;

        // Map single-char codes used by the wizard ('m'/'f') to the full words stored in the DB.
        $genderValue = match($request->gender) {
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
            ->get(['id', 'name', 'price', 'duration_months', 'description', 'type', 'gender', 'age_min', 'age_max']);

        return response()->json(['packages' => $packages]);
    }

    public function uploadTemp(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,heic,webp,pdf',
            'type' => 'required|in:profile_photo,cpr_image',
        ]);

        $sessionId = session()->getId();
        $dir       = "temp/wizard/{$sessionId}";
        $ext       = $request->file('file')->getClientOriginalExtension();
        $filename  = Str::uuid() . '.' . $ext;

        $request->file('file')->storeAs($dir, $filename, 'public');

        return response()->json([
            'success' => true,
            'path'    => "{$dir}/{$filename}",
            'url'     => Storage::disk('public')->url("{$dir}/{$filename}"),
        ]);
    }

    public function submit(Request $request)
    {
        $type  = $request->input('registration_type');
        $rules = [
            'full_name'         => 'required|string|max:255',
            'email'             => 'required|email|max:255|unique:users,email',
            'password'          => ['required', Rules\Password::defaults()],
            'mobile_code'       => 'required|string|max:10',
            'mobile_number'     => 'required|string|max:20',
            'nationality'       => 'required|string|max:255',
            'registration_type' => 'required|in:self,kids',
            'club_slug'         => 'required|string',
        ];

        if ($type === 'self') {
            $rules['self_gender']    = 'required|in:Male,Female';
            $rules['self_birthdate'] = 'required|date|before:today';
            $rules['self_packages']  = 'nullable|array';
        } else {
            $rules['children']                = 'required|array|min:1';
            $rules['children.*.full_name']    = 'required|string|max:255';
            $rules['children.*.gender']       = 'required|in:Male,Female';
            $rules['children.*.birthdate']    = 'required|date|before:today';
        }

        $request->validate($rules);

        $tenant = Tenant::where('slug', $request->club_slug)->firstOrFail();

        DB::beginTransaction();
        try {
            $parentUser = User::create([
                'name'        => $request->full_name,
                'full_name'   => $request->full_name,
                'email'       => $request->email,
                'password'    => Hash::make($request->password),
                'mobile'      => ['code' => $request->mobile_code, 'number' => $request->mobile_number],
                'nationality' => $request->nationality,
                'gender'      => $type === 'self' ? $request->self_gender : null,
                'birthdate'   => $type === 'self' ? $request->self_birthdate : null,
                'health_conditions' => $this->parseHealthConditions($request->self_health_conditions),
            ]);

            if ($request->self_profile_photo) {
                $path = $this->moveTempFile($request->self_profile_photo, "profile-pictures/{$parentUser->id}");
                $parentUser->update(['profile_picture' => $path]);
            }

            if ($request->self_cpr_number || $request->self_cpr_image) {
                $imgPath = $request->self_cpr_image
                    ? $this->moveTempFile($request->self_cpr_image, "documents/{$parentUser->id}")
                    : null;
                $parentUser->update(['documents' => [
                    ['type' => 'cpr', 'number' => $request->self_cpr_number ?? '', 'image' => $imgPath],
                ]]);
            }

            if ($type === 'self' && $request->self_packages) {
                $this->createSubscriptions($tenant, $parentUser, $request->self_packages);
            }

            if ($type === 'kids' && $request->children) {
                foreach ($request->children as $childData) {
                    $child = User::create([
                        'name'              => $childData['full_name'],
                        'full_name'         => $childData['full_name'],
                        'email'             => null,
                        'password'          => Hash::make(Str::random(32)),
                        'gender'            => $childData['gender'],
                        'birthdate'         => $childData['birthdate'],
                        'nationality'       => $childData['nationality'] ?? null,
                        'health_conditions' => $this->parseHealthConditions($childData['health_conditions'] ?? null),
                    ]);

                    if (!empty($childData['profile_photo'])) {
                        $path = $this->moveTempFile($childData['profile_photo'], "profile-pictures/{$child->id}");
                        $child->update(['profile_picture' => $path]);
                    }

                    if (!empty($childData['cpr_number']) || !empty($childData['cpr_image'])) {
                        $imgPath = !empty($childData['cpr_image'])
                            ? $this->moveTempFile($childData['cpr_image'], "documents/{$child->id}")
                            : null;
                        $child->update(['documents' => [
                            ['type' => 'cpr', 'number' => $childData['cpr_number'] ?? '', 'image' => $imgPath],
                        ]]);
                    }

                    UserRelationship::create([
                        'guardian_user_id'  => $parentUser->id,
                        'dependent_user_id' => $child->id,
                        'relationship_type' => 'parent',
                        'is_billing_contact' => true,
                    ]);

                    if (!empty($childData['packages'])) {
                        $this->createSubscriptions($tenant, $child, $childData['packages']);
                    }
                }
            }

            // NOTE: never grant roles from public registration. The first
            // super-admin is provisioned out-of-band (SuperAdminSeeder /
            // `php artisan` command) during deployment — not here.

            event(new Registered($parentUser));
            Auth::login($parentUser, true);

            $intended = session('url.intended') ?: route('clubs.explore');

            try {
                Mail::to($parentUser->email)->queue(new WelcomeEmail($parentUser, $parentUser, null, $intended));
            } catch (\Exception $e) {
                \Log::error('Wizard welcome email failed: ' . $e->getMessage());
            }

            DB::commit();

            Storage::disk('public')->deleteDirectory("temp/wizard/" . session()->getId());

            return response()->json([
                'success'  => true,
                'redirect' => route('verification.notice'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Wizard registration failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Registration failed. Please try again.'], 500);
        }
    }

    private function createSubscriptions(Tenant $tenant, User $user, array $packageIds): void
    {
        Membership::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            ['status' => 'pending']
        );

        foreach ($packageIds as $packageId) {
            $package = ClubPackage::where('id', $packageId)->where('tenant_id', $tenant->id)->first();
            if (!$package) continue;

            ClubMemberSubscription::create([
                'tenant_id'      => $tenant->id,
                'user_id'        => $user->id,
                'package_id'     => $package->id,
                'status'         => 'pending',
                'payment_status' => 'unpaid',
                'amount_due'     => $package->price,
                'amount_paid'    => 0,
                'start_date'     => now()->toDateString(),
            ]);
        }
    }

    private function moveTempFile(string $tempPath, string $destDir): string
    {
        // The path must be one this session uploaded via uploadTemp() — never
        // trust a client-supplied path that could traverse out of temp/wizard.
        $expectedPrefix = 'temp/wizard/' . session()->getId() . '/';
        if (! str_starts_with($tempPath, $expectedPrefix)
            || str_contains($tempPath, '..')
            || str_contains($tempPath, '\\')) {
            abort(422, 'Invalid upload reference.');
        }

        $ext      = strtolower(pathinfo($tempPath, PATHINFO_EXTENSION));
        $filename = Str::uuid() . '.' . $ext;
        $destPath = "{$destDir}/{$filename}";

        if (Storage::disk('public')->exists($tempPath)) {
            Storage::disk('public')->move($tempPath, $destPath);
        }

        return $destPath;
    }

    private function parseHealthConditions(?string $text): ?array
    {
        if (empty(trim($text ?? ''))) return null;
        return [['condition' => trim($text), 'notes' => '']];
    }
}
