package bh.takeone.app;

import android.Manifest;
import android.app.DownloadManager;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.os.PowerManager;
import android.provider.Settings;
import android.util.Log;

import androidx.core.content.ContextCompat;
import androidx.core.content.FileProvider;

import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.io.File;

/**
 * Native bridge:
 *  - start()/stop(): the background MQTT notification service (no Firebase).
 *  - requestBatteryExemption(): ask the OS to keep the service alive (reliability).
 *  - downloadAndInstall(): in-app self-update.
 */
@CapacitorPlugin(name = "MqttPush")
public class MqttPushPlugin extends Plugin {

    @PluginMethod
    public void start(PluginCall call) {
        try {
            // Notification permission (Android 13+) — needed to SHOW alerts.
            if (Build.VERSION.SDK_INT >= 33) {
                if (ContextCompat.checkSelfPermission(getContext(), Manifest.permission.POST_NOTIFICATIONS)
                        != PackageManager.PERMISSION_GRANTED) {
                    try { getActivity().requestPermissions(new String[]{ Manifest.permission.POST_NOTIFICATIONS }, 7311); } catch (Throwable ignore) {}
                }
            }

            String baseUrl = call.getString("baseUrl", "https://takeone.bh");
            Intent svc = new Intent(getContext(), MqttNotificationService.class);
            svc.putExtra("baseUrl", baseUrl);
            ContextCompat.startForegroundService(getContext(), svc);
            call.resolve();
        } catch (Throwable e) {
            call.reject("start failed: " + e.getMessage());
        }
    }

    @PluginMethod
    public void stop(PluginCall call) {
        try {
            getContext().stopService(new Intent(getContext(), MqttNotificationService.class));
            call.resolve();
        } catch (Throwable e) {
            call.reject("stop failed: " + e.getMessage());
        }
    }

    /** Ask the OS to exempt us from battery optimization so the service isn't killed. */
    @PluginMethod
    public void requestBatteryExemption(PluginCall call) {
        try {
            PowerManager pm = (PowerManager) getContext().getSystemService(Context.POWER_SERVICE);
            String pkg = getContext().getPackageName();
            if (pm != null && Build.VERSION.SDK_INT >= Build.VERSION_CODES.M
                    && !pm.isIgnoringBatteryOptimizations(pkg)) {
                Intent i = new Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS);
                i.setData(Uri.parse("package:" + pkg));
                i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                getContext().startActivity(i);
            }
            call.resolve();
        } catch (Throwable e) {
            call.reject("battery exemption failed: " + e.getMessage());
        }
    }

    /** Download an APK (only from our own https origin) and launch the system installer. */
    @PluginMethod
    public void downloadAndInstall(PluginCall call) {
        try {
            String url = call.getString("url", "");
            Uri uri = Uri.parse(url);
            if (!("https".equalsIgnoreCase(uri.getScheme()) && "takeone.bh".equalsIgnoreCase(uri.getHost()))) {
                call.reject("invalid url");
                return;
            }

            final Context ctx = getContext();
            final File out = new File(ctx.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS), "takeone-update.apk");
            if (out.exists()) out.delete();

            DownloadManager dm = (DownloadManager) ctx.getSystemService(Context.DOWNLOAD_SERVICE);
            DownloadManager.Request req = new DownloadManager.Request(uri);
            req.setTitle("TAKEONE update");
            req.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            req.setMimeType("application/vnd.android.package-archive");
            req.setDestinationInExternalFilesDir(ctx, Environment.DIRECTORY_DOWNLOADS, "takeone-update.apk");
            final long id = dm.enqueue(req);

            BroadcastReceiver receiver = new BroadcastReceiver() {
                @Override public void onReceive(Context c, Intent i) {
                    long done = i.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1);
                    if (done != id) return;
                    try { c.unregisterReceiver(this); } catch (Throwable ignore) {}
                    installApk(c, out);
                }
            };
            ContextCompat.registerReceiver(ctx, receiver,
                    new IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE),
                    ContextCompat.RECEIVER_EXPORTED);

            call.resolve();
        } catch (Throwable e) {
            call.reject("download failed: " + e.getMessage());
        }
    }

    private void installApk(Context ctx, File apk) {
        try {
            if (!apk.exists() || apk.length() == 0) { Log.e("MqttPush", "apk missing"); return; }
            Uri content = FileProvider.getUriForFile(ctx, ctx.getPackageName() + ".fileprovider", apk);
            Intent intent = new Intent(Intent.ACTION_VIEW);
            intent.setDataAndType(content, "application/vnd.android.package-archive");
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_ACTIVITY_NEW_TASK);
            ctx.startActivity(intent);
        } catch (Throwable e) {
            Log.e("MqttPush", "install failed", e);
        }
    }
}
