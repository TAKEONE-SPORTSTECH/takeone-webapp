<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'                 => 'required|string|max:255',
            'email'                     => 'required|email|max:255|unique:users,email,' . Auth::id(),
            'mobile_code'               => 'nullable|string|max:5',
            'mobile'                    => 'nullable|string|max:20',
            'gender'                    => 'required|in:m,f',
            'marital_status'            => 'nullable|in:single,married,divorced,widowed',
            'birthdate'                 => 'required|date',
            'blood_type'                => 'nullable|string|max:10',
            'nationality'               => 'required|string|max:100',
            'social_links'              => 'nullable|array',
            'social_links.*.platform'   => 'required_with:social_links.*.url|string',
            'social_links.*.url'        => 'required_with:social_links.*.platform|url',
            'motto'                     => 'nullable|string|max:500',
            'remove_profile_picture'    => 'nullable|boolean',
            'profile_picture_is_public' => 'nullable|boolean',
        ];
    }
}
