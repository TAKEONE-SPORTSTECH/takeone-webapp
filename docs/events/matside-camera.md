# Matside Camera — replay at the desk, and getting the footage off the phone

**Status (rechecked against the code 31 Oct 2026):**

- **Part A — replay at the referee desk: specification, not built.** Recording
  is still one MP4 per bout (no segmented recording, no phone-side replay
  server, no replay tickets).
- **Part B — "upload to Play": superseded, never built as written.** The sibling
  video platform `video.takeone.bh` ("Play") was **disconnected on 27 Aug 2026**
  (CLAUDE.md → *Match video is ours*): its client, jobs, inbound API, config,
  token and SSH key are gone and must not be re-added. The phone now uploads
  the clip to **this** platform, which stores, transcodes, authorises and plays
  it itself — see §3. Part B is kept below only as the record of what was
  planned; do not build it.
- The camera fleet it builds on IS built (23 Aug 2026) — §1 describes it as it
  is today.

The document this one extended, `VIDEO-INTEGRATION.md`, no longer exists (it
described the Play integration). Its section references below (§3, §5.3, §5.4,
§6.x, §7) are kept for the record; their target is gone.

---

## 0. What is being asked for

Two things, both starting from footage that already exists on a phone beside the mat:

**A. Replay at the referee desk.** A coach queries a point. The desk wants the last ~20 seconds
back, on the angle that saw it, while the bout is still live.

**B. Upload to `video.takeone.bh`.** When the bout is over, one press sends that clip to TAKEONE
Play with everything Play needs to know about it — event, bout, competitors, clubs, officials,
result — so it lands as a proper match video and not an orphan file. *(Superseded: the clip now
goes to this platform, attached to its bout — §3.)*

### The governing constraint

> No separate network. No cabling. No new hardware. Everything evolves from what is already built.

That rules out the recorder box and the wired camera rig ("Tier C" in the former `VIDEO-INTEGRATION.md` §7), and
it decides most of what follows. The devices in the hall are: phones running the camera app, a
tablet running the scoring console, televisions running the hall screens, and the venue's own wifi.
That is the whole inventory.

---

## 1. What exists today (built 23 Aug 2026; paths as of 31 Oct 2026)

| Piece | Where |
|---|---|
| `event_cameras` — the fleet: token, mat, angle 1–4, telemetry, liveness | `app/Events/Models/EventCamera.php` |
| `event_camera_clips` — the index of what each phone holds, and where each clip is on its way to the server | `app/Events/Models/EventCameraClip.php` |
| `CameraFleet::observe()` — hooked into all three sports' `Scoring::apply()` (`app/Scoreboard/Sports/{Karate,Taekwondo,BrazilianJiuJitsu}/Mat/Scoring.php`), so hajime starts every camera on the mat and the decision stops them | `app/Events/Support/Cameras/` |
| `CameraChannel` — one derived MQTT topic per camera, subscribe-only JWT | same |
| Device API — enrol, config, telemetry, live-feed credential, file a clip, known/plan clips, delete a clip, **chunked resumable upload** (`camera.clip.upload`), share files | `app/Events/routes.php` (`/camera/…`) → `App\Events\Support\Cameras\CameraController` |
| A camera in a browser (no app) | `/camera` → `BrowserCameraController` |
| Console panel — cameras beside the screens, per mat, with storage/battery/REC and unpair | `<x-court-screens>` (`resources/views/components/court-screens.blade.php`), fed by `CameraConsoleController` (`me.events.cameras`) |
| Scoring-table panel — the cameras on ONE mat, as the table drives them | `App\Events\Support\Cameras\MatCameras` / `MatCameraController` |
| The app — Flutter, `flutter/TV/` variant `cam` (manifest `flutter/TV/android_cam/`), records 1080p 30/60, publishes each clip to the phone's gallery (`Movies/TAKEONE`), keeps its own ledger, plays clips back, deletes them, uploads (auto-upload is a setting, **off by default**) | `flutter/TV/lib/src/camera/` (`recorder`, `uploader`, `clips`, `station`, `drawer`, `link`, `api`, `player`) |
| Server side of the footage — ingest into our own vaults, HLS transcode, per-segment authorised streaming, the bout watch page with a highlights bar derived from the officiating log, share-as-file | `App\Jobs\IngestClipMedia` → `App\Media\MediaVaults` → `App\Jobs\TranscodeMedia` → `App\Media\Http\MediaStreamController`; `me.events.bout.video` (`BoutVideoController`), `App\Media\BoutTimeline`, `App\Media\BoutShare` |

