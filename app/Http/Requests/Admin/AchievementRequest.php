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
            'short_title'      => 'nullable|string|max:255',
            'type_icon'        => 'nullable|string|max:10',
            'description'      => 'nullable|string|max:2000',
            'location'         => 'nullable|string|max:255',
            'achievement_date' => 'nullable|date',
            'date_label'       => 'nullable|string|max:60',
            'medals_gold'      => 'nullable|integer|min:0',
            'medals_silver'    => 'nullable|integer|min:0',
            'medals_bronze'    => 'nullable|integer|min:0',
            'bouts_count'      => 'nullable|integer|min:0',
            'wins_count'       => 'nullable|integer|min:0',
            'category'         => 'nullable|string|max:255',
            'chips'            => 'nullable|string',
            'athletes'         => 'nullable|string',
            'tag'              => 'required|string|max:60',
            'tag_icon'         => 'nullable|string|max:60',
            'bg_from'          => 'nullable|string|max:20',
            'bg_to'            => 'nullable|string|max:20',
            'status'           => 'required|in:active,inactive',
            'sort_order'       => 'nullable|integer|min:0',
        ];
    }
}
