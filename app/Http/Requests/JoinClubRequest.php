<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'club_id' => 'required|exists:tenants,id',
            'registrants' => 'required|array|min:1',
            'registrants.*.type' => 'required|in:self,child',
            'registrants.*.name' => 'required|string|max:255',
            'registrants.*.user_id' => 'nullable',
            'registrants.*.package_id' => 'required|exists:club_packages,id',
            'registrants.*.gender' => 'nullable|string',
            'registrants.*.date_of_birth' => 'nullable|date',
            'registrants.*.equipment' => 'nullable|array',
            'registrants.*.equipment.*' => 'integer',
            'registrants.*.owned_equipment' => 'nullable|array',
            'registrants.*.owned_equipment.*' => 'integer',
            'registrants.*.variants' => 'nullable|array',
            'pay_later' => 'nullable',
            'payment_proof_base64' => 'nullable|string',
        ];
    }
}
