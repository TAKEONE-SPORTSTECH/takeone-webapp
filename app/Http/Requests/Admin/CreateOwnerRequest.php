<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'   => 'required|string|max:255',
            'email'       => 'nullable|email',
            'gender'      => 'required|in:m,f',
            'birthdate'   => 'required|date',
            'nationality' => 'required|string|max:100',
            'password'    => 'required|string|min:8',
        ];
    }
}
