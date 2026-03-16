<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TimelinePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body'      => 'required|string',
            'category'  => 'required|string|max:100',
            'image'     => 'nullable|image|max:5120',
            'posted_at' => 'required|date',
            'status'    => 'required|in:published,draft',
        ];
    }
}
