# Karate Release — Commit Set for Review

> **Nothing has been staged, committed, merged, pushed, deployed or migrated.** This is a
> proposal for your review, generated from the working tree at `/var/www/takeone`
> (branch `development`, HEAD `c12d1ada`, 421 uncommitted paths when expanded).
> Date: 2026-08-29

## Why the scope is this large

Shipping the Karate release alone is impossible. The Karate console builds a URL from
`karate-scoreboard.photo`, which exists only in the modified `routes/web.php` — and that file
references **9 controllers that exist in no commit** (Open Mat, camera console, live streaming,
media vaults, bout video, media streaming). Commit `routes/web.php` without them and
`route:cache` fails and every request 500s.

So the release unit is effectively the whole uncommitted tree. What follows orders it so that
**the application boots after every single commit**.

## Ordering guarantee

Every new `app/`, `config/` and migration file lands in commit 3 — before anything routes it
and before any modified file calls it. Verified mechanically by resolving every `App\…` class
reference in all 421 paths and checking its commit index:

```
ordering violations (a commit using a class committed later): 0
```

## The commits

### Commit 1. Docs

**Purpose:** Documentation only — no runtime effect.

**Paths:** 28  ·  **Risk:** Low

**Notes:** The review trail plus CLAUDE.md rule updates. Safe to land first; gives the rest of the review something to cite.

<details><summary>File list</summary>

```
CLAUDE.md
Documentation/MATSIDE-CAMERA.md
Documentation/MCP.md
Documentation/OPEN-MAT.md
Documentation/play-patch/MatchController.php
Documentation/play-patch/MatchMediaImporter.php
Documentation/play-patch/README.md
Documentation/play-patch/TimelineController.php
Documentation/play-patch/UploadController.php
Documentation/play-patch/routes-api.php
docs/F1_AUDIT_LOG_CONFIG_REVIEW.md
docs/F2_SCREEN_TOKEN_SECURITY_REPORT.md
docs/KARATE_RELEASE_SAFETY_REPORT.md
docs/KARATE_RELEASE_TEST_FIX_REPORT.md
docs/KARATE_SCORING_RELEASE_DEPENDENCIES.md
docs/PHASE_0_SCORING_SAFETY_REPORT.md
docs/PHASE_1_TEST_ENVIRONMENT_AND_AUTHORIZATION_REPORT.md
docs/SPORTS_ARCHITECTURE_AUDIT.md
docs/SPORTS_ARCHITECTURE_EVIDENCE_BUNDLE.md
docs/UNCOMMITTED_REVIEW_CHANGE_GROUPS.md
docs/UNCOMMITTED_REVIEW_README.md
docs/UNCOMMITTED_REVIEW_SENSITIVE_FILES_EXCLUDED.md
docs/UNCOMMITTED_REVIEW_WORKTREE_INVENTORY.md
drafts/Camera app with live broadcast.zip
drafts/HTML Templates/Karate Shotokan Scoreboard Controller.zip
drafts/WhatsApp Image 2026-08-26 at 2.32.15 AM.jpeg
drafts/match-review-mobile.html
drafts/match-watch-standalone.html
```

</details>

### Commit 2. Play removal

**Purpose:** Delete the disconnected video-platform integration (12 tracked deletions).

**Paths:** 12  ·  **Risk:** Low

**Notes:** Self-contained and already documented as intentional. `MatchEventLog` no longer reads `config('play.*')`, so either ordering is safe.

<details><summary>File list</summary>

```
Documentation/VIDEO-INTEGRATION.md
app/Console/Commands/PlayHealth.php
app/Console/Commands/PlayPullTimeline.php
app/Console/Commands/PlayToken.php
app/Http/Controllers/Api/PlayIntegrationController.php
app/Jobs/PushBoutToPlay.php
app/Models/PlayTimelinePoint.php
app/Models/PlayTimelineRound.php
app/Play/BoutPayload.php
app/Play/MirrorBoutTimeline.php
app/Play/PlayClient.php
config/play.php
```

