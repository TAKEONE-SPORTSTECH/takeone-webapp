<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:2000',
            'notes'            => 'nullable|string|max:1000',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'            => 'Activity title is required.',
            'name.max'                 => 'Activity title must not exceed 255 characters.',
            'description.max'          => 'Description must not exceed 2000 characters.',
            'notes.max'                => 'Additional notes must not exceed 1000 characters.',
            'duration_minutes.integer' => 'Duration must be a whole number of minutes.',
            'duration_minutes.min'     => 'Duration must be at least 1 minute.',
            'duration_minutes.max'     => 'Duration cannot exceed 1440 minutes (24 hours).',
        ];
    }
}
