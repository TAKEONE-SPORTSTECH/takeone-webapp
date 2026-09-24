<?php

namespace App\Clubs\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveYoutubeUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'youtube_url' => 'nullable|url|max:500',
        ];
    }
}
