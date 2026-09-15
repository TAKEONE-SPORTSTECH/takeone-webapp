<?php

namespace App\Http\Controllers;

use App\Support\FileAccess;
use App\Support\Thumbnails;
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

        /* ── A smaller copy, for the lists that paint it tiny ───────────────
         *
         * Asked for by NAME (`?s=card`), never by pixels: drawing an image is
         * expensive work behind a cheap URL, and a free number would let a
         * stranger fill the disk one request at a time (Part 3 §14). An
         * unknown name is ignored rather than refused — the original is always
         * a correct answer.
         *
         * Authorisation has already happened, above, against the SOURCE path:
         * a rendition of a private photograph is just as private, and there is
         * no second door into it.
         */
        $size = (string) $request->query('s', '');

        if ($size !== '' && Thumbnails::knows($size)) {
            $small = Thumbnails::for($path, $size);

            if ($small !== null) {
                $path = $small;
            }
        }

        $headers = [
            'Content-Disposition' => 'inline',
            // These are per-viewer decisions, so a shared cache must not hold
            // one. The browser may keep its own copy briefly.
            'Cache-Control' => 'private, max-age=600',
        ];

        // The authorisation above is cheap; streaming the bytes through PHP is
        // not, and an image-heavy page ties up one worker per picture. When the
        // web server can do it, PHP names the file and steps out of the way —
        // Apache serves it with sendfile(2), ranges and its own caching.
        //
        // Guarded by a flag that is OFF unless mod_xsendfile is actually
        // enabled: a server that does not understand the header passes it
        // straight to the browser and sends an EMPTY body, which would break
        // every file on the site at once.
        if (config('filesystems.x_sendfile')) {
            $absolute = $disk->path($path);

            return response('', 200, $headers + [
                'X-Sendfile' => $absolute,
                // Apache needs to be told what it is; it will not sniff for us.
                'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            ]);
        }

        return $disk->response($path, null, $headers);
    }
}