**[revises] `VIDEO-INTEGRATION.md` §5.3** says recorders should reuse `CourtDisplayDevice` with a
device *kind*. They do not: cameras got their own sport-neutral fleet (`event_cameras`), because a
screen's row is per-sport and per-package while a lens is neither. The pairing *flow* is shared —
same 6-character code, same claim page, same unpair-never-revoke rule.

**[revises] `VIDEO-INTEGRATION.md` §7** says "phones as cameras are a demo, not a product". That
judgement stands on its merits (§7 below) and is overruled by the constraint above: phones are what
the halls have. This document treats the limits as design inputs rather than pretending they are
gone.

---

## 2. Part A — Replay at the referee desk

### 2.1 Why today's recording cannot be replayed

`MediaRecorder` writes an MP4 whose index (`moov`) is written on stop. Until the bout ends the file
is not seekable, not playable, and not copyable — by anything, including the app that is writing it.
There is no clever read that gets around this. The recording model has to change.

### 2.2 Segmented recording

The camera records the bout as a chain of **~8-second segments** using
`MediaRecorder.setNextOutputFile()` (API 26+), which hands the encoder the next file *before* the
current one closes — the seam is frame-accurate and nothing is dropped.

Consequences:

- Every segment except the one being written is a finished, playable MP4.
- The worst-case age of the newest replayable frame is one segment: **≤ 8 s**.
- A **rolling window of the last 15 segments (~2 min)** is kept addressable for replay. Older
  segments stay on disk as part of the bout, they are simply not offered for replay.
- **At stop, the segments are stitched** into the single bout file the gallery and the upload
  expect. Same encoder settings throughout, so this is a `MediaMuxer` remux — no re-encode, a few
  seconds of CPU, no quality loss. The stitched file is what gets published to `Movies/TAKEONE`.
- If stitching fails, the segments are kept and the clip is marked `segmented` rather than lost.

Segment length is a trade: shorter means fresher replay and more files; 8 s keeps the worst case
inside "a coach is still talking" and the file count for an 8-hour day around 3,600 per phone, which
is fine on any modern filesystem.

### 2.3 The phone is the replay server

No relay, no upload, no cloud round trip: the tablet fetches the segments **directly from the phone
over the venue wifi**.

The camera app runs a `dart:io` `HttpServer` bound to the LAN, **started only when a replay is
authorised and stopped when it expires**. There is no port open on a phone in a public hall the rest
of the day.

```
GET  /replay/{ticket}/manifest        → the segments in the window, with their wall-clock spans
GET  /replay/{ticket}/segment/{n}     → one MP4 segment
```

### 2.4 The ticket, and why the phone does not decide

The phone must not be its own bouncer — it cannot know who an organiser is. So:

1. The console asks takeone: *"replay Mat 1, angle 2, the last 20 s."*
2. takeone checks `EventAccess::canManage` (or a new `canReview`), mints a **single-mat, 90-second,
   random ticket**, and pushes it to that phone over the existing `CameraChannel`
   (`{action:'replay', ticket, window}`).
3. takeone answers the console with the same ticket plus the phone's LAN address.
4. The console fetches directly from the phone. The phone accepts that ticket and nothing else,
   then shuts the server when it expires.

The video never leaves the venue, the ticket is worthless after 90 seconds and off that mat, and the
authorisation decision stays on the server where every other one in this product lives.

### 2.5 Finding the phone

The camera already beats telemetry every 20 s. It adds `lan_ip` and `lan_port`. The console gets
them from the fleet endpoint it already calls. Nothing new is discovered, scanned or broadcast — no
mDNS, no port scanning.

### 2.6 Jump to the moment, not scrub to it

This is the feature that makes it worth building. Every scoring command is already written to
`MatchEventLog` with the mat clock (`clockRemaining`, `clockDuration`) and a wall-clock stamp. So the
console offers the *events*, not a timeline:

> `−4 s · GAM-JEOM AKA` `−17 s · BODY AO (2)` `−31 s · HEAD AKA (3)`

Pressing one seeks all available angles to that wall-clock instant. No scrubbing, and the same
instant on every phone because they are all seeking a wall clock, not a file offset.

### 2.7 Sync between angles — honestly

