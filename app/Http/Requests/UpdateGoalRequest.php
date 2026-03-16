<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_progress_value' => 'required|numeric|min:0',
            'status'                 => 'required|in:active,completed',
        ];
    }
}