</details>

### Commit 3. New code + migrations

**Purpose:** Every NEW app/config/migration file: media vaults, live streaming, cameras, Open Mat, bout video, and 16 migrations.

**Paths:** 79  ·  **Risk:** High

**Notes:** **Dead code until commit 5 routes it**, so the app boots unchanged after this lands. Contains all 16 new migrations — these are what will run against production.

<details><summary>File list</summary>

```
app/Console/Commands/LiveReap.php
app/Console/Commands/MediaDrain.php
app/Console/Commands/MediaMigrate.php
app/Console/Commands/MediaVerify.php
app/Events/OpenMat/OpenMat.php
app/Events/OpenMat/OpenMatCode.php
app/Events/OpenMat/OpenMatController.php
app/Events/OpenMat/OpenMatCorner.php
app/Events/OpenMat/OpenMatPerson.php
app/Events/OpenMat/OpenMatResult.php
app/Events/OpenMat/OpenMatSession.php
app/Events/OpenMat/resources/lang/ar/messages.php
app/Events/OpenMat/resources/lang/en/messages.php
app/Events/OpenMat/resources/views/join.blade.php
app/Events/OpenMat/resources/views/manage/desktop.blade.php
app/Events/OpenMat/resources/views/manage/fill-sheet.blade.php
app/Events/OpenMat/resources/views/manage/mobile.blade.php
app/Events/OpenMat/resources/views/manage/runtime.blade.php
app/Events/OpenMat/resources/views/mat-panel.blade.php
app/Events/OpenMat/resources/views/no-club.blade.php
app/Events/Support/Cameras/CameraChannel.php
app/Events/Support/Cameras/CameraConsoleController.php
app/Events/Support/Cameras/CameraController.php
app/Events/Support/Cameras/CameraFleet.php
app/Events/Support/Live/LabController.php
app/Events/Support/Live/LiveAuthController.php
app/Events/Support/Live/LiveStreamController.php
app/Http/Controllers/Admin/MediaVaultController.php
app/Http/Controllers/BoutVideoController.php
app/Jobs/IngestClipMedia.php
app/Jobs/IngestLiveRecording.php
app/Jobs/MigrateMediaToVault.php
app/Jobs/TranscodeMedia.php
app/Mcp/Tools/GetBoutVideoTool.php
app/Mcp/Tools/ListEventVideosTool.php
app/Media/BoutArena.php
app/Media/BoutFilm.php
app/Media/BoutTimeline.php
app/Media/Contracts/VaultDriver.php
app/Media/Drivers/LocalDriver.php
app/Media/Drivers/MountDriver.php
app/Media/Drivers/SmbDriver.php
app/Media/Ffmpeg.php
app/Media/Http/MediaStreamController.php
app/Media/MediaIngest.php
app/Media/MediaVaults.php
app/Media/VideoLibrary.php
app/Models/BoutCoachNote.php
app/Models/BoutComment.php
app/Models/BoutCommentLike.php
app/Models/EventCamera.php
app/Models/EventCameraClip.php
app/Models/LiveStream.php
app/Models/MediaFile.php
app/Models/MediaVault.php
app/Observers/UserObserver.php
app/Support/Avatar.php
app/Support/BoutHistory.php
app/Support/BoutStage.php
app/Support/StoragePath.php
config/events.php
config/live.php
config/media.php
database/migrations/2026_08_23_120000_create_event_cameras_table.php
database/migrations/2026_08_23_180000_add_play_upload_to_event_camera_clips.php
database/migrations/2026_08_24_150000_add_scoreboard_settings_to_club_events.php
database/migrations/2026_08_25_120000_create_open_mat_tables.php
database/migrations/2026_08_25_160000_create_open_mat_people_table.php
database/migrations/2026_08_25_170000_create_media_vaults_table.php
database/migrations/2026_08_25_170100_create_media_files_table.php
database/migrations/2026_08_25_170200_add_media_file_to_clips_and_recordings.php
database/migrations/2026_08_25_180000_create_live_streams_table.php
database/migrations/2026_08_25_190000_add_match_repointed_to_live_streams.php
database/migrations/2026_08_25_200000_create_open_mat_codes_table.php
database/migrations/2026_08_26_060000_add_club_to_open_mat_tables.php
database/migrations/2026_08_26_090000_add_remote_arm_to_live_streams.php
database/migrations/2026_08_26_100000_add_broadcasting_to_event_cameras.php
database/migrations/2026_08_27_080000_create_bout_coach_notes_table.php
database/migrations/2026_08_27_130000_create_bout_comments_table.php
```

