# Worktree Inventory

Every uncommitted path in `/var/www/takeone`, classified **by path only**. No file contents were read, and no secret value appears anywhere in this bundle.

**411 paths** (untracked directories expanded to individual files; `git status --short` collapses these to 300 lines).

`Sensitive/Excluded?` refers to the untracked-file archive only — a `no` on a tracked file simply means it is carried in `TRACKED_CHANGES.patch`.


## Live scoring — Karate (15)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| tracked-modified | `app/Events/Sports/Karate/Karate.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/Tournament/CourtDisplay/CourtDisplayController.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/Tournament/Scoreboard/MatState.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/Tournament/Scoreboard/ScoreboardController.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/Tournament/Scoreboard/Scoring.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/Tournament/Tournament.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/Tournament/resources/lang/ar/messages.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/Tournament/resources/lang/en/messages.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/Tournament/resources/views/court-display/board.blade.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/Tournament/resources/views/court-display/screen.blade.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/Tournament/resources/views/scoreboard/control.blade.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/Tournament/resources/views/scoreboard/mat.blade.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/resources/lang/ar/messages.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Karate/resources/lang/en/messages.php` | Live scoring — Karate | no — in patch | Karate competition package — treat as match-deciding |
| untracked | `tests/Unit/Events/Sports/Karate/KarateScoringSafetyTest.php` | Live scoring — Karate | no — archived | Karate competition package — treat as match-deciding |

## Live scoring — Taekwondo (11)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| tracked-modified | `app/Events/Sports/Taekwondo/Taekwondo.php` | Live scoring — Taekwondo | no — in patch | Taekwondo competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Taekwondo/Tournament/CourtDisplay/CourtDisplayController.php` | Live scoring — Taekwondo | no — in patch | Taekwondo competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Taekwondo/Tournament/Scoreboard/ScoreboardController.php` | Live scoring — Taekwondo | no — in patch | Taekwondo competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Taekwondo/Tournament/Scoreboard/Scoring.php` | Live scoring — Taekwondo | no — in patch | Taekwondo competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Taekwondo/Tournament/resources/views/court-display/board.blade.php` | Live scoring — Taekwondo | no — in patch | Taekwondo competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Taekwondo/Tournament/resources/views/court-display/screen.blade.php` | Live scoring — Taekwondo | no — in patch | Taekwondo competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Taekwondo/Tournament/resources/views/scoreboard/control.blade.php` | Live scoring — Taekwondo | no — in patch | Taekwondo competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Taekwondo/Tournament/resources/views/scoreboard/mat.blade.php` | Live scoring — Taekwondo | no — in patch | Taekwondo competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Taekwondo/resources/lang/ar/messages.php` | Live scoring — Taekwondo | no — in patch | Taekwondo competition package — treat as match-deciding |
| tracked-modified | `app/Events/Sports/Taekwondo/resources/lang/en/messages.php` | Live scoring — Taekwondo | no — in patch | Taekwondo competition package — treat as match-deciding |
| untracked | `tests/Unit/Events/Sports/Taekwondo/TaekwondoScoringSafetyTest.php` | Live scoring — Taekwondo | no — archived | Taekwondo competition package — treat as match-deciding |

