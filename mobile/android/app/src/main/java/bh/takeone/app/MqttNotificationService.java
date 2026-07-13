package bh.takeone.app;

import android.app.AlarmManager;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.pm.ServiceInfo;
import android.os.Build;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.util.Log;
import android.webkit.CookieManager;

import androidx.core.app.NotificationCompat;

import org.eclipse.paho.client.mqttv3.IMqttDeliveryToken;
import org.eclipse.paho.client.mqttv3.MqttAsyncClient;
import org.eclipse.paho.client.mqttv3.MqttCallback;
import org.eclipse.paho.client.mqttv3.MqttConnectOptions;
import org.eclipse.paho.client.mqttv3.MqttMessage;
import org.eclipse.paho.client.mqttv3.persist.MemoryPersistence;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * Keeps a native MQTT connection to the TAKEONE broker alive so message/notification
 * alerts reach the phone tray even when the app (WebView) is closed — using the same
 * broker/token flow as the web client. No Firebase / no Google Play Services needed.
 */
public class MqttNotificationService extends Service {

    private static final String TAG = "MqttPush";
    public static final String CH_ONGOING = "takeone_service";
    public static final String CH_ALERTS = "takeone_alerts2"; // v2: forces the new sound setting
    private static final int ONGOING_ID = 1001;