</details>

### Commit 4. Scoring + contract

**Purpose:** The Karate and Taekwondo scoring engines, the EventType contract pair, ClubEvent, screens support.

**Paths:** 34  ·  **Risk:** High

**Notes:** The reviewed Karate release unit. Depends on CameraFleet from commit 3. This is the code that decides bouts.

<details><summary>File list</summary>

```
app/Events/AbstractEventType.php
app/Events/Contracts/EventType.php
app/Events/Sports/Karate/Karate.php
app/Events/Sports/Karate/Tournament/CourtDisplay/CourtDisplayController.php
app/Events/Sports/Karate/Tournament/Scoreboard/MatState.php
app/Events/Sports/Karate/Tournament/Scoreboard/ScoreboardController.php
app/Events/Sports/Karate/Tournament/Scoreboard/Scoring.php
app/Events/Sports/Karate/Tournament/Tournament.php
app/Events/Sports/Karate/Tournament/resources/lang/ar/messages.php
app/Events/Sports/Karate/Tournament/resources/lang/en/messages.php
app/Events/Sports/Karate/Tournament/resources/views/court-display/board.blade.php
app/Events/Sports/Karate/Tournament/resources/views/court-display/screen.blade.php
app/Events/Sports/Karate/Tournament/resources/views/scoreboard/control.blade.php
app/Events/Sports/Karate/Tournament/resources/views/scoreboard/mat.blade.php
app/Events/Sports/Karate/resources/lang/ar/messages.php
app/Events/Sports/Karate/resources/lang/en/messages.php
app/Events/Sports/Taekwondo/Taekwondo.php
app/Events/Sports/Taekwondo/Tournament/CourtDisplay/CourtDisplayController.php
app/Events/Sports/Taekwondo/Tournament/Scoreboard/ScoreboardController.php
app/Events/Sports/Taekwondo/Tournament/Scoreboard/Scoring.php
app/Events/Sports/Taekwondo/Tournament/resources/views/court-display/board.blade.php
app/Events/Sports/Taekwondo/Tournament/resources/views/court-display/screen.blade.php
app/Events/Sports/Taekwondo/Tournament/resources/views/scoreboard/control.blade.php
app/Events/Sports/Taekwondo/Tournament/resources/views/scoreboard/mat.blade.php
app/Events/Sports/Taekwondo/resources/lang/ar/messages.php
app/Events/Sports/Taekwondo/resources/lang/en/messages.php
app/Events/Support/HallScreenRouter.php
app/Events/Support/MatchEventLog.php
app/Events/Support/ScreenMedia.php
app/Events/Support/ScreenMediaController.php
app/Events/Support/ScreenPairingController.php
app/Models/ClubEvent.php
app/Sports/Combat/AbstractCombatSport.php
app/Sports/Combat/CombatSport.php
```

</details>

### Commit 5. ROUTES (activates all)

**Purpose:** routes/web.php, routes/api.php, routes/console.php, bootstrap/app.php.

**Paths:** 4  ·  **Risk:** High

**Notes:** **The activation switch.** Everything above is inert until this lands. Verified: all 67 classes it references exist by this point.

