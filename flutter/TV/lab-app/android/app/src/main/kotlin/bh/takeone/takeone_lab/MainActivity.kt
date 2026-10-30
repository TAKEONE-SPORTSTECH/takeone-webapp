package bh.takeone.takeone_lab

import android.Manifest
import android.content.ClipData
import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.media.MediaMetadataRetriever
import android.net.Uri
import android.content.pm.PackageManager
import android.os.BatteryManager
import android.os.Build
import android.os.Environment
import android.os.Handler
import android.os.Looper
import android.os.PowerManager
import android.os.StatFs
import android.provider.MediaStore
import android.view.WindowManager
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import androidx.annotation.OptIn
import androidx.core.content.FileProvider
import androidx.media3.common.MediaItem
import androidx.media3.common.MimeTypes
import androidx.media3.common.util.UnstableApi
import androidx.media3.effect.FrameDropEffect
import androidx.media3.effect.OverlayEffect
import androidx.media3.effect.Crop
import androidx.media3.effect.Presentation
import androidx.media3.effect.ScaleAndRotateTransformation
import androidx.media3.effect.TextureOverlay
import androidx.media3.transformer.EditedMediaItemSequence
import com.google.common.collect.ImmutableList
import com.google.common.collect.ImmutableSet
import androidx.media3.common.C
import org.json.JSONObject
import androidx.media3.transformer.Composition
import androidx.media3.transformer.DefaultEncoderFactory
import androidx.media3.transformer.EditedMediaItem
import androidx.media3.transformer.Effects
import androidx.media3.transformer.ExportException
import androidx.media3.transformer.ExportResult
import androidx.media3.transformer.Transformer
import androidx.media3.transformer.VideoEncoderSettings
import java.io.File

/**
 * The three numbers Flutter cannot see, and the one window flag that matters.
 *
 * Battery level, thermal status and free space are each a handful of lines of
 * platform code — and thermal status is the whole point of this harness: it is
 * Android telling us the phone is getting too hot to keep doing both jobs, which
 * is exactly the failure being measured. There is no Flutter package that
 * exposes it, so it comes through a channel.
 */
class MainActivity : FlutterActivity() {

    private val channel = "bh.takeone.lab/device"

    override fun configureFlutterEngine(engine: FlutterEngine) {
        super.configureFlutterEngine(engine)

        // A measurement run is minutes to hours long and nobody is touching the
        // screen. Letting it sleep would end the run and the recording with it.
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        MethodChannel(engine.dartExecutor.binaryMessenger, channel).setMethodCallHandler { call, result ->
            when (call.method) {
                "read" -> result.success(read())
                else -> result.notImplemented()
            }
        }

        // The phone's own gallery: where every finished bout is moved so it
        // outlives the app (lib/vault.dart, ClipVault.load / keep).
        MethodChannel(engine.dartExecutor.binaryMessenger, galleryChannel).setMethodCallHandler { call, result ->
            when (call.method) {
                "publish" -> publish(
                    call.argument<String>("path") ?: "",
                    call.argument<String>("name") ?: "",
                    result,
                    // Only the two albums this app owns, never a folder the caller names.
                    if (call.argument<String>("album") == "sent") sentFolder else folder,
                )
                "scan" -> scan(result)
                "canRead" -> result.success(canReadGallery())
                "askRead" -> askRead(result)
                else -> result.notImplemented()
            }
        }

        // The VS screen's music: the operator's own song, picked and cut to the
        // seconds they want (lib/music.dart).
        MethodChannel(engine.dartExecutor.binaryMessenger, musicChannel).setMethodCallHandler { call, result ->
            when (call.method) {
                "pick" -> pickMusic(result)
                "make" -> makeMusic(
                    call.argument<String>("path") ?: "",
                    (call.argument<Number>("start") ?: 0).toLong(),
                    (call.argument<Number>("end") ?: 0).toLong(),
                    result,
                )
                else -> result.notImplemented()
            }
        }

        // Sending a bout to WhatsApp & co (lib/send.dart): make the phone's own
        // copy smaller, and hand a file to Android's share sheet.
        MethodChannel(engine.dartExecutor.binaryMessenger, shareChannel).setMethodCallHandler { call, result ->
            when (call.method) {
                "compress" -> compress(
                    call.argument<String>("input") ?: "",
                    call.argument<String>("output") ?: "",
                    result,
                )
                "render" -> render(
                    call.argument<String>("input") ?: "",
                    call.argument<String>("output") ?: "",
                    call.argument<String>("plan") ?: "",
                    call.argument<String>("kind") ?: "full",
                    call.argument<List<String>>("parts")?.toSet() ?: ScoreBar.ALL,
                    (call.argument<Double>("lift") ?: ScoreBar.LIFT_DEFAULT.toDouble()).toFloat(),
                    call.argument<Double>("tilt") ?: 0.0,
                    call.argument<String>("music") ?: "",
                    (call.argument<Double>("vsLift") ?: VsScreen.INFO_DEFAULT.toDouble()).toFloat(),
                    result,
                )
                "vs" -> vsFrame(
                    call.argument<String>("plan") ?: "",
                    call.argument<Int>("width") ?: 1280,
                    call.argument<Int>("height") ?: 720,
                    (call.argument<Double>("vsLift") ?: VsScreen.INFO_DEFAULT.toDouble()).toFloat(),
                    result,
                )
                "statsCard" -> statsFrame(
                    call.argument<String>("plan") ?: "",
                    call.argument<Int>("width") ?: 1280,
                    call.argument<Int>("height") ?: 720,
                    result,
                )
                "frame" -> frameAt(
                    call.argument<String>("path") ?: "",
                    (call.argument<Number>("atMs") ?: 0).toLong(),
                    call.argument<Int>("width") ?: 960,
                    result,
                )
                "vsLayers" -> vsLayers(
                    call.argument<String>("plan") ?: "",
                    call.argument<Int>("width") ?: 1280,
                    call.argument<Int>("height") ?: 720,
                    (call.argument<Double>("vsLift") ?: VsScreen.INFO_DEFAULT.toDouble()).toFloat(),
                    result,
                )
                "preview" -> preview(
                    call.argument<String>("plan") ?: "",
                    call.argument<String>("key") ?: "",
                    call.argument<Int>("width") ?: 1280,
                    call.argument<Int>("height") ?: 720,
                    call.argument<Double>("at") ?: 0.0,
                    call.argument<List<String>>("parts")?.toSet() ?: ScoreBar.ALL,
                    (call.argument<Double>("lift") ?: ScoreBar.LIFT_DEFAULT.toDouble()).toFloat(),
                    result,
                )
                "progress" -> result.success(progress())
                "send" -> send(
                    call.argument<String>("path") ?: "",
                    call.argument<String>("text") ?: "",
                    call.argument<String>("title") ?: "",
                    result,
                )
                else -> result.notImplemented()
            }
        }
    }


