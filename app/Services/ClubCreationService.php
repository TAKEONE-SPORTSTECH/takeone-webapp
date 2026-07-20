<?php

namespace App\Services;

use App\Http\Requests\Admin\StoreClubRequest;
use App\Models\ClubBankAccount;
use App\Models\ClubSocialLink;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Facades\DB;

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

        if ($request->filled('logo') && str_starts_with($request->logo, 'data:image')) {
            $data['logo'] = $this->handleBase64Image($request->logo, 'clubs/logos', 'logo_'.time());
        }

        if ($request->filled('cover_image') && str_starts_with($request->cover_image, 'data:image')) {
            $data['cover_image'] = $this->handleBase64Image($request->cover_image, 'clubs/covers', 'cover_'.time());
        }

        $data['status'] = $request->input('club_status', 'active');
        $data['public_profile_enabled'] = $request->boolean('public_profile_enabled', true);
        $data['social_links'] = $request->input('social_links', []);
        $data['bank_accounts'] = $request->input('bank_accounts', []);

        return $this->create($data);
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
                        'account_number' => $account['account_number'] ?? null,
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
