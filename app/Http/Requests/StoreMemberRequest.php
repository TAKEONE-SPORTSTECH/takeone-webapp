<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'           => 'required|string|max:255',
            'email'               => 'nullable|email|max:255|unique:users,email',
            'gender'              => 'required|in:m,f',
            'birthdate'           => 'required|date',
            'blood_type'          => 'nullable|string|max:10',
            'nationality'         => 'required|string|max:100',
            'relationship_type'   => 'required|string|max:50',
            'is_billing_contact'  => 'boolean',
        ];
    }
}
