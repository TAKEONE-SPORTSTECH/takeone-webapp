<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'              => 'required|string|max:255',
            'date'               => 'required|date',
            'end_date'           => 'nullable|date|after_or_equal:date',
            'start_time'         => 'required',
            'end_time'           => 'nullable',
            'location'           => 'nullable|string|max:255',
            'level'              => 'nullable|string|max:255',
            'description'        => 'nullable|string',
            'max_capacity'       => 'nullable|integer|min:1',
            'cancel_within_days' => 'nullable|integer|min:1|max:365',
            'tags'               => 'nullable|string',
            'color'              => 'nullable|string|max:20',
        ];
    }
}
