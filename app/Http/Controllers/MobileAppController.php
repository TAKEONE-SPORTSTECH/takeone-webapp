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
    /** Public version manifest polled by the installed app. */
    public function manifest(): JsonResponse
    {
        return response()->json([
            'versionName' => (string) config('mobile_app.version_name'),
            'versionCode' => (int) config('mobile_app.version_code'),
            'url' => url(config('mobile_app.apk_url')),
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
            'apkUrl' => url(config('mobile_app.apk_url')),
            'notes' => (string) config('mobile_app.notes'),
            'apkExists' => $apkExists,
        ]);
    }
}
