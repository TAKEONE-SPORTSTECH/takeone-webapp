<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'target_value' => 'required|numeric|min:0',
            'current_progress_value' => 'nullable|numeric|min:0',
            'unit' => 'required|string|max:30',
            'start_date' => 'nullable|date',
            'target_date' => 'required|date|after_or_equal:today',
            'priority_level' => 'nullable|in:low,medium,high',
            'icon_type' => 'nullable|string|max:40',
        ];
    }
}
