<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level authorization (super-admin / self / guardian) is enforced
        // in the controller; this request only validates shape.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:150',
            'organization' => 'required|string|max:150',
            'employment_type' => ['nullable', Rule::in(['Full-time', 'Part-time', 'Contract', 'Freelance', 'Volunteer', 'Internship'])],
            'location' => 'nullable|string|max:150',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:2000',
        ];
    }
}
