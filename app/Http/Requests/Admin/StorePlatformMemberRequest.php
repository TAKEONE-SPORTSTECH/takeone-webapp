<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePlatformMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'      => 'required|string|max:255',
            'email'          => 'required|email|max:255|unique:users,email',
            'password'       => 'required|string|min:8|confirmed',
            'gender'         => 'required|in:m,f',
            'birthdate'      => 'required|date|before:today',
            'nationality'    => 'required|string|max:100',
            'blood_type'     => 'nullable|string|max:10',
            'mobile_code'    => 'nullable|string|max:10',
            'mobile'         => 'nullable|string|max:20',
            'marital_status' => 'nullable|string|max:50',
            'motto'          => 'nullable|string|max:500',
        ];
    }
}
