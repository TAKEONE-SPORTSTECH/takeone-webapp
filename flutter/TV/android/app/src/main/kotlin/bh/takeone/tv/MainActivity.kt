package bh.takeone.tv

import android.Manifest
import android.app.KeyguardManager
import android.content.ContentValues
import android.content.Context
import android.content.pm.PackageManager
import android.app.DownloadManager
import android.content.BroadcastReceiver
import android.content.IntentFilter
import android.net.Uri
import android.os.Environment
import android.provider.Settings
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import android.content.Intent
import android.os.BatteryManager
import android.os.Build
import android.os.Bundle
import android.os.StatFs
import android.provider.MediaStore
import java.io.File
import android.view.View
import android.view.WindowInsets
import android.view.WindowInsetsController
import android.view.WindowManager
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

/**
 * A screen on a wall: awake, and with nothing on it but the board.
 *
 * Both of those are done natively as well as from Dart, and the duplication is
 * deliberate — these take effect before the Flutter engine has started, so a
 * cold boot never flashes a status bar across the hall and is never eligible for
 * the launcher's daydream during the seconds it takes to come up.
 */
class MainActivity : FlutterActivity() {

    companion object {
        /**
         * True while the app's UI is in front.
         *
         * Read by MqttNotificationService so it does not post a notification for
         * something the member is already looking at — the open WebView shows
         * the update live. Carried over from the Capacitor shell this build
         * replaces, along with the service itself.
         */
        @JvmField
        @Volatile
        var isForeground: Boolean = false
    }

    /**
     * Which build this is, read from the manifest rather than guessed.
     *
     * The Dart side gets it from --dart-define, which the platform cannot see,
     * so the variant manifest states it once and both halves agree. Only the
     * member app sets it; the hall variants have no meta-data and fall through
     * to the kiosk behaviour they have always had.
     */
    private val meta: android.os.Bundle? by lazy {
        try {
            packageManager.getApplicationInfo(packageName, PackageManager.GET_META_DATA).metaData
        } catch (_: Throwable) {
            null
        }
    }

    private val isMemberApp: Boolean by lazy { meta?.getString("bh.takeone.variant") == "app" }

    /**
     * The host this build talks to, stamped into the variant manifest by
     * build.sh — the same value the Dart side gets as --dart-define. The
     * service needs it natively because it runs with no Flutter engine at all.
     */
    private val baseUrl: String by lazy {
        meta?.getString("bh.takeone.baseUrl") ?: "https://takeone.bh"
    }

