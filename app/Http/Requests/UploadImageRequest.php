<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Must be a base64 image data-URI. The actual bytes are re-validated
            // server-side (real MIME sniff) in StoresBase64Images::storeBase64Image().
            'image' => ['required', 'string', 'starts_with:data:image/'],
            // Restrict path segments to a safe charset so folder/filename can never
            // introduce a traversal (../) or an unexpected extension.
            'folder' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-\/]+$/'],
            'filename' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ];
    }
}
