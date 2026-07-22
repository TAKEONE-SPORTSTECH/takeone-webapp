package bh.takeone.app;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;

import androidx.core.content.ContextCompat;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    /** True while the app UI is in front — the service uses this to avoid
     *  double-notifying (the open WebView already shows the update live). */
    public static volatile boolean isForeground = false;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(MqttPushPlugin.class);
        super.onCreate(savedInstanceState);
        allowMediaAutoplay();
        handleDeepLink(getIntent());
        // Start the notification service natively so it never depends on web JS.
        ensureNotificationPermission();
        startMqttService();
    }

    /**
     * Let the WebView play sounds (notification chimes) without a prior user
     * gesture. Regular browsers enforce the autoplay policy; inside our own app
     * shell we opt out so notification tones ring the moment they arrive.
     */
    private void allowMediaAutoplay() {
        try {
            if (getBridge() != null && getBridge().getWebView() != null) {
                getBridge().getWebView().getSettings().setMediaPlaybackRequiresUserGesture(false);
            }
        } catch (Throwable ignore) {}
    }

    private void ensureNotificationPermission() {
        if (Build.VERSION.SDK_INT >= 33) {
            try {
                if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                        != PackageManager.PERMISSION_GRANTED) {
                    requestPermissions(new String[]{ Manifest.permission.POST_NOTIFICATIONS }, 7311);
                }
            } catch (Throwable ignore) {}
        }
    }

    private void startMqttService() {
        try {
            Intent svc = new Intent(this, MqttNotificationService.class);
            svc.putExtra("baseUrl", "https://takeone.bh");
            ContextCompat.startForegroundService(this, svc);
        } catch (Throwable ignore) {}
    }

    @Override
    public void onResume() {
        super.onResume();
        isForeground = true;
    }

    @Override
    public void onPause() {
        super.onPause();
        isForeground = false;
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleDeepLink(intent);
    }

    /** When a tray notification is tapped, navigate the WebView to its action_url. */
    private void handleDeepLink(Intent intent) {
        if (intent == null) return;
        final String target = sanitizeUrl(intent.getStringExtra("action_url"));
        if (target == null) return; // reject anything that isn't our own https origin

        // Defer until the bridge/webview is ready, then navigate.
        getWindow().getDecorView().postDelayed(() -> {
            try {
                if (getBridge() != null && getBridge().getWebView() != null) {
                    getBridge().getWebView().post(() -> getBridge().getWebView().loadUrl(target));
                }
            } catch (Throwable ignore) {}
        }, 600);
    }

    /**
     * Only allow navigation to our own site. MainActivity is an exported LAUNCHER,
     * so a hostile app could send an intent with a crafted action_url — reject
     * anything that isn't an https://takeone.bh URL (or a root-relative path we map
     * onto it). This blocks javascript:/data:/other-host/protocol-relative payloads.
     */
    private String sanitizeUrl(String raw) {
        if (raw == null || raw.isEmpty()) return null;
        try {
            if (raw.startsWith("/") && !raw.startsWith("//")) {
                return "https://takeone.bh" + raw;
            }
            Uri u = Uri.parse(raw);
            if ("https".equalsIgnoreCase(u.getScheme()) && "takeone.bh".equalsIgnoreCase(u.getHost())) {
                return raw;
            }
        } catch (Throwable ignore) {}
        return null;
    }
}
