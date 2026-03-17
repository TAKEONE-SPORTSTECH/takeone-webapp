<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_type'   => 'required|in:all,selected',
            'recipient_ids'    => 'required_if:recipient_type,selected|array',
            'recipient_ids.*'  => 'integer|exists:users,id',
            'subject'          => 'required|string|min:3|max:255',
            'message'          => 'required|string|min:10|max:5000',
        ];
    }
}
