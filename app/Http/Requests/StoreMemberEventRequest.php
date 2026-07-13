<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:150',
            'event_date' => 'required|date',
            'location' => 'nullable|string|max:150',
            'role' => 'nullable|string|max:80',
            'result' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
