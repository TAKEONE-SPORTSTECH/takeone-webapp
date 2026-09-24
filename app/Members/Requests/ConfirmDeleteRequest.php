<?php

namespace App\Members\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_name' => 'required|string',
        ];
    }
}
