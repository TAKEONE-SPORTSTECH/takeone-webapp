<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstructorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $compensation = [
            'compensation_type' => 'nullable|in:volunteer,paid',
            'wage_amount' => 'nullable|numeric|min:0|required_if:compensation_type,paid',
            'wage_period' => 'nullable|in:monthly,session,hourly|required_if:compensation_type,paid',
            'package_slots' => 'nullable|array',
            'package_slots.*' => 'integer',
            'translations' => ['nullable', 'array'],
            'translations.*.*' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->input('creation_type') === 'new') {
            return $compensation + [
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'name' => 'required|string|max:255',
                'phone' => 'required|string',
                'gender' => 'required|in:Male,Female',
                'birthdate' => 'required|date',
                'nationality' => 'required|string',
                'specialty' => 'nullable|string|max:255',
                'experience' => 'nullable|integer|min:0',
                'skills' => 'nullable|string',
                'bio' => 'nullable|string',
            ];
        }

        return $compensation + [
            'selected_member_id' => 'required|exists:users,id',
            'specialty_existing' => 'nullable|string|max:255',
            'experience_existing' => 'nullable|integer|min:0',
            'skills_existing' => 'nullable|string',
            'bio_existing' => 'nullable|string',
        ];
    }
}
