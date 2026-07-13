# Push Notifications Setup (Firebase Cloud Messaging)

Everything in the code is **already built and wired**. To turn push on you only need to
create a free Firebase project and drop **two files** into place. No coding. ~15 minutes.

When done, users get a phone notification for **any** chat message or app notification —
**even when the app is closed or the phone is locked**.

---

## How it works (so you know what you're setting up)

```
Something happens on the server (new message, like, payment approved, …)
        │
        ├─► MQTT  ──►  open app updates live   (already existed)
        │
        └─► FCM   ──►  Google  ──►  phone's notification tray   (NEW — works when app is closed)
```

FCM (Firebase Cloud Messaging) is Google's free push service. It needs:
- **`google-services.json`** — identifies the Android app (goes in the app).
- **A service-account key** — lets your Laravel server send pushes (goes on the server).

---

## Step 1 — Create a Firebase project (once)

1. Go to <https://console.firebase.google.com> and sign in with a Google account.
2. Click **Add project** → name it e.g. `TAKEONE` → Continue.
3. Google Analytics is optional — you can disable it → **Create project**.

## Step 2 — Add the Android app → get `google-services.json`

1. In the project, click the **Android** icon (“Add app”).
2. **Android package name:** `bh.takeone.app`  ← must match exactly.
3. App nickname: `TAKEONE` (optional). Click **Register app**.
4. Click **Download google-services.json**.
5. Put that file here:
   ```
   mobile/android/app/google-services.json
   ```
6. Skip the remaining “add SDK” steps in the wizard — Capacitor already did that.

## Step 3 — Get the server key (service account)

1. Firebase console → ⚙️ **Project settings** → **Service accounts** tab.
2. Click **Generate new private key** → **Generate key**. A `.json` file downloads.
3. Put that file on the **server** (not the app) here:
   ```
   storage/app/firebase/service-account.json
   ```
   > This file is secret — it's already git-ignored. Never commit it or share it.

## Step 4 — Turn it on (server)

Make sure the queue worker is running (it already is, for other jobs) and refresh config:

```bash
php artisan config:clear
php artisan queue:restart
```

`FCM_ENABLED=true` is the default. Set `FCM_ENABLED=false` in `.env` to switch push off.

## Step 5 — Rebuild the app with Firebase baked in

Now that `google-services.json` exists, rebuild so the app can register for push:

```bash
cd mobile
npm run sync
cd android && ./gradlew assembleRelease     # signed installable APK
# → android/app/build/outputs/apk/release/app-release.apk
```

Install that APK on your phone (replace the old one).

## Step 6 — Test it

1. Open the app and log in. Android will ask **“Allow notifications?”** → Allow.
   (This registers the device — behind the scenes it POSTs the FCM token to
   `/me/push-tokens`.)
2. **Close the app completely** (swipe it away).
3. From another account, send that user a chat message (or trigger any notification —
   a like, a club message, a payment approval).
4. A notification appears in the phone's tray. Tapping it opens the app to the right screen.

---

## Troubleshooting

- **No permission prompt / no token:** make sure you rebuilt AFTER adding
  `google-services.json` (Step 5). Without it, push is inert by design.
- **Prompt appears but no notifications arrive:** check the server has
  `storage/app/firebase/service-account.json`, then `php artisan config:clear` and confirm a
  `php artisan queue:work` (or Supervisor) is running. Failures are logged in
  `storage/logs/laravel.log` (search `FCM`).
- **“Package name mismatch” in Firebase:** the Android app package MUST be `bh.takeone.app`.
- **Tokens table:** registered devices live in the `push_tokens` DB table (one row per device).
  Dead tokens are pruned automatically when Google reports them gone.

## What was built (for reference)

| Piece | Location |
|---|---|
| Device-token storage | `push_tokens` table + `App\Models\PushToken` |
| Register/unregister endpoint | `POST/DELETE /me/push-tokens` → `PushTokenController` |
| FCM sender (HTTP v1, no SDK) | `App\Services\FcmService` |
| Queued delivery | `App\Jobs\SendPushNotification` |
| Hooked into notifications | `UserNotification::notifyUser()` |
| Hooked into DMs | `MessengerController::pushRealtime()` |
| Hooked into club messages | `Admin\ClubMessageController::pushRealtime()` |
| App-side registration JS | `resources/views/partials/push-register.blade.php` |
| Native plugin | `@capacitor/push-notifications` (in `mobile/`) |
