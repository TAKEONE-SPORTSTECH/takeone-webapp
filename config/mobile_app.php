<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Android App Release
    |--------------------------------------------------------------------------
    |
    | Drives the in-app "Get the App / Update available" hub. After building a
    | new APK, bump these to match `versionName`/`versionCode` in
    | mobile/android/app/build.gradle, drop the new APK at the `apk_url` path
    | (public/app/takeone.apk by default), and users on older builds will see
    | "Update available".
    |
    */

    'version_name' => env('ANDROID_VERSION_NAME', '1.9'),
    'version_code' => (int) env('ANDROID_VERSION_CODE', 10),

    // Public URL/path to the downloadable APK (served from public/).
    'apk_url' => env('ANDROID_APK_URL', '/app/takeone.apk'),

    // Short "what's new" note shown on the update screen.
    'notes' => env('ANDROID_RELEASE_NOTES', "• Fixed app icon (no more cropping)\n• Notification sound\n• Shows connection status"),

];
