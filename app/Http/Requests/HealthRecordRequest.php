<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HealthRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recorded_at'           => 'required|date',
            'height'                => 'nullable|numeric|min:50|max:250',
            'weight'                => 'nullable|numeric|min:0|max:999.9',
            'body_fat_percentage'   => 'nullable|numeric|min:0|max:100',
            'bmi'                   => 'nullable|numeric|min:0|max:100',
            'body_water_percentage' => 'nullable|numeric|min:0|max:100',
            'muscle_mass'           => 'nullable|numeric|min:0|max:999.9',
            'bone_mass'             => 'nullable|numeric|min:0|max:999.9',
            'visceral_fat'          => 'nullable|integer|min:0|max:50',
            'bmr'                   => 'nullable|integer|min:0|max:10000',
            'protein_percentage'    => 'nullable|numeric|min:0|max:100',
            'body_age'              => 'nullable|integer|min:0|max:150',
        ];
    }
}
