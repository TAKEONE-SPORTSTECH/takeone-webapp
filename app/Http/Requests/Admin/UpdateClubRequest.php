<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'club_name'                 => 'required|string|max:255',
            'slogan'                    => 'nullable|string|max:255',
            'description'               => 'nullable|string',
            'enrollment_fee'            => 'nullable|numeric|min:0',
            'commercial_reg_number'     => 'nullable|string|max:100',
            'vat_reg_number'            => 'nullable|string|max:100',
            'vat_percentage'            => 'nullable|numeric|min:0|max:100',
            'email'                     => 'nullable|email|max:255',
            'country'                   => 'nullable|string|max:2',
            'phone_code'                => 'nullable|string|max:10',
            'phone_number'              => 'nullable|string|max:20',
            'currency'                  => 'nullable|string|max:3',
            'timezone'                  => 'nullable|string|max:50',
            'slug'                      => 'nullable|string|max:100',
            'address'                   => 'nullable|string|max:500',
            'gps_lat'                   => 'nullable|numeric',
            'gps_long'                  => 'nullable|numeric',
            'maps_url'                  => 'nullable|url|max:500',
            'logo'                      => 'nullable',
            'favicon'                   => 'nullable',
            'cover_image'               => 'nullable',
            'settings'                  => 'nullable|array',
            'social_links'              => 'nullable|array',
            'social_links.*.platform'   => 'required_with:social_links.*.url|string',
            'social_links.*.url'        => 'required_with:social_links.*.platform|url',
        ];
    }
}
