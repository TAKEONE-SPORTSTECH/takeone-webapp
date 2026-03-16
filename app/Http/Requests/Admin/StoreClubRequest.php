<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_user_id'             => 'required|exists:users,id',
            'club_name'                 => 'required|string|max:255',
            'slug'                      => 'required|string|max:255|unique:tenants,slug',
            'slogan'                    => 'nullable|string|max:100',
            'description'               => 'nullable|string|max:1000',
            'established_date'          => 'nullable|date',
            'commercial_reg_number'     => 'nullable|string|max:100',
            'vat_reg_number'            => 'nullable|string|max:100',
            'vat_percentage'            => 'nullable|numeric|min:0|max:100',
            'email'                     => 'nullable|email',
            'phone_code'                => 'nullable|string',
            'phone_number'              => 'nullable|string',
            'currency'                  => 'nullable|string|max:3',
            'timezone'                  => 'nullable|string',
            'country'                   => 'nullable|string',
            'address'                   => 'nullable|string',
            'gps_lat'                   => 'nullable|numeric|between:-90,90',
            'gps_long'                  => 'nullable|numeric|between:-180,180',
            'enrollment_fee'            => 'nullable|numeric|min:0',
            'club_status'               => 'nullable|in:active,inactive,pending',
            'public_profile_enabled'    => 'nullable|boolean',
            'logo'                      => 'nullable',
            'cover_image'               => 'nullable',
            'social_links'              => 'nullable|array',
            'social_links.*.platform'   => 'required_with:social_links.*.url|string',
            'social_links.*.url'        => 'required_with:social_links.*.platform|url',
            'bank_accounts'             => 'nullable|array',
            'bank_accounts.*.bank_name'     => 'required_with:bank_accounts|string',
            'bank_accounts.*.account_name'  => 'required_with:bank_accounts|string',
        ];
    }
}
