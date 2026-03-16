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
        if ($this->input('creation_type') === 'new') {
            return [
                'email'       => 'required|email|unique:users,email',
                'password'    => 'required|string|min:6',
                'name'        => 'required|string|max:255',
                'phone'       => 'required|string',
                'gender'      => 'required|in:m,f',
                'birthdate'   => 'required|date',
                'nationality' => 'required|string',
                'specialty'   => 'nullable|string|max:255',
                'experience'  => 'nullable|integer|min:0',
                'skills'      => 'nullable|string',
                'bio'         => 'nullable|string',
            ];
        }

        return [
            'selected_member_id'  => 'required|exists:users,id',
            'specialty_existing'  => 'nullable|string|max:255',
            'experience_existing' => 'nullable|integer|min:0',
            'skills_existing'     => 'nullable|string',
            'bio_existing'        => 'nullable|string',
        ];
    }
}
