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
            'name'       => 'nullable|string|max:255',
            'role'       => 'nullable|string|max:255',
            'experience' => 'nullable|integer|min:0',
            'skills'     => 'nullable|string',
            'bio'        => 'nullable|string',
        ];
    }
}