Four phones start within a frame or two of each other (one MQTT publish), but their clocks drift and
their segment boundaries do not align. Achievable sync is **±0.5 s or so**, using each phone's
segment wall-clock spans. That is enough to decide *"was the foot up before the buzzer"* on one
angle and to look at the same exchange on another. It is **not** frame-locked multi-angle
adjudication; that needs one machine taking all angles (Tier C) and no amount of phone software
gets there.

State this to officials before an event. A tool that is trusted for more than it can do is worse
than no tool.

### 2.8 What the hall sees

Default: **nothing**. The wall board shows `UNDER REVIEW` and the footage stays at the desk — WT and
WKF both treat replay as an officials' instrument.

Optional, per event, organiser's choice: push the same replay to the mat's scoreboard as an overlay
(the screen is already a web page taking commands on its own MQTT channel; it gains a `<video>` and
a `replay` action). Off by default, and a setting an organiser has to turn on deliberately.

### 2.9 The failure that will actually happen

**AP client isolation.** Guest and venue wifi commonly blocks device-to-device traffic. If the tablet
cannot reach the phone, this design does not work in that hall.

- **Test before the event, in one minute:** on the console tablet, open
  `http://<phone-lan-ip>:8080/health` while both are on the event wifi. Answer = fine.
- **Fallback:** the phone POSTs the requested segments to takeone and the console plays them from
  there. Costs the upload (≈8–25 MB for 20 s) and adds seconds, but works on any network where the
  phones can reach the server — which they must anyway, or nothing about the fleet works.
- The console picks the route by trying direct first with a 1.5 s timeout, then relaying. It says
  which route it used, so a venue's behaviour is visible rather than mysterious.

Other failure modes, and what they do:

| Failure | Behaviour |
|---|---|
| Phone thermally throttled or busy encoding | Replay serves from disk; encoding has priority. Serving a 3 MB segment is negligible next to 1080p encode. |
| Phone screen off / app backgrounded | Cannot happen by design — the app holds `KEEP_SCREEN_ON` and shows over the keyguard. |
| Requested window predates the bout | The manifest returns what exists; the console shows less than 20 s rather than an error. |
| Camera unpaired mid-review | Ticket dies with the claim. |

### 2.10 Security posture

- The HTTP server binds only while a ticket is live, answers only that ticket, and serves only
  segments inside that ticket's window and mat.
- Tickets are random, single-mat, 90 s, minted server-side against a real authorisation check.
- No competitor identity travels on the replay path — segments are named by index, not by bout.
- Plain HTTP on the LAN, deliberately: a self-signed certificate on a phone would train people to
  click through certificate warnings, and the payload is footage a room full of strangers is already
  watching live.

---

## 3. Part B — Upload to `video.takeone.bh` — SUPERSEDED, do not build

> **What happens instead (built):** the phone streams the finished clip to
> `POST /camera/{token}/clip/{clip}/upload` in 8 MB chunks; the server's offset
> is authoritative, so a phone that lost wifi asks with `Upload-Offset: 0` and
> resumes rather than restarting (the resumability §3.2 asked of Play).
> `IngestClipMedia` files it in our own media store through `MediaVaults`
> (`events/{event-uuid}/matches/{match-id}/clips/{media-uuid}.mp4`, or
> `…/unassigned/clips/…` when no bout was loaded), attaches it to its bout,
> and `TranscodeMedia` builds the HLS ladder. It is watched at
> `me.events.bout.video`, where `MediaStreamController` re-checks
> authorisation on every segment and the two athletes who fought the bout
> reach their own footage whatever the event's scope. The highlights bar comes
> from `App\Media\BoutTimeline` reading `MatchEventLog` — the anchor problem
> of §3.3 is handled there, on the server. `event_recordings.play_*` columns
> are dormant remnants of the few bouts published to Play before the split.
>
> Still open from this part: the per-competitor **consent gate for minors**
> (§3.5) — this check found no consent logic in `app/Media` or the camera
> code, so who may watch a minor's bout rests on the event's own access rules.
> The bandwidth reasoning of §3.6 still applies to uploads to our own server.

The original plan follows, unchanged, for the record.

### 3.1 Who does what

From the former `VIDEO-INTEGRATION.md` §3: **takeone owns the competition truth, Play owns the
media.** So the metadata is *not* sent by the phone. The phone has bytes; the server has facts.

