# takeone/realtime

Self-hosted **MQTT realtime plugin** for Laravel — instant in-app notifications
and 1:1 messaging with **no page reloads**. Drop-in like a WordPress plugin:
require it, run the bundled broker, flip the switch in the admin panel.

- **Broker:** self-hosted [EMQX](https://www.emqx.io/) (Docker stack included)
- **Server → broker:** `php-mqtt/laravel-client` over TCP, published from your jobs/controllers
- **Browser ← broker:** MQTT.js over WebSockets, subscribe-only
- **Security:** short-lived HS256 JWT per user; EMQX enforces per-user topic ACL
- **Graceful:** broker down? DB writes still succeed; users just see updates on next load

---

## 1. Install

```bash
composer require takeone/realtime
php artisan migrate          # creates realtime_settings
```

Add the JS client (already wired in this project via `resources/js/realtime.js`):

```bash
php artisan vendor:publish --tag=realtime-assets   # copies realtime.js
npm install mqtt && npm run build
```

Import it in `resources/js/app.js`:

```js
import './realtime';
```

Inject an auth marker the client gates on (in your `<head>`, authed users only):

```blade
@auth <meta name="rt-user" content="{{ Auth::id() }}"> @endauth
```

## 2. Run the broker

```bash
php artisan vendor:publish --tag=realtime-docker    # copies docker/ stack
cd docker/realtime
EMQX_JWT_SECRET="$(php -r 'echo getenv("APP_KEY");')" docker compose up -d
```

Dashboard: http://localhost:18083 (default `admin` / `public`). Create the
backend publisher account (username `takeone-server`) and mark it a superuser,
or use the local-dev escape hatch in `emqx/emqx.conf`.

## 3. Configure

Add to `.env`:

```dotenv
REALTIME_ENABLED=true
REALTIME_MQTT_HOST=127.0.0.1
REALTIME_MQTT_PORT=1883
REALTIME_MQTT_USERNAME=takeone-server
REALTIME_MQTT_PASSWORD=your-broker-password
REALTIME_MQTT_WS_URL=ws://127.0.0.1:8083/mqtt    # wss://yourhost/mqtt in prod
REALTIME_JWT_SECRET=                              # defaults to APP_KEY; must match EMQX_JWT_SECRET
REALTIME_JWT_TTL=3600
```

…or set everything from the **admin → Realtime / MQTT** page (DB overrides win
over env). Hit **Test connection** to confirm the broker is reachable.

## 4. Use it

```php
// Push an arbitrary payload to one user's channel
Realtime()->publishToUser($userId, 'notifications', [
    'id' => 123, 'subject' => 'Welcome!', 'club_name' => 'Acme BJJ',
]);

// Efficient fan-out over a single connection
Realtime()->publishMany([
    ['topic' => Realtime()->userTopic(1, 'messages'), 'payload' => [...]],
    ['topic' => Realtime()->userTopic(2, 'messages'), 'payload' => [...]],
]);
```

Browser side — listen for the CustomEvents `realtime.js` emits:

```js
window.addEventListener('realtime:notification', e => { /* e.detail */ });
window.addEventListener('realtime:message',      e => { /* e.detail */ });
window.addEventListener('realtime:status',       e => { /* {connected} */ });
```

## Topics

```
takeone/user/{id}/notifications
takeone/user/{id}/messages
```

A user may **subscribe** to `takeone/user/{ownId}/#` only (enforced by the JWT
ACL claim) and may **not publish** — all writes flow through normal HTTP routes.

## Configuration reference

See `config/realtime.php`. Publish it with:

```bash
php artisan vendor:publish --tag=realtime-config
```