<details><summary>File list</summary>

```
bootstrap/app.php
routes/api.php
routes/console.php
routes/web.php
```

</details>

### Commit 6. Tests

**Purpose:** The Phase 0/1 characterisation and authorization tests, plus the two corrected tests.

**Paths:** 5  ·  **Risk:** Low

**Notes:** No runtime effect. Land with or after commit 4 so the Karate tests pass.

<details><summary>File list</summary>

```
tests/Feature/Events/KarateScoreboardSettingsSafetyTest.php
tests/Feature/Events/ScoringAuthorizationSafetyTest.php
tests/Feature/McpServerTest.php
tests/Unit/Events/Sports/Karate/KarateScoringSafetyTest.php
tests/Unit/Events/Sports/Taekwondo/TaekwondoScoringSafetyTest.php
```

</details>

### Commit 7. TV/mobile app

**Purpose:** Flutter kiosk and boutcam camera app.

**Paths:** 117  ·  **Risk:** Medium

**Notes:** Separate deliverable; no effect on the web app at runtime.

<details><summary>File list</summary>

```
TV/android/app/build.gradle.kts
TV/android/app/src/main/AndroidManifest.xml
TV/android/app/src/main/kotlin/bh/takeone/tv/MainActivity.kt
TV/android/app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml
TV/android/app/src/main/res/mipmap-hdpi/ic_launcher_foreground.png
TV/android/app/src/main/res/mipmap-hdpi/ic_launcher_monochrome.png
TV/android/app/src/main/res/mipmap-mdpi/ic_launcher_foreground.png
TV/android/app/src/main/res/mipmap-mdpi/ic_launcher_monochrome.png
TV/android/app/src/main/res/mipmap-xhdpi/ic_launcher_foreground.png
TV/android/app/src/main/res/mipmap-xhdpi/ic_launcher_monochrome.png
TV/android/app/src/main/res/mipmap-xxhdpi/ic_launcher_foreground.png
TV/android/app/src/main/res/mipmap-xxhdpi/ic_launcher_monochrome.png
TV/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher_foreground.png
TV/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher_monochrome.png
TV/android/app/src/main/res/values/ic_launcher_background.xml
TV/android_cam/AndroidManifest.xml
TV/android_shared_res/drawable-xhdpi/tv_banner.png
TV/android_shared_res/mipmap-anydpi-v26/ic_launcher.xml
TV/android_shared_res/mipmap-hdpi/ic_launcher.png
TV/android_shared_res/mipmap-hdpi/ic_launcher_foreground.png
TV/android_shared_res/mipmap-hdpi/ic_launcher_monochrome.png
TV/android_shared_res/mipmap-mdpi/ic_launcher.png
TV/android_shared_res/mipmap-mdpi/ic_launcher_foreground.png
TV/android_shared_res/mipmap-mdpi/ic_launcher_monochrome.png
TV/android_shared_res/mipmap-xhdpi/ic_launcher.png
TV/android_shared_res/mipmap-xhdpi/ic_launcher_foreground.png
TV/android_shared_res/mipmap-xhdpi/ic_launcher_monochrome.png
TV/android_shared_res/mipmap-xxhdpi/ic_launcher.png
TV/android_shared_res/mipmap-xxhdpi/ic_launcher_foreground.png
TV/android_shared_res/mipmap-xxhdpi/ic_launcher_monochrome.png
TV/android_shared_res/mipmap-xxxhdpi/ic_launcher.png
TV/android_shared_res/mipmap-xxxhdpi/ic_launcher_foreground.png
TV/android_shared_res/mipmap-xxxhdpi/ic_launcher_monochrome.png
TV/android_shared_res/values/ic_launcher_background.xml
TV/android_tab/AndroidManifest.xml
TV/android_tv/AndroidManifest.xml
TV/assets/fonts/ArchivoBlack-Regular.ttf
TV/assets/fonts/BarlowCondensed-Bold.ttf
TV/assets/fonts/BarlowCondensed-Regular.ttf
TV/assets/fonts/BarlowCondensed-SemiBold.ttf
TV/build.sh
TV/lab-app/.gitignore
TV/lab-app/.metadata
TV/lab-app/README.md
TV/lab-app/analysis_options.yaml
TV/lab-app/android/.gitignore
TV/lab-app/android/app/build.gradle.kts
TV/lab-app/android/app/src/debug/AndroidManifest.xml
TV/lab-app/android/app/src/main/AndroidManifest.xml
TV/lab-app/android/app/src/main/kotlin/bh/takeone/takeone_lab/MainActivity.kt
TV/lab-app/android/app/src/main/res/README.md
TV/lab-app/android/app/src/main/res/drawable-v21/launch_background.xml
TV/lab-app/android/app/src/main/res/drawable-xhdpi/tv_banner.png
TV/lab-app/android/app/src/main/res/drawable/launch_background.xml
TV/lab-app/android/app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml
TV/lab-app/android/app/src/main/res/mipmap-hdpi/ic_launcher.png
TV/lab-app/android/app/src/main/res/mipmap-hdpi/ic_launcher_foreground.png
TV/lab-app/android/app/src/main/res/mipmap-hdpi/ic_launcher_monochrome.png
TV/lab-app/android/app/src/main/res/mipmap-mdpi/ic_launcher.png
TV/lab-app/android/app/src/main/res/mipmap-mdpi/ic_launcher_foreground.png
TV/lab-app/android/app/src/main/res/mipmap-mdpi/ic_launcher_monochrome.png
TV/lab-app/android/app/src/main/res/mipmap-xhdpi/ic_launcher.png
TV/lab-app/android/app/src/main/res/mipmap-xhdpi/ic_launcher_foreground.png
TV/lab-app/android/app/src/main/res/mipmap-xhdpi/ic_launcher_monochrome.png
TV/lab-app/android/app/src/main/res/mipmap-xxhdpi/ic_launcher.png
TV/lab-app/android/app/src/main/res/mipmap-xxhdpi/ic_launcher_foreground.png
TV/lab-app/android/app/src/main/res/mipmap-xxhdpi/ic_launcher_monochrome.png
TV/lab-app/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png
TV/lab-app/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher_foreground.png
TV/lab-app/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher_monochrome.png
TV/lab-app/android/app/src/main/res/values-night/styles.xml
TV/lab-app/android/app/src/main/res/values/ic_launcher_background.xml
TV/lab-app/android/app/src/main/res/values/styles.xml
TV/lab-app/android/app/src/profile/AndroidManifest.xml
TV/lab-app/android/build.gradle.kts
TV/lab-app/android/gradle.properties
TV/lab-app/android/gradle/wrapper/gradle-wrapper.properties
TV/lab-app/android/settings.gradle.kts
TV/lab-app/assets/fonts/ArchivoBlack-Regular.ttf
TV/lab-app/assets/fonts/BarlowCondensed-Bold.ttf
TV/lab-app/assets/fonts/BarlowCondensed-Regular.ttf
TV/lab-app/assets/fonts/BarlowCondensed-SemiBold.ttf
TV/lab-app/assets/fonts/Poppins-Bold.ttf
TV/lab-app/assets/fonts/Poppins-Medium.ttf
TV/lab-app/assets/fonts/Poppins-Regular.ttf
TV/lab-app/assets/fonts/Poppins-SemiBold.ttf
TV/lab-app/build.sh
TV/lab-app/lib/api.dart
TV/lab-app/lib/kit.dart
TV/lab-app/lib/library.dart
TV/lab-app/lib/link.dart
TV/lab-app/lib/main.dart
TV/lab-app/lib/tally.dart
TV/lab-app/lib/vault.dart
TV/lab-app/pubspec.lock
TV/lab-app/pubspec.yaml
TV/lib/main.dart
TV/lib/src/camera/api.dart
TV/lib/src/camera/clips.dart
TV/lib/src/camera/drawer.dart
TV/lib/src/camera/kit.dart
TV/lib/src/camera/link.dart
TV/lib/src/camera/player.dart
TV/lib/src/camera/recorder.dart
TV/lib/src/camera/station.dart
TV/lib/src/camera/uploader.dart
TV/lib/src/config.dart
TV/lib/src/device.dart
TV/lib/src/kiosk.dart
TV/pubspec.lock
TV/pubspec.yaml
TV/test/drawer_golden_test.dart
TV/test/goldens/delete-dialog.png
TV/test/goldens/drawer-select.png
TV/test/goldens/drawer.png
TV/tools-make-icons.php
public/lab/boutcam-9b5db19c645c.apk
```