    private val galleryChannel = "bh.takeone.lab/gallery"

    /** The one folder every bout lives in, on every phone: Movies/TAKEONE. */
    private val folder = Environment.DIRECTORY_MOVIES + "/TAKEONE"

    /**
     * The videos made for sending (score bar burned in, highlights, the smaller
     * copy): Movies/Sent bouts. A folder of its own, OUTSIDE Movies/TAKEONE on
     * purpose — `scan` rebuilds the list of recordings from everything there,
     * and a finished highlight must never come back as a bout to upload.
     */
    private val sentFolder = Environment.DIRECTORY_MOVIES + "/Sent bouts"

    private val main = Handler(Looper.getMainLooper())

    /** Copying 400MB must never run on the UI thread — the preview would freeze. */
    private fun background(result: MethodChannel.Result, work: () -> Any?) {
        Thread {
            val answer = runCatching(work)
            main.post {
                answer.fold(
                    onSuccess = { result.success(it) },
                    onFailure = { result.error("gallery", it.message, null) },
                )
            }
        }.start()
    }

    /**
     * Move a finished bout out of the app's private storage into the phone's
     * gallery, and answer with where it now lives as a plain file path.
     *
     * Private storage dies with the app: uninstalling it to install a newer
     * build took a whole competition's list of clips with it once. The gallery
     * does not. The copy is PROVEN — same size, and a byte reads back — before
     * the private original is removed; if anything is off, the original stays
     * exactly where it was and null is returned.
     */
    private fun publish(path: String, name: String, result: MethodChannel.Result, album: String = folder) =
        background(result) {
            val source = File(path)
            if (!source.isFile || name.isEmpty()) return@background null

            val size = source.length()

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val values = ContentValues().apply {
                    put(MediaStore.Video.Media.DISPLAY_NAME, name)
                    put(MediaStore.Video.Media.MIME_TYPE, "video/mp4")
                    put(MediaStore.Video.Media.RELATIVE_PATH, album)
                    put(MediaStore.Video.Media.IS_PENDING, 1)
                }

                // The PRIMARY volume by name; the catch-all is refused on some builds.
                val uri = contentResolver.insert(
                    MediaStore.Video.Media.getContentUri(MediaStore.VOLUME_EXTERNAL_PRIMARY), values,
                ) ?: contentResolver.insert(MediaStore.Video.Media.EXTERNAL_CONTENT_URI, values)
                    ?: return@background null

                val wrote = runCatching {
                    contentResolver.openOutputStream(uri)?.use { out ->
                        source.inputStream().use { it.copyTo(out, 1 shl 20) }
                    } ?: -1L
                }.getOrDefault(-1L)

                values.clear()
                values.put(MediaStore.Video.Media.IS_PENDING, 0)
                runCatching { contentResolver.update(uri, values, null, null) }

                val target = pathOf(uri)

                if (wrote != size || target == null || File(target).length() != size || !readable(uri)) {
                    runCatching { contentResolver.delete(uri, null, null) }
                    return@background null
                }

                source.delete()
                return@background target
            }

            // Below Android 10: an ordinary file in the public Movies folder.
            @Suppress("DEPRECATION")
            val dir = File(Environment.getExternalStorageDirectory(), album)
            if (!dir.exists()) dir.mkdirs()

            // Never over another file of the same name: "x (1).mp4", as Android 10+ does.
            var target = File(dir, name)
            var n = 1
            while (target.exists()) target = File(dir, name.replace(Regex("(\\.mp4)?$", RegexOption.IGNORE_CASE), " (${n++})$1"))
            source.copyTo(target, overwrite = false)

