<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email',
            'gender' => 'required|in:Male,Female',
            'birthdate' => 'required|date',
            'blood_type' => 'nullable|string|max:10',
            // Centimetres, for the arena VS screen's stat line. Bounds are
            // deliberately wide — this is a human height, not a sport rule.
            'height_cm' => 'nullable|integer|min:50|max:260',
            'nationality' => 'required|string|max:100',
            'relationship_type' => 'required|string|max:50',
            'is_billing_contact' => 'boolean',
        ];
    }
}
