@auth
{{-- Starts the native background MQTT notification service (Android app, v1.7+ only).
     No Firebase — uses the broker already on the server, so it can't crash from Google
     Play Services. No-op on web and on older builds without the MQTT service. --}}
<script>
(function () {
    var Cap = window.Capacitor;
    if (!Cap || typeof Cap.isNativePlatform !== 'function' || !Cap.isNativePlatform()) return;

    var MqttPush = Cap.Plugins && Cap.Plugins.MqttPush;
    if (!MqttPush || !MqttPush.start) return; // requires the MQTT build (v1.7+)

    if (window.__mqttPushInit) return;
    window.__mqttPushInit = true;

    // Defer off the sign-in transition so it never interferes with login/navigation.
    setTimeout(function () {
        try { MqttPush.start({ baseUrl: window.location.origin }).catch(function () {}); } catch (e) {}
        // A moment later, ask to be exempt from battery optimization (keeps delivery
        // reliable). Native no-ops if already granted, so it prompts at most once.
        setTimeout(function () {
            try { if (MqttPush.requestBatteryExemption) MqttPush.requestBatteryExemption().catch(function () {}); } catch (e) {}
        }, 3000);
    }, 3000);
})();
</script>
@endauth
