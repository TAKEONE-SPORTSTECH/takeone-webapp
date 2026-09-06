<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PersonFieldRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
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
            'email' => 'required|email|max:255|unique:users,email,'.Auth::id(),
            'mobile_code' => 'nullable|string|max:5',
            'mobile' => 'nullable|string|max:20',
            'gender' => $this->personRule('in:Male,Female', optional(auth()->user())->id),
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            // Never required, of anyone. A birthdate is the field people most
            // often do not have to hand, and an invented one is worse than a blank.
            // The format is still enforced when a value IS given.
            'birthdate' => 'nullable|date',
            'blood_type' => 'nullable|string|max:10',
            // Centimetres, for the arena VS screen's stat line. Bounds are
            // deliberately wide — this is a human height, not a sport rule.
            'height_cm' => 'nullable|integer|min:50|max:260',
            'nationality' => $this->personRule('string|max:100', optional(auth()->user())->id),
            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links.*.url|string',
            'social_links.*.url' => 'required_with:social_links.*.platform|url',
            'motto' => 'nullable|string|max:500',
            'remove_profile_picture' => 'nullable|boolean',
            'profile_picture_is_public' => 'nullable|boolean',
        ];
    }
}
