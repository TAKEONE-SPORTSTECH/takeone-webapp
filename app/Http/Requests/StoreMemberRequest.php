<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PersonFieldRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
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
            'email' => 'nullable|email|max:255|unique:users,email',
            'gender' => $this->personRule('in:Male,Female'),
            // Never required, of anyone. A birthdate is the field people most
            // often do not have to hand, and an invented one is worse than a blank.
            // The format is still enforced when a value IS given.
            'birthdate' => 'nullable|date',
            'blood_type' => 'nullable|string|max:10',
            // Centimetres, for the arena VS screen's stat line. Bounds are
            // deliberately wide — this is a human height, not a sport rule.
            'height_cm' => 'nullable|integer|min:50|max:260',
            'nationality' => $this->personRule('string|max:100'),
            'relationship_type' => 'required|string|max:50',
            'is_billing_contact' => 'boolean',
        ];
    }
}
