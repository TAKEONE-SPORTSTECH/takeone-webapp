<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PerkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'badge'       => 'required|string|max:50',
            'icon'        => 'nullable|string|max:60',
            'bg_from'     => 'nullable|string|max:20',
            'bg_to'       => 'nullable|string|max:20',
            'perk_type'   => 'required|in:code,qr',
            'perk_value'  => 'nullable|string|max:1000',
            'status'      => 'required|in:active,inactive',
            'sort_order'  => 'nullable|integer|min:0',
        ];
    }
}
