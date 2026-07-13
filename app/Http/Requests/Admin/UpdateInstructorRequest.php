<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstructorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:255',
            'experience' => 'nullable|integer|min:0',
            'skills' => 'nullable|string',
            'bio' => 'nullable|string',
            'compensation_type' => 'nullable|in:volunteer,paid',
            'wage_amount' => 'nullable|numeric|min:0|required_if:compensation_type,paid',
            'wage_period' => 'nullable|in:monthly,session,hourly|required_if:compensation_type,paid',
            'package_slots' => 'nullable|array',
            'package_slots.*' => 'integer',
            'translations' => ['nullable', 'array'],
            'translations.*.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
