# Sensitive / Excluded Files

Paths kept out of `UNTRACKED_SAFE_FILES.tar.gz`, and changed tracked files flagged for a
human look. **No secret value was read, and none appears in this bundle.**

> The `.env` at `/var/www/takeone/.env` was never opened. In an earlier phase only its
> **key names** were listed, with every value replaced by `<redacted>` before display.
> It is not dirty, so it is not part of this bundle at all.

## 1. Not present in the dirty set at all ✅

Checked explicitly and **none** of these appear among the 411 uncommitted paths, so none
could have reached the archive or the patch:

| Path or Pattern | Why Excluded | Recommended Human Handling |
|---|---|---|
| `.env`, `.env.*` | Real credentials — mail password, MQTT password, JWT secret | Never archive. Rotate on any suspicion of exposure |
| `storage/` | Uploads, logs, private media, session files | Back up with `takeone:backup`, never in a review bundle |
| `database/*.sqlite*` | The live database | `takeone:backup` (`VACUUM INTO`), verified read-back |
| `vendor/`, `node_modules/` | Dependencies | Reinstall from the lock files |
| `bootstrap/cache/` | Generated config/route/package caches | Regenerate; never preserve |
| `public/build/` | Vite build output | Rebuild with `npm run build` |
| `.git/` | Repository internals | Not applicable |

## 2. Excluded binary / media / generated files (65)

Real files in the working tree, deliberately left out of the archive.

| Path or Pattern | Why Excluded | Recommended Human Handling |
|---|---|---|
| `*.png` (43) | Binary image — app icons, launcher assets, UI screenshots | Preserve separately if they are new artwork; most are generated icon sets |
| `*.ttf` (12) | Font binaries under `TV/assets/` | Preserve with the TV app source |
| `*.zip` (2) | `drafts/HTML Templates/Karate Shotokan Scoreboard Controller.zip`, `drafts/Camera app with live broadcast.zip` | Design drafts — copy by hand if wanted; opaque archives are not reviewable |
| `*.jpg` (2), `*.jpeg` (1) | Screenshots and a WhatsApp image in `drafts/` | Discard or file outside the repo |
| `*.apk` (1) | Built Android package under `TV/` | Build output — rebuild, never commit |

## 3. ⚠️ Excluded by extension allowlist — NOT sensitive (21)

**These are genuine source files and this is the one exclusion worth acting on.** They were
omitted only because their extensions fall outside the allowlist this phase was given
(`.php .js .css .json .md .yml .yaml .xml .html .svg .txt .sql .sh .ts .tsx .jsx`). They are
**untracked**, so nothing else in this bundle backs them up.

| Path or Pattern | Why Excluded | Recommended Human Handling |
|---|---|---|
| `*.dart` (17) — `TV/lib/src/camera/*`, `TV/lab-app/*` | Flutter source; `.dart` not on the allowlist | **Preserve manually.** This is the boutcam / TV camera work |
| `*.kt` (1), `*.kts` (3) — `TV/android*/…` | Kotlin/Gradle source; not on the allowlist | **Preserve manually** with the Android shell |
| `*.properties` (2), `*.metadata` (1), `*.lock` (1) | Gradle/Flutter project metadata | Preserve with the TV app |
| `.gitignore` (2, untracked in new dirs) | No extension to match | Trivial; recreate if lost |

> Suggested one-liner for the owner, run from `/var/www/takeone`:
> `tar -czf /tmp/tv-app-source.tar.gz $(git ls-files --others --exclude-standard | grep -E '\.(dart|kt|kts|properties)$')`

## 4. Tracked changed files flagged by the path scan (1)

Per the brief, the patch was **not** inspected for content — only paths were scanned.

| Path or Pattern | Why Excluded | Recommended Human Handling |
|---|---|---|
| `resources/views/admin/platform/mobile/backup.blade.php` | Filename contains `backup`. Judged **benign** — it is the Blade view for the admin backup page, i.e. application source, not a credential store. Its change is carried in `TRACKED_CHANGES.patch` | Skim the diff to confirm no literal path, key or credential was hard-coded into the view |

No other changed tracked path matched `secret`, `credential`, `password`, `token`, `key`,
`private`, `dump` or `export`.

## 5. Related, but outside this bundle

| Path or Pattern | Why noted | Recommended Human Handling |
|---|---|---|
| `Documentation/SUPER_ADMIN_CREDENTIALS.md` | A **committed** credentials document. Not dirty, so not in this bundle — but anyone with repo read access has whatever it holds | Rotate the credentials and remove the file from history. Flagged in the Phase 0 report too; still open |
| `mobile/android/app/takeone-release.jks` | Git-ignored release keystore. Not dirty | Copy to secure external storage — losing it means the Play Store app can never be updated |
