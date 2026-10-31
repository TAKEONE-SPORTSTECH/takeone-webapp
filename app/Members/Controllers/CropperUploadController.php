<?php

namespace App\Members\Controllers;

use App\Http\Controllers\Controller;
use App\Support\StoragePath;
use App\Traits\StoresBase64Images;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The guarded replacement for the takeone/cropper package's own `POST /image-upload`.
 *
 * The package registers that route with only the `web` middleware and writes
 * wherever the client says — folder, filename AND extension — so anybody, signed
 * in or not, could put any bytes anywhere in the uploads tree. This answers the
 * same URL and route name (`image.upload`), so the package's handler is never
 * reached, and keeps the same JSON keys (`success`, `path`, `url`).
 *
 * Nothing in the product posts here: every cropper is given its own endpoint
 * through `uploadUrl`. It stays only so an old client gets a safe answer.
 *
 * See docs/uploads.md for the rule this enforces and what is left to do.
 * Constraint: the destination is decided here, never by the request — the
 * `folder` and `filename` fields are accepted for compatibility and ignored.
 */
class CropperUploadController extends Controller
{
    use StoresBase64Images;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image/'],
            // Old clients send these; the format is still checked, the values are not used.
            'folder' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9_\-\/]+$/'],
            'filename' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ]);

        // The uploader's own subtree, under a purpose FileAccess shows to nobody else.
        $path = $this->storeBase64Image(
            $request->string('image')->toString(),
            StoragePath::member($request->user(), 'uploads'),
            (string) Str::uuid(),
        );

        if ($path === null) {
            return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
        }

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => route('file.show', ['path' => $path]),
        ]);
    }
}
