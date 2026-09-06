<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClubApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clubId = $this->route('club')?->id ?? $this->route('id');

        return [
            'owner_user_id' => 'required|exists:users,id',
            'club_name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug,'.$clubId,
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
            'registration_fee' => 'nullable|numeric|min:0',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'club_status' => 'nullable|in:active,inactive,pending',
            'public_profile_enabled' => 'nullable|boolean',
            // The club modal posts these as a base64 data-URI (the cropper's
            // output) or as the already-stored path when the picture was not
            // touched — never as an uploaded file. `image` therefore rejected
            // every edit, which is why the modal could not be saved at all.
            // The bytes are validated for real by StoresBase64Images.
            'logo' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'registration_splash_image' => 'nullable|string',
            'social_links' => 'nullable|array',
            'bank_accounts' => 'nullable|array',
        ];
    }
}
