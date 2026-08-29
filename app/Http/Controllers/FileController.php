<?php

namespace App\Http\Controllers;

use App\Support\FileAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The single door to stored files.
 *
 * Every upload the product holds lives under one root and is reached through
 * here. Nothing is served because of where it sits on disk — `FileAccess`
 * decides, in code, whether this viewer may read this path, and it denies
 * anything it has not been taught about.
 *
 * This replaces the `public/storage` symlink, which granted access by folder:
 * a file placed in the wrong directory was world-readable, and no amount of
 * checking elsewhere in the app could take that back.
 */
class FileController extends Controller
{
    public function show(Request $request, string $path)
    {
        $path = ltrim($path, '/');

        // Refused before the disk is touched, and with the same answer whether
        // the file is missing or forbidden — a different reply for "exists but
        // denied" maps which files are real.
        abort_unless(FileAccess::allows($path, Auth::user()), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Disposition' => 'inline',
            // These are per-viewer decisions, so a shared cache must not hold
            // one. The browser may keep its own copy briefly.
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
