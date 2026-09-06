<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PersonFieldRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyMemberRequest extends FormRequest
{
    use PersonFieldRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,'.$id,
            'mobile_code' => 'nullable|string|max:5',
            'mobile' => 'nullable|string|max:20',
            /*
             * Optional on EDIT, on purpose.
             *
             * These are required when a member is CREATED, where someone is sitting
             * with the person in front of them. On an edit they are not: the imported
             * and federation records this platform holds routinely arrive without a
             * birthdate or a nationality — several already exist in the database with
             * NULL in exactly these columns — and demanding them meant an organiser
             * had to invent a value to correct anything else on the profile. An
             * invented birthdate is worse data than no birthdate.
             *
             * The FORMAT rules stay: a gender is still Male or Female, a birthdate is
             * still a date. Only the demand that they be present is lifted.
             */
            'gender' => $this->personRule('in:Male,Female', (int) $this->route('id')),
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            // Never required, of anyone. A birthdate is the field people most
            // often do not have to hand, and an invented one is worse than a blank.
            // The format is still enforced when a value IS given.
            'birthdate' => 'nullable|date',
            'blood_type' => 'nullable|string|max:10',
            // Centimetres, for the arena VS screen's stat line. Bounds are
            // deliberately wide — this is a human height, not a sport rule.
            'height_cm' => 'nullable|integer|min:50|max:260',
            'nationality' => $this->personRule('string|max:100', (int) $this->route('id')),
            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links.*.url|string',
            'social_links.*.url' => 'required_with:social_links.*.platform|url',
            'motto' => 'nullable|string|max:500',
            'relationship_type' => 'nullable|string|max:50',
            'is_billing_contact' => 'boolean',
            'remove_profile_picture' => 'nullable|boolean',
            'profile_picture_is_public' => 'nullable|boolean',
        ];
    }
}
