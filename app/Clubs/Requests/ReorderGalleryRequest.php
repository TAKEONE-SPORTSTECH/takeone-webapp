<?php

namespace App\Clubs\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order' => 'required|array',
            'order.*' => 'integer',
        ];
    }
}