## Tournament/event domain (40)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| tracked-modified | `app/Events/AbstractEventType.php` | Tournament/event domain | no — in patch | Shared event, media or bracket domain |
| tracked-modified | `app/Events/Contracts/EventType.php` | Tournament/event domain | no — in patch | Shared event, media or bracket domain |
| tracked-modified | `app/Events/Support/MatchEventLog.php` | Tournament/event domain | no — in patch | Shared event, media or bracket domain |
| tracked-modified | `app/Http/Controllers/PersonalEventController.php` | Tournament/event domain | no — in patch | Shared event, media or bracket domain |
| tracked-modified | `app/Models/ClubEvent.php` | Tournament/event domain | no — in patch | Shared event, media or bracket domain |
| tracked-modified | `app/Models/EventRecording.php` | Tournament/event domain | no — in patch | Shared event, media or bracket domain |
| tracked-modified | `app/Sports/Combat/AbstractCombatSport.php` | Tournament/event domain | no — in patch | Shared event, media or bracket domain |
| tracked-modified | `app/Sports/Combat/CombatSport.php` | Tournament/event domain | no — in patch | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/OpenMat.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/OpenMatCode.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/OpenMatController.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/OpenMatCorner.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/OpenMatPerson.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/OpenMatResult.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/OpenMatSession.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/resources/lang/ar/messages.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/resources/lang/en/messages.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/resources/views/join.blade.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/resources/views/manage/desktop.blade.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/resources/views/manage/fill-sheet.blade.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/resources/views/manage/mobile.blade.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/resources/views/manage/runtime.blade.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/resources/views/mat-panel.blade.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/OpenMat/resources/views/no-club.blade.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/Support/Live/LabController.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/Support/Live/LiveAuthController.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Events/Support/Live/LiveStreamController.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Mcp/Tools/ListEventVideosTool.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/BoutArena.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/BoutFilm.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/BoutTimeline.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/Contracts/VaultDriver.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/Drivers/LocalDriver.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/Drivers/MountDriver.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/Drivers/SmbDriver.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/Ffmpeg.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/Http/MediaStreamController.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/MediaIngest.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/MediaVaults.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |
| untracked | `app/Media/VideoLibrary.php` | Tournament/event domain | no — archived | Shared event, media or bracket domain |

## Screens/TV/court display (15)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| tracked-modified | `app/Events/Support/HallScreenRouter.php` | Screens/TV/court display | no — in patch | Hall screen / pairing / camera surface |
| tracked-modified | `app/Events/Support/ScreenMedia.php` | Screens/TV/court display | no — in patch | Hall screen / pairing / camera surface |
| tracked-modified | `app/Events/Support/ScreenMediaController.php` | Screens/TV/court display | no — in patch | Hall screen / pairing / camera surface |
| tracked-modified | `app/Events/Support/ScreenPairingController.php` | Screens/TV/court display | no — in patch | Hall screen / pairing / camera surface |
| tracked-modified | `resources/views/components/court-screens.blade.php` | Screens/TV/court display | no — in patch | Hall screen / pairing / camera surface |
| tracked-modified | `resources/views/components/event-screen-audio.blade.php` | Screens/TV/court display | no — in patch | Hall screen / pairing / camera surface |
| tracked-modified | `resources/views/components/screen-pairing.blade.php` | Screens/TV/court display | no — in patch | Hall screen / pairing / camera surface |
| tracked-modified | `resources/views/events/screen/claim.blade.php` | Screens/TV/court display | no — in patch | Hall screen / pairing / camera surface |
| tracked-modified | `resources/views/events/screen/new.blade.php` | Screens/TV/court display | no — in patch | Hall screen / pairing / camera surface |
| untracked | `app/Events/Support/Cameras/CameraChannel.php` | Screens/TV/court display | no — archived | Hall screen / pairing / camera surface |
| untracked | `app/Events/Support/Cameras/CameraConsoleController.php` | Screens/TV/court display | no — archived | Hall screen / pairing / camera surface |
| untracked | `app/Events/Support/Cameras/CameraController.php` | Screens/TV/court display | no — archived | Hall screen / pairing / camera surface |
| untracked | `app/Events/Support/Cameras/CameraFleet.php` | Screens/TV/court display | no — archived | Hall screen / pairing / camera surface |
| untracked | `app/Models/EventCamera.php` | Screens/TV/court display | no — archived | Hall screen / pairing / camera surface |
| untracked | `app/Models/EventCameraClip.php` | Screens/TV/court display | no — archived | Hall screen / pairing / camera surface |

## Database/migrations (16)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| untracked | `database/migrations/2026_08_23_120000_create_event_cameras_table.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_23_180000_add_play_upload_to_event_camera_clips.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_24_150000_add_scoreboard_settings_to_club_events.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_25_120000_create_open_mat_tables.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_25_160000_create_open_mat_people_table.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_25_170000_create_media_vaults_table.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_25_170100_create_media_files_table.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_25_170200_add_media_file_to_clips_and_recordings.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_25_180000_create_live_streams_table.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_25_190000_add_match_repointed_to_live_streams.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_25_200000_create_open_mat_codes_table.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_26_060000_add_club_to_open_mat_tables.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_26_090000_add_remote_arm_to_live_streams.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_26_100000_add_broadcasting_to_event_cameras.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_27_080000_create_bout_coach_notes_table.php` | Database/migrations | no — archived | Schema — must ship with its code |
| untracked | `database/migrations/2026_08_27_130000_create_bout_comments_table.php` | Database/migrations | no — archived | Schema — must ship with its code |

