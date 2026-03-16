<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TransferOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->input('mode') === 'existing') {
            return [
                'user_id' => 'required|integer|exists:users,id',
            ];
        }

        return [
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8',
        ];
    }
}
