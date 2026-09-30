<?php

namespace App\Clubs\Requests;

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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'address' => 'nullable|string',
            'gps_lat' => 'nullable|numeric',
            'gps_long' => 'nullable|numeric',
            // Only a real Google Maps link (App\Support\MapLink) — the map pin follows it.
            'maps_url' => ['nullable', 'string', 'max:500', function ($attr, $value, $fail) {
                if (! \App\Support\MapLink::isGoogle((string) $value)) {
                    $fail(__('shared.map_link_invalid'));
                }
            }],
            'is_available' => 'nullable|boolean',
            'facility_images' => 'nullable|array',
            'facility_images.*' => 'image|max:4096',
            'translations' => ['nullable', 'array'],
            'translations.*.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