```
1. Organiser presses "Send to Play" on a clip (console), or the app's per-clip action.
2. takeone POSTs /api/v1/matches to Play  — the bout, fully described (§3.3).
   Play creates the video + sports_match rows and returns {video_id, upload_url, upload_token}.
3. takeone pushes {action:'upload', clip, upload_url, upload_token} to the phone over CameraChannel.
4. The phone PUTs the file straight to Play — one hop, not two.
5. Play transcodes (NVENC → HLS) and calls takeone's webhook: video.ready.
6. takeone writes play_video_id / play_url into event_recordings and the console shows the link.
```

The phone never learns anything about the bout it did not already know, and Play never has to trust
a phone for anything except bytes it was given a one-time ticket for.

### 3.2 Which endpoints

Exactly the ones `VIDEO-INTEGRATION.md` §6.5 already proposes (`POST /api/v1/matches`,
`PUT /matches/{ref}`, `POST /matches/{ref}/timeline`, `POST /matches/{ref}/publish`,
`GET /matches/{ref}`, plus the `video.ready` webhook). **This document adds one:**

| Method | Path | Purpose |
|---|---|---|
| `PUT` | `/api/v1/uploads/{upload_token}` | Resumable byte upload from a paired camera. Accepts `Content-Range`; returns the byte offset it holds so a phone that lost wifi mid-upload resumes instead of restarting. |

Resumable is not a nicety. A 1 GB upload over hall wifi will be interrupted, and a fleet that
restarts from zero each time never finishes.

### 3.3 What takeone sends about the bout

Per §6.3's field mapping. Everything below already exists in takeone — nothing new is collected:

- **Event** — title, host club, country, dates, venue, event uuid (deep link).
- **Bout** — `match_no`, round/phase, category and weight class, mat, scheduled and actual time.
- **Competitors** — names, the club each competes for (`ClubEventRegistration::competingClub()`),
  country codes (the club's country — see the standing rule), seeds, and the corner each fought.
- **Officials** — the mat's appointed officials from `event_officials`.
- **Result** — winner, scores, method, and the round it ended in.
- **The camera** — angle 1–4, and the mat, so four angles of one bout are identifiable as such.
- **The timeline** — the bout's `MatchEventLog` rows converted to Play's rounds/points model
  (§6.4's vocabulary mapping), anchored on the recording's start so the Highlights panel lines up
  with the video.

**The anchor is the part to get right** (§4 of the other document): the phone reports the exact wall
clock at the first frame; every point is `event_time − anchor` in seconds. Off by a second and every
marker sits after the thing it marks.

### 3.4 Idempotency

External ref = `{event_uuid}:{match_id}:{angle}`. Retrying a create returns the same video; retrying
a timeline replaces it wholesale; retrying an upload resumes it. A camera that reboots mid-upload
must never produce a second video of the same bout.

### 3.5 Consent, and who may publish

> **Current behaviour, decided 2026-08-23: an uploaded bout is PUBLIC.** The first cut
> uploaded as unlisted, which produced videos that played from their link and appeared
> in no gallery, with nothing on either side able to publish them afterwards. Until the
> gate below is built, the organiser's decision to upload is the whole of the decision —
> which means a bout involving a minor can be published today with no check. This is the
> highest-priority gap in this document.

`VIDEO-INTEGRATION.md` §5.4 already defines the gate: per-competitor consent captured at entry;
either side withholding public consent means the video uploads **restricted**, and minors default to
restricted. The upload action must read that gate, not the organiser's enthusiasm. A bout with a
minor and no guardian consent is not offered an upload button at all.

### 3.6 Bandwidth — the reason this is opt-in, not automatic

1080p30 is ≈ 120 MB per minute; 60 fps doubles it. A 4-minute bout is ~0.5 GB per angle. A day of 40
bouts on 4 angles is **~80 GB per mat**. No hall uplink carries that during competition, and
saturating the uplink would take the scoring console and the screens down with it — the exact
regression RULE #1 exists to prevent.

Therefore:

- **Upload is a choice, never a default.** Finals, medal bouts, disputed bouts.
- **Bulk "upload the day" exists but is throttled** and runs when the app is charging and idle
  (overnight at the hotel or back at the club).
- **A concurrency cap of one upload per mat**, and a global cap set by the organiser.
- The console shows a queue with progress, and what it is costing.

### 3.7 Deletion across three places

Already true for the local clip; the upload adds a fourth home. The rule: **deleting a clip on the
phone never deletes the video on Play.** Once uploaded, the Play video is the published record, and
`event_recordings` links to it (`status: linked`). The phone's copy is a working copy and can be
cleared to free space without touching anything else. Removing a video from Play is a Play-side
action with its own authorisation, per §3 ownership.

---

## 4. What changes, file by file

*(Planned, 23 Aug 2026. The Part A items are still the plan; every Play item —
`PlayClient`, the `/api/v1/` surface, the webhook, `play_*` columns on
`event_camera_clips` — is dropped. Paths updated: the app is
`flutter/TV/lib/src/camera/`; the native side is the `cam` variant's.)*

**Camera app** (`flutter/TV/lib/src/camera/`)

- `recorder.dart` — segmented recording, rolling window, stitch-on-stop, per-segment wall-clock spans.
- `replay_server.dart` *(new)* — the ticketed `HttpServer`, started and stopped by ticket.
- `link.dart` — two new commands: `replay`, `upload`.
- `api.dart` — `lan_ip`/`lan_port` on the telemetry beat; resumable `PUT` with `Content-Range`.
- `station.dart` / `drawer.dart` — an upload state per clip (queued / sending / on Play / failed).
- `MainActivity.kt` — `setNextOutputFile` plumbing and the `MediaMuxer` stitch.

**takeone**

- `CameraFleet` / `CameraChannel` — mint and publish replay tickets; publish upload orders.
- `CameraController` (`app/Events/Support/Cameras/`) — accept `lan_ip`/`lan_port`; a relay endpoint for the AP-isolation fallback.
- `app/Events/Support/Cameras/ReplayController.php` *(new)* — the console's "replay this moment"
  request, authorised, returning ticket + address + the `MatchEventLog` moments.
- `PlayClient` *(new)* — the §6.5 calls, plus the webhook receiver.
- `event_camera_clips` — additive columns: `play_video_id`, `play_status`, `uploaded_at`,
  `anchor_at`, `segments`.
- Console — a replay panel on the scoring console; upload state and queue in the camera panel.

**Hall screen** (optional, §2.8) — a `replay` action and a `<video>` overlay on the bout surface.

**TAKEONE Play** — the `/api/v1/` surface of §6.5 plus the resumable upload endpoint. Match-type
files only, per that repo's RULE #3.

---

## 5. Phasing

| Phase | What ships | Why this order |
|---|---|---|
| **P1** *(not built)* | Segmented recording + stitch-on-stop | Everything else depends on it, and it changes nothing an operator sees. Ship it alone and prove a day of recording still produces one clean file per bout. |
| **P2** *(not built)* | Replay: ticket, phone server, console panel, jump-to-moment, relay fallback | The feature the desk asked for. Test AP isolation at the venue first (§2.9). |
| **P3** | ~~Upload to Play: create → ticket → resumable upload → webhook → link in the console~~ | Superseded — resumable upload to this platform is built (§3 note). |
| **P4** | Optional hall-screen replay overlay; bulk overnight upload | Convenience, once the rest is trusted. |

---

## 6. Decisions needed before P1

1. **Who may call a replay?** Any event manager, or only an appointed official (`canScore`)? A
   review is an officiating act, so my recommendation is the narrower one.
2. **Replay on the hall screen** — build the overlay in P2, or leave it desk-only until asked for?
3. **Upload trigger** — organiser-only from the console, or also from the phone? (Phone-side is
   convenient and is also how the wrong bout gets published.) *(Answered in practice: the phone
   uploads, by hand or by its auto-upload switch, default off.)*
4. **Retention on the phone** — clips currently stay until deleted by hand. Auto-delete after upload
   confirms, after N days, or never?

## 7. The limits, stated once

Phones are the fleet because phones are what the halls have. What that costs:

- **Thermals.** A phone encoding 1080p60 for hours will throttle; it may drop to 30 fps or stop.
  Mitigation: 30 fps default, keep the screen brightness low, and the console already shows a camera
  that stopped beating.
- **Battery.** 8 hours of recording needs mains power at every tripod. This is a venue requirement,
  not a software problem — say it in the event checklist.
- **Sync.** ±0.5 s across angles (§2.7), not frame-locked.
- **Storage.** ~7 GB per hour at 1080p30. The console's amber warning exists for this; the
  free-up-space flow is the answer during an event.

None of these blocks the two features asked for. All of them will surprise somebody at an event if
this document is the only place they are written down.