## Mobile/TV native app (116)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| tracked-modified | `TV/android/app/build.gradle.kts` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/android/app/src/main/AndroidManifest.xml` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/android/app/src/main/kotlin/bh/takeone/tv/MainActivity.kt` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/android_shared_res/drawable-xhdpi/tv_banner.png` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/android_shared_res/mipmap-hdpi/ic_launcher.png` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/android_shared_res/mipmap-mdpi/ic_launcher.png` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/android_shared_res/mipmap-xhdpi/ic_launcher.png` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/android_shared_res/mipmap-xxhdpi/ic_launcher.png` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/android_shared_res/mipmap-xxxhdpi/ic_launcher.png` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/android_tab/AndroidManifest.xml` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/android_tv/AndroidManifest.xml` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/build.sh` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/lib/main.dart` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/lib/src/config.dart` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/lib/src/device.dart` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/lib/src/kiosk.dart` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/pubspec.lock` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/pubspec.yaml` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| tracked-modified | `TV/tools-make-icons.php` | Mobile/TV native app | no — in patch | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/mipmap-hdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/mipmap-hdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/mipmap-mdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/mipmap-mdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/mipmap-xhdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/mipmap-xhdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/mipmap-xxhdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/mipmap-xxhdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android/app/src/main/res/values/ic_launcher_background.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/android_cam/AndroidManifest.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/mipmap-anydpi-v26/ic_launcher.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/mipmap-hdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/mipmap-hdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/mipmap-mdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/mipmap-mdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/mipmap-xhdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/mipmap-xhdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/mipmap-xxhdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/mipmap-xxhdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/mipmap-xxxhdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/mipmap-xxxhdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/android_shared_res/values/ic_launcher_background.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/assets/fonts/ArchivoBlack-Regular.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/assets/fonts/BarlowCondensed-Bold.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/assets/fonts/BarlowCondensed-Regular.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/assets/fonts/BarlowCondensed-SemiBold.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/.gitignore` | Mobile/TV native app | **EXCLUDED** — Extension "gitignore" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/.metadata` | Mobile/TV native app | **EXCLUDED** — Extension "metadata" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/README.md` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/analysis_options.yaml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/.gitignore` | Mobile/TV native app | **EXCLUDED** — Extension "gitignore" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/build.gradle.kts` | Mobile/TV native app | **EXCLUDED** — Extension "kts" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/debug/AndroidManifest.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/AndroidManifest.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/kotlin/bh/takeone/takeone_lab/MainActivity.kt` | Mobile/TV native app | **EXCLUDED** — Extension "kt" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/README.md` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/drawable-v21/launch_background.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/drawable-xhdpi/tv_banner.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/drawable/launch_background.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-hdpi/ic_launcher.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-hdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-hdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-mdpi/ic_launcher.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-mdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-mdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-xhdpi/ic_launcher.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-xhdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-xhdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-xxhdpi/ic_launcher.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-xxhdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-xxhdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher_foreground.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher_monochrome.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/values-night/styles.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/values/ic_launcher_background.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/main/res/values/styles.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/app/src/profile/AndroidManifest.xml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/build.gradle.kts` | Mobile/TV native app | **EXCLUDED** — Extension "kts" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/gradle.properties` | Mobile/TV native app | **EXCLUDED** — Extension "properties" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/gradle/wrapper/gradle-wrapper.properties` | Mobile/TV native app | **EXCLUDED** — Extension "properties" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/android/settings.gradle.kts` | Mobile/TV native app | **EXCLUDED** — Extension "kts" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/assets/fonts/ArchivoBlack-Regular.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/assets/fonts/BarlowCondensed-Bold.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/assets/fonts/BarlowCondensed-Regular.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/assets/fonts/BarlowCondensed-SemiBold.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/assets/fonts/Poppins-Bold.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/assets/fonts/Poppins-Medium.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/assets/fonts/Poppins-Regular.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/assets/fonts/Poppins-SemiBold.ttf` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (ttf) | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/build.sh` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/lib/api.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/lib/kit.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/lib/library.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/lib/link.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/lib/main.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/lib/tally.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/lib/vault.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/pubspec.lock` | Mobile/TV native app | **EXCLUDED** — Extension "lock" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lab-app/pubspec.yaml` | Mobile/TV native app | no — archived | Flutter/Android kiosk + camera app |
| untracked | `TV/lib/src/camera/api.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lib/src/camera/clips.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lib/src/camera/drawer.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lib/src/camera/kit.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lib/src/camera/link.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lib/src/camera/player.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lib/src/camera/recorder.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lib/src/camera/station.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/lib/src/camera/uploader.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/test/drawer_golden_test.dart` | Mobile/TV native app | **EXCLUDED** — Extension "dart" outside the allowlist | Flutter/Android kiosk + camera app |
| untracked | `TV/test/goldens/delete-dialog.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/test/goldens/drawer-select.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |
| untracked | `TV/test/goldens/drawer.png` | Mobile/TV native app | **EXCLUDED** — Binary/media/generated (png) | Flutter/Android kiosk + camera app |

## Web/UI/Blade (152)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| tracked-modified | `app/Http/Controllers/Admin/PlatformController.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `app/Http/Controllers/MemberController.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `app/Http/Controllers/PersonalMobileController.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `app/Http/Requests/TournamentRequest.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `app/Mcp/Servers/TakeOneServer.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `app/Providers/AppServiceProvider.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/ar/events.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/ar/header.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/ar/member.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/ar/nav.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/ar/personal.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/ar/shared.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/en/events.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/en/header.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/en/member.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/en/nav.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/en/personal.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `lang/en/shared.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/css/app.css` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/ai/index.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/ai/mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/achievements/mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/activities/index.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/activities/mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/events/mobile-form.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/facilities/mobile-form.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/financials/mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/instructors/mobile-add.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/instructors/mobile-edit.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/members/mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/members/partials/member-popup.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/members/partials/mobile-add-member.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/packages/mobile-form.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/perks/mobile-form.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/roles/mobile-access-form.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/roles/mobile-role-form.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/roles/mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/shop/mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/club/timeline/mobile-form.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/platform/mobile/activities.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/admin/platform/mobile/backup.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/auth/register-wizard.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components-templates/member/mobile/partials/achievement-detail-sheet.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components-templates/member/mobile/show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components-templates/member/show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/club-modal.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/event-checklist.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/event-documents.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/member-add-chooser-mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/member-create-sheet-mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/member-search-existing-sheet-mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/partials/profile-modal-fields.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/partials/profile-modal-mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/profile-photo-sheet.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/qr-code.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/schedule-session-modal.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/components/substitute-picker.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/family/mobile/index.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/family/mobile/tree.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/family/show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/layouts/admin.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/layouts/app.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/layouts/personal-mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/messenger/mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/partials/event-finance-sheet.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/partials/event-join-sheet.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/partials/event-payment-proof.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/partials/event-results-sheet.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/partials/event-squad-entry.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/partials/mobile-chat.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/partials/mobile-header.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/people/partials/vouch-list.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/challenge-history.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/challenge.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/duel-show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/event-bout.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/event-bracket.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/event-manage.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/event-next-up.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/event-show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/events.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/get-app.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/home.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/market-show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/market.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/packages.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/schedule-show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/schedule.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/desktop/settings.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/event-create.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/mobile/duel-show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/mobile/event-bout.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/mobile/event-bracket.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/mobile/event-manage.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/mobile/event-show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/mobile/events.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/mobile/market-show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/mobile/market.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/mobile/schedule-show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/orders.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/partials/event-people.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/partials/post-viewers-modal.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/personal/payments.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/platform/mobile/explore.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/platform/mobile/show.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/platform/partials/join-club-modal-mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `resources/views/security/mobile.blade.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `routes/api.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `routes/console.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| tracked-modified | `routes/web.php` | Web/UI/Blade | no — in patch | Server-rendered UI, routes or lang |
| untracked | `app/Console/Commands/LiveReap.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Console/Commands/MediaDrain.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Console/Commands/MediaMigrate.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Console/Commands/MediaVerify.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Http/Controllers/Admin/MediaVaultController.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Http/Controllers/BoutVideoController.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Jobs/IngestClipMedia.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Jobs/IngestLiveRecording.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Jobs/MigrateMediaToVault.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Jobs/TranscodeMedia.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Mcp/Tools/GetBoutVideoTool.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Models/BoutCoachNote.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Models/BoutComment.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Models/BoutCommentLike.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Models/LiveStream.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Models/MediaFile.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Models/MediaVault.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Observers/UserObserver.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Support/Avatar.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Support/BoutHistory.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Support/BoutStage.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `app/Support/StoragePath.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `public/lab/boutcam-9b5db19c645c.apk` | Web/UI/Blade | **EXCLUDED** — Binary/media/generated (apk) | Server-rendered UI, routes or lang |
| untracked | `public/vendor/hls/hls.min.js` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/images/avatars/female-source.jpg` | Web/UI/Blade | **EXCLUDED** — Binary/media/generated (jpg) | Server-rendered UI, routes or lang |
| untracked | `resources/images/avatars/male-source.jpg` | Web/UI/Blade | **EXCLUDED** — Binary/media/generated (jpg) | Server-rendered UI, routes or lang |
| untracked | `resources/views/admin/storage/index.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/admin/storage/mobile.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/components-templates/member/mobile/partials/tournament-detail-sheet.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/components/bout-highlights.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/components/bout-vs-card.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/components/event-live.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/components/media-lightbox.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/components/video-player.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/events/live/broadcast.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/events/live/watch.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/personal/desktop/bout-video.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/personal/desktop/event-gallery.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/personal/desktop/videos.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/personal/mobile/bout-video.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/personal/mobile/event-gallery.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |
| untracked | `resources/views/personal/mobile/videos.blade.php` | Web/UI/Blade | no — archived | Server-rendered UI, routes or lang |

