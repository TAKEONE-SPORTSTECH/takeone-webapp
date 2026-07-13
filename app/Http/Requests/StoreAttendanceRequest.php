<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_datetime' => 'required|date',
            'session_type' => 'required|string|max:100',
            'trainer_name' => 'nullable|string|max:100',
            'status' => 'required|in:completed,no_show',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
