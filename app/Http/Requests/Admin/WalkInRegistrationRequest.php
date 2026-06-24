<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalkInRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // A Child is a standalone member with no account — no email/password, just a phone.
        $isChild = $this->input('registrant_type') === 'child';

        return [
            'registrant_type'               => 'nullable|in:guardian,child',
            'guardian'                      => 'required|array',
            'guardian.name'                 => 'required|string|max:255',
            'guardian.email'                => $isChild
                                                ? 'nullable'
                                                : ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'guardian.password'             => $isChild ? 'nullable|string|min:8' : 'required|string|min:8',
            'guardian.phone'                => 'required|string|max:30',
            'guardian.dob'                  => 'required|date|before:today',
            'guardian.gender'               => 'required|in:Male,Female',
            'guardian.nationality'          => 'required|string|max:100',
            'guardian.countryCode'          => 'nullable|string|max:10',
            'guardian.address'              => 'nullable|string|max:500',
            'people'                        => 'required|array|min:1',
            'people.*.name'                 => 'required|string|max:255',
            'people.*.dob'                  => 'required|date',
            'people.*.gender'               => 'required|in:Male,Female',
            'people.*.type'                 => 'required|in:guardian,child',
            // Children carry only a phone number (no email/password); guardians keep theirs on `guardian`.
            'people.*.phone'                => 'nullable|string|max:30',
            'people.*.countryCode'          => 'nullable|string|max:10',
            'people.*.selectedPackageIds'   => 'nullable|array',
            'people.*.selectedPackageIds.*' => 'integer|exists:club_packages,id',
            'discount_type'                 => 'nullable|in:percentage,fixed',
            'discount_value'                => 'nullable|numeric|min:0',
        ];
    }
}