## Tests (2)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| tracked-modified | `tests/Feature/McpServerTest.php` | Tests | no — in patch | Test only — no runtime effect |
| untracked | `tests/Feature/Events/ScoringAuthorizationSafetyTest.php` | Tests | no — archived | Test only — no runtime effect |

## Documentation (19)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| tracked-modified | `CLAUDE.md` | Documentation | no — in patch | Docs only — no runtime effect |
| tracked-modified | `Documentation/MCP.md` | Documentation | no — in patch | Docs only — no runtime effect |
| untracked | `Documentation/MATSIDE-CAMERA.md` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `Documentation/OPEN-MAT.md` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `Documentation/play-patch/MatchController.php` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `Documentation/play-patch/MatchMediaImporter.php` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `Documentation/play-patch/README.md` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `Documentation/play-patch/TimelineController.php` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `Documentation/play-patch/UploadController.php` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `Documentation/play-patch/routes-api.php` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `docs/PHASE_0_SCORING_SAFETY_REPORT.md` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `docs/PHASE_1_TEST_ENVIRONMENT_AND_AUTHORIZATION_REPORT.md` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `docs/SPORTS_ARCHITECTURE_AUDIT.md` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `docs/SPORTS_ARCHITECTURE_EVIDENCE_BUNDLE.md` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `drafts/Camera app with live broadcast.zip` | Documentation | **EXCLUDED** — Binary/media/generated (zip) | Docs only — no runtime effect |
| untracked | `drafts/HTML Templates/Karate Shotokan Scoreboard Controller.zip` | Documentation | **EXCLUDED** — Binary/media/generated (zip) | Docs only — no runtime effect |
| untracked | `drafts/WhatsApp Image 2026-08-26 at 2.32.15 AM.jpeg` | Documentation | **EXCLUDED** — Binary/media/generated (jpeg) | Docs only — no runtime effect |
| untracked | `drafts/match-review-mobile.html` | Documentation | no — archived | Docs only — no runtime effect |
| untracked | `drafts/match-watch-standalone.html` | Documentation | no — archived | Docs only — no runtime effect |