</details>

### Commit 8. Web/UI remainder

**Purpose:** Remaining Blade views, language files and controllers.

**Paths:** 142  ·  **Risk:** Medium

**Notes:** Largely presentation. Must land for the new routes to render their pages.

<details><summary>File list</summary>

```
app/Http/Controllers/Admin/PlatformController.php
app/Http/Controllers/MemberController.php
app/Http/Controllers/PersonalEventController.php
app/Http/Controllers/PersonalMobileController.php
app/Http/Requests/TournamentRequest.php
app/Mcp/Servers/TakeOneServer.php
app/Models/EventRecording.php
app/Providers/AppServiceProvider.php
config/event_types.php
lang/ar/events.php
lang/ar/header.php
lang/ar/member.php
lang/ar/nav.php
lang/ar/personal.php
lang/ar/shared.php
lang/en/events.php
lang/en/header.php
lang/en/member.php
lang/en/nav.php
lang/en/personal.php
lang/en/shared.php
p-clubs.html
p-honours.html
p-record.html
p-sports.html
public/vendor/hls/hls.min.js
resources/css/app.css
resources/images/avatars/female-source.jpg
resources/images/avatars/male-source.jpg
resources/views/admin/ai/index.blade.php
resources/views/admin/ai/mobile.blade.php
resources/views/admin/club/achievements/mobile.blade.php
resources/views/admin/club/activities/index.blade.php
resources/views/admin/club/activities/mobile.blade.php
resources/views/admin/club/events/mobile-form.blade.php
resources/views/admin/club/facilities/mobile-form.blade.php
resources/views/admin/club/financials/mobile.blade.php
resources/views/admin/club/instructors/mobile-add.blade.php
resources/views/admin/club/instructors/mobile-edit.blade.php
resources/views/admin/club/members/mobile.blade.php
resources/views/admin/club/members/partials/member-popup.blade.php
resources/views/admin/club/members/partials/mobile-add-member.blade.php
resources/views/admin/club/packages/mobile-form.blade.php
resources/views/admin/club/perks/mobile-form.blade.php
resources/views/admin/club/roles/mobile-access-form.blade.php
resources/views/admin/club/roles/mobile-role-form.blade.php
resources/views/admin/club/roles/mobile.blade.php
resources/views/admin/club/shop/mobile.blade.php
resources/views/admin/club/timeline/mobile-form.blade.php
resources/views/admin/platform/mobile/activities.blade.php
resources/views/admin/platform/mobile/backup.blade.php
resources/views/admin/storage/index.blade.php
resources/views/admin/storage/mobile.blade.php
resources/views/auth/register-wizard.blade.php
resources/views/components-templates/member/mobile/partials/achievement-detail-sheet.blade.php
resources/views/components-templates/member/mobile/partials/tournament-detail-sheet.blade.php
resources/views/components-templates/member/mobile/show.blade.php
resources/views/components-templates/member/show.blade.php
resources/views/components/bout-highlights.blade.php
resources/views/components/bout-vs-card.blade.php
resources/views/components/club-modal.blade.php
resources/views/components/court-screens.blade.php
resources/views/components/event-checklist.blade.php
resources/views/components/event-documents.blade.php
resources/views/components/event-live.blade.php
resources/views/components/event-screen-audio.blade.php
resources/views/components/media-lightbox.blade.php
resources/views/components/member-add-chooser-mobile.blade.php
resources/views/components/member-create-sheet-mobile.blade.php
resources/views/components/member-search-existing-sheet-mobile.blade.php
resources/views/components/partials/profile-modal-fields.blade.php
resources/views/components/partials/profile-modal-mobile.blade.php
resources/views/components/profile-photo-sheet.blade.php
resources/views/components/qr-code.blade.php
resources/views/components/schedule-session-modal.blade.php
resources/views/components/screen-pairing.blade.php
resources/views/components/substitute-picker.blade.php
resources/views/components/video-player.blade.php
resources/views/events/live/broadcast.blade.php
resources/views/events/live/watch.blade.php
resources/views/events/screen/claim.blade.php
resources/views/events/screen/new.blade.php
resources/views/family/mobile/index.blade.php
resources/views/family/mobile/tree.blade.php
resources/views/family/show.blade.php
resources/views/layouts/admin.blade.php
resources/views/layouts/app.blade.php
resources/views/layouts/personal-mobile.blade.php
resources/views/messenger/mobile.blade.php
resources/views/partials/event-finance-sheet.blade.php
resources/views/partials/event-join-sheet.blade.php
resources/views/partials/event-payment-proof.blade.php
resources/views/partials/event-results-sheet.blade.php
resources/views/partials/event-squad-entry.blade.php
resources/views/partials/mobile-chat.blade.php
resources/views/partials/mobile-header.blade.php
resources/views/people/partials/vouch-list.blade.php
resources/views/personal/desktop/bout-video.blade.php
resources/views/personal/desktop/challenge-history.blade.php
resources/views/personal/desktop/challenge.blade.php
resources/views/personal/desktop/duel-show.blade.php
resources/views/personal/desktop/event-bout.blade.php
resources/views/personal/desktop/event-bracket.blade.php
resources/views/personal/desktop/event-gallery.blade.php
resources/views/personal/desktop/event-manage.blade.php
resources/views/personal/desktop/event-next-up.blade.php
resources/views/personal/desktop/event-show.blade.php
resources/views/personal/desktop/events.blade.php
resources/views/personal/desktop/get-app.blade.php
resources/views/personal/desktop/home.blade.php
resources/views/personal/desktop/market-show.blade.php
resources/views/personal/desktop/market.blade.php
resources/views/personal/desktop/packages.blade.php
resources/views/personal/desktop/schedule-show.blade.php
resources/views/personal/desktop/schedule.blade.php
resources/views/personal/desktop/settings.blade.php
resources/views/personal/desktop/videos.blade.php
resources/views/personal/event-create.blade.php
resources/views/personal/mobile/bout-video.blade.php
resources/views/personal/mobile/duel-show.blade.php
resources/views/personal/mobile/event-bout.blade.php
resources/views/personal/mobile/event-bracket.blade.php
resources/views/personal/mobile/event-gallery.blade.php
resources/views/personal/mobile/event-manage.blade.php
resources/views/personal/mobile/event-show.blade.php
resources/views/personal/mobile/events.blade.php
resources/views/personal/mobile/market-show.blade.php
resources/views/personal/mobile/market.blade.php
resources/views/personal/mobile/schedule-show.blade.php
resources/views/personal/mobile/videos.blade.php
resources/views/personal/orders.blade.php
resources/views/personal/partials/event-people.blade.php
resources/views/personal/partials/post-viewers-modal.blade.php
resources/views/personal/payments.blade.php
resources/views/platform/mobile/explore.blade.php
resources/views/platform/mobile/show.blade.php
resources/views/platform/partials/join-club-modal-mobile.blade.php
resources/views/security/mobile.blade.php
shot-clubs.png
shot-honours.png
shot-record.png
shot-sports.png
```

