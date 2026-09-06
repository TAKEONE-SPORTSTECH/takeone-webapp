# Storage & Cleanup Audit

> Four read-only agents, 2026-08-29. Nothing was modified except the eight provably-empty
> stray files listed under "Done" below. No code, config, database row or upload was changed.

## Done (verified safe, already removed)

Eight files at the repository root, all from one failed headless-screenshot run (identical mtime):

- `p-clubs.html`, `p-honours.html`, `p-record.html`, `p-sports.html` — **0 bytes each**
- `shot-clubs.png`, `shot-honours.png`, `shot-record.png`, `shot-sports.png` — **byte-identical**
  (`md5 876f65a292e78361b49c0d76088ec26c`), 430×1050 blanks

Zero references anywhere in code, views, routes, config or the database. There was no content to lose.

## Would have been a mistake to delete

Each of these looks like clutter and is untracked or unreferenced, but is load-bearing:

| Path | Why it must stay |
|---|---|
| `public/vendor/hls/hls.min.js` | **Untracked but load-bearing.** Five views depend on it via `asset()` — `events/live/watch`, `components/video-player`, both `bout-video` screens. A sweep of untracked files breaks video playback. **It should be committed**, not removed. |
| `resources/images/avatars/{male,female}-source.jpg` | The 896×1200 **masters** for the shipped 600×804 `public/images/avatars/*.jpg` derivatives. No code references them because they are source art. |
| `drafts/` (19 tracked files) | `CLAUDE.md:1136` states the bout-review screens use these verbatim. `.gitignore` deliberately ignores only `drafts/*.png`. |
| `drafts/match-review-mobile.html`, `match-watch-standalone.html` | Newest files in the folder, same class as the tracked siblings — simply not `git add`ed yet. |

## ⚠️ Security findings — these outrank the cleanup

Found while checking whether uploads follow the storage structure. **Not fixed; reported only.**

### 1. `ClubPerkController` accepts an unvalidated destination — `app/Http/Controllers/Admin/ClubPerkController.php:35,75`

The folder *and* the filename come straight from the request:

```php
$request->input('image_folder', 'perks/'.$club->slug)
$request->input('image_filename', 'perk_'.time())
```

`PerkRequest` declares **no rules at all** for either field — unlike the `UploadImageRequest`
endpoints, there is not even a charset guard. A club admin can write to any folder on the
`public` disk under any name: overwrite another club's logo, cover or gallery image, or plant
a file at a path an existing record already points at. Flysystem likely blocks traversal
itself; arbitrary-location overwrite it does not.

### 2. Eight endpoints let the caller choose the destination

All pass `$request->folder` / `$request->filename` into `storeBase64Image(...)` on the `public`
disk. `UploadImageRequest` constrains the *charset* but **not the destination to the caller's
own entity**, so any authenticated user can overwrite another user's avatar by naming its
folder and filename:

`FamilyController:219,871` · `MemberController:912` · `PlatformController:1023,1055,1418` ·
`ClubFacilityController:151` · `ClubInstructorController:330`

Bytes are sniffed and the extension is server-assigned, so this is **not** an RCE path — but it
directly violates the rule that names and paths are ours, never the client's.

### 3. Copy from an unchecked source path — `app/Http/Controllers/Admin/ClubActivityController.php:84`

`Storage::disk('public')->copy()` where the source is derived from `$request->existing_picture_url`
by string-replacing the asset prefix. Only `exists()` is checked — no ownership, no traversal
guard. A club admin can copy any file on the public disk into their own activity folder and
obtain a URL for it.

### 4. Member identity documents are world-readable — `app/Http/Controllers/MemberController.php:1019`

`->store('documents/'.$id, 'public')` puts member documents on the **public** disk, fetchable at
`/storage/documents/{id}/…` with no authorization. Every comparable flow — `event_documents`,
`proof_of_payment` — correctly uses the private disk.

Related, from the database side: `users.documents` stores a **full absolute URL**
(`https://stage.takeone.bh/storage/documents/…`), baking the environment's hostname into the
data. It breaks on any domain change.

## Storage-structure compliance

| Class | Sites |
|---|---|
| Compliant (via `StoragePath`, or documented shape + generated name) | 17 |
| Legacy-known (documented pre-existing flat folders) | 16 |
| **Non-compliant, new** (hand-built roots, or client-controlled path/name) | **24** |
| Writes outside `storage/` (temp dirs, scratch, `.env`) | 9 |

**No site writes into `public/`.** Nothing uses `move_uploaded_file`.

The recurring patterns among the 24:

- **`StoragePath` methods that exist but are never called** — `StoragePath::duel()` (vs the
  hand-built `duel-media/{id}`), `::memberPosts()` (vs `user-posts/{id}`), `::business()` (vs
  `business-logos`), `::memberPayments()` (vs five sites writing a flat `payment-proofs`).
