# TAKEONE — Android App (Capacitor)

A native Android shell that wraps the TAKEONE web app (`https://takeone.bh`) using
[Capacitor](https://capacitorjs.com). It produces a real `.apk`/`.aab` you can install
on a phone or upload to Google Play, with access to native device APIs.

- **App ID:** `bh.takeone.app`
- **App name:** TAKEONE
- **Loads:** `https://takeone.bh` (set in `capacitor.config.json` → `server.url`)
- **Native plugins:** App (hardware back button), StatusBar (brand purple), SplashScreen

Everything here is self-contained — it does **not** affect the Laravel app or its Vite build.

---

## What's already done

- ✅ Capacitor project + native Android Gradle project (`android/`)
- ✅ App icons + splash screens for every density (brand purple `#7F6CE0`), light + dark
- ✅ App name, brand colors, status-bar color
- ✅ Permissions: Internet, Camera (QR scanner / photo cropper), Location (map picker)

## What you need to build the APK

The final compile step needs the Android toolchain (not required for anything above):

1. **JDK 17** — `sudo apt install openjdk-17-jdk` (Linux) or Temurin 17.
2. **Android SDK** — easiest via [Android Studio](https://developer.android.com/studio),
   or command-line tools only. Install **Platform 34** + **Build-Tools 34**.
3. Point Gradle at the SDK — create `android/local.properties`:
   ```properties
   sdk.dir=/path/to/Android/sdk
   ```
   (Android Studio writes this automatically when you open the `android/` folder.)

## Build it

From this `mobile/` folder:

```bash
# Debug APK (installable directly on a phone with USB debugging / sideload)
npm run build:debug
# → android/app/build/outputs/apk/debug/app-debug.apk

# Release bundle (for the Play Store — requires signing, see below)
npm run build:release
# → android/app/build/outputs/bundle/release/app-release.aab
```

Or open in Android Studio and hit ▶ Run:

```bash
npm run open      # opens the android/ project in Android Studio
```

## Install the debug APK on a phone

- **USB:** enable Developer Options → USB debugging, then `adb install android/app/build/outputs/apk/debug/app-debug.apk`
- **Sideload:** copy the `.apk` to the phone and tap it (allow "install unknown apps").

## Signing — ALREADY SET UP ✅

Release signing is wired up and working. Built artifacts are signed with:

- **Keystore:** `android/app/takeone-release.jks`
- **Credentials:** `android/keystore.properties` (git-ignored)
  - store/key password: `TakeOne2026!`
  - alias: `takeone`
- Signing config lives in `android/app/build.gradle` (reads `keystore.properties`).

### 🚨 CRITICAL — back up the keystore

`android/app/takeone-release.jks` + its passwords are the **only** way to publish updates
to the same Play Store app. **If you lose this file, you can never update the app** — you'd
have to publish a brand-new listing. Copy `takeone-release.jks` and `keystore.properties`
somewhere safe (password manager / secure backup) **now**. They are intentionally git-ignored
so they are NOT in the repo.

### Build signed artifacts

```bash
npm run build:release      # → android/app/build/outputs/bundle/release/app-release.aab  (upload to Play)
# signed installable APK:
cd android && ./gradlew assembleRelease
# → android/app/build/outputs/apk/release/app-release.apk
```

---

## Releasing an update (in-app updater)

The app has a built-in **“Get the App / Update available”** hub (in the member drawer →
top item, and at `/me/app`). Installed apps poll `/app/manifest.json` and compare their
`versionCode` to the server's. To ship an update:

1. **Bump the version** in `android/app/build.gradle`:
   ```gradle
   versionCode 2          // must INCREASE every release
   versionName "1.1"
   ```
2. **Build** the signed APK: `cd android && ./gradlew assembleRelease`.
3. **Publish** it to the download path and update the manifest the app reads:
   - Copy the new APK to `public/app/takeone.apk` (on the server).
   - Set the matching values in `config/mobile_app.php` (or via `.env`):
     ```
     ANDROID_VERSION_NAME=1.1
     ANDROID_VERSION_CODE=2
     ANDROID_RELEASE_NOTES="What changed…"
     ```
   - `php artisan config:clear`
4. Done. Every installed app now shows **“Update available”** in the drawer; tapping it
   downloads the new APK and opens the installer. (Web browsers see a **Download** button.)

> The download path (`public/app/takeone.apk`) is git-ignored so the binary isn't committed —
> place it on the server as part of deploy.

## Changing the site it loads

Edit `capacitor.config.json` → `server.url`, then run `npm run sync`.

- **Production:** `https://takeone.bh` (current)
- **Local testing against your dev machine:** set it to your LAN IP,
  e.g. `http://192.168.1.20:8000`, and add that host to `server.allowNavigation`.
  For a plain `http://` dev URL you must also allow cleartext traffic in the Android manifest.

## Regenerating icons / splash

The source images are `resources/icon.png` (1024²) and `resources/splash.png` (2732²),
composited from `public/images/logo.png` by `gen-assets.mjs`. To refresh (e.g. after
dropping in a higher-resolution logo):

```bash
node gen-assets.mjs
npx capacitor-assets generate --android \
  --iconBackgroundColor '#7F6CE0' --iconBackgroundColorDark '#7F6CE0' \
  --splashBackgroundColor '#7F6CE0' --splashBackgroundColorDark '#7F6CE0'
```

> The current icon is built from the existing 120×120 logo, so it's a little soft when
> upscaled. Drop a crisp ≥1024×1024 `resources/icon.png` and re-run the two commands above
> for a sharp launcher icon.

## Useful scripts

| Command | What it does |
|---|---|
| `npm run sync` | Copy config + web assets into the native project (run after config edits) |
| `npm run open` | Open the Android project in Android Studio |
| `npm run build:debug` | Build a debug `.apk` |
| `npm run build:release` | Build a release `.aab` for Play |
| `npm run assets` | Regenerate icons/splash from `resources/` |

## Push notifications — BUILT ✅ (needs Firebase project)

Native push (tray notifications when the app is closed) is fully implemented — chat messages
and every app notification. The code is done; you just create a free Firebase project and
drop in two files. **See [`PUSH-SETUP.md`](PUSH-SETUP.md)** for the ~15-min walkthrough.

`@capacitor/push-notifications` is already installed and synced.

## Other native features you can add later

Because this is Capacitor (not just a web wrapper), you can progressively add native
capability without rewriting the web app:

- **Native camera / gallery** — `@capacitor/camera`.
- **Biometric unlock**, **share sheet**, **haptics**, **status/keyboard** control, etc.
