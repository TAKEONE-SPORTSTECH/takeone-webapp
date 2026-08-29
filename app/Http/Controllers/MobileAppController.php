<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Get the App / Update" hub for the Android build.
 * - manifest(): public JSON the installed app polls to detect a newer version.
 * - page(): device-aware Blade hub (download in a browser, update status in-app).
 */
class MobileAppController extends Controller
{

    /**
     * The APK's URL, carrying a fingerprint of the file it points at.
     *
     * The download sits behind a CDN, and a CDN caches `/app/takeone.apk` by
     * its URL — so republishing the file under the same name kept serving the
     * PREVIOUS build for hours, with nothing at the origin to show for it. A
     * released app that silently hands out the last binary is the worst kind of
     * deploy bug: everything looks correct on the server.
     *
     * The fingerprint changes whenever the file does, so a new build is a new
     * URL and can never be answered from a stale cache. It is derived from
     * size and mtime rather than a hash of the bytes, because hashing ~50MB on
     * every page view is not worth it.
     */
    private function apkUrl(): string
    {
        $configured = (string) config('mobile_app.apk_url');
        $path = public_path(ltrim($configured, '/'));

        if (! is_file($path)) {
            return url($configured);
        }

        $fingerprint = substr(hash('xxh3', filesize($path).':'.filemtime($path)), 0, 10);

        return url($configured).'?v='.$fingerprint;
    }

    /** Public version manifest polled by the installed app. */
    public function manifest(): JsonResponse
    {
        return response()->json([
            'versionName' => (string) config('mobile_app.version_name'),
            'versionCode' => (int) config('mobile_app.version_code'),
            'url' => $this->apkUrl(),
            'notes' => (string) config('mobile_app.notes'),
        ]);
    }

    /** The "Get the App" hub page (mobile shell or desktop). */
    public function page(Request $request)
    {
        $apkExists = is_file(public_path(ltrim((string) config('mobile_app.apk_url'), '/')));

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'personal.mobile.get-app' : 'personal.desktop.get-app', [
            'shellTitle' => 'TAKEONE App',
            'versionName' => (string) config('mobile_app.version_name'),
            'versionCode' => (int) config('mobile_app.version_code'),
            'apkUrl' => $this->apkUrl(),
            'notes' => (string) config('mobile_app.notes'),
            'apkExists' => $apkExists,
        ]);
    }
}
