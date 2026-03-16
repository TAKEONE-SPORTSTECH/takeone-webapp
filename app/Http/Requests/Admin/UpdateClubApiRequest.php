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
            'owner_user_id'          => 'required|exists:users,id',
            'club_name'              => 'required|string|max:255',
            'slug'                   => 'required|string|max:255|unique:tenants,slug,' . $clubId,
            'slogan'                 => 'nullable|string|max:100',
            'description'            => 'nullable|string|max:1000',
            'established_date'       => 'nullable|date',
            'commercial_reg_number'  => 'nullable|string|max:100',
            'vat_reg_number'         => 'nullable|string|max:100',
            'vat_percentage'         => 'nullable|numeric|min:0|max:100',
            'email'                  => 'nullable|email',
            'phone_code'             => 'nullable|string',
            'phone_number'           => 'nullable|string',
            'currency'               => 'nullable|string|max:3',
            'timezone'               => 'nullable|string',
            'country'                => 'nullable|string',
            'address'                => 'nullable|string',
            'gps_lat'                => 'nullable|numeric|between:-90,90',
            'gps_long'               => 'nullable|numeric|between:-180,180',
            'enrollment_fee'         => 'nullable|numeric|min:0',
            'club_status'            => 'nullable|in:active,inactive,pending',
            'public_profile_enabled' => 'nullable|boolean',
            'logo'                   => 'nullable|image|max:2048',
            'cover_image'            => 'nullable|image|max:2048',
            'social_links'           => 'nullable|array',
            'bank_accounts'          => 'nullable|array',
        ];
    }
}
