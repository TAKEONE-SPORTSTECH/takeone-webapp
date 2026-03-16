<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'address'           => 'nullable|string',
            'gps_lat'           => 'nullable|numeric',
            'gps_long'          => 'nullable|numeric',
            'maps_url'          => 'nullable|url|max:500',
            'is_available'      => 'nullable|boolean',
            'facility_images'   => 'nullable|array',
            'facility_images.*' => 'image|max:4096',
        ];
    }
}