## Configuration (5)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| tracked-modified | `bootstrap/app.php` | Configuration | no — in patch | Config — review before deploy |
| tracked-modified | `config/event_types.php` | Configuration | no — in patch | Config — review before deploy |
| untracked | `config/events.php` | Configuration | no — archived | Config — review before deploy |
| untracked | `config/live.php` | Configuration | no — archived | Config — review before deploy |
| untracked | `config/media.php` | Configuration | no — archived | Config — review before deploy |

## Removed legacy code (12)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| tracked-deleted | `Documentation/VIDEO-INTEGRATION.md` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |
| tracked-deleted | `app/Console/Commands/PlayHealth.php` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |
| tracked-deleted | `app/Console/Commands/PlayPullTimeline.php` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |
| tracked-deleted | `app/Console/Commands/PlayToken.php` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |
| tracked-deleted | `app/Http/Controllers/Api/PlayIntegrationController.php` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |
| tracked-deleted | `app/Jobs/PushBoutToPlay.php` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |
| tracked-deleted | `app/Models/PlayTimelinePoint.php` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |
| tracked-deleted | `app/Models/PlayTimelineRound.php` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |
| tracked-deleted | `app/Play/BoutPayload.php` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |
| tracked-deleted | `app/Play/MirrorBoutTimeline.php` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |
| tracked-deleted | `app/Play/PlayClient.php` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |
| tracked-deleted | `config/play.php` | Removed legacy code | no — in patch | Play integration removal (disconnected 2026-08-27) |

