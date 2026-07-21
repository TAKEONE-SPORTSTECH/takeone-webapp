<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level authorization (super-admin / self / guardian) is enforced
        // in the controller; this request only validates shape.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:150',
            'issuer' => 'nullable|string|max:150',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'credential_id' => 'nullable|string|max:120',
            // Only real http(s) links — blocks javascript:/data: URL injection when rendered as an <a>.
            'credential_url' => 'nullable|url|max:300|starts_with:http://,https://',
            // Optional certificate photo/scan as a base64 data-URI; validated/stored via StoresBase64Images.
            'image' => 'nullable|string|starts_with:data:image/',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
