<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\PersonFieldRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformMemberRequest extends FormRequest
{
    use PersonFieldRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
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
