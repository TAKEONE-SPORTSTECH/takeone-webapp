<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string',
            'price'              => 'required|numeric|min:0',
            'duration_months'    => 'required|integer|min:1',
            'gender_restriction' => 'nullable|string|in:mixed,male,female',
            'age_min'            => 'nullable|integer|min:0',
            'age_max'            => 'nullable|integer|min:0',
        ];
    }
}
