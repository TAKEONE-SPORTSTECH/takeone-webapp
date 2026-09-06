package bh.takeone.takeone_lab

import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.BatteryManager
import android.os.Build
import android.os.PowerManager
import android.os.StatFs
import android.view.WindowManager
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

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