            if (target.length() != size) {
                target.delete()
                return@background null
            }

            source.delete()

            val values = ContentValues().apply {
                put(MediaStore.Video.Media.DISPLAY_NAME, name)
                put(MediaStore.Video.Media.MIME_TYPE, "video/mp4")
                @Suppress("DEPRECATION")
                put(MediaStore.Video.Media.DATA, target.absolutePath)
            }
            runCatching { contentResolver.insert(MediaStore.Video.Media.EXTERNAL_CONTENT_URI, values) }

            target.absolutePath
        }

    /**
     * Every video in Movies/TAKEONE: what the app rebuilds its list from after
     * a reinstall. Also finds the old mat camera's recordings, which lived in
     * the same folder. Path, name, size, length — nothing else.
     */
    private fun scan(result: MethodChannel.Result) = background(result) {
        val out = ArrayList<Map<String, Any>>()

        @Suppress("DEPRECATION")
        val data = MediaStore.Video.Media.DATA
        val columns = arrayOf(
            MediaStore.Video.Media._ID,
            MediaStore.Video.Media.DISPLAY_NAME,
            MediaStore.Video.Media.SIZE,
            MediaStore.Video.Media.DURATION,
            data,
        )

        val (where, args) = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            "${MediaStore.Video.Media.RELATIVE_PATH} LIKE ?" to arrayOf("$folder%")
        } else {
            "$data LIKE ?" to arrayOf("%/Movies/TAKEONE/%")
        }

        contentResolver.query(MediaStore.Video.Media.EXTERNAL_CONTENT_URI, columns, where, args, null)?.use { c ->
            while (c.moveToNext()) {
                val path = c.getString(4) ?: continue
                val name = c.getString(1) ?: File(path).name
                val bytes = c.getLong(2)
                if (bytes <= 0 || !name.endsWith(".mp4", ignoreCase = true)) continue

                out.add(
                    mapOf(
                        "path" to path,
                        "name" to name,
                        "bytes" to bytes,
                        "seconds" to (c.getLong(3) / 1000L).toInt(),
                    )
                )
            }
        }

        out
    }

    /** The file a gallery row is, as a path the Dart side can open directly. */
    private fun pathOf(uri: Uri): String? = runCatching {
        @Suppress("DEPRECATION")
        val data = MediaStore.Video.Media.DATA
        contentResolver.query(uri, arrayOf(data), null, null, null)?.use { c ->
            if (c.moveToFirst()) c.getString(0) else null
        }
    }.getOrNull()

    /** A byte has to come back out; opening alone proves nothing. */
    private fun readable(uri: Uri): Boolean = runCatching {
        contentResolver.openInputStream(uri)?.use { it.read() != -1 } ?: false
    }.getOrDefault(false)

    /**
     * The permission that matters after a reinstall. A gallery file belongs to
     * the install that wrote it; the next install may only see it with this.
     * Below Android 10 the same grant is also what lets a bout be written there.
     */
    private fun galleryPermissions(): Array<String> = when {
        Build.VERSION.SDK_INT >= 33 -> arrayOf(Manifest.permission.READ_MEDIA_VIDEO)
        Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q -> arrayOf(Manifest.permission.READ_EXTERNAL_STORAGE)
        else -> arrayOf(Manifest.permission.READ_EXTERNAL_STORAGE, Manifest.permission.WRITE_EXTERNAL_STORAGE)
    }

    private fun canReadGallery(): Boolean = galleryPermissions().all {
        checkSelfPermission(it) == PackageManager.PERMISSION_GRANTED
    }

    private var pendingAsk: MethodChannel.Result? = null

    private val askCode = 4711

    private fun askRead(result: MethodChannel.Result) {
        if (canReadGallery()) {
            result.success(true)
            return
        }

        // One question at a time; a second caller just learns the answer later.
        pendingAsk?.success(false)
        pendingAsk = result
        requestPermissions(galleryPermissions(), askCode)
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)

        if (requestCode == askCode) {
            pendingAsk?.success(canReadGallery())
            pendingAsk = null
        }
    }

    private val shareChannel = "bh.takeone.lab/share"

    private val musicChannel = "bh.takeone.lab/music"
    private val pickCode = 4712
    private var pendingPick: MethodChannel.Result? = null

    /**
     * The phone's own picker for a song (any audio file), copied into the app's
     * private folder so it stays usable whatever happens to the original.
     * Answers {path, name, ms} or null when nothing was chosen.
     */
    private fun pickMusic(result: MethodChannel.Result) {
        pendingPick?.success(null)
        pendingPick = result
        try {
            startActivityForResult(
                Intent(Intent.ACTION_OPEN_DOCUMENT).apply {
                    addCategory(Intent.CATEGORY_OPENABLE)
                    type = "audio/*"
                },
                pickCode,
            )
        } catch (t: Throwable) {
            pendingPick = null
            result.error("pick", t.message, null)
        }
    }

    @Deprecated("Deprecated in Java")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode != pickCode) return

        val result = pendingPick ?: return
        pendingPick = null
        val uri = data?.data
        if (resultCode != RESULT_OK || uri == null) {
            result.success(null)
            return
        }

        Thread {
            val answer = runCatching {
                var name = "song"
                contentResolver.query(uri, arrayOf(android.provider.OpenableColumns.DISPLAY_NAME), null, null, null)?.use { c ->
                    if (c.moveToFirst()) name = c.getString(0) ?: name
                }
                val dir = File(filesDir, "music").apply { mkdirs() }
                val ext = name.substringAfterLast('.', "m4a").lowercase().filter { it.isLetterOrDigit() }.take(5).ifEmpty { "m4a" }
                val copy = File(dir, "picked.$ext")
                dir.listFiles()?.filter { it.name.startsWith("picked.") }?.forEach { it.delete() }
                contentResolver.openInputStream(uri)?.use { input -> copy.outputStream().use { input.copyTo(it) } }
                val r = MediaMetadataRetriever()
                r.setDataSource(copy.absolutePath)
                val ms = r.extractMetadata(MediaMetadataRetriever.METADATA_KEY_DURATION)?.toLongOrNull() ?: 0L
                r.release()
                mapOf("path" to copy.absolutePath, "name" to name, "ms" to ms)
            }
            runOnUiThread { answer.fold(onSuccess = { result.success(it) }, onFailure = { result.error("pick", it.message, null) }) }
        }.start()
    }

    /**
     * Cut the picked song to [start, end) and fade both ends, into the app's
     * VS music file. Answers its path; the song itself is left as it was.
     */
    @OptIn(UnstableApi::class)
    private fun makeMusic(path: String, startMs: Long, endMs: Long, result: MethodChannel.Result) {
        val source = File(path)
        if (!source.isFile || endMs <= startMs) {
            result.error("make", "nothing to cut", null)
            return
        }

        try {
            val out = File(File(filesDir, "music").apply { mkdirs() }, "vs_custom.m4a")
            val partial = File(out.parentFile, "vs_custom.part.m4a")
            partial.delete()

            val lengthUs = (endMs - startMs) * 1000
            val item = EditedMediaItem.Builder(
                MediaItem.Builder()
                    .setUri(Uri.fromFile(source))
                    .setClippingConfiguration(
                        MediaItem.ClippingConfiguration.Builder().setStartPositionMs(startMs).setEndPositionMs(endMs).build()
                    )
                    .build()
            )
                .setRemoveVideo(true)
                .setEffects(Effects(listOf(FadeAudio(lengthUs, 150_000, minOf(3_000_000L, lengthUs / 2))), emptyList()))
                .build()

            Transformer.Builder(this)
                .setAudioMimeType(MimeTypes.AUDIO_AAC)
                .addListener(object : Transformer.Listener {
                    override fun onCompleted(composition: Composition, exportResult: ExportResult) {
                        out.delete()
                        partial.renameTo(out)
                        result.success(out.absolutePath)
                    }

                    override fun onError(composition: Composition, exportResult: ExportResult, exportException: ExportException) {
                        partial.delete()
                        result.error("make", exportException.message, null)
                    }
                })
                .build()
                .start(item, partial.absolutePath)
        } catch (t: Throwable) {
            result.error("make", t.message, null)
        }
    }

    /**
     * The recording, made the size WhatsApp would make it — on the phone's own
     * video chip, so it takes seconds, not minutes, and works with no network.
     *
     * H.264 (WhatsApp re-compresses anything else), at most 1080 lines, 30 fps,
     * a capped bitrate. The recording itself is only read; the copy goes where
     * the caller says (the app's cache). Answers with the copy's path, or an
     * error the caller turns into "send the original instead".
     */
    @OptIn(UnstableApi::class)
    private fun compress(input: String, output: String, result: MethodChannel.Result) {
        val source = File(input)

        if (!source.isFile || output.isEmpty()) {
            result.error("compress", "no such file", null)
            return
        }

        try {
            File(output).delete()

            // Only ever DOWN: a 720p recording is not blown up to 1080p.
            val shortSide = try {
                val r = MediaMetadataRetriever()
                r.setDataSource(input)
                val h = r.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_HEIGHT)?.toIntOrNull() ?: 0
                val w = r.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_WIDTH)?.toIntOrNull() ?: 0
                r.release()
                minOf(h, w)
            } catch (_: Throwable) {
                0
            }

            val effects = ArrayList<androidx.media3.common.Effect>()
            if (shortSide > 1080) effects.add(Presentation.createForHeight(1080))
            effects.add(FrameDropEffect.createDefaultFrameDropEffect(30f))

            val item = EditedMediaItem.Builder(MediaItem.fromUri(Uri.fromFile(source)))
                .setEffects(Effects(emptyList(), effects))
                .build()

            val encoders = DefaultEncoderFactory.Builder(this)
                .setRequestedVideoEncoderSettings(
                    VideoEncoderSettings.Builder().setBitrate(3_500_000).build()
                )
                .build()

            val transformer = Transformer.Builder(this)
                .setVideoMimeType(MimeTypes.VIDEO_H264)
                .setAudioMimeType(MimeTypes.AUDIO_AAC)
                .setEncoderFactory(encoders)
                .addListener(object : Transformer.Listener {
                    override fun onCompleted(composition: Composition, exportResult: ExportResult) {
                        result.success(output)
                    }

                    override fun onError(
                        composition: Composition,
                        exportResult: ExportResult,
                        exportException: ExportException,
                    ) {
                        File(output).delete()
                        result.error("compress", exportException.message, null)
                    }
                })
                .build()

            transformer.start(item, output)
        } catch (t: Throwable) {
            File(output).delete()
            result.error("compress", t.message, null)
        }
    }

    /**
     * The bout with its score bar burned in — whole, or cut to its highlights —
     * made HERE, on the phone's own video chip, from the plan the event sent
     * (ScoreBar draws it). Nothing is uploaded; the recording is only read.
     *
     * Same output as `compress` (H.264, at most 1080 lines, 30 fps, a capped
     * bitrate), so WhatsApp takes it as it is. Highlights are the plan's
     * windows played one after another, each carrying its own copy of the bar
     * so the clock and score are the moment's, not the cut's.
     */
    @OptIn(UnstableApi::class)
    private fun render(input: String, output: String, planJson: String, kind: String, parts: Set<String>, lift: Float,
                       tilt: Double, musicPath: String, vsLift: Float, result: MethodChannel.Result) {
        val source = File(input)

        if (!source.isFile || output.isEmpty() || planJson.isEmpty()) {
            result.error("render", "no such file", null)
            return
        }

        try {
            val plan = JSONObject(planJson)
            File(output).delete()

            // The frame as it will be encoded: only ever scaled DOWN to 1080 lines.
            var w = 1920
            var h = 1080
            try {
                val r = MediaMetadataRetriever()
                r.setDataSource(input)
                w = r.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_WIDTH)?.toIntOrNull() ?: w
                h = r.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_HEIGHT)?.toIntOrNull() ?: h
                val rot = r.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_ROTATION)?.toIntOrNull() ?: 0
                if (rot == 90 || rot == 270) { val t = w; w = h; h = t }
                r.release()
            } catch (_: Throwable) {}

            val shrink = minOf(w, h) > 1080
            val outH = if (shrink) (if (h <= w) 1080 else (1080.0 * h / w).toInt()) else h
            val outW = if (shrink) (if (h <= w) (1080.0 * w / h).toInt() else 1080) else w
            val evenW = outW - outW % 2
            val evenH = outH - outH % 2

            fun item(from: Double, to: Double?): EditedMediaItem {
                val media = MediaItem.Builder().setUri(Uri.fromFile(source))
                if (to != null) {
                    media.setClippingConfiguration(
                        MediaItem.ClippingConfiguration.Builder()
                            .setStartPositionMs((from * 1000).toLong())
                            .setEndPositionMs((to * 1000).toLong())
                            .build()
                    )
                }

                val effects = ArrayList<androidx.media3.common.Effect>()

                // The leveling the clip was filmed with (Clip.tilt): turn the
                // picture, then keep the centre that has no empty corners and
                // bring it back to the output size. Before the bar, so the bar
                // itself stays straight.
                if (Math.abs(tilt) >= 0.1) {
                    val t = Math.toRadians(Math.abs(tilt))
                    val bw = w * Math.cos(t) + h * Math.sin(t)
                    val bh = w * Math.sin(t) + h * Math.cos(t)
                    val fill = Math.cos(t) + Math.max(w.toDouble() / h, h.toDouble() / w) * Math.sin(t)
                    val cw = ((w / fill) / bw).toFloat().coerceIn(0.05f, 1f)
                    val ch = ((h / fill) / bh).toFloat().coerceIn(0.05f, 1f)
                    effects.add(ScaleAndRotateTransformation.Builder().setRotationDegrees((-tilt).toFloat()).build())
                    effects.add(Crop(-cw, cw, -ch, ch))
                    effects.add(Presentation.createForWidthAndHeight(evenW, evenH, Presentation.LAYOUT_SCALE_TO_FIT))
                } else if (shrink) {
                    effects.add(Presentation.createForWidthAndHeight(evenW, evenH, Presentation.LAYOUT_SCALE_TO_FIT))
                }
                effects.add(FrameDropEffect.createDefaultFrameDropEffect(30f))
                effects.add(OverlayEffect(ImmutableList.of<TextureOverlay>(ScoreBar(plan, evenW, evenH, from, assets, parts, lift))))

                return EditedMediaItem.Builder(media.build())
                    .setEffects(Effects(emptyList(), effects))
                    .build()
            }

            val errors = ArrayList<String>()

            // stage 0: everything asked for · 1: without the VS music · 2: without the VS screen
            fun attempt(stage: Int) {
                val items = ArrayList<EditedMediaItem>()

                // The VS screen opens the video when it is switched on: one still,
                // four seconds, drawn by VsScreen from the same plan, at the very
                // size the rest is encoded at.
                if ("intro" in parts && stage < 2) {
                    try {
                        val still = File(cacheDir, "share/vs-${System.currentTimeMillis()}.png")
                        still.parentFile?.mkdirs()
                        still.outputStream().use { VsScreen(plan, assets, vsLift, evenH > evenW).draw(evenW, evenH).compress(android.graphics.Bitmap.CompressFormat.PNG, 100, it) }
                        still.deleteOnExit()

                        items.add(
                            EditedMediaItem.Builder(
                                MediaItem.Builder()
                                    .setUri(Uri.fromFile(still))
                                    .setMimeType(MimeTypes.IMAGE_PNG)
                                    .setImageDurationMs(4000)
                                    .build()
                            )
                                .setFrameRate(30)
                                .setEffects(Effects(emptyList(), listOf(
                                    Presentation.createForWidthAndHeight(evenW, evenH, Presentation.LAYOUT_SCALE_TO_FIT),
                                    // Animated as the bout page's intro is (VsAnim).
                                    OverlayEffect(ImmutableList.of<TextureOverlay>(VsAnimOverlay(evenW, evenH,
                                        VsScreen(plan, assets, vsLift, evenH > evenW).let { it.layers(minOf(evenW / it.W, evenH / it.H)) }))),
                                )))
                                .build()
                        )
                    } catch (_: Throwable) {
                        // No intro is a smaller loss than no video.
                    }
                }

                if (kind == "highlights") {
                    val windows = plan.optJSONArray("windows")
                    for (i in 0 until (windows?.length() ?: 0)) {
                        val win = windows!!.getJSONArray(i)
                        val from = win.optDouble(0)
                        val to = win.optDouble(1)
                        if (to - from >= 0.5) items.add(item(from, to))
                    }
                    if (items.none { it.mediaItem.localConfiguration?.mimeType != MimeTypes.IMAGE_PNG }) {
                        result.error("render", "nothing scored", null)
                        return
                    }
                } else {
                    items.add(item(0.0, null))
                }

                // The statistics card closes the video when it is switched on:
                // one still, seven seconds, after the footage has shown the
                // winner declared (StatsCard, from the plan's `stats`). Dropped
                // with the VS screen when a phone cannot take the extras.
                val card = StatsCard(plan, assets, evenH > evenW)
                if ("stats" in parts && stage < 2 && card.available) {
                    try {
                        val still = File(cacheDir, "share/stats-${System.currentTimeMillis()}.png")
                        still.parentFile?.mkdirs()
                        still.outputStream().use { card.draw(evenW, evenH).compress(android.graphics.Bitmap.CompressFormat.PNG, 100, it) }
                        still.deleteOnExit()

                        items.add(
                            EditedMediaItem.Builder(
                                MediaItem.Builder()
                                    .setUri(Uri.fromFile(still))
                                    .setMimeType(MimeTypes.IMAGE_PNG)
                                    .setImageDurationMs(7000)
                                    .build()
                            )
                                .setFrameRate(30)
                                .setEffects(Effects(emptyList(), listOf(
                                    Presentation.createForWidthAndHeight(evenW, evenH, Presentation.LAYOUT_SCALE_TO_FIT),
                                )))
                                .build()
                        )
                    } catch (_: Throwable) {
                        // No card is a smaller loss than no video.
                    }
                }

                // The VS screen's music, when switched on: a second, audio-only
                // sequence mixed over the first — it plays under the VS screen and
                // fades away (in the file itself) over the bout's first seconds.
                val sequences = ArrayList<EditedMediaItemSequence>()
                sequences.add(
                    EditedMediaItemSequence.Builder(ImmutableSet.of(C.TRACK_TYPE_AUDIO, C.TRACK_TYPE_VIDEO))
                        .addItems(items)
                        .build()
                )

                if ("intro" in parts && "music" in parts && stage < 1 && items.firstOrNull()?.mediaItem?.localConfiguration?.mimeType == MimeTypes.IMAGE_PNG) {
                    try {
                        val own = File(musicPath)
                        val music = if (musicPath.isNotEmpty() && own.isFile) own else File(cacheDir, "share/vs_intro.m4a")
                        if (!music.isFile) {
                            music.parentFile?.mkdirs()
                            assets.open("flutter_assets/assets/audio/vs_intro.m4a").use { input ->
                                music.outputStream().use { input.copyTo(it) }
                            }
                        }
                        sequences.add(
                            EditedMediaItemSequence.Builder(ImmutableSet.of(C.TRACK_TYPE_AUDIO))
                                .addItem(EditedMediaItem.Builder(MediaItem.fromUri(Uri.fromFile(music))).setRemoveVideo(true).build())
                                .build()
                        )
                    } catch (_: Throwable) {
                        // No music is a smaller loss than no video.
                    }
                }

                // An image has no sound; the sequence keeps an audio track all the
                // way through so the bout's sound still lines up after the intro.
                val composition = Composition.Builder(sequences)
                    .experimentalSetForceAudioTrack(true)
                    .build()

                val encoders = DefaultEncoderFactory.Builder(this)
                    .setRequestedVideoEncoderSettings(
                        VideoEncoderSettings.Builder().setBitrate(4_500_000).build()
                    )
                    .build()

                val transformer = Transformer.Builder(this)
                    .setVideoMimeType(MimeTypes.VIDEO_H264)
                    .setAudioMimeType(MimeTypes.AUDIO_AAC)
                    .setEncoderFactory(encoders)
                    .addListener(object : Transformer.Listener {
                        override fun onCompleted(composition: Composition, exportResult: ExportResult) {
                            renderJob = null
                            result.success(mapOf(
                                "path" to output,
                                // What this phone could not take, so the screen says so.
                                "dropped" to when (stage) {
                                    0 -> emptyList()
                                    1 -> listOf("music")
                                    else -> listOfNotNull("music", "intro", if ("stats" in parts) "stats" else null)
                                },
                                "errors" to errors,
                            ))
                        }

                        override fun onError(
                            composition: Composition,
                            exportResult: ExportResult,
                            exportException: ExportException,
                        ) {
                            renderJob = null
                            File(output).delete()
                            val why = describe(exportException)
                            errors.add(why)

                            // Try again with less: without the music, then without
                            // the VS screen — the bout with its score bar is what
                            // matters, the rest is dressing.
                            val next = stage + 1
                            val wanted = "intro" in parts || "stats" in parts
                            if (next <= 2 && wanted) {
                                try {
                                    attempt(next)
                                } catch (t: Throwable) {
                                    errors.add("${t.javaClass.simpleName}: ${t.message}".take(400))
                                    result.error("render", errors.joinToString(" | "), null)
                                }
                            } else {
                                result.error("render", errors.joinToString(" | "), null)
                            }
                        }
                    })
                    .build()

                renderJob = transformer
                transformer.start(composition, output)
            }

            // A failure while BUILDING the job (not while running it) takes the
            // same road as one while running: try again with less.
            fun safely(stage: Int) {
                try {
                    attempt(stage)
                } catch (t: Throwable) {
                    errors.add("${t.javaClass.simpleName}: ${t.message}".take(400))
                    if (stage < 2 && ("intro" in parts || "stats" in parts)) safely(stage + 1) else result.error("render", errors.joinToString(" | "), null)
                }
            }

            safely(0)
        } catch (t: Throwable) {
            renderJob = null
            File(output).delete()
            result.error("render", t.message, null)
        }
    }

    /**
     * The score bar for one instant, as a PNG, for the player to lay over the
     * video while it plays — painted by the very ScoreBar that burns it in, so
     * what is previewed is what is sent. Null when nothing visible changed
     * since the last ask (the player keeps the picture it has).
     */
    private var previewBar: ScoreBar? = null
    private var previewKey = ""

    private fun preview(planJson: String, key: String, width: Int, height: Int, at: Double, parts: Set<String>, lift: Float,
                        result: MethodChannel.Result) {
        Thread {
            val answer = runCatching {
                synchronized(this) {
                    val id = "$key|$width|$height|$lift|${parts.sorted().joinToString(",")}"
                    if (id != previewKey || previewBar == null) {
                        previewBar = ScoreBar(JSONObject(planJson), width, height, 0.0, assets, parts, lift)
                        previewKey = id
                    }
                    val (bitmap, changed) = previewBar!!.frame(at)
                    if (!changed) null else {
                        val out = java.io.ByteArrayOutputStream()
                        bitmap.compress(android.graphics.Bitmap.CompressFormat.PNG, 100, out)
                        out.toByteArray()
                    }
                }
            }
            runOnUiThread {
                answer.fold(
                    onSuccess = { result.success(it) },
                    onFailure = { result.error("preview", it.message, null) },
                )
            }
        }.start()
    }

    /**
     * The VS screen in its parts, for the player to animate with VsAnim's
     * timings: [{name, png, rect: [l, t, r, b] on the 1920×1080 canvas}].
     */
    private fun vsLayers(planJson: String, width: Int, height: Int, vsLift: Float, result: MethodChannel.Result) {
        Thread {
            val answer = runCatching {
                val vs = VsScreen(JSONObject(planJson), assets, vsLift, height > width)
                vs.layers(minOf(width / vs.W, height / vs.H)).map { l ->
                    val out = java.io.ByteArrayOutputStream()
                    l.bitmap.compress(android.graphics.Bitmap.CompressFormat.PNG, 100, out)
                    mapOf(
                        "name" to l.name,
                        "png" to out.toByteArray(),
                        "rect" to listOf(l.rect.left.toDouble(), l.rect.top.toDouble(), l.rect.right.toDouble(), l.rect.bottom.toDouble()),
                    )
                }
            }
            runOnUiThread { answer.fold(onSuccess = { result.success(it) }, onFailure = { result.error("vs", it.message, null) }) }
        }.start()
    }

    /** The VS screen as a PNG, for the player's intro. */
    private fun vsFrame(planJson: String, width: Int, height: Int, vsLift: Float, result: MethodChannel.Result) {
        Thread {
            val answer = runCatching {
                val out = java.io.ByteArrayOutputStream()
                VsScreen(JSONObject(planJson), assets, vsLift, height > width).draw(width, height).compress(android.graphics.Bitmap.CompressFormat.PNG, 100, out)
                out.toByteArray()
            }
            runOnUiThread {
                answer.fold(onSuccess = { result.success(it) }, onFailure = { result.error("vs", it.message, null) })
            }
        }.start()
    }

    /** The closing statistics card as a PNG, for the player's ending and the send preview; null when the plan has none. */
    private fun statsFrame(planJson: String, width: Int, height: Int, result: MethodChannel.Result) {
        Thread {
            val answer = runCatching {
                val card = StatsCard(JSONObject(planJson), assets, height > width)
                if (!card.available) null else {
                    val out = java.io.ByteArrayOutputStream()
                    card.draw(width, height).compress(android.graphics.Bitmap.CompressFormat.PNG, 100, out)
                    out.toByteArray()
                }
            }
            runOnUiThread {
                answer.fold(onSuccess = { result.success(it) }, onFailure = { result.error("stats", it.message, null) })
            }
        }.start()
    }

    /** One frame of a recording, as a JPEG — the picture behind the send sheet's preview. */
    private fun frameAt(path: String, atMs: Long, width: Int, result: MethodChannel.Result) {
        Thread {
            val answer = runCatching {
                val r = MediaMetadataRetriever()
                r.setDataSource(path)
                val frame = r.getFrameAtTime(atMs * 1000, MediaMetadataRetriever.OPTION_CLOSEST_SYNC)
                r.release()
                frame?.let {
                    val scaled = android.graphics.Bitmap.createScaledBitmap(it, width, (width.toFloat() * it.height / it.width).toInt().coerceAtLeast(1), true)
                    val out = java.io.ByteArrayOutputStream()
                    scaled.compress(android.graphics.Bitmap.CompressFormat.JPEG, 82, out)
                    out.toByteArray()
                }
            }
            runOnUiThread { answer.fold(onSuccess = { result.success(it) }, onFailure = { result.error("frame", it.message, null) }) }
        }.start()
    }

    /** An export failure in words: Media3's code name, its message, and the cause. */
    @OptIn(UnstableApi::class)
    private fun describe(e: ExportException): String {
        val cause = e.cause?.let { " ← ${it.javaClass.simpleName}: ${it.message}" } ?: ""

        return "${e.errorCodeName}: ${e.message}$cause".take(400)
    }

    /** The export in progress, for the progress readout. */
    private var renderJob: Transformer? = null

    /** 0–100 while a render runs, -1 when unknown. */
    @OptIn(UnstableApi::class)
    private fun progress(): Int {
        val job = renderJob ?: return -1
        val holder = androidx.media3.transformer.ProgressHolder()

        return if (job.getProgress(holder) == Transformer.PROGRESS_STATE_AVAILABLE) holder.progress else -1
    }

    /**
     * Android's own share sheet with this file — WhatsApp, Telegram, Drive,
     * whatever the phone has. The file is offered through a content URI with a
     * one-off read grant, never as a path another app could reach by itself.
     */
    private fun send(path: String, text: String, title: String, result: MethodChannel.Result) {
        try {
            val file = File(path)

            if (!file.isFile) {
                result.error("send", "no such file", null)
                return
            }

            val uri = FileProvider.getUriForFile(this, "$packageName.share", file)

            val intent = Intent(Intent.ACTION_SEND).apply {
                type = "video/mp4"
                putExtra(Intent.EXTRA_STREAM, uri)
                if (text.isNotEmpty()) putExtra(Intent.EXTRA_TEXT, text)
                if (title.isNotEmpty()) putExtra(Intent.EXTRA_SUBJECT, title)
                clipData = ClipData.newRawUri(title, uri)
                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            }

            startActivity(Intent.createChooser(intent, "Send bout"))
            result.success(null)
        } catch (t: Throwable) {
            result.error("send", t.message, null)
        }
    }

    private fun read(): Map<String, Any> {
        val out = HashMap<String, Any>()

        out["model"] = "${Build.MANUFACTURER} ${Build.MODEL} (Android ${Build.VERSION.RELEASE})"

        // Battery percentage, straight off the sticky broadcast.
        try {
            val status = registerReceiver(null, IntentFilter(Intent.ACTION_BATTERY_CHANGED))
            val level = status?.getIntExtra(BatteryManager.EXTRA_LEVEL, -1) ?: -1
            val scale = status?.getIntExtra(BatteryManager.EXTRA_SCALE, -1) ?: -1
            out["battery"] = if (level >= 0 && scale > 0) level * 100 / scale else -1
        } catch (_: Throwable) {
            out["battery"] = -1
        }

        // Thermal status: the encoder's real constraint on a phone in a hot hall.
        out["thermal"] = try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val pm = getSystemService(Context.POWER_SERVICE) as PowerManager
                when (pm.currentThermalStatus) {
                    PowerManager.THERMAL_STATUS_NONE -> "none"
                    PowerManager.THERMAL_STATUS_LIGHT -> "light"
                    PowerManager.THERMAL_STATUS_MODERATE -> "moderate"
                    PowerManager.THERMAL_STATUS_SEVERE -> "SEVERE"
                    PowerManager.THERMAL_STATUS_CRITICAL -> "CRITICAL"
                    PowerManager.THERMAL_STATUS_EMERGENCY -> "EMERGENCY"
                    PowerManager.THERMAL_STATUS_SHUTDOWN -> "SHUTDOWN"
                    else -> "?"
                }
            } else {
                "unsupported"
            }
        } catch (_: Throwable) {
            "?"
        }

        // Room left where the clips are written.
        out["freeMb"] = try {
            val dir = getExternalFilesDir(null) ?: filesDir
            val fs = StatFs(dir.absolutePath)
            (fs.availableBlocksLong * fs.blockSizeLong / (1024L * 1024L)).toInt()
        } catch (_: Throwable) {
            -1
        }

        return out
    }
}
