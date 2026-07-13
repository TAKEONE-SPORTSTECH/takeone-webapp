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
        return [
            'registrant_type' => 'nullable|in:guardian,child',
            'guardian' => 'required|array',
            'guardian.name' => 'required|string|max:255',
            // Email is optional. When present it must be unique — it's the handle the member
            // uses to claim their account later. No password is collected at the desk; the
            // member sets one on first login via an email link.
            'guardian.email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'guardian.password' => 'nullable|string',
            'guardian.phone' => 'required|string|max:30',
            'guardian.dob' => 'required|date|before:today',
            'guardian.gender' => 'required|in:Male,Female',
            'guardian.nationality' => 'nullable|string|max:100',
            'guardian.countryCode' => 'nullable|string|max:10',
            'guardian.address' => 'nullable|string|max:500',
            'guardian.role' => ['nullable', Rule::in(['club-admin', 'instructor', 'staff', 'moderator'])],
            'guardian.roleLabel' => 'nullable|string|max:100',
            'people' => 'required|array|min:1',
            'people.*.name' => 'required|string|max:255',
            'people.*.dob' => 'required|date',
            'people.*.gender' => 'required|in:Male,Female',
            'people.*.type' => 'required|in:guardian,child',
            // Children carry only a phone number (no email/password); guardians keep theirs on `guardian`.
            'people.*.phone' => 'nullable|string|max:30',
            'people.*.countryCode' => 'nullable|string|max:10',
            'people.*.waiveRegFee' => 'nullable|boolean',
            'people.*.selectedPackageIds' => 'nullable|array',
            'people.*.selectedPackageIds.*' => 'integer|exists:club_packages,id',
            'people.*.selectedEquipmentIds' => 'nullable|array',
            'people.*.selectedEquipmentIds.*' => 'integer|exists:club_activity_equipment,id',
            'people.*.selectedVariants' => 'nullable|array',
            'people.*.selectedVariants.*' => 'nullable|integer|exists:club_product_variants,id',
            'people.*.ownedEquipmentIds' => 'nullable|array',
            'people.*.ownedEquipmentIds.*' => 'integer|exists:club_activity_equipment,id',
            'people.*.relationship' => 'nullable|in:son,daughter,spouse,sponsor,other,child',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
        ];
    }
}
