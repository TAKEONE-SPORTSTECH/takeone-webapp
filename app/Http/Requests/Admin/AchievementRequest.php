<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AchievementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => 'required|string|max:255',
            'type_icon'        => 'nullable|string|max:10',
            'description'      => 'nullable|string|max:2000',
            'location'         => 'nullable|string|max:255',
            'achievement_date' => 'nullable|date',
            'category'         => 'nullable|string|max:255',
            'athletes'         => 'nullable|string',
            'tag'              => 'nullable|string|max:60',
            'status'           => 'required|in:active,inactive',
            'translations'     => ['nullable', 'array'],
            'translations.*.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
