<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => 'required|string|max:100',
            'url'      => 'required|string|max:500',
            'icon'     => 'nullable|string|max:50',
        ];
    }
}