</details>

## The 16 migrations that would run on production

These are already applied on this box. They have **never** run anywhere else.

```
database/migrations/2026_08_23_120000_create_event_cameras_table.php
database/migrations/2026_08_23_180000_add_play_upload_to_event_camera_clips.php
database/migrations/2026_08_24_150000_add_scoreboard_settings_to_club_events.php
database/migrations/2026_08_25_120000_create_open_mat_tables.php
database/migrations/2026_08_25_160000_create_open_mat_people_table.php
database/migrations/2026_08_25_170000_create_media_vaults_table.php
database/migrations/2026_08_25_170100_create_media_files_table.php
database/migrations/2026_08_25_170200_add_media_file_to_clips_and_recordings.php
database/migrations/2026_08_25_180000_create_live_streams_table.php
database/migrations/2026_08_25_190000_add_match_repointed_to_live_streams.php
database/migrations/2026_08_25_200000_create_open_mat_codes_table.php
database/migrations/2026_08_26_060000_add_club_to_open_mat_tables.php
database/migrations/2026_08_26_090000_add_remote_arm_to_live_streams.php
database/migrations/2026_08_26_100000_add_broadcasting_to_event_cameras.php
database/migrations/2026_08_27_080000_create_bout_coach_notes_table.php
database/migrations/2026_08_27_130000_create_bout_comments_table.php
```

