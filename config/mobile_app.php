<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Android App Release
    |--------------------------------------------------------------------------
    |
    | Drives the in-app "Get the App / Update available" hub. After building a
    | new APK — `./flutter/TV/build.sh app https://takeone.bh` — bump these to
    | match the VERSION_CODE/VERSION_NAME that build.sh stamps for the `app`
    | variant, drop the new APK at the `apk_url` path (public/app/takeone.apk by
    | default), and users on older builds will see "Update available".
    |
    | ⚠️ Bump these ONLY once the APK is actually published at `apk_url`. The
    | installed app compares its own versionCode against `version_code` and
    | offers a download; raising this first sends every phone to a file that is
    | not there yet.
    |
    */

    'version_name' => env('ANDROID_VERSION_NAME', '1.11'),
    'version_code' => (int) env('ANDROID_VERSION_CODE', 12),

    // Public URL/path to the downloadable APK (served from public/).
    'apk_url' => env('ANDROID_APK_URL', '/app/takeone.apk'),

    // Short "what's new" note shown on the update screen.
    'notes' => env('ANDROID_RELEASE_NOTES', "• QR scanning works again — the app now asks Android for the camera\n• Stays signed in: the login is saved before the app is closed"),

];