- **Right shape, wrong key** — `clubs/{numeric-id}/gallery|activities|branding|facilities`
  where `StoragePath` uses the club **slug**. Five controllers.
- **Purpose-first, owner-second** — `clubs/logos`, `clubs/covers`, `clubs/splash` in
  `PlatformController`, `ClubApiController` and `ClubCreationService`. This is the exact
  inversion `StoragePath` was written to eliminate: "what belongs to this club?" becomes
  unanswerable.
- **Four competing avatar roots** — `members/`, `people/`, `users/{numeric-id}`, legacy `avatars/`.
  Note `StoragePath::member()` emits `members/{uuid}/…` while several compliant-looking sites
  hand-write `people/{uuid}/…`; shape-correct, but a second root `StoragePath` will never
  produce or clean up.
- **New roots with no `StoragePath` method** — `form-uploads/{form-id}` (arbitrary user files on
  the public disk), `activity-catalog/uploads`, `event-payment-proofs/{tenant-id}/{event-id}`.

`WizardRegistrationController:95` builds the stored name with `getClientOriginalExtension()` —
a **client-supplied extension** rather than a server MIME sniff, unlike every other flow.
`moveTempFile()` at `:671` is dead code with no callers.

## Files in the wrong place on disk

| Path | Size | Problem |
|---|---|---|
| `public/lab/boutcam-9b5db19c645c.apk` | **80 MB** | Untracked, owned by `www-data`, referenced by nothing, **world-downloadable** from the web root. Its siblings live in `storage/app/private/tv/` and are served through `ScreenPairingController` with a whitelist. Needs a human decision: the hashed name suggests it may have been handed to testers as a direct link. |
| `public/app/takeone.apk` (`config/mobile_app.php:27`) | empty | Same mistake, not yet made. `MobileAppController` hands out a direct static URL, so the next release drops ~80 MB into the web root. The correct pattern already exists in this codebase. |
| `public/emperor-dashboard.html` | 50 KB | Tracked, zero references, client-named prototype publicly reachable at a guessable URL. |
| `.env.bak-play-removal-20260827-113113` | 2.9 KB | A secrets snapshot (not opened). Gitignored, so it will never be committed — but it should not live on disk indefinitely. |

## Orphans and hygiene

- **~650 files with no owning database row**: `goal-proofs/` 341 files against 0 non-null rows,
  `order-proofs/` 281 against 0, `activity-catalog/` 20 against 0, plus smaller counts in
  `packages/`, `images/`, `documents/`, `perks/`, `club-products/`, `timeline/`.
  Almost certainly the residue of the demo purge / baseline resets. **Verify against a backup
  before deleting** — the reset history makes it plausible, not certain.
- **`temp/` holds 6 files** — temp uploads are not being swept.
- **Order-proofs, goal-proofs, documents and payment-screenshots sit on the `public` disk**, so
  they are fetchable by URL with no authorization check, while `payment-proofs` is correctly
  private. A policy inconsistency worth settling.
- **`$fileUploads` gaps** — only 11 models declare it. `Tenant` (logo, favicon, cover_image),
  `ClubAffiliation`, `ClubFacility`, `ClubPackage`, `User` (profile_picture, documents) all have
  populated path columns without it, so **deleting one of those rows orphans its files today**.
  JSON-array columns (`club_facilities.images`, `users.documents`, `user_posts.images`) cannot
  use the trait at all as written — it only handles string attributes.
- **`SwitchDatabase.php:54`** rewrites `.env` in place with `file_put_contents`, no lock, no backup.

## Good news

- **No broken attachments.** Every non-null path in the database resolved on disk — 60/60 checked.
- **No stored absolute filesystem paths**, and no database value points outside `storage/`.
- `public/storage` is a correct symlink to `storage/app/public`.
- No backups, dumps, `.sqlite`, `.env*`, keystores or credential-named files under `public/`.
- No `.bak`/`.old`/`.orig`/`~` files anywhere outside ignored build output.

## Suggested order

1. `ClubPerkController` — validate the destination, or drop the client-supplied folder/filename entirely.
2. The eight `UploadImageRequest` endpoints — derive the folder server-side from the resolved entity; ignore `folder`/`filename`.
3. `ClubActivityController:84` — check the source belongs to this club.
4. Move member documents to the private disk, and stop storing an absolute URL in `users.documents`.
5. Commit `public/vendor/hls/hls.min.js` before it disappears in a clean deploy.
6. Decide on the 80 MB APK and `public/emperor-dashboard.html`.
7. Route the APK drop point through a controller like the screen APKs already are.
8. Then, and only with a verified backup, the orphan sweep and the `StoragePath` migrations.