## Unknown / needs owner review (8)

| Status | Path | Category | Sensitive/Excluded? | Notes |
|---|---|---|---|---|
| untracked | `p-clubs.html` | Unknown / needs owner review | no — archived | Unclassified — sits at repo root |
| untracked | `p-honours.html` | Unknown / needs owner review | no — archived | Unclassified — sits at repo root |
| untracked | `p-record.html` | Unknown / needs owner review | no — archived | Unclassified — sits at repo root |
| untracked | `p-sports.html` | Unknown / needs owner review | no — archived | Unclassified — sits at repo root |
| untracked | `shot-clubs.png` | Unknown / needs owner review | **EXCLUDED** — Binary/media/generated (png) | Unclassified — sits at repo root |
| untracked | `shot-honours.png` | Unknown / needs owner review | **EXCLUDED** — Binary/media/generated (png) | Unclassified — sits at repo root |
| untracked | `shot-record.png` | Unknown / needs owner review | **EXCLUDED** — Binary/media/generated (png) | Unclassified — sits at repo root |
| untracked | `shot-sports.png` | Unknown / needs owner review | **EXCLUDED** — Binary/media/generated (png) | Unclassified — sits at repo root |

---

# Summaries

## Count by Git status

| Status | Count |
|---|---|
| untracked | 224 |
| tracked-modified | 175 |
| tracked-deleted | 12 |
| **Total** | **411** |

## Count by category

| Category | Count |
|---|---|
| Live scoring — Karate | 15 |
| Live scoring — Taekwondo | 11 |
| Tournament/event domain | 40 |
| Screens/TV/court display | 15 |
| Database/migrations | 16 |
| Mobile/TV native app | 116 |
| Web/UI/Blade | 152 |
| Tests | 2 |
| Documentation | 19 |
| Configuration | 5 |
| Removed legacy code | 12 |
| Unknown / needs owner review | 8 |
| **Total** | **411** |

## Key counts

| Measure | Count |
|---|---|
| Scoring-related paths (Karate + Taekwondo packages) | 26 |
| Untracked paths | 224 |
| Deleted paths | 12 |
| Tracked-modified paths | 175 |
| Excluded from the archive | 88 |
| Untracked files preserved in the archive | 136 |

## Files likely UNSAFE to deploy without review

Ranked by blast radius on an event day. These decide official results, drive the
hall's screens, or change the database shape.

