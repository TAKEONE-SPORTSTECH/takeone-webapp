<?php

namespace App\Clubs\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise before validating: a slug is lower-case and trimmed, and a
     * legacy full country name ("Bahrain") becomes its ISO code, so an old
     * row can be saved without re-picking the country.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('slug') && is_string($this->input('slug'))) {
            $merge['slug'] = strtolower(trim($this->input('slug')));
        }
        // Clubs store their country as an upper-case ISO code ("BH").
        $country = $this->input('country');
        if (is_string($country) && trim($country) !== '') {
            $country = trim($country);
            $merge['country'] = strtoupper(strlen($country) > 2 ? (\App\Support\Countries::iso2($country) ?? $country) : $country);
        }
        if ($merge) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $club = $this->route('club');
        $clubId = is_object($club) ? $club->id : null;

        return [
            'club_name' => 'required|string|max:255',
            'slogan' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'registration_terms' => 'nullable|string|max:50000',
            'registration_requirements' => 'nullable|string|max:50000',
            'require_email_verification' => 'nullable|boolean',
            'registration_fee' => 'nullable|numeric|min:0',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'commercial_reg_number' => 'nullable|string|max:100',
            'vat_reg_number' => 'nullable|string|max:100',
            'vat_percentage' => 'nullable|numeric|min:0|max:100',
            'email' => 'nullable|email|max:255',
            'use_owner_contact' => 'nullable|boolean',
            // These columns are NOT NULL: when the form sends them they must be set.
            'country' => 'sometimes|required|string|size:2|alpha',
            'phone_code' => 'nullable|string|max:10',
            'phone_number' => 'nullable|string|max:20',
            'currency' => 'sometimes|required|string|size:3|alpha',
            'timezone' => 'sometimes|required|string|max:50|timezone:all',
            // The slug is the club's public address: lower-case words joined by
            // hyphens, never all digits (a number would resolve as a club ID),
            // and unique across every club including deleted ones.
            'slug' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'not_regex:/^\d+$/',
                \Illuminate\Validation\Rule::unique('tenants', 'slug')->ignore($clubId)],
            'address' => 'nullable|string|max:500',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_long' => 'nullable|numeric|between:-180,180',
            // Only a real Google Maps link (App\Support\MapLink) — the map pin follows it.
            'maps_url' => ['nullable', 'string', 'max:500', function ($attr, $value, $fail) {
                if (! \App\Support\MapLink::isGoogle((string) $value)) {
                    $fail(__('shared.map_link_invalid'));
                }
            }],
            // A picture arrives either as a cropped data-URI (≈ 8 MB of image
            // once decoded) or as a file; the controller decides which. Files are
            // validated HERE, before anything on disk is touched.
            'logo' => ['nullable', function ($a, $v, $fail) { self::imageInput($a, $v, $fail); }],
            'favicon' => ['nullable', function ($a, $v, $fail) { self::imageInput($a, $v, $fail); }],
            'cover_image' => ['nullable', function ($a, $v, $fail) { self::imageInput($a, $v, $fail); }],
            'registration_splash_image' => ['nullable', function ($a, $v, $fail) { self::imageInput($a, $v, $fail); }],
            'remove_logo' => 'nullable|boolean',
            'remove_favicon' => 'nullable|boolean',
            'remove_cover_image' => 'nullable|boolean',
            'remove_registration_splash_image' => 'nullable|boolean',
            'social_links_present' => 'nullable|boolean',
            'translations' => ['nullable', 'array'],
            'translations.*.*' => ['nullable', 'string', 'max:50000'],
            'translations.club_name.*' => ['nullable', 'string', 'max:255'],
            'translations.slogan.*' => ['nullable', 'string', 'max:255'],
            // Only the keys this form owns. WhatsApp has its own endpoint and
            // contact_source is derived from use_owner_contact.
            'settings' => 'nullable|array:member_code_prefix,child_code_prefix,invoice_code_prefix,receipt_code_prefix,expense_code_prefix,specialist_code_prefix,block_explore',
            'settings.*_code_prefix' => 'nullable|string|max:10|regex:/^[A-Za-z0-9-]*$/',
            'settings.block_explore' => 'nullable|boolean',
            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links.*.url|string|max:30|regex:/^[a-z0-9_-]+$/',
            'social_links.*.url' => ['required_with:social_links.*.platform', 'url:http,https', 'max:500'],
        ];
    }

    /** A data-URI image (≤ ~11 MB of text ≈ 8 MB decoded) or an uploaded image file ≤ 8 MB. */
    private static function imageInput(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($value instanceof \Illuminate\Http\UploadedFile) {
            $ok = $value->isValid()
                && in_array(strtolower((string) $value->guessExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
                && $value->getSize() <= 8 * 1024 * 1024;
            if (! $ok) {
                $fail(__('validation.image', ['attribute' => str_replace('_', ' ', $attribute)]));
            }

            return;
        }
        if (is_string($value) && str_starts_with($value, 'data:') && strlen($value) > 11 * 1024 * 1024) {
            $fail(__('validation.max.file', ['attribute' => str_replace('_', ' ', $attribute), 'max' => 8192]));
        }
    }
}
