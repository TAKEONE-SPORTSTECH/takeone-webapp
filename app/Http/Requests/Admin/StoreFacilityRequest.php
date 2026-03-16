<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'address'      => 'nullable|string|max:500',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'maps_url'     => 'nullable|url|max:500',
            'is_available' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Facility name is required.',
            'name.max'          => 'Facility name must not exceed 255 characters.',
            'description.max'   => 'Description must not exceed 1000 characters.',
            'address.max'       => 'Address must not exceed 500 characters.',
            'latitude.numeric'  => 'Latitude must be a valid number.',
            'latitude.between'  => 'Latitude must be between -90 and 90.',
            'longitude.numeric' => 'Longitude must be a valid number.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
            'maps_url.url'      => 'Please enter a valid URL for the Google Maps link.',
            'maps_url.max'      => 'Google Maps URL must not exceed 500 characters.',
        ];
    }
}
