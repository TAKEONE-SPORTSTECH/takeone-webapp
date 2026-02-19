<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\ClubSocialLink;
use App\Models\ClubBankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ClubApiController extends Controller
{
    /**
     * Get all users for user picker.
     */
    public function getUsers(Request $request)
    {
        $users = User::select('id', 'full_name', 'email', 'mobile', 'profile_picture')
            ->orderBy('full_name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'mobile' => $user->mobile_formatted,
                    'profile_picture' => $user->profile_picture
                        ? asset('storage/' . $user->profile_picture)
                        : null,
                ];
            });

        return response()->json($users);
    }

    /**
     * Get club data for editing.
     */
    public function getClub($id)
    {
        $club = Tenant::with(['owner', 'socialLinks', 'bankAccounts'])
            ->findOrFail($id);

        return response()->json([
            'id' => $club->id,
            'owner_user_id' => $club->owner_user_id,
            'club_name' => $club->club_name,
            'slug' => $club->slug,
            'logo' => $club->logo,
            'cover_image' => $club->cover_image,
            'slogan' => $club->slogan,
            'description' => $club->description,
            'established_date' => $club->established_date,
            'commercial_reg_number' => $club->commercial_reg_number,
            'vat_reg_number' => $club->vat_reg_number,
            'vat_percentage' => $club->vat_percentage,
            'email' => $club->email,
            'phone' => $club->phone,
            'currency' => $club->currency,
            'timezone' => $club->timezone,
            'country' => $club->country,
            'address' => $club->address,
            'gps_lat' => $club->gps_lat,
            'gps_long' => $club->gps_long,
            'enrollment_fee' => $club->enrollment_fee,
            'status' => $club->status ?? 'active',
            'public_profile_enabled' => $club->public_profile_enabled ?? true,
            'owner' => $club->owner ? [
                'id' => $club->owner->id,
                'full_name' => $club->owner->full_name,
                'email' => $club->owner->email,
                'mobile' => $club->owner->mobile_formatted,
                'profile_picture' => $club->owner->profile_picture
                    ? asset('storage/' . $club->owner->profile_picture)
                    : null,
            ] : null,
            'social_links' => $club->socialLinks->map(function ($link) {
                return [
                    'platform' => $link->platform,
                    'url' => $link->url,
                ];
            }),
            'bank_accounts' => $club->bankAccounts->map(function ($account) {
                return [
                    'bank_name' => $account->bank_name,
                    'account_name' => $account->account_name,
                    'account_number' => $account->account_number,
                    'iban' => $account->iban,
                    'swift_code' => $account->swift_code,
                    'benefitpay_account' => $account->benefitpay_account ?? '',
                ];
            }),
        ]);
    }

    /**
     * Check if slug is available.
     */
    public function checkSlug(Request $request)
    {
        $slug = $request->input('slug');
        $clubId = $request->input('club_id');

        $query = Tenant::where('slug', $slug);

        if ($clubId) {
            $query->where('id', '!=', $clubId);
        }

        $exists = $query->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'This slug is already taken' : 'Slug is available'
        ]);
    }

    /**
     * Store a new club.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'owner_user_id' => 'required|exists:users,id',
            'club_name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug',
            'slogan' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'established_date' => 'nullable|date',
            'commercial_reg_number' => 'nullable|string|max:100',
            'vat_reg_number' => 'nullable|string|max:100',
            'vat_percentage' => 'nullable|numeric|min:0|max:100',
            'email' => 'nullable|email',
            'phone_code' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string',
            'country' => 'nullable|string',
            'address' => 'nullable|string',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_long' => 'nullable|numeric|between:-180,180',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'club_status' => 'nullable|in:active,inactive,pending',
            'public_profile_enabled' => 'nullable|boolean',
            'logo' => 'nullable',
            'cover_image' => 'nullable',
            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links.*.url|string',
            'social_links.*.url' => 'required_with:social_links.*.platform|url',
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.bank_name' => 'required_with:bank_accounts|string',
            'bank_accounts.*.account_name' => 'required_with:bank_accounts|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();

            // Handle phone as JSON
            if ($request->filled('phone_code') && $request->filled('phone_number')) {
                $data['phone'] = [
                    'code' => $request->phone_code,
                    'number' => $request->phone_number,
                ];
            }

            // Handle logo upload
            if ($request->filled('logo') && str_starts_with($request->logo, 'data:image')) {
                $data['logo'] = $this->handleBase64Image($request->logo, 'clubs/logos', 'logo_' . time());
            }

            // Handle cover image upload
            if ($request->filled('cover_image') && str_starts_with($request->cover_image, 'data:image')) {
                $data['cover_image'] = $this->handleBase64Image($request->cover_image, 'clubs/covers', 'cover_' . time());
            }

            // Set status
            $data['status'] = $request->input('club_status', 'active');
            $data['public_profile_enabled'] = $request->boolean('public_profile_enabled', true);

            // Create club
            $club = Tenant::create($data);

            // Handle social links
            if ($request->has('social_links')) {
                foreach ($request->social_links as $index => $link) {
                    if (!empty($link['platform']) && !empty($link['url'])) {
                        ClubSocialLink::create([
                            'tenant_id' => $club->id,
                            'platform' => $link['platform'],
                            'url' => $link['url'],
                            'display_order' => $index,
                        ]);
                    }
                }
            }

            // Handle bank accounts
            if ($request->has('bank_accounts')) {
                foreach ($request->bank_accounts as $account) {
                    if (!empty($account['bank_name']) && !empty($account['account_name'])) {
                        ClubBankAccount::create([
                            'tenant_id' => $club->id,
                            'bank_name' => $account['bank_name'],
                            'account_name' => $account['account_name'],
                            'account_number' => $account['account_number'] ?? null,
                            'iban' => $account['iban'] ?? null,
                            'swift_code' => $account['swift_code'] ?? null,
                            'benefitpay_account' => $account['benefitpay_account'] ?? null,
                        ]);
                    }
                }
            }

            // Assign club-admin role to owner
            $owner = User::find($data['owner_user_id']);
            $owner->assignRole('club-admin', $club->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Club created successfully!',
                'club' => $club
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create club: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing club.
     */
    public function update(Request $request, Tenant $club)
    {
        $validator = Validator::make($request->all(), [
            'owner_user_id' => 'required|exists:users,id',
            'club_name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug,' . $club->id,
            'slogan' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'established_date' => 'nullable|date',
            'commercial_reg_number' => 'nullable|string|max:100',
            'vat_reg_number' => 'nullable|string|max:100',
            'vat_percentage' => 'nullable|numeric|min:0|max:100',
            'email' => 'nullable|email',
            'phone_code' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string',
            'country' => 'nullable|string',
            'address' => 'nullable|string',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_long' => 'nullable|numeric|between:-180,180',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'club_status' => 'nullable|in:active,inactive,pending',
            'public_profile_enabled' => 'nullable|boolean',
            'logo' => 'nullable',
            'cover_image' => 'nullable',
            'social_links' => 'nullable|array',
            'bank_accounts' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();

            // Handle phone as JSON
            if ($request->filled('phone_code') && $request->filled('phone_number')) {
                $data['phone'] = [
                    'code' => $request->phone_code,
                    'number' => $request->phone_number,
                ];
            }

            // Handle logo upload
            if ($request->filled('logo') && str_starts_with($request->logo, 'data:image')) {
                if ($club->logo) {
                    Storage::disk('public')->delete($club->logo);
                }
                $data['logo'] = $this->handleBase64Image($request->logo, 'clubs/logos', 'logo_' . time());
            }

            // Handle cover image upload
            if ($request->filled('cover_image') && str_starts_with($request->cover_image, 'data:image')) {
                if ($club->cover_image) {
                    Storage::disk('public')->delete($club->cover_image);
                }
                $data['cover_image'] = $this->handleBase64Image($request->cover_image, 'clubs/covers', 'cover_' . time());
            }

            // Set status
            $data['status'] = $request->input('club_status', 'active');
            $data['public_profile_enabled'] = $request->boolean('public_profile_enabled', true);

            // Update club
            $club->update($data);

            // Update social links
            $club->socialLinks()->delete();
            if ($request->has('social_links')) {
                foreach ($request->social_links as $index => $link) {
                    if (!empty($link['platform']) && !empty($link['url'])) {
                        ClubSocialLink::create([
                            'tenant_id' => $club->id,
                            'platform' => $link['platform'],
                            'url' => $link['url'],
                            'display_order' => $index,
                        ]);
                    }
                }
            }

            // Update bank accounts
            $club->bankAccounts()->delete();
            if ($request->has('bank_accounts')) {
                foreach ($request->bank_accounts as $account) {
                    if (!empty($account['bank_name']) && !empty($account['account_name'])) {
                        ClubBankAccount::create([
                            'tenant_id' => $club->id,
                            'bank_name' => $account['bank_name'],
                            'account_name' => $account['account_name'],
                            'account_number' => $account['account_number'] ?? null,
                            'iban' => $account['iban'] ?? null,
                            'swift_code' => $account['swift_code'] ?? null,
                            'benefitpay_account' => $account['benefitpay_account'] ?? null,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Club updated successfully!',
                'club' => $club
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update club: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle base64 image upload.
     */
    private function handleBase64Image($base64String, $folder, $filename)
    {
        $imageParts = explode(";base64,", $base64String);
        $imageTypeAux = explode("image/", $imageParts[0]);
        $extension = $imageTypeAux[1];
        $imageBinary = base64_decode($imageParts[1]);

        $fullPath = $folder . '/' . $filename . '.' . $extension;
        Storage::disk('public')->put($fullPath, $imageBinary);

        return $fullPath;
    }
}