| Path | Why it is high-consequence |
|---|---|
| `app/Events/Sports/Karate/Tournament/Scoreboard/Scoring.php` | Applies every Karate scoring command; decides bouts |
| `app/Events/Sports/Karate/Tournament/Scoreboard/MatState.php` | The live score and the new configurable rule set |
| `app/Events/Sports/Karate/Tournament/Scoreboard/ScoreboardController.php` | The scoring endpoint — the authorization surface |
| `app/Events/Sports/Taekwondo/Tournament/Scoreboard/Scoring.php` | Applies every Taekwondo scoring command |
| `app/Events/Sports/Taekwondo/Tournament/Scoreboard/ScoreboardController.php` | The Taekwondo scoring endpoint |
| `app/Events/Sports/Karate/Tournament/resources/views/scoreboard/control.blade.php` | +1272/-602 — the operator console, largest single change in the tree |
| `app/Events/Sports/Karate/Tournament/Tournament.php`, `app/Events/Sports/{Karate,Taekwondo}/*.php` | Event-type packages: draw, advancement, results |
| `routes/web.php` (+262) | New endpoints; a routing mistake exposes or hides a surface |
| `bootstrap/app.php` | Global middleware and exception handling |
| all 16 files in `database/migrations/` (untracked) | Schema changes — irreversible without a verified backup |
| `app/Events/Support/{HallScreenRouter,ScreenPairingController,ScreenMedia*}.php` | Screen pairing/identity — a fault strands a wall screen mid-event |
| `app/Events/Support/Cameras/*` (untracked) | New camera fleet control surface |
| `resources/views/components/court-screens.blade.php` (+809/-26) | Hall screens panel in the event console |

## Files carrying the 549-line live scoring change

Confirmed by `git diff --numstat HEAD` over both `Scoreboard/` directories.
**+549 / −26** exactly, matching the Phase 1 finding.

| Path | Added | Removed |
|---|---|---|
| `app/Events/Sports/Karate/Tournament/Scoreboard/Scoring.php` | +219 | −13 |
| `app/Events/Sports/Karate/Tournament/Scoreboard/ScoreboardController.php` | +170 | −10 |
| `app/Events/Sports/Karate/Tournament/Scoreboard/MatState.php` | +114 | −1 |
| `app/Events/Sports/Taekwondo/Tournament/Scoreboard/ScoreboardController.php` | +27 | −2 |
| `app/Events/Sports/Taekwondo/Tournament/Scoreboard/Scoring.php` | +19 | 0 |
| **Total** | **+549** | **−26** |

Widened to the whole of `app/Events/Sports/`, the churn is **24 files, +2,715 / −745** —
the 549 is the scoring engine proper; the rest is consoles, screens and language files.

The Karate half of this change set is what the Phase 0 characterization tests assert and
what the committed `HEAD` does **not** contain (it introduces `senshuRule`, `autoSenshu`,
`winByPenalties`, `gapOn`, `gap`, `warning`, `MatState::SETTINGS` and the `rules` command).

## Untracked files that are safe candidates to preserve/commit later

All 136 are in `UNTRACKED_SAFE_FILES.tar.gz`. By area:

| Area | Count | Examples |
|---|---|---|
| PHP application source | 103 | `app/Media/*`, `app/Events/OpenMat/*`, `app/Events/Support/{Cameras,Live}/*`, `app/Models/EventCamera*.php` |
| Database migrations | 16 (within the PHP count) | `2026_08_25_170000_create_media_vaults_table.php`, `2026_08_25_180000_create_live_streams_table.php` |
| Config | 3 | `config/events.php`, `config/live.php`, `config/media.php` |
| Documentation | 9 | `docs/PHASE_0…md`, `docs/PHASE_1…md`, `Documentation/OPEN-MAT.md`, `Documentation/MATSIDE-CAMERA.md` |
| Tests | 3 | `tests/Unit/Events/Sports/{Karate,Taekwondo}/*SafetyTest.php`, `tests/Feature/Events/ScoringAuthorizationSafetyTest.php` |
| Android manifests / XML | 14 | `TV/android_cam/AndroidManifest.xml` |
| HTML drafts | 6 | `p-clubs.html`, `drafts/HTML Templates/*` |
| Shell / JS / YAML | 4 | `TV/build.sh`-adjacent scripts, workflow YAML |

**Not in the archive but still real work:** 21 Flutter/Android source files
(`.dart`, `.kt`, `.kts`, `.properties`) were excluded solely because those extensions sit
outside the allowlist this phase was given — **not** because they are sensitive. They are
untracked, so the archive is otherwise their only backup. See
`SENSITIVE_FILES_EXCLUDED.md` → "Excluded by extension allowlist".