    private String baseUrl = "https://takeone.bh";
    private MqttAsyncClient client;
    private final AtomicBoolean running = new AtomicBoolean(false);
    private final Handler handler = new Handler(Looper.getMainLooper());
    private int alertId = 2000;

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (intent != null && intent.getStringExtra("baseUrl") != null) {
            baseUrl = intent.getStringExtra("baseUrl");
        }
        // Must call startForeground quickly — and on Android 10+ (esp. 14) it MUST
        // carry the foreground-service type or the OS throws and kills the service.
        Notification ongoing = buildOngoing("Connecting…");
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                startForeground(ONGOING_ID, ongoing, ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC);
            } else {
                startForeground(ONGOING_ID, ongoing);
            }
        } catch (Throwable e) {
            Log.e(TAG, "startForeground failed", e);
            try { startForeground(ONGOING_ID, ongoing); } catch (Throwable ignore) {}
        }

        if (running.compareAndSet(false, true)) {
            new Thread(this::connectLoop).start();
        }
        return START_STICKY;
    }

    /** When the app is swiped away, ask the OS to restart us shortly after. */
    @Override
    public void onTaskRemoved(Intent rootIntent) {
        try {
            Intent restart = new Intent(getApplicationContext(), MqttNotificationService.class);
            restart.putExtra("baseUrl", baseUrl);
            restart.setPackage(getPackageName());
            int flags = PendingIntent.FLAG_ONE_SHOT;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) flags |= PendingIntent.FLAG_IMMUTABLE;
            PendingIntent pi = PendingIntent.getService(this, 1, restart, flags);
            AlarmManager am = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
            if (am != null) am.set(AlarmManager.RTC_WAKEUP, System.currentTimeMillis() + 1500, pi);
        } catch (Throwable ignore) {}
        super.onTaskRemoved(rootIntent);
    }

    /** Fetch a broker token (via the WebView's session cookie) and connect. */
    private void connectLoop() {
        try {
            JSONObject creds = fetchToken();
            if (creds == null || !creds.optBoolean("enabled", false)) {
                Log.w(TAG, "realtime disabled or no creds; retrying later");
                updateOngoing("Open the app & sign in");
                scheduleReconnect(15000);
                return;
            }

            String wsUrl = creds.optString("ws_url");
            String username = creds.optString("username");
            String token = creds.optString("token");
            final String topic = creds.optString("topic");
            long expiresAt = creds.optLong("expires_at", 0);

            if (wsUrl.isEmpty() || token.isEmpty()) { scheduleReconnect(15000); return; }

            if (client != null) {
                try { client.disconnectForcibly(500); } catch (Exception ignore) {}
                try { client.close(); } catch (Exception ignore) {}
            }

            client = new MqttAsyncClient(wsUrl, username + "-svc-" + System.currentTimeMillis(), new MemoryPersistence());
            client.setCallback(new MqttCallback() {
                @Override public void connectionLost(Throwable cause) {
                    Log.w(TAG, "connection lost: " + (cause == null ? "?" : cause.getMessage()));
                    updateOngoing("Reconnecting…");
                    scheduleReconnect(4000);
                }
                @Override public void messageArrived(String t, MqttMessage message) {
                    handleMessage(t, new String(message.getPayload()));
                }
                @Override public void deliveryComplete(IMqttDeliveryToken t) {}
            });

            MqttConnectOptions opts = new MqttConnectOptions();
            opts.setUserName(username);
            opts.setPassword(token.toCharArray());
            opts.setCleanSession(true);
            opts.setAutomaticReconnect(false);
            opts.setConnectionTimeout(15);
            opts.setKeepAliveInterval(30);

            client.connect(opts, null, new org.eclipse.paho.client.mqttv3.IMqttActionListener() {
                @Override public void onSuccess(org.eclipse.paho.client.mqttv3.IMqttToken t) {
                    try {
                        client.subscribe(topic, 1);
                        Log.i(TAG, "connected + subscribed: " + topic);
                        updateOngoing("Connected · you'll get new messages");
                    } catch (Exception e) { Log.e(TAG, "subscribe failed", e); updateOngoing("Reconnecting…"); }
                }
                @Override public void onFailure(org.eclipse.paho.client.mqttv3.IMqttToken t, Throwable e) {
                    Log.e(TAG, "connect failed: " + (e == null ? "?" : e.getMessage()));
                    updateOngoing("Reconnecting…");
                    scheduleReconnect(6000);
                }
            });

            long nowSec = System.currentTimeMillis() / 1000L;
            long delayMs = Math.max((expiresAt - nowSec - 60) * 1000L, 30000L);
            handler.postDelayed(this::reconnectNow, delayMs);

        } catch (Throwable e) {
            Log.e(TAG, "connectLoop error", e);
            scheduleReconnect(8000);
        }
    }

    private void scheduleReconnect(long ms) {
        if (!running.get()) return;
        handler.postDelayed(this::reconnectNow, ms);
    }

    private void reconnectNow() {
        if (!running.get()) return;
        new Thread(this::connectLoop).start();
    }

    /** GET {baseUrl}/realtime/token using the WebView's cookies. */
    private JSONObject fetchToken() {
        HttpURLConnection conn = null;
        try {
            String cookie = CookieManager.getInstance().getCookie(baseUrl);
            URL url = new URL(baseUrl + "/realtime/token");
            conn = (HttpURLConnection) url.openConnection();
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("X-Requested-With", "XMLHttpRequest");
            if (cookie != null) conn.setRequestProperty("Cookie", cookie);
            conn.setConnectTimeout(10000);
            conn.setReadTimeout(10000);
            conn.setInstanceFollowRedirects(false);

            int code = conn.getResponseCode();
            if (code != 200) { Log.w(TAG, "token http " + code); return null; }

            StringBuilder sb = new StringBuilder();
            BufferedReader r = new BufferedReader(new InputStreamReader(conn.getInputStream()));
            String line; while ((line = r.readLine()) != null) sb.append(line);
            r.close();
            return new JSONObject(sb.toString());
        } catch (Throwable e) {
            Log.e(TAG, "fetchToken error: " + e.getMessage());
            return null;
        } finally {
            if (conn != null) conn.disconnect();
        }
    }

    /** Turn an inbound MQTT payload into a tray notification. */
    private void handleMessage(String topic, String payloadStr) {
        try {
            // App is open in front -> the WebView (realtime.js) already shows it live.
            if (MainActivity.isForeground) return;

            JSONObject p = new JSONObject(payloadStr);
            boolean isMessage = topic.endsWith("/messages");

            String title, body;
            if (isMessage) {
                String action = p.optString("action", "new");
                if ("delete".equals(action) || "edit".equals(action)) return;
                title = p.optString("from_name", p.optString("club_name", "New message"));
                body = p.optString("body", "");
            } else {
                title = p.optString("subject", p.optString("club_name", "TAKEONE"));
                body = p.optString("body", "");
            }
            if (body == null) body = "";

            String actionUrl = p.optString("action_url", "");

            Intent open = new Intent(this, MainActivity.class);
            open.addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP | Intent.FLAG_ACTIVITY_CLEAR_TOP);
            if (!actionUrl.isEmpty()) open.putExtra("action_url", actionUrl);

            int piFlags = PendingIntent.FLAG_UPDATE_CURRENT;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) piFlags |= PendingIntent.FLAG_IMMUTABLE;
            PendingIntent pi = PendingIntent.getActivity(this, alertId, open, piFlags);

            NotificationCompat.Builder b = new NotificationCompat.Builder(this, CH_ALERTS)
                    .setSmallIcon(R.mipmap.ic_launcher)
                    .setContentTitle(title.isEmpty() ? "TAKEONE" : title)
                    .setContentText(body)
                    .setStyle(new NotificationCompat.BigTextStyle().bigText(body))
                    .setAutoCancel(true)
                    .setPriority(NotificationCompat.PRIORITY_HIGH)
                    .setDefaults(NotificationCompat.DEFAULT_ALL)
                    .setContentIntent(pi);

            NotificationManager nm = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            nm.notify(alertId++, b.build());
        } catch (Throwable e) {
            Log.e(TAG, "handleMessage error", e);
        }
    }

    private Notification buildOngoing(String status) {
        createChannels();
        Intent open = new Intent(this, MainActivity.class);
        int piFlags = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) piFlags |= PendingIntent.FLAG_IMMUTABLE;
        PendingIntent pi = PendingIntent.getActivity(this, 0, open, piFlags);

        return new NotificationCompat.Builder(this, CH_ONGOING)
                .setSmallIcon(R.mipmap.ic_launcher)
                .setContentTitle("TAKEONE")
                .setContentText(status)
                .setPriority(NotificationCompat.PRIORITY_MIN)
                .setOngoing(true)
                .setShowWhen(false)
                .setContentIntent(pi)
                .build();
    }

    /** Update the permanent notification's status text (visible connection diagnostic). */
    private void updateOngoing(String status) {
        try {
            NotificationManager nm = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            if (nm != null) nm.notify(ONGOING_ID, buildOngoing(status));
        } catch (Throwable ignore) {}
    }

    private void createChannels() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationManager nm = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            NotificationChannel ongoing = new NotificationChannel(CH_ONGOING, "Background connection", NotificationManager.IMPORTANCE_MIN);
            ongoing.setShowBadge(false);

            NotificationChannel alerts = new NotificationChannel(CH_ALERTS, "Messages & alerts", NotificationManager.IMPORTANCE_HIGH);
            alerts.enableVibration(true);
            alerts.enableLights(true);
            // Explicit default notification sound so alerts are audible.
            android.net.Uri sound = android.media.RingtoneManager.getDefaultUri(android.media.RingtoneManager.TYPE_NOTIFICATION);
            android.media.AudioAttributes attrs = new android.media.AudioAttributes.Builder()
                    .setUsage(android.media.AudioAttributes.USAGE_NOTIFICATION)
                    .setContentType(android.media.AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .build();
            if (sound != null) alerts.setSound(sound, attrs);

            nm.createNotificationChannel(ongoing);
            nm.createNotificationChannel(alerts);
        }
    }

    @Override
    public void onDestroy() {
        running.set(false);
        handler.removeCallbacksAndMessages(null);
        if (client != null) {
            try { client.disconnectForcibly(500); } catch (Exception ignore) {}
            try { client.close(); } catch (Exception ignore) {}
        }
        super.onDestroy();
    }

    @Override public IBinder onBind(Intent intent) { return null; }
}