    /**
     * The two facts only the platform can answer, for the camera build.
     *
     * How much room is left is the number that decides whether a phone lasts
     * the competition, and it is worth knowing BEFORE the final rather than
     * after it — so it is reported on every beat and shown on the organiser's
     * console beside the mat. Battery is the same argument, one step less
     * urgent.
     *
     * A method channel rather than two more packages: this is twenty lines of
     * platform API, and a build that films competitions should carry as few
     * things that can break on a device we cannot reach as possible. Registered
     * for all three variants and simply never called by the two that are
     * WebView shells.
     */
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        // A link that is not ours leaves the app. Handing it to Android rather
        // than rendering it inside our chrome: a foreign login form wearing the
        // TAKEONE frame is a phishing surface even when the link is honest.
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "bh.takeone/app")
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "openExternally" -> {
                        val raw = call.argument<String>("url")
                        if (raw.isNullOrBlank()) {
                            result.error("no-url", "No url given.", null)
                        } else {
                            try {
                                startActivity(
                                    Intent(Intent.ACTION_VIEW, Uri.parse(raw))
                                        .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                )
                                result.success(null)
                            } catch (e: Throwable) {
                                result.error("no-handler", e.message, null)
                            }
                        }
                    }
                    // ---- the bridge the mobile web already calls -------------
                    // Ported from the Capacitor plugin this build replaces, so
                    // app-update.blade.php and push-register.blade.php keep
                    // working with no change on the server.
                    "appInfo" -> result.success(
                        mapOf(
                            "version" to packageManager.getPackageInfo(packageName, 0).versionName,
                            "build" to packageManager.getPackageInfo(packageName, 0).let {
                                if (Build.VERSION.SDK_INT >= 28) it.longVersionCode.toString()
                                else @Suppress("DEPRECATION") it.versionCode.toString()
                            },
                        )
                    )
                    "mqttStart" -> { startNotificationService(); result.success(null) }
                    "mqttStop" -> {
                        try { stopService(Intent(this, MqttNotificationService::class.java)) } catch (_: Throwable) {}
                        result.success(null)
                    }
                    "batteryExemption" -> { requestBatteryExemption(); result.success(null) }
                    "downloadAndInstall" -> {
                        val url = call.argument<String>("url")
                        if (url.isNullOrBlank()) result.error("no-url", "No url given.", null)
                        else { downloadAndInstall(url); result.success(null) }
                    }
                    else -> result.notImplemented()
                }
            }

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "bh.takeone.camera/device")
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "storage" -> {
                        // The volume the app's own files live on — the same one
                        // the clips are written to, so the number means what the
                        // app thinks it means.
                        val stat = StatFs(filesDir.absolutePath)
                        result.success(
                            mapOf(
                                "total" to stat.blockCountLong * stat.blockSizeLong,
                                "free" to stat.availableBlocksLong * stat.blockSizeLong,
                            )
                        )
                    }
                    "battery" -> {
                        val manager = getSystemService(Context.BATTERY_SERVICE) as BatteryManager?
                        val level = manager?.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY)

                        if (level != null && level in 0..100) {
                            result.success(level)
                        } else {
                            // Pre-Lollipop-style fallback: read the sticky
                            // broadcast. Some devices refuse the property.
                            val status = registerReceiver(null, IntentFilter(Intent.ACTION_BATTERY_CHANGED))
                            val now = status?.getIntExtra(BatteryManager.EXTRA_LEVEL, -1) ?: -1
                            val scale = status?.getIntExtra(BatteryManager.EXTRA_SCALE, -1) ?: -1

                            result.success(if (now >= 0 && scale > 0) now * 100 / scale else null)
                        }
                    }
                    /*
                     * Put a finished clip where a HUMAN can find it.
                     *
                     * The app's own directory is private storage: invisible to
                     * the gallery, invisible over USB, and unreachable by any
                     * other app — which makes a recording that technically
                     * exists and practically does not. A competition video
                     * nobody can play is not a recording.
                     *
                     * So each clip is published into the phone's media library
                     * under Movies/TAKEONE, where the gallery indexes it and a
                     * computer sees it over USB like any other video. Inserted
                     * through MediaStore rather than written to a public path:
                     * that is the only route scoped storage allows, and it
                     * needs no storage permission because the app owns what it
                     * inserts.
                     *
                     * The file is MOVED, not copied — a phone filming all day
                     * cannot afford two of everything.
                     */
                    "publishVideo" -> {
                        val path = call.argument<String>("path")
                        val name = call.argument<String>("name")

                        if (path == null || name == null) {
                            result.error("bad_args", "path and name are required", null)
                            return@setMethodCallHandler
                        }

                        try {
                            result.success(publishVideo(File(path), name))
                        } catch (e: Exception) {
                            // Never fatal: the clip stays in private storage and
                            // is still listed in the app. Losing the gallery copy
                            // is a nuisance; losing the bout is not survivable.
                            result.error("publish_failed", e.message, null)
                        }
                    }
                    /*
                     * Delete a clip, from wherever it actually is.
                     *
                     * Two homes, two doors: a published clip is a MediaStore row
                     * and is deleted through the resolver (which also takes it
                     * out of the gallery); an unpublished one is a plain file in
                     * private storage. The app knows which it holds and passes
                     * that; this does not guess.
                     *
                     * Returns true when the video is gone — including when it
                     * was already gone. A volunteer who deletes the same clip
                     * twice has got what they asked for both times.
                     */
                    "deleteVideo" -> {
                        val uri = call.argument<String>("uri")
                        val path = call.argument<String>("path")

                        try {
                            var gone = false

                            if (uri != null) {
                                gone = contentResolver.delete(android.net.Uri.parse(uri), null, null) > 0
                            }

                            if (!gone && path != null) {
                                val file = File(path)
                                gone = !file.exists() || file.delete()
                            }

                            result.success(gone)
                        } catch (e: SecurityException) {
                            // Android 11+ can require the user's consent to
                            // delete a media item this app did not create — a
                            // clip restored from a backup, say. Reported rather
                            // than swallowed, so the app can say why.
                            result.error("delete_denied", e.message, null)
                        } catch (e: Exception) {
                            result.error("delete_failed", e.message, null)
                        }
                    }
                    /*
                     * Android's own settings page for this app.
                     *
                     * The one place the app hands somebody off. A camera
                     * permission refused with "don't ask again" cannot be
                     * re-requested from inside the app at all — the only route
                     * back is here, and a volunteer standing at a mat should not
                     * have to go looking for it.
                     */
                    "openSettings" -> {
                        val intent = Intent(android.provider.Settings.ACTION_APPLICATION_DETAILS_SETTINGS)
                        intent.data = android.net.Uri.fromParts("package", packageName, null)
                        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                        startActivity(intent)
                        result.success(true)
                    }
                    /*
                     * A gallery clip as a plain file, for upload.
                     *
                     * A `content://` item cannot be opened as a File, and the
                     * upload needs byte ranges so it can resume after a hall's
                     * wifi drops. So it is copied into the app's cache once and
                     * read from there; the caller deletes it when the upload
                     * finishes, and the OS clears the cache regardless.
                     */
                    "cacheCopy" -> {
                        val uri = call.argument<String>("uri")

                        if (uri == null) {
                            result.error("bad_args", "uri is required", null)
                            return@setMethodCallHandler
                        }

                        try {
                            val target = File(cacheDir, "upload-" + uri.hashCode().toUInt() + ".mp4")

                            if (!target.exists() || target.length() == 0L) {
                                contentResolver.openInputStream(android.net.Uri.parse(uri)).use { input ->
                                    if (input == null) {
                                        result.success(null)
                                        return@setMethodCallHandler
                                    }
                                    target.outputStream().use { output -> input.copyTo(output) }
                                }
                            }

                            result.success(target.absolutePath)
                        } catch (e: Exception) {
                            result.error("copy_failed", e.message, null)
                        }
                    }
                    else -> result.notImplemented()
                }
            }
    }

    /**
     * Insert a video into the shared media library and return its content URI.
     *
     * Two paths, because scoped storage split in Android 10: on Q and up the
     * bytes are streamed into a MediaStore row marked pending until complete
     * (so the gallery never indexes a half-written file); below it, the file is
     * moved into the public Movies directory and MediaStore is told about it.
     */
    private fun publishVideo(source: File, name: String): String? {
        if (!source.exists()) return null

        val resolver = contentResolver

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            val values = ContentValues().apply {
                put(MediaStore.Video.Media.DISPLAY_NAME, name)
                put(MediaStore.Video.Media.MIME_TYPE, "video/mp4")
                // One folder, so a day's bouts are together and a coach can copy
                // the lot off in one drag.
                put(MediaStore.Video.Media.RELATIVE_PATH, Environment.DIRECTORY_MOVIES + "/TAKEONE")
                put(MediaStore.Video.Media.IS_PENDING, 1)
            }

            /*
             * The PRIMARY volume by name, not the catch-all `external`.
             *
             * EXTERNAL_CONTENT_URI resolves to the "external" volume, which some
             * Android versions treat as a read-only union of every volume and
             * refuse inserts on outright. The documented target for writing is
             * VOLUME_EXTERNAL_PRIMARY — the phone's own storage, which is where
             * the gallery looks. The old constant is kept as a fallback for the
             * devices that only accept that one.
             */
            val uri = resolver.insert(
                MediaStore.Video.Media.getContentUri(MediaStore.VOLUME_EXTERNAL_PRIMARY),
                values,
            ) ?: resolver.insert(MediaStore.Video.Media.EXTERNAL_CONTENT_URI, values)
                ?: return null

            resolver.openOutputStream(uri)?.use { out ->
                source.inputStream().use { input -> input.copyTo(out) }
            } ?: return null

            values.clear()
            values.put(MediaStore.Video.Media.IS_PENDING, 0)
            resolver.update(uri, values, null, null)

            source.delete()

            return uri.toString()
        }

        @Suppress("DEPRECATION")
        val dir = File(Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_MOVIES), "TAKEONE")
        if (!dir.exists()) dir.mkdirs()

        val target = File(dir, name)
        source.copyTo(target, overwrite = true)
        source.delete()

        val values = ContentValues().apply {
            put(MediaStore.Video.Media.DISPLAY_NAME, name)
            put(MediaStore.Video.Media.MIME_TYPE, "video/mp4")
            @Suppress("DEPRECATION")
            put(MediaStore.Video.Media.DATA, target.absolutePath)
        }

        return resolver.insert(MediaStore.Video.Media.EXTERNAL_CONTENT_URI, values)?.toString()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // The member app is an ordinary phone app, and every kiosk habit below
        // is wrong for it: a pocket app must not pin the screen on, must not
        // defeat the lock screen, and must keep its system bars. Those three
        // exist for a machine bolted to a wall with nobody holding it.
        if (isMemberApp) {
            ensureNotificationPermission()
            startNotificationService()

            return
        }

        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        stayUnlocked()
        hideSystemBars()
    }

    /**
     * Ask for notifications, once, at first launch.
     *
     * Android 13 made this a runtime permission. Without it the MQTT service
     * still runs and still receives, and every notification it posts is dropped
     * silently — which reads as "the app stopped telling me things".
     */
    private fun ensureNotificationPermission() {
        if (Build.VERSION.SDK_INT < 33) return

        try {
            val granted = ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
            if (granted != PackageManager.PERMISSION_GRANTED) {
                requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), 7311)
            }
        } catch (_: Throwable) {
        }
    }

    /**
     * Start the notification service natively, so it never depends on the web
     * layer having loaded. This is the one thing the app does that a browser
     * tab cannot: hear about a message after the member has closed it.
     */
    /**
     * Ask Android to stop dozing this app.
     *
     * A background MQTT connection that Doze is free to sever is a member who
     * quietly stops being told things overnight. The user decides — this only
     * opens the settings page that lets them.
     */
    private fun requestBatteryExemption() {
        if (Build.VERSION.SDK_INT < 23) return

        try {
            startActivity(
                Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS)
                    .setData(Uri.parse("package:\$packageName"))
            )
        } catch (_: Throwable) {
        }
    }

    /**
     * The app updates itself: MobileAppController serves /app/manifest.json, the
     * installed app notices a higher versionCode and downloads the APK here.
     * Ported unchanged in behaviour from the Capacitor plugin.
     */
    private fun downloadAndInstall(url: String) {
        try {
            val name = "takeone-update.apk"
            val target = File(getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS), name)
            if (target.exists()) target.delete()

            val request = DownloadManager.Request(Uri.parse(url))
                .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                .setDestinationInExternalFilesDir(this, Environment.DIRECTORY_DOWNLOADS, name)

            val manager = getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
            val id = manager.enqueue(request)

            registerReceiver(
                object : BroadcastReceiver() {
                    override fun onReceive(context: Context, intent: Intent) {
                        if (intent.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1) != id) return
                        try { unregisterReceiver(this) } catch (_: Throwable) {}
                        installApk(target)
                    }
                },
                IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE),
                if (Build.VERSION.SDK_INT >= 33) Context.RECEIVER_EXPORTED else 0,
            )
        } catch (_: Throwable) {
        }
    }

    private fun installApk(apk: File) {
        try {
            val uri = FileProvider.getUriForFile(this, "\$packageName.fileprovider", apk)
            startActivity(
                Intent(Intent.ACTION_VIEW)
                    .setDataAndType(uri, "application/vnd.android.package-archive")
                    .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
            )
        } catch (_: Throwable) {
        }
    }

    private fun startNotificationService() {
        try {
            val svc = Intent(this, MqttNotificationService::class.java)
            svc.putExtra("baseUrl", baseUrl)
            ContextCompat.startForegroundService(this, svc)
        } catch (_: Throwable) {
        }
    }

    /**
     * The screen stays on, and the lock screen never gets in front of it.
     *
     * KEEP_SCREEN_ON alone only stops the display timing out — it does nothing
     * about the keyguard. That gap is invisible on a wall screen and serious on
     * a camera: a phone clamped to a tripod gets its power button knocked, or
     * comes back from a reboot on the lock screen, and then the mat calls
     * hajime at an app that is not on top. It cannot ask anyone to swipe,
     * because the person who set it up is three mats away.
     *
     * So the activity declares itself showable over the keyguard, turns the
     * screen on by itself, and asks for the keyguard to be dismissed where the
     * device allows it (a phone with no PIN dismisses; one with a PIN keeps its
     * secure lock, which is right — this must not be a way past a lock).
     *
     * Applies to all three builds. A television has no keyguard to speak of, so
     * this is a no-op there, and duplicating the setting per variant would be
     * one more thing to forget.
     */
    private fun stayUnlocked() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)

            val keyguard = getSystemService(Context.KEYGUARD_SERVICE) as KeyguardManager?
            keyguard?.requestDismissKeyguard(this, null)
        } else {
            @Suppress("DEPRECATION")
            window.addFlags(
                WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED
                    or WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON
                    or WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD
            )
        }
    }

    /**
     * Re-applied on every regain of focus. Android puts the bars back after a
     * dialog, a volume overlay or an HDMI wake, and there is nobody standing at
     * this screen to swipe them away again.
     */
    override fun onWindowFocusChanged(hasFocus: Boolean) {
        super.onWindowFocusChanged(hasFocus)
        if (hasFocus) hideSystemBars()
    }

    /** Re-asserted on every resume: a reboot or a knocked power button lands here. */
    override fun onResume() {
        super.onResume()
        isForeground = true

        if (isMemberApp) return

        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        stayUnlocked()
    }

    override fun onPause() {
        super.onPause()
        isForeground = false
    }

    private fun hideSystemBars() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            window.setDecorFitsSystemWindows(false)
            window.insetsController?.apply {
                hide(WindowInsets.Type.systemBars())
                systemBarsBehavior =
                    WindowInsetsController.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
            }
        } else {
            @Suppress("DEPRECATION")
            window.decorView.systemUiVisibility = (
                View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                    or View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
                    or View.SYSTEM_UI_FLAG_FULLSCREEN
                    or View.SYSTEM_UI_FLAG_LAYOUT_STABLE
                    or View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
                    or View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
                )
        }
    }
}
