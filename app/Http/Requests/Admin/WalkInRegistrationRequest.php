<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WalkInRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guardian'                      => 'required|array',
            'guardian.name'                 => 'required|string|max:255',
            'guardian.email'                => 'required|email|max:255|unique:users,email',
            'guardian.password'             => 'required|string|min:8',
            'guardian.phone'                => 'required|string|max:30',
            'guardian.dob'                  => 'required|date|before:today',
            'guardian.gender'               => 'required|in:m,f',
            'guardian.nationality'          => 'required|string|max:100',
            'guardian.countryCode'          => 'nullable|string|max:10',
            'guardian.address'              => 'nullable|string|max:500',
            'people'                        => 'required|array|min:1',
            'people.*.name'                 => 'required|string|max:255',
            'people.*.dob'                  => 'required|date',
            'people.*.gender'               => 'required|in:m,f',
            'people.*.type'                 => 'required|in:guardian,child',
            'people.*.selectedPackageIds'   => 'nullable|array',
            'people.*.selectedPackageIds.*' => 'integer|exists:club_packages,id',
            'discount_type'                 => 'nullable|in:percentage,fixed',
            'discount_value'                => 'nullable|numeric|min:0',
        ];
    }
}
