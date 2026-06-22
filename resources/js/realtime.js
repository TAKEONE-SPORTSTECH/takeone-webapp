/**
 * TAKEONE Realtime — MQTT browser client (powered by takeone/realtime plugin).
 *
 * Connects the signed-in user to the self-hosted broker over WebSockets and
 * turns inbound MQTT messages into DOM CustomEvents the rest of the app already
 * understands. It does NOT touch the DOM itself — feature code listens for:
 *
 *   window.addEventListener('realtime:notification', e => e.detail = {...})
 *   window.addEventListener('realtime:message',      e => e.detail = {...})
 *   window.addEventListener('realtime:status',       e => e.detail = {connected})
 *
 * The browser is subscribe-only; all writes go through normal HTTP endpoints.
 */
import mqtt from 'mqtt';

const RT = {
    client: null,
    creds: null,
    refreshTimer: null,
    connected: false,
};

function emit(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail }));
}

function channelFromTopic(topic) {
    // takeone/user/42/messages -> "messages"
    const parts = topic.split('/');
    return parts[parts.length - 1];
}

async function fetchCredentials() {
    const res = await fetch('/realtime/token', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
    });
    if (!res.ok) return null;
    return res.json();
}

function scheduleRefresh(expiresAt) {
    clearTimeout(RT.refreshTimer);
    // Reconnect ~60s before the token expires so the session never drops.
    const ms = Math.max((expiresAt - Math.floor(Date.now() / 1000) - 60) * 1000, 30000);
    RT.refreshTimer = setTimeout(() => connect(true), ms);
}

async function connect(isRefresh = false) {
    const creds = await fetchCredentials();
    if (!creds || !creds.enabled || !creds.token || !creds.ws_url) return;

    RT.creds = creds;

    // Tear down any prior client on token refresh.
    if (RT.client) {
        try { RT.client.end(true); } catch (e) { /* noop */ }
        RT.client = null;
    }

    const client = mqtt.connect(creds.ws_url, {
        username: creds.username,
        password: creds.token,
        clientId: creds.username + '-' + Math.random().toString(16).slice(2, 10),
        clean: true,
        reconnectPeriod: 4000,
        connectTimeout: 8000,
        keepalive: 30,
    });

    client.on('connect', () => {
        RT.connected = true;
        client.subscribe(creds.topic, { qos: 1 });
        emit('realtime:status', { connected: true });
    });

    client.on('message', (topic, payload) => {
        let data;
        try { data = JSON.parse(payload.toString()); } catch (e) { return; }
        const channel = channelFromTopic(topic);
        // Opt-in debug: run localStorage.setItem('rtdebug','1') in the console
        // to see every inbound realtime event (channel + payload).
        if (window.__rtDebug || localStorage.getItem('rtdebug')) {
            // eslint-disable-next-line no-console
            console.log('[realtime ←]', channel, data);
        }
        if (channel === 'notifications') emit('realtime:notification', data);
        else if (channel === 'messages') emit('realtime:message', data);
        else emit('realtime:' + channel, data);
    });

    client.on('close', () => {
        if (RT.connected) emit('realtime:status', { connected: false });
        RT.connected = false;
    });

    client.on('error', (err) => {
        // Auth failures (e.g. expired token) — force a fresh token+reconnect.
        if (String(err && err.message).includes('Not authorized') && !isRefresh) {
            client.end(true);
            connect(true);
        }
    });

    RT.client = client;
    scheduleRefresh(creds.expires_at);
}

function boot() {
    // Only attempt for authenticated pages (meta injected when logged in).
    if (!document.querySelector('meta[name="rt-user"]')) return;
    connect().catch(() => { /* broker optional — page still works */ });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

window.TakeoneRealtime = RT;
