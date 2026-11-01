<?php

namespace App\Members\Requests;

use App\Members\Requests\Concerns\PersonFieldRules;
use App\Members\Support\UniqueEmail;
use Illuminate\Foundation\Http\FormRequest;

class StorePlatformMemberRequest extends FormRequest
{
    use PersonFieldRules;

    public function authorize(): bool
    {
        return true;
    }

    /** New addresses are stored lower-cased and trimmed (UniqueEmail). */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => UniqueEmail::normalise($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', new UniqueEmail],
            'password' => 'required|string|min:8|confirmed',
            'gender' => $this->personRule('in:Male,Female'),
            // Never required, of anyone. A birthdate is the field people most
            // often do not have to hand, and an invented one is worse than a blank.
            // The format is still enforced when a value IS given.
            'birthdate' => 'nullable|date|before:today',
            'nationality' => $this->personRule('string|max:100'),
            'blood_type' => 'nullable|string|max:10',
            'mobile_code' => 'nullable|string|max:10',
            'mobile' => 'nullable|string|max:20',
            'marital_status' => 'nullable|string|max:50',
            'motto' => 'nullable|string|max:500',
        ];
    }
}
