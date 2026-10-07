<?php

namespace App\Clubs\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClubRequest extends FormRequest
{
    /**
     * Every platform the club forms offer — the popup's own short list plus the
     * club details page's longer one, so a link saved on either survives a save
     * from the other.
     */
    public const SOCIAL_PLATFORMS = [
        'facebook', 'instagram', 'twitter', 'tiktok', 'youtube', 'whatsapp', 'website',
        'linkedin', 'snapchat', 'telegram', 'discord', 'reddit', 'pinterest', 'twitch',
        'github', 'spotify', 'skype', 'slack', 'medium', 'vimeo', 'messenger', 'wechat', 'line',
    ];

    /** Lowercase letters, digits and single hyphens; never all digits (a number resolves as a club ID). */
    public const SLUG_FORMAT = ['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'not_regex:/^\d+$/'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The business-dashboard "Create Club" flow (route business.clubs.store)
     * never trusts a client-supplied owner — the acting business owner always
     * becomes the club's owner, which also auto-links the club to their chain
     * via Tenant::booted()'s creating hook. The super-admin platform flow
     * (platform.clubs.store) is unaffected and still picks an arbitrary owner
     * via its own picker UI.
     */
    protected function prepareForValidation(): void
    {
        if ($this->routeIs('business.clubs.store')) {
            $this->merge(['owner_user_id' => auth()->id()]);
        }
    }

    public function rules(): array
    {
        return [
            'owner_user_id' => 'required|exists:users,id',
            'club_name' => 'required|string|max:255',
            'slug' => array_merge(['required', 'string', 'max:255'], self::SLUG_FORMAT, ['unique:tenants,slug']),
        ] + self::modalRules();
    }

    /**
     * The club popup's fields, shared by create (this request) and the popup's
     * update (UpdateClubApiRequest).
     *
     * @return array<string, mixed>
     */
    public static function modalRules(): array
    {
        return [
            'slogan' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'registration_requirements' => 'nullable|string|max:50000',
            'registration_terms' => 'nullable|string|max:50000',
            'translations' => 'nullable|array',
            'translations.*' => 'nullable|array',
            'translations.*.*' => 'nullable|string|max:50000',
            'established_date' => 'nullable|date',
            'commercial_reg_number' => 'nullable|string|max:100',
            'vat_reg_number' => 'nullable|string|max:100',
            'vat_percentage' => 'nullable|numeric|min:0|max:100',
            'email' => 'nullable|email|max:255',
            'phone_code' => 'nullable|string|max:10',
            'phone_number' => 'nullable|string|max:20',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:3',
            'address' => 'nullable|string|max:500',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_long' => 'nullable|numeric|between:-180,180',
            // Only a real Google Maps link (App\Support\MapLink) — the map pin follows it.
            'maps_url' => ['nullable', 'string', 'max:500', function ($attr, $value, $fail) {
                if (! \App\Support\MapLink::isGoogle((string) $value)) {
                    $fail(__('shared.map_link_invalid'));
                }
            }],
            'registration_fee' => 'nullable|numeric|min:0',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'club_status' => 'nullable|in:active,inactive,pending',
            'public_profile_enabled' => 'nullable|boolean',
            // A base64 data-URI from the cropper, or the path already on file.
            // Only a data-URI is ever stored (bytes checked by StoresBase64Images).
            'logo' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'registration_splash_image' => 'nullable|string',
            // Only the keys this form owns.
            'settings' => 'nullable|array:contact_source',
            'settings.contact_source' => 'nullable|in:owner,club',
            'social_links' => 'nullable|array|max:30',
            'social_links.*.platform' => ['required_with:social_links.*.url', 'string', 'in:'.implode(',', self::SOCIAL_PLATFORMS)],
            'social_links.*.url' => ['required_with:social_links.*.platform', 'url:http,https', 'max:500'],
            'bank_accounts' => 'nullable|array|max:20',
            'bank_accounts.*.bank_name' => 'required_with:bank_accounts|string|max:255',
            'bank_accounts.*.account_name' => 'required_with:bank_accounts|string|max:255',
            'bank_accounts.*.account_number' => 'required_with:bank_accounts|string|max:64', // NOT NULL column (and the model's encryptor turns '' into null)
            'bank_accounts.*.iban' => 'nullable|string|max:64',
            'bank_accounts.*.swift_code' => 'nullable|string|max:20',
            'bank_accounts.*.benefitpay_account' => 'nullable|string|max:100',
        ];
    }
}