## What still blocks a production deploy

| # | Blocker | Why it matters |
|---|---|---|
| 1 | **Production deploys from `main`** by an Actions runner on 192.168.0.37. Nothing reaches it until these commits are merged there | Mechanical prerequisite |
| 2 | **The media / live / Open Mat subsystems have had no review and no test pass.** Only the 22-file Karate unit was reviewed | Commit 3 is 79 paths of unreviewed code, including all 16 migrations |
| 3 | **18 pre-existing suite failures** unrelated to this release | CI will not be green |
| 4 | **No verified off-box backup.** Latest is `db-20260828-033001.sqlite` on this same disk | CLAUDE.md RULE #2 — no backup, no migration |
| 5 | **Pre-Launch Runbook open items** — production `APP_DEBUG`, `LOG_LEVEL`, off-box backups | Documented go-live gates |
| 6 | **Migration-before-code ordering on prod** | `MatState::persistSettings()` writes `scoreboard_settings` unguarded; code-first means a 500 at a live mat |

## Proposed sequence — for a human to run

```bash
# 1. Verified backup FIRST (RULE #2), and get a copy OFF this box
sudo -u actionsrunner php artisan takeone:backup

# 2. Commit in order, on development. Review each diff before committing.
#    (Commit 5 is the activation switch — nothing is live until it lands.)
git add <files from commit 1> && git commit
#    … repeat for commits 2 through 8 …

# 3. Push development, let CI run. Expect the 18 pre-existing failures.
git push origin development

# 4. Merge to main only once you accept those failures and the unreviewed scope.
#    The Actions runner then deploys to 192.168.0.37.

# 5. On production, in this order — the order is the whole point:
php artisan down
php artisan migrate --force        # BEFORE the new code serves a request
php artisan config:cache && php artisan route:cache && php artisan view:cache
npm ci && npm run build
php artisan up

# 6. Verify: open a Karate console (proves karate-scoreboard.photo resolves),
#    issue a 'meta' command, set a rule and confirm it survives a reload.
```

## Rollback

Revert the commits and redeploy. **Leave the migrations applied** — every one is additive and
nullable, so old code ignores the new columns and no data is lost. Rolling migrations back
would drop real competition configuration. Never flush the cache mid-competition: running mats
hold their score only in the cache.

## Status

**Nothing executed.** No `git add`, `commit`, `merge`, `push`, `tag` or branch change; no
migration, no cache command, no deployment; production was not contacted. The working tree is
exactly as it was found.
