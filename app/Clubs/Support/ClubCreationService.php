<?php

namespace App\Clubs\Support;

use App\Clubs\Requests\StoreClubRequest;
use App\Clubs\Models\ClubBankAccount;
use App\Clubs\Models\ClubSocialLink;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Facades\DB;
use App\Support\StoragePath;
use Illuminate\Support\Str;

/*
 * Shared kernel — deliberately NOT private to a module.
 * Consumed by App\Clubs (ClubApiController), App\Http (BusinessClubController,
 * Admin\CopilotController) and the seeders. More than one vertical creates clubs,
 * so it stays shared rather than becoming a club-private internal.
 */

/**
 * Creates a Tenant (club) + its social links / bank accounts + owner club-admin
 * role assignment, all in one transaction. Shared by the super-admin platform
 * flow (Admin\ClubApiController::store) and the business/chain-owner flow
 * (BusinessClubController::store) — the only difference between those two
 * callers is which user ends up as owner_user_id, decided upstream by
 * StoreClubRequest::prepareForValidation().
 */
class ClubCreationService
{
    use StoresBase64Images;

    public function createFromValidatedRequest(StoreClubRequest $request): Tenant
    {
        $data = $request->validated();

        if ($request->filled('phone_code') && $request->filled('phone_number')) {
            $data['phone'] = [
                'code' => $request->phone_code,
                'number' => $request->phone_number,
            ];
        }

        // Branding lands in this club's own folder from the first file. The row
        // does not exist yet, but the slug is already validated and in $data, so
        // there is no need for the flat `clubs/logos` root these used to use.
        $branding = StoragePath::clubBySlug((string) ($data['slug'] ?? ''), 'branding', $data['country'] ?? null);

        // Branding pictures: only a base64 data-URI from the cropper is a new
        // upload. Any other string is dropped, so a client-supplied path can
        // never become the stored one.
        $prefixes = ['logo' => 'logo_', 'cover_image' => 'cover_', 'registration_splash_image' => 'splash_'];
        foreach ($prefixes as $field => $prefix) {
            $value = (string) $request->input($field, '');
            if ($value !== '' && str_starts_with($value, 'data:image')) {
                $data[$field] = $this->handleBase64Image($value, $branding, $prefix.Str::random(24));
            } else {
                unset($data[$field]);
            }
        }

        // A Google Maps link with no pin: read the pin from the link here too,
        // so the club lands on the map even when the form's script did not run.
        if (! empty($data['maps_url']) && (blank($data['gps_lat'] ?? null) || blank($data['gps_long'] ?? null))
            && ($at = \App\Support\MapLink::resolve($data['maps_url']))) {
            $data['gps_lat'] = $at['lat'];
            $data['gps_long'] = $at['lng'];
        }

        // Only the settings keys this form owns (validated: contact_source).
        unset($data['settings']);
        if (in_array($request->input('settings.contact_source'), ['owner', 'club'], true)) {
            $data['settings'] = ['contact_source' => $request->input('settings.contact_source')];
        }

        // Other-locale copies go through the model's translation mechanism
        // (rich fields sanitised by Tenant::setTranslation), after the row exists.
        $translations = $request->input('translations');
        unset($data['translations']);

        $data['status'] = $request->input('club_status', 'active');
        $data['public_profile_enabled'] = $request->boolean('public_profile_enabled', true);
        $data['social_links'] = $request->input('social_links', []);
        $data['bank_accounts'] = $request->input('bank_accounts', []);

        $club = $this->create($data);

        if (is_array($translations) && $translations !== []) {
            $club->setTranslations($translations)->save();
        }

        return $club;
    }

    /**
     * Create a club from a fully-prepared data array (paths already stored,
     * status/owner already decided). Shared commit path for the request-based
     * callers above and the Copilot draft-confirm flow. Optional `social_links`
     * / `bank_accounts` array keys are peeled off and persisted as relations.
     *
     * @param  array<string,mixed>  $data
     */
    public function create(array $data): Tenant
    {
        $socialLinks = $data['social_links'] ?? [];
        $bankAccounts = $data['bank_accounts'] ?? [];
        unset($data['social_links'], $data['bank_accounts'], $data['phone_code'], $data['phone_number']);

        return DB::transaction(function () use ($data, $socialLinks, $bankAccounts) {
            $club = Tenant::create($data);

            foreach ($socialLinks as $index => $link) {
                if (! empty($link['platform']) && ! empty($link['url'])) {
                    ClubSocialLink::create([
                        'tenant_id' => $club->id,
                        'platform' => $link['platform'],
                        'url' => $link['url'],
                        'display_order' => $index,
                    ]);
                }
            }

            foreach ($bankAccounts as $account) {
                if (! empty($account['bank_name']) && ! empty($account['account_name'])) {
                    ClubBankAccount::create([
                        'tenant_id' => $club->id,
                        'bank_name' => $account['bank_name'],
                        'account_name' => $account['account_name'],
                        'account_number' => $account['account_number'] ?? '', // NOT NULL column
                        'iban' => $account['iban'] ?? null,
                        'swift_code' => $account['swift_code'] ?? null,
                        'benefitpay_account' => $account['benefitpay_account'] ?? null,
                    ]);
                }
            }

            if (! empty($data['owner_user_id'])) {
                User::find($data['owner_user_id'])?->assignRole('club-admin', $club->id);
            }

            return $club;
        });
    }

    /**
     * Validate content + assign a safe extension server-side; reject anything
     * that isn't a real whitelisted image (client-controlled extension is never trusted).
     */
    private function handleBase64Image(string $base64String, string $folder, string $filename): string
    {
        $fullPath = $this->storeBase64Image($base64String, $folder, $filename);
        if ($fullPath === null) {
            throw new \RuntimeException('Invalid or unsupported image.');
        }

        return $fullPath;
    }
}
