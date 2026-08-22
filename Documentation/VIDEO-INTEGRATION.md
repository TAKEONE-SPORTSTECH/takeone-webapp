# Match Video Integration — takeone ⇄ TAKEONE Play

**Status:** specification / not implemented
**Date:** 2026-08-18 · identity/tenancy (§3.1–3.3), full field mapping (§6.3), private coaching notes (§5.5, §8.4) and deep links (§6.6) added 2026-08-19
**Systems:** `takeone` (this repo, tournament platform) · `TAKEONE Play` (`video.takeone.bh`, `/var/www/videoplatform` on `192.168.0.31`)

---

## 0. Governing constraint — nothing that works may break

Both systems are live and represent years of work. **This integration is entirely additive.** No existing feature of either platform may regress at any point during it.

Concretely, for this project:

- Play's **manual match-annotation flow keeps working untouched**. Matches created by hand carry a null external reference and behave exactly as they do today.
- The mat scoring console's behaviour **does not change**. `Scoring::apply()` gains an append-only side effect; its inputs, outputs and `MatState` semantics stay identical.
- New tables only. No altering or dropping existing columns on either side.
- The whole integration sits behind a **feature flag, default OFF**. An event running with the flag off must be indistinguishable from today.
- **The Phase 0 security fix is itself a break risk** (§8.1): Play's existing annotation UI calls those unauthenticated routes. Do not simply wrap them in auth — add the authenticated `/api/v1/` surface, verify the existing UI's callers, gate the old routes only after, keeping them working for the authenticated owner.
- **Only the `match` video type is touched on Play** (RULE #3). The `music` type — 54 of that platform's 58 videos, i.e. its live content — and the `generic` type are off limits. Where match work needs shared code (`VideoController`, `Models/Video`, `videos/show.blade.php`, the player), add a type-guarded branch rather than changing behaviour any other type observes, and verify a music and a generic video still work afterwards.
- Every phase is independently revertible, and verified against a real bout before the next begins.
- **A verified database backup is taken before every migration in this project, on both sides** (RULE #2). Play has no backup command yet — back it up by hand with `VACUUM INTO` + `integrity_check` until one exists.

See RULE #1 and RULE #2 in `CLAUDE.md`.

---

## 1. What this is

Today a combat match is scored on a mat console in takeone, and — separately, later, by hand — someone uploads a video to TAKEONE Play and types the points in again so the Highlights panel works.

This spec removes the second step. **The mat produces the timeline.** A match is recorded continuously, the scoring console's own commands become the video's markers, and when the match ends the clip is cut, transcoded and published with its Highlights already built.

It also covers the two things that fall out of the same design for free: watching a match live, and reviewing a disputed point while the recording is still running.

### Scope, in three tiers

| Tier | Delivers | Depends on |
|------|----------|-----------|
| **A — Post-match** | Continuous recording, auto-cut clip per match, timeline auto-built, published to Play | nothing new |
| **B — Live** | Public live stream per mat, labelled with the current bout | A |
| **C — Jury review** | Multi-angle synced review, formal challenge record with quota + outcome | A, schema change |

Tier A is the substrate; B and C are additive. **Build A first** — on its own it gives every competitor a clip of their own match, which is the thing members actually want, and it proves the clock model that B and C both depend on.

---

## 2. What already exists (verified, 2026-08-18)

Neither side is starting from zero. This matters — most of the "build" is a contract, not new product.

### On TAKEONE Play

Laravel 10, PHP 8.1+, FFmpeg/FFProbe with NVIDIA NVENC, HLS adaptive streaming (480p/720p/1080p variants), MySQL in prod, Sanctum.

Already models a sports match:

| Table | Columns that matter here |
|-------|--------------------------|
| `sports_matches` | `video_id`, `user_id`, `status` (draft\|published), `title`, `event_name`, `match_date/time`, `participant1_name`, `participant2_name`, `referee_name`, `sport`, `match_type`, `venue_name`, plus JSON groups: `competition`, `participants`, `officials`, `venue`, `result`, `segments`, `statistics`, `reviews` |
| `match_rounds` | `video_id`, `round_number`, `name`, `start_time_seconds`, unique(`video_id`,`round_number`) |
| `match_points` | `video_id`, `match_round_id`, `timestamp_seconds`, `action`, `points`, `competitor` (`blue`\|`red`), `notes`, `score_blue`, `score_red` |
| `coach_reviews` | `video_id`, `user_id`, `start_time_seconds`, `end_time_seconds`, `note`, `coach_name`, `emoji`, `position` |

CRUD lives in `MatchEventController` (`storeRound`/`storePoint`/`storeReview` + update/destroy), and `GET /videos/{video}/match-data` returns the whole timeline as JSON. The video lifecycle is `pending → processing → ready` via `CompressVideoJob` (NVENC h264, CRF 23) then `GenerateHlsJob`.

**The Highlights panel is already built and already reads this data.** We are changing who writes it, not what it looks like.

### On takeone

- `EventMatch` — the bout: `event_id`, `category_id`, `round`, `phase`, `match_no`, `a_name`/`a_competitor_id`/`a_country`/`a_score`, same for `b_`, `winner`, `court`, `day`, `scheduled_time`, `status`.
- `app/Events/Sports/{Karate,Taekwondo}/Tournament/Scoreboard/Scoring.php` — **`apply(ClubEvent $event, string $court, string $command, array $payload): MatState`** is the single entry point every scoring command passes through: `load`, `start`, `pause`, `point`, `undo_point`, `penalty`, `senshu` (Karate), `time`, `reset`, `finish`, `clear`, `commit`, `duration`, `corner`, `meta`.
- `MatState` — the live mat blob (`aka`/`ao` corners, scores, penalties, senshu, `running`, `finished`, clock as remaining-as-of-a-moment).
- `CourtDisplayDevice` + `PairCourtDisplay` + `ScreenChannel` — the Raspberry Pi wall-screen fleet: 6-character pairing code, claim, mat assignment, liveness, unpair, MQTT nudges.
- MQTT via `Realtime()->publishToUser()` / `publishMany()`.

### The one real gap

`Scoring::apply()` mutates `MatState` and saves it. **There is no persisted, timestamped log of what happened** — only the current state. `MatState::$lastEvent` holds the most recent point with a millisecond stamp, but it is transient.

A timeline needs history. This is the single most important new thing to build on the takeone side (§5).

---

## 3. Ownership — who owns what

Both systems now have a "match" record, which is a trap if left unresolved.

**Proposed split:**

- **takeone owns the competition truth** — who fought, in which category and round, their clubs and countries, the bracket position, the official result. It is the tournament system; this is its job.
- **Play owns the media** — the recording, transcoding, HLS variants, playback, and the timeline *as rendered on the video*.
- They join on **`EventMatch.id` + event uuid**, carried on the Play side as an external reference.

`sports_matches` therefore becomes largely **derived** for integrated matches: takeone pushes title, competitors, event name, date, venue, officials and result; a human never types them. Manually-created matches on Play (someone uploading a match from another federation) keep working exactly as today — the external reference is simply null.

**Open question:** should Play's `sports_matches.status = published` be driven by takeone (publish when the bout is committed) or stay a Play-side editorial action? Recommend takeone-driven, with Play able to unpublish.

---

### 3.1 Identity and tenancy — Play is the media backend, takeone is the front door

§3 settles which system owns a *match*. It does not settle who owns a *viewer*, and that is the larger problem: takeone is multi-tenant and Play is not.

**Verified on Play, 2026-08-19:**

- `users` is flat — 19 rows, and no `tenant_id`, `club_id` or equivalent column exists anywhere in its schema. Play has no notion of a club.
- A video's entire access rule is `user_id` ownership plus `visibility` ∈ `public | unlisted | private`, with a `share_token` for unlisted links.
- Every match write path is scoped to the session user: `store()` requires `video_id` to `exists('videos','id')->where('user_id', Auth::id())`, and `update()` runs `abort_unless($sportsMatch->user_id === Auth::id(), 403)`.

None of takeone's access model survives that trip. Tenant scoping, club roles and permissions, family relationships, `is_discoverable`, per-competitor recording consent, and the minors rule cannot be expressed in a flat user table. Mirroring takeone's users into Play would not fix it — it would mean **building multi-tenancy inside Play**, which is precisely the shared-code surgery RULE #3 forbids and the kind of change to a live platform RULE #1 exists to prevent.

**Decision: do not mirror identity. Play stores and serves media; takeone decides who may watch.**

This is not a new pattern for Play — it is the one that platform already uses for live streaming. `LiveAuthController` has MediaMTX POST back to Laravel before accepting any publisher or viewer, and says so in its own docblock: *"MediaMTX holds no policy of its own … That keeps every 'who may do what' decision inside the application."* Recorded video extends the same principle one hop further out, with takeone in the role Laravel plays there.

Concretely:

- **Ownership.** Videos created from takeone are owned on Play by a **service account**, never by a mirrored human user. One identity, scoped abilities, rotatable (§8.2).
- **Visibility.** Integrated videos are created **`private`**, and never `public` by default. Public is an explicit, deliberate, per-video action — see §3.2.
  - ⚠️ **`unlisted` is not a privacy setting.** Verified in `Video::canView()`: it returns true for `public` *and* `unlisted` with no token or ownership test, so unlisted means anyone holding the URL, forever. It is a sharing convenience, never a substitute for the signed playback grant.
- **Brokered playback.** A viewer never receives a Play URL directly. takeone authorises against its own model — tenant, club role, family relationship, competitor consent, minor status — and only then mints a **short-lived signed playback grant**. Play validates the grant; it does not decide. An unguessable URL is not authorisation (§8.3).
- **Annotations never cross the boundary.** Anything carrying an access rule — coaching notes above all (§5.5) — stays in takeone. Play receives the official, public-facing record: the score timeline, the competitors, the result. If a value needs an answer to "who may see this?", it does not go to Play.
- **Play's own product is untouched.** Its native audience keeps seeing exactly what it sees today: the music library, generic videos, and manually-created matches. takeone content does not appear in Play's own listings, search or recommendations.

The single principle, worth stating once and holding to: **one video row on Play, one link row on takeone, and takeone always answers "may you watch this."**

### 3.2 The two sources of video, under one model

Both cases the platform actually has run through the same pipeline; only the owner of the link row differs.

**Tournament bouts.** The bout is the subject. `EventMatch.id` + the event uuid are carried on Play as the external reference (§3), the media link lives in `event_recordings` (§5.2), and once Play reports ready the same video surfaces on the bracket, on both competitors' profiles, and on the event page. That is the "go back and watch it" path. Consent decides whether it publishes restricted or shareable; **minors default to restricted**, and a bout where either side withheld public consent never becomes public regardless of the other side.

**Member and club uploads.** A member records their own training or a club uploads its own footage. Same upload path, same service-account ownership on Play, but the link row points at a user or a tenant instead of an `EventMatch`, and takeone's ordinary tenant/family authorisation answers the playback question. Club-private footage stays brokered indefinitely — it is never reachable on `video.takeone.bh` and never appears in Play's listings.

**Going public is one door, not a default.** Where a club deliberately wants a highlight to be world-visible, that is a single explicit action that flips the Play video to `public`. Starting closed and opening later is reversible; starting open is not.

### 3.3 The link tables

`event_recordings` (§5.2) already carries `play_video_id` / `play_url` for bouts. Note that **`event_matches` has no video column today** — nothing currently joins a bout to a video, so this link is genuinely new, and it belongs on the recording row rather than on the bout (one bout can have several angles in Tier C).

Member and club uploads need a sibling table, `video_links`, with the same shape and no tournament coupling:

```
id
owner_type          user | tenant
owner_id            → users | tenants
uploaded_by         → users
title / description
visibility          private | club | platform | public
consent_public      bool
play_video_id       int|null
play_url            string|null
status              uploading|processing|ready|failed
created_at/updated_at
```

`visibility` here is **takeone's** vocabulary, not Play's — it is the input to takeone's authorisation decision, and it is deliberately richer than Play's three values because Play never evaluates it.

---

### 3.4 Manual sport videos keep working — they are not the legacy path

Play must remain able to create a sport video **on its own**, with a person filling the modal: someone uploads a bout from another federation, an old recording, a club's own footage, a match takeone never knew about. This is not a transitional flow to be retired once the integration lands. It is the **only** flow for anything that did not happen at a takeone event, which is most of the sport in the world.

Nothing in this project changes it. Verified 2026-08-19: `SportsMatchController` and `videos/types/match.blade.php` are **untouched** by Phase 0, and `POST/PUT /sports-matches` still serve the modal exactly as before.

#### The one distinction that matters

The external reference (§6.3) is what separates the two, and it is the **only** thing that may:

| | Manual match (ref **null**) | Integrated match (ref set) |
|---|---|---|
| Who creates it | a person, in the modal | takeone, via `/api/v1` |
| Owner | the uploading user | the service account |
| Default visibility | whatever they choose, as today | `private` (§3.1) |
| Play's editor | **fully editable, as today** | read-only — takeone is authoritative (§10.2) |
| Coach reviews | Play's own `coach_reviews`, as today | never used — notes live in takeone (§5.5) |
| Deep links (§6.6) | none — there is nothing to link to | full |

**Every restriction this project introduces is conditional on the reference being set.** A manual match must behave in every respect as it did before any of this existed, and any check that turns something off has to read `if (ref)` rather than assume the integrated case. Getting that backwards would disable the editor on 4 existing matches and every one a person adds afterwards — the exact regression RULE #1 forbids.

#### Do not converge them

There will be a temptation to "unify" the two paths — have the modal write through the same API, or make takeone's payload flow through the modal's controller. Resist it. They have different owners, different authority over the data, and different visibility defaults, and the manual path is the one that already works. Two paths that share the *table* and nothing else is the correct shape.

---

## 4. The time model — the part that must be right

Everything else is plumbing. This is where the design succeeds or quietly corrupts itself.

**Three clocks are in play and they are not the same:**

1. **Wall clock** — when the referee pressed the button (`2026-09-14 11:04:22.310`).
2. **Media time** — offset in seconds into the recording file (`47`). What `match_points.timestamp_seconds` and every seek/scrub needs.
3. **Bout clock** — time remaining on the mat (`1:47`). What the scoreboard shows. Pauses. Resets. **Useless for seeking.**

### Rules

- **The recorder's clock is the authority.** Every scoring command is stamped with wall-clock time *by the recorder on arrival*, not by the console — a laptop with a drifted clock must not be able to shift the timeline.
- **Store both.** Persist wall clock on the takeone event log, and compute media time at publish. If the two ever disagree, media time is derivable and wall clock is the audit record; keeping only media time means a re-encode or a dropped segment silently moves every marker.
- **Media time = `event_wall_clock − recording_anchor`**, where the anchor is the wall-clock time of media offset 0 for that recording. The recorder emits the anchor when the segment sequence starts.
- **Never derive marker positions from the bout clock.** It pauses, it resets between rounds, it gets edited via the `duration` command. It has no monotonic relationship to the video.
- All hosts (mat console, recorder, Play) run **NTP**. Non-negotiable; a 4-second drift puts every highlight on the wrong technique.

### Do not start and stop the encoder per match

An encoder cold-start is 2–5 seconds (camera handshake, first keyframe, playlist negotiation). A Taekwondo exchange is 300 ms. Starting on `load`/`start` loses the first attack of every bout, and loses the pre-match moments a review sometimes needs.

**Record continuously per mat, all session.** "The match started" is a marker on that continuous timeline, not an encoder state change. Match end triggers the *cut*, not the stop.

This is also what makes live review possible at no extra cost (§7).

---

## 5. New on the takeone side

### 5.1 `event_match_events` — the persisted timeline

The one genuinely new table. Every command that passes `Scoring::apply()` is appended here.

```
id
event_id            → club_events
match_id            → event_matches (nullable: a command on an empty mat)
court               string
command             string      load|start|pause|point|undo_point|penalty|
                                senshu|time|reset|finish|clear|commit|...
payload             json        side, n, dir, minutes, …  (as received)
side                string|null aka|ao  (denormalised for querying)
points              int|null
score_aka           int         running score AFTER this command
score_ao            int
occurred_at         datetime(3) wall clock, millisecond precision
sequence            int         monotonic per (event, court) — ordering that
                                survives identical timestamps
created_at/updated_at
```

Written from **inside `apply()`**, after the `match(...)` dispatch and before `save()`, so it captures post-command state and no caller can bypass it. Both sports use the same table; the vocabulary differs (senshu is Karate-only) and the mapping layer (§6.4) handles that.

Value beyond video: this is an **audit trail of officiating**, which a federation will eventually ask for regardless of whether video ships.

### 5.2 `event_recordings` — the media link

```
id
event_id            → club_events
court               string
match_id            → event_matches (nullable until a bout is loaded)
angle               string      main|corner_a|corner_b|overhead   (Tier C)
recorder_uuid       → recording device
anchor_at           datetime(3) wall clock of media offset 0
started_at / ended_at
play_video_id       int|null    Play's video id, once published
play_url            string|null
status              string      recording|cutting|uploading|processing|ready|failed
```

### 5.3 Recorder enrolment — reuse the court-screen pattern

Cameras enrol **exactly like the Pi wall screens already do**: `CourtDisplayDevice` + `PairCourtDisplay` + a 6-character pairing code, claimed by an organiser, assigned to a mat, with liveness and unpair. Generalise that pairing flow to a device *kind* (`screen` | `recorder`) rather than writing a second one.

Same reasoning as the existing rule: **unpair ≠ revoke.** Unclaiming returns the recorder to a fresh pairing code; revoking the token strands a device the venue crew can never re-enrol.

### 5.4 Consent gate

Per-competitor consent, captured at entry (`ClubEventRegistration`): may this athlete be recorded, and may that recording be public. A bout where either side has withheld public consent records normally but publishes **restricted** (see §8). Minors default to restricted.

---

### 5.5 `match_annotations` — private coaching notes stay on takeone

A bout video is shared by two clubs who are opponents. Coaching notes on it are not shared: a coach reviews *their own* fighter, and that note must never reach the other athlete or the other corner's staff. One video, two mutually-invisible annotation sets.

**Play cannot host this.** Verified 2026-08-19:

- `coach_reviews` is `video_id, user_id, start/end_time_seconds, note, coach_name, emoji, position_x/y` — **no side, no club, no visibility column.** There is nothing to scope on.
- `MatchEventController::getMatchData()` returns `CoachReview::where('video_id', $video->id)->get()` — **every review on the video, to every viewer who can see the video.** Side A reading side B's notes is not a misconfiguration there; it is the intended behaviour of a single-owner model.
- Writing requires `Auth::id() === $video->user_id`. Under service-account ownership (§3.1) that is nobody, so an integrated match could not accept a coach's note at all.

These are not defects in Play — its model assumes one owner reviewing their own upload, which is exactly right for the manual flow it was built for. **That flow keeps working untouched** (RULE #1): Play's own matches keep using `coach_reviews` as they do today. Integrated matches simply never write to it.

**Coaching notes are a takeone-side layer, keyed to the shared media clock.** Play supplies pixels and a clock; every annotation that carries an access rule stays in the system that can enforce one.

```
match_annotations
id
event_match_id      → event_matches
recording_id        → event_recordings      which angle/clip the note sits on
side                a | b                   the competitor the note is ABOUT
tenant_id           → tenants                the club that owns the note
author_user_id      → users
media_seconds       numeric(10,3)           start, on the shared media clock (§4)
end_seconds         numeric(10,3)|null
note                text
emoji               string|null
position_x/y        numeric|null            overlay pin, mirrors Play's shape
visibility          side | coach            default `side`; see below
created_at/updated_at

index (event_match_id, tenant_id), (recording_id, media_seconds)
```

`media_seconds` is the one value shared with Play, and it is not sensitive — it is an offset into a file. **The note body never crosses the boundary.**

#### Authorisation

- **Write:** the author holds a coaching/staff permission in `tenant_id`, **and** `tenant_id` is the competing club of that `side` for that bout — resolved through `ClubEventRegistration::competingClub()`, which already encodes who an athlete actually represents. Add a club permission (`review-athletes`) rather than hardcoding roles, so a club can grant it to assistant coaches without making them admins.
- **Read:** the same set, **plus the fighter the note is about** — a coaching note is written for them. Also their guardian where the athlete is a minor, consistent with how the rest of the platform treats a minor's record.
- **Never:** the opposing side's club, any other club, spectators, the public, or an unauthenticated request.
- **Organisers and super-admin get no note bodies by default.** A coaching note is not event administration, and "the platform can read every club's private tactical notes" is a promise no club should have to accept. Existence and count may be visible for moderation; content is not.

`visibility` defaults to **`side`** — the fighter sees their own coach's notes. That is the point of the feature: the athlete reviews the bout with their coach's markers on it, rather than the notes being a file kept about them.

`coach` is the **exception**, set per note, for the cases where staff need to talk among themselves — a tactical read on the opponent, a selection or fitness concern, anything about the athlete rather than for them. It is a deliberate act on one note, not the resting state.

Two consequences to build for, not retrofit:

- **The write UI must show which it is.** A coach must be able to see, at the moment of typing, whether this note is going to their fighter. A note written believing it was private and read by the athlete is the failure this design has to prevent — so make the default visible in the composer, not a setting discovered later.
- **Flipping `coach` → `side` reveals history.** Treat it as a share action with a confirmation, and never expose a bulk "make all visible" control.

#### Delivery

- Notes are fetched from **takeone**, per viewer, **filtered server-side**. Never embedded in the Play payload, and never fetched whole and filtered in the player — that is the same mistake `getMatchData()` makes, moved one layer up.
- The takeone player renders them as an overlay on the Play video. The video is inert; the annotation layer is what carries identity.
- Realtime nudges scope to that club's staff only — `Realtime()->publishMany()` over the owning tenant's authorised readers, never a broadcast on the bout.

#### Edge cases that must be handled, not discovered

- **Both competitors from the same club.** One club legitimately owns both sides' notes. Scoping on `side` (not only `tenant_id`) keeps each set attached to the right fighter.
- **Unattached or disowned competitor** (`isDisowned()`, no `representing_tenant_id`): no club, therefore no one may write a note for that side. Not an error — the correct outcome.
- **A coach in several clubs.** Membership in the side's club is what grants access, not a global "is a coach" flag.
- **Club affiliation changes after the event.** Access is evaluated against the club the athlete competed for *at that bout*, which is what the registration row records — never against present-day membership.
  - **A coach leaving the club loses access**, with the membership. The note stays with the club that owns it.
  - **The fighter keeps access permanently** to the notes they could already see (`visibility = side`), including after leaving the club, changing clubs, or competing against it later. It is their own record of their own bout, and it does not become the club's to withdraw. The club retains its `coach` notes either way.
  - This falls out of the read query rather than needing a rule: the athlete is authorised by being `event_matches.{side}_competitor_id` for that bout — a historical fact that no membership change can alter — while staff are authorised by current membership in `tenant_id`. Two different tests, deliberately.
  - **Deleting a clip must not silently delete the athlete's access to it.** Whatever retention policy §10.4 lands on applies to the media; a note whose recording has aged out should degrade to a readable note without video, not vanish.

#### Not the same thing as a formal challenge

A private coaching note is one club talking to itself. A **formal video challenge** (§7, Tier C) is an official act — requested at a timestamp, adjudicated by the jury, quota consumed, score possibly corrected. Its outcome is part of the official result and is visible accordingly. Keep the two in separate tables; conflating them either leaks private notes or buries an official decision in a private one.

---

## 6. The contract

### 6.1 Transport

Two different needs, two different mechanisms:

- **Live, in-venue (mat → recorder):** MQTT, the existing `Realtime()` broker. A new channel per court. Fire-and-forget, best-effort, low latency. The recorder subscribes; markers arrive as they happen.
- **Durable, cross-platform (takeone → Play):** authenticated HTTP with retry. **The DB is the source of truth** — MQTT delivery is never assumed. At publish time takeone sends the timeline from `event_match_events`, so a dropped MQTT message costs a live marker, never a stored one.

This mirrors the existing platform rule: realtime is a nudge, the database is truth.

### 6.2 Match lifecycle

```
takeone                          recorder                    Play
───────                          ────────                    ────
session opens                    starts continuous
                                 recording, publishes
                                 anchor_at
apply('load')      ──MQTT──►     marks bout boundary
  create/attach recording row
apply('start')     ──MQTT──►     marker
apply('point'…)    ──MQTT──►     marker  (live review usable from here)
apply('finish')    ──MQTT──►     marker
apply('commit')    ──HTTP──►                    ─────────►  POST /api/v1/matches
  (bout result is final)         cut clip from                creates video +
                                 anchor+start-30s               sports_match, queues
                                 to end+15s, upload           CompressVideoJob →
                                                              GenerateHlsJob
                                                ◄─────────   webhook: ready
  store play_video_id/url                       ─────────►  POST …/timeline
  surface the video on the                                   rounds + points +
  bout, both competitors'                                    reviews in one call
  profiles, the event page
```

**Cut on `commit`, not `finish`.** `finish` stops the bout; `commit` is the point at which the result is accepted and written to the bracket. Cutting on commit means an official who corrects a score before committing does not produce a video with a wrong timeline.

**Pad the cut** — 30 s before `load`, 15 s after `finish` (tunable). Bows, introductions and the immediate aftermath are what people actually want to watch, and a review may need the moments before the disputed action.

### 6.3 Field mapping — everything takeone sends

Play's `sports_matches` requires only `video_id` and `title`; every other field is `nullable` because its own creation flow is progressive disclosure for a human filling a modal. **takeone is not a human filling a modal.** It already holds every one of those values as competition truth, so it sends all of them on the first call and a person never types a match detail again.

This table is the contract. Left column is Play's input name exactly as `SportsMatchController::rules()` validates it; right column is where takeone gets it.

#### Video record (created first, `type = match`)

| Play field | Source in takeone |
|---|---|
| `title` | same composed title as the match, below |
| `description` | `club_events.description`, plus category and round line |
| `type` | literal `match` |
| `visibility` | `private` — never public on create (§3.1) |
| `download_access` | `disabled` by default; widened only by explicit consent |
| `primary_language` | host club's locale (`tenants.settings` / event locale) |
| `video` | the cut clip from the recorder (§6.2) |

#### Scalar columns

| Play field | Source in takeone |
|---|---|
| `video_id` | returned by `POST /api/v1/matches` |
| `status` | `published` on `commit`; takeone-driven (§3) |
| `title` | composed: `{a_name} vs {b_name} — {category.name}, {round}` |
| `event_name` | `club_events.title` |
| `match_date` | date of `event_recordings.anchor_at` (the day it was actually fought), falling back to `club_events.date` + `event_matches.day` |
| `match_time` | `H:i` of the `start` command in `event_match_events`, falling back to `event_matches.scheduled_time` |
| `participant1_name` | `event_matches.a_name` |
| `participant2_name` | `event_matches.b_name` |
| `referee_name` | `event_officials` row for this event/court with a referee role → `users.full_name` |
| `sport` | `club_events.sport` |
| `match_type` | `event_matches.phase` + `round` (e.g. `Knockout — Semi-finals`) |
| `venue_name` | `club_events.location` |

#### JSON groups

| Play group | Keys takeone fills |
|---|---|
| `competition` | event `uuid`, `club_events.title`, `sport`, `league`, `scope`, `level`, `tags`, `event_type`, host club (`tenants.club_name`), `event_categories.name`, `weight_class`, `event_matches.round`, `phase`, `match_no`, `slot`, `court`, `day` |
| `participants` | per side: name, competing club (`ClubEventRegistration::competingClub()->club_name`), country (`countryCode()` — **the club's country, not the athlete's nationality**), `a_seed`/`b_seed`, `belt_colour`, `belt_grade`, `weight` + `weighed_in_at`, `entry_channel`, `unattached` flag from `isDisowned()`, and the competitor's public uuid for deep-linking back to takeone |
| `participants.extra` | reserved — team events, where a side is more than one athlete |
| `venue` | `club_events.location`, `location_url`, `gps_lat`, `gps_long`, host club name + `tenants.country`, `tenants.address`, `tenants.timezone` |
| `result` | `event_matches.winner` (resolved to the winning name), `a_score`, `b_score`, `status`, decision type from the closing `event_match_events` row, and `event_categories.podium` once the category completes |
| `reviews` | **never populated from takeone.** Private coaching notes stay on takeone entirely (§5.5). Only a formal challenge *outcome*, once Tier C exists, may appear — and only because it is part of the official result |
| `media` | `caption`, `credit` (host club), `public` — the consent flag, `false` for any restricted bout (§5.4) |

#### Repeatable groups

| Play group | Source |
|---|---|
| `officials[*]` `role` / `name` / `photo` | `event_officials` joined to `users` — `role`, `full_name`, `profile_picture` |
| `segments[*]` `type` / `number` / `score` / `winner` / `notes` | rounds derived from `event_match_events`: each `start`…`finish` span becomes a segment with its running score at close |
| `statistics[*]` `name` / `value` / `owner` | derived by aggregating `event_match_events` per side — points by value, penalty counts by rung, senshu (Karate), undo count. Each package supplies its own stat vocabulary; the exporter does not know the sport |

#### Images

| Play field | Source |
|---|---|
| `media_participant1_photo` | `club_event_registrations.photo` for side A, falling back to `users.profile_picture` |
| `media_participant2_photo` | same, side B |
| `media_referee_photo` | referee's `users.profile_picture` |
| `media_club1_logo` | side A's `club_event_registrations.club_logo`, falling back to `competingClub()->logo` |
| `media_club2_logo` | same, side B |
| `media_event_poster` | first entry in `club_events.images` |

All six are `jpg,jpeg,png,webp`, max 5 MB, and land on Play at `users/{slug}/sports/{matchId}/{key}.{ext}`. A restricted bout still sends club logos and the poster; it does **not** send competitor photos.

#### What takeone deliberately does NOT send

Play's match record is a public-facing artefact and its user table is flat, so anything sent there is outside takeone's authorisation model permanently. Never send:

- birthdate, age, email, mobile, address, national ID or passport details
- `users.health_conditions`, `documents`, `emergency_contacts`, `blood_type`
- `users.nationality` — the flag beside a competitor is the **club's** country (`countryCode()`); nationality is a fact about the person and stays on their profile
- anything about a **minor** beyond what the hall screen already shows publicly — name and club
- payment, fee, `paid_by`, `payment_proof` or any other billing field from the registration
- internal numeric ids where a uuid exists

`weight` and `belt_grade` are competition facts printed on the draw sheet, so they go; they are still withheld for a restricted bout.

#### What Play cannot hold yet

Checked against the live schema, 2026-08-19. `sports_matches` is otherwise clean — every column has a validation rule and every rule has a column, so there are no dead fields.

- **No external reference column.** Nothing records which takeone bout a Play match is. `$fillable` has no `external_id` / `source_id` equivalent and no migration adds one. Everything idempotent in §6.5 depends on it, and it is the **one schema addition Phase 2 needs** — nullable, additive, invisible to existing matches. Note it is a *lookup* key, not display data, which is why it is a column while everything in §6.6 is JSON.
- **`match_points.match_round_id` is NOT NULL, and Karate has no rounds.** A Karate bout is one continuous period. Resolve by always creating a synthetic `Round 1` per bout and hanging every point off it — no schema change.
- **Penalties have no distinct representation.** `match_points` is `action` (string) + `points` (int, NOT NULL). A Karate penalty awards the offender nothing; a Taekwondo gam-jeom gives the *opponent* a point. Both encode as action strings, but nothing structurally separates a point from a penalty — a consumer must know each sport's vocabulary to tell them apart.
- **Senshu and round wins** have no home in `match_points`. Round wins belong in `segments` (which already carries a per-segment `winner`); senshu belongs in `statistics`.
- **Never filled by takeone:** `reviews` (private notes stay here — §5.5) and `extra_participants` (team events, reserved).

One consequence worth stating plainly: `match_points` keeps only `timestamp_seconds`, never a wall clock. That is by design (§4), but it means **Play cannot recompute its own markers** if a video is ever re-encoded and offsets move. Recovery is a re-push from `event_match_events`. The takeone side is not a convenience — it is the only copy that can rebuild a timeline.

#### Write rules

- **Idempotent on the external reference.** A retry after a timeout must update, never duplicate. The whole payload is replayable.
- **takeone wins on conflict.** For an integrated match, takeone's values are authoritative and a re-push overwrites; Play's editor is disabled for these records (§10.2).
- **Send the whole payload every time.** Partial patches invite drift between two databases neither of which is wrong.

---

### 6.4 Vocabulary mapping

takeone's vocabulary is per-sport; Play's `match_points.competitor` is `blue`/`red`. The mapping lives in the **event package** (per the self-contained-packages rule), not in a shared translator:

| takeone | Play | Notes |
|---------|------|-------|
| `aka` (Karate) / `red` | `red` | |
| `ao` (Karate) / `blue` | `blue` | |
| `point` n=1/2/3 | `action` = yuko/waza-ari/ippon (Karate), or the WT equivalent | package supplies the label |
| `penalty` | `action` = the penalty at that ladder rung | ladder is package-owned |
| `senshu` | `action` = senshu | Karate only |
| `undo_point` | *not emitted* — corrects the prior point | timeline shows the corrected state |
| round boundaries | `match_rounds.round_number` + `start_time_seconds` | |

Each `EventType` exposes a method returning its timeline rows for a bout. Adding a sport must not require editing the exporter.

### 6.5 Proposed endpoints on Play

All under a versioned, authenticated `/api/v1/` prefix — **not** the current public web routes.

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/v1/matches` | Create video + `sports_match` from a takeone bout; returns `video_id`, upload target |
| `PUT` | `/api/v1/matches/{ref}` | Update metadata / result (idempotent on external ref) |
| `POST` | `/api/v1/matches/{ref}/timeline` | Replace the whole timeline: rounds, points, reviews. Idempotent — safe to retry |
| `POST` | `/api/v1/matches/{ref}/publish` | Flip to published |
| `GET` | `/api/v1/matches/{ref}` | Status + playback URLs |
| webhook | takeone ← Play | `video.ready` / `video.failed` |

**Idempotency throughout**, keyed on the external match reference. Retries after a timeout must not duplicate a match or double-write a timeline.

**There is deliberately no annotation endpoint.** Coaching notes are never sent to Play (§5.5), so nothing in this API accepts one. If a future need seems to call for it, the answer is a takeone endpoint, not a Play one.

---

### 6.6 Deep links back to takeone — everything in JSON, no new columns

On the Play match page, every name and logo is a way back into takeone: the bout name opens that bout, the event name opens the event, a club's name or crest opens the club, a competitor opens their profile.

**These links live in the JSON groups that already exist** — `competition`, `participants`, `venue`, `officials` — and **no column is added to `sports_matches` for any of them.** That is a deliberate choice, not a shortcut. New sports will arrive with things combat does not have (a team sheet, a lane, a route, a judging panel), and a schema that grows a column per sport becomes a schema nobody can change. The JSON bags absorb that; a column does not. It also keeps Play's table untouched, which is what RULE #1 asks for.

Play renders whatever links it is given and knows nothing about takeone's routing.

#### What each group carries

Every linkable entity carries the same two things: a **non-predictable public id** and a **ready-built URL**.

```jsonc
"competition": {
  "event": { "uuid": "…", "title": "Spring Open", "url": "https://takeone.bh/…" },
  "bout":  { "ref": "…", "match_no": 14, "round": "Semi-finals", "url": "…" },
  "category": { "name": "Men −75 kg", "weight_class": "…" },
  "court": "1", "day": 2
},
"participants": {
  "a": {
    "name": "…", "country": "BH", "seed": 3,
    "profile": { "uuid": "…", "url": "…" },        // omitted — see guards
    "club":    { "slug": "…", "name": "…", "url": "…", "logo": "…" }
  },
  "b": { "…": "…" }
},
"venue":     { "name": "…", "club": { "slug": "…", "name": "…", "url": "…" } },
"officials": [ { "role": "Referee", "name": "…", "profile": { "uuid": "…", "url": "…" } } ]
```

**takeone builds the URLs, Play never assembles one.** If a route changes, it changes in one place and old matches keep whatever they were sent. A URL built on Play from an id would rot silently.

#### The guards — a link is not always allowed

A profile link is an act of disclosure, so it is **omitted rather than rendered** whenever:

- **The member opted out of discovery** (`users.is_discoverable = false`). They chose not to be found; a link from a public video is exactly being found.
- **The competitor is a minor.** Consistent with §5.4 — name and club are what the hall screen already shows, a profile is not.
- **Consent for public media was withheld** for that competitor (§5.4). A restricted bout carries no profile links at all.

A **club** link is omitted when the entry is **unattached or disowned** (`ClubEventRegistration::isDisowned()`), because there is no club to link to — the athlete competed for nobody, and re-badging them with a club they merely train at is the thing `competingClub()` exists to prevent.

Omission must be **silent** — no greyed-out link, no "profile hidden" label. That would disclose the very thing the guard protects.

#### Link targets, and what still has to be built

| Click | Target in takeone | Status |
|---|---|---|
| Competitor name / photo | `people.show` → `/people/{uuid}` — the **safe** public profile | ✅ exists. Never `member.show`, which is family/admin-gated and would 404 for a stranger |
| Club name / crest | `clubs.show` → `/{country}/clubs/{slug}` | ✅ exists. `country` is the club's ISO code, `slug` its handle |
| Event name | `me.events.show` → `/me/events/{uuid}` | ✅ **already uuid-bound.** `routes/web.php` declares `{event:uuid}` explicitly. (An earlier draft of this section claimed it bound the numeric id — that was a misreading of `route:list`, which prints the parameter without its field.) |
| Bout name | `me.events.bout` → `/me/events/{event-uuid}/bout/{matchNo}` | ✅ **built 2026-08-19** |

#### The bout URL

There is no per-bout page, and `event_matches` has **no uuid column** — so a bout cannot be addressed by a non-predictable key of its own.

**Built as `/me/events/{event-uuid}/bout/{matchNo}`** (`me.events.bout`), scoped under the event's uuid rather than adding a column. The unguessable part is the event uuid, which the viewer already holds if they were given the link at all; `match_no` is only meaningful inside it, and enumerating the bouts of an event you can already see discloses nothing new. **No migration was needed**, which is why this was preferred over adding `event_matches.uuid`.

Authorisation is the event's own `assertVisible()` — the same gate as the event page and the bracket. Nothing about a bout is visible to anyone who could not already see the event. An unknown match number 404s without revealing which numbers exist.

The page shows what was asked for: **where in the event this bout sat** — division, round, mat, day and time — both corners with their clubs and countries, the result, and a way through to the draw. Mobile and desktop are separate views per the device-split rule; the guards above are applied in the controller, not the templates, so a template that forgot one cannot leak.

Verified: both views render against real event data; an unknown bout 404s; a discoverable adult gets a profile link, a **minor does not**, a non-discoverable member does not, and a **disowned entry gets no club link**.

#### Authentication reality

Every one of these targets is **behind login** on takeone. A viewer clicking from a public Play video lands on takeone's login screen.

That is acceptable and arguably correct for Phase 2 — but it should be a decision, not a discovery. Three options, in order of preference:

1. **Leave them gated.** The links serve members, who are the audience that clicks them. Simplest, discloses nothing.
2. **Add public read-only views** for event and bout only (never profiles). More work, and each one is a new public surface to secure.
3. **Signed links** with a short life, minted alongside the playback grant (§8.3).

Recommend (1) for Phase 2 and revisit once there is evidence anyone outside the platform is clicking.

---

## 7. Live stream and live review (Tier B / C)

Both are consequences of continuous segmented recording, not separate features.

- **Live review while recording.** Play already emits HLS `.ts` segments. Any segment already closed is a complete, playable file — so the jury can seek anywhere from the session start to ~2 s ago while the current segment is still being written. Serve the review player **from the venue LAN**, not over the internet: that is what makes it feel instant, and it keeps working when the venue uplink does not.
- **Live public stream.** One published output per mat, labelled with the bout currently loaded. `apply('load')` changes the label and the overlay; it does not restart the stream.
- **Jump-to-the-moment.** The disputed point is already a marker with a timestamp. The jury clicks the marker rather than scrubbing. This is the feature that makes the whole thing worth building.
- **The formal challenge is a record, not a video player.** WT and WKF both formalise the coach's video replay request — a limited quota, the card retained if the challenge succeeds. So: who requested, at which timestamp, the jury's decision, quota remaining, and the score correction if upheld. That belongs in a takeone table with an audit trail. It does **not** map onto Play's `coach_reviews` — that table is unscoped and unfiltered (§5.5), and a challenge record is rendered by takeone's own player like every other annotation.

### Multi-camera (Tier C)

Play currently models one video per match. Angles need either N videos sharing one match timeline, or an angle column — a schema decision requiring approval per the Play repo's own rule.

Two hard constraints:

- **Sync.** Four angles are worthless for adjudication unless they share a timeline. Same anchor, same clock, one scrub bar driving four players. Simplest reliable build: one recorder box taking all angles for a mat, so they share a wall clock by construction.
- **Bandwidth.** 4 angles × 4 Mbps × 8 mats ≈ **128 Mbps sustained upstream**. No club hall has that. Therefore: record all angles locally, stream **one** program angle out, upload the rest after the session. This is not an optimisation — it is the only version that works in a real venue.

Storage: 4 × 4 Mbps × 8 h × 8 mats ≈ **450 GB per tournament day** of masters. Keep masters days, keep per-match clips seasons. Needs a stated retention policy before the first event, not after.

Hardware: cameras that encode themselves (any PoE/IP camera with RTSP out) into a modest x86 box per mat. **The Raspberry Pi court screens cannot do this** — they are display devices and will not encode 4×1080p. Phones as cameras are a demo, not a product (battery, thermal throttling, sync).

---

## 8. Security requirements

These are prerequisites, not follow-up work.

### 8.1 BLOCKER — Play's match-event routes are unauthenticated

**CLOSED 2026-08-19.** The write routes now sit in `Route::middleware(['auth', 'throttle:300,1'])`.

**Correction to the original finding.** This section previously claimed anyone on the internet could rewrite or delete any match's timeline. **That was overstated**, and the correction is recorded here rather than quietly edited away, because acting on a wrong severity is its own risk. Probed against production before any change:

| Request | Result |
|---|---|
| `POST /videos/{key}/rounds`, no session | **419** — CSRF rejected it |
| `GET /videos/{key}/match-data`, no session | **200** — public read, by design |

The routes lacked `auth` middleware, but two things stood in front of them: they sit in the `web` group, so **CSRF blocked every cross-origin write**, and **every one of the nine controller methods already checked `Auth::id() === $video->user_id`**. The timeline was not open to the internet.

What was real is the *fragility*: with no middleware, that protection rested entirely on two checks being present in every method forever. One new method missing the ownership line, or one entry added to `VerifyCsrfToken::$except`, and it would have been open — with nothing in the route definition to say it mattered.

**What changed:** the nine write routes gained `auth` + a 300/min throttle. Guests now get `401` instead of `419`, which is the honest answer. Nothing changed for the owner: the annotation UI only issues these calls when signed in (it emits an empty CSRF token otherwise), and the controllers' ownership checks are untouched and still enforced.

**Deliberately unchanged:** `GET /videos/{video}/match-data` stays public. A public match video showing its rounds and points to a visitor is what the Highlights panel has always done, and `Video::canView()` still keeps a private video private. Gating it would have broken a working feature to fix a problem that was not there — see RULE #1.

### 8.2 Service authentication

takeone authenticates to Play as a **service client** (Sanctum token, its own user, scoped abilities), not as a human. The token lives in `.env` on the takeone side only, never in the repo, never in client-side output. Rotatable without redeploying either app. Rate-limited independently of human traffic.

### 8.3 Playback authorisation

The two platforms have separate user tables. A match video's audience must be decided deliberately:

- **Restricted** (default for minors, or where consent is withheld): the two competitors, their guardians, their clubs' staff, event organisers. Requires short-lived signed playback URLs — an unguessable URL is not authorisation.
- **Public**: anyone, only where both sides consented and no minor is involved.

Play has `Video::visibleTo($user)` but no notion of a takeone identity, and no notion of a club at all. This is settled in **§3.1**: identity is not mirrored — takeone authorises against its own model and mints a short-lived signed playback grant, and Play validates the grant rather than deciding.

### 8.4 Coaching notes are not shareable content

One video, two opposing clubs. `MatchEventController::getMatchData()` returns every `coach_review` on a video to every viewer of that video, and `coach_reviews` has no column that could scope it. For integrated matches, coaching notes therefore live only in takeone's `match_annotations` (§5.5), filtered server-side per viewer.

Two rules follow, and both are easy to violate later by accident:

- **Never widen the Play payload to carry note bodies**, however convenient it looks for the player.
- **Never fetch a bout's annotations whole and filter them in the client.** The filter is the authorisation; it belongs on the server.

Test it the way the anti-enumeration rule requires: coach of club A requests the bout's annotations and must receive nothing belonging to club B — with a valid bout id, a valid recording id, and a valid annotation id.

### 8.5 Everything else

- Unpredictable public identifiers for match videos (Play already uses a short key; confirm it is high-entropy, not sequential).
- Recorder devices authenticate with per-device tokens; a stolen recorder token must be revocable without touching the others.
- Do not log the service token, playback signatures, or competitor personal data in either app's logs.
- Uploads from recorder → Play validate real bytes, not declared type.
- MQTT marker payloads carry the minimum: no personal data beyond what the wall screen already shows publicly.

---

## 9. Phasing

**Phase 0 — prerequisites** · **complete, 2026-08-19**

| Item | Status |
|---|---|
| Gate the match write routes on Play | ✅ `auth` + `throttle:300,1`; guests 401, owner unaffected (§8.1) |
| `/api/v1/` surface, deny by default | ✅ `auth:sanctum` + per-token abilities + own rate limiter; `GET /api/v1/health` live |
| Service client | ✅ dedicated account, lowest role the schema allows, token abilities `match:read`,`match:write` |
| Token minting / rotation / revocation | ✅ `php artisan takeone:integration-token` on Play (`--rotate`, `--revoke`, `--show`) |
| takeone-side config + feature flag | ✅ `config/play.php`, flag **OFF** by default |
| takeone-side verification | ✅ `php artisan play:health` — connectivity, token, abilities, clock skew |
| Regression cover | ✅ 9 tests (`tests/Feature/IntegrationApiTest.php`); Play suite green |
| SSH key access between boxes | ✅ already in place |
| **NTP on both hosts** | ✅ inherited from the hypervisor; measured agreement **4 ms**. Host-level change ruled out by decision — mitigated by the `play:health` skew gate. See below |

**NTP: the requirement is real, but it must not be satisfied inside these boxes.**

Both "hosts" are **privileged LXC containers** (`systemd-detect-virt` → `lxc`), and they share the hypervisor's clock — there is no time namespace between them. Consequences, verified rather than assumed:

- `systemd-timesyncd` is *enabled* on Play but never runs: its unit carries `ConditionVirtualization=!container`, and the journal says so outright — `Condition check resulted in Network Time Synchronization being skipped`. It is refusing by design, not by misconfiguration.
- Both containers *do* hold `CAP_SYS_TIME`, so a daemon forced to run inside one **could** step the clock — and would be stepping the **hypervisor's** clock, and therefore every sibling container's. Two time daemons, one in each container, would fight each other over a single shared clock. That is worse than the problem.

**So NTP could only be configured on the Proxmox host** — and that host is out of scope: **no changes are to be made to the hypervisor** (decision, 2026-08-19). Nothing is therefore installed or altered on either box for timekeeping, which is the correct outcome for containers in any case.

**What stands in its place.** The clocks are inherited from the hypervisor and currently agree to 4 ms, comfortably inside what §4 needs. The residual risk is that nothing on our side *disciplines* them, so drift is unbounded in principle. That risk is carried, not eliminated, and it is bounded at the point where it would do damage:

- **`php artisan play:health` is the gate.** It fails hard above `PLAY_MAX_CLOCK_SKEW` (default 2 s), so a drifted pair refuses to publish rather than silently writing a timeline whose markers sit on the wrong technique.
- **Run it before each event day**, as part of setting up — the same check that confirms the token also confirms the clock, and it costs a second.
- If it ever reports skew approaching the limit, the fix is on the hypervisor and needs a decision then. It is not something the application can correct for itself.

**Measured reality (2026-08-19):** the two containers agree to **+0.004 s** — four milliseconds, measured over a warm connection across seven samples. Earlier readings of −0.437 s were the SSH and TLS handshakes, not drift; a single cold request cannot measure a clock. `play:health` now samples five times and keeps the lowest-round-trip exchange, the way NTP does, and Play reports `server_time` to millisecond precision (`toIso8601String()` truncates to the second, which injected up to a second of error into the estimate — larger than the drift being looked for).

Four milliseconds is far inside anything §4 needs. The residual risk is not today's offset but the absence of a *verified* discipline on the host, so `play:health` keeps failing above `PLAY_MAX_CLOCK_SKEW` (default 2 s) as a backstop.

**Found while doing Phase 0, unrelated to it but worse than it:** Play's `phpunit.xml` had `DB_CONNECTION` and `DB_DATABASE` **commented out**, so `RefreshDatabase` would have run `migrate:fresh` against the **live production database** — the same accident that destroyed takeone's staging DB on 2026-08-02. Both entries are restored, and `tests/TestCase.php` now aborts if a config cache is present or if sqlite resolves to anything but `:memory:`. Proof it was real: `ExampleTest` had been passing only because it was querying production; against a clean `:memory:` database it 500s, and now declares `RefreshDatabase` like it always should have.

**Phase 1 — the timeline exists** · *core done 2026-08-19; export outstanding*

| Item | Status |
|---|---|
| `event_match_events` table | ✅ additive migration; no existing table or column touched |
| Written from inside `Scoring::apply()` | ✅ both packages (Karate + Taekwondo), after the dispatch, before `save()` |
| Fail-safe | ✅ `MatchEventLog` cannot throw — verified with the table **dropped mid-bout**; scoring continued |
| Kill switch | ✅ `EVENT_MATCH_LOG` (defaults ON, independent of the Play flag) |
| Verification | ✅ 24 checks green against an isolated `:memory:` database |
| **Per-package export of a bout's timeline** | ⛔ **not started** — see below |

**Deviation from §5.1, deliberate.** The table uses neutral **`score_a` / `score_b` and `side` ∈ `a`/`b`**, not the `score_aka`/`score_ao` this document originally specified. `aka`/`ao` is Karate's vocabulary; Taekwondo's corners are not called that, and one shared table cannot speak one sport's dialect. `a`/`b` are the columns `event_matches` already uses, and `Scoring::load()` builds `aka` from `a_*` and `ao` from `b_*` in both packages — so the mapping is exact, not a convention. Each package translates to its own words on export, which is where §6.4 already says vocabulary belongs.

**⚠️ Found while building, and it shapes the exporter: `score_a`/`score_b` do not mean the same thing in the two sports.** Karate's are the running total for the bout. **Taekwondo's are the CURRENT ROUND only** — `MatState` clears them at every round break, and the match standing lives in `akaRounds`/`aoRounds`. The rows are faithful either way (they record what the scoreboard displayed at that instant), but a Taekwondo timeline must first establish round boundaries from the `award_round` and `rest` commands before the numbers can be read. An exporter written on the assumption that these are bout totals would produce a Taekwondo timeline that is quietly, plausibly wrong — the exact failure mode §4 warns about, arriving through a different door.

**What the export still needs.** `EventType::boutTimeline(EventMatch)` on the contract, defaulted to empty in `AbstractEventType` (all three concrete types extend it, so nothing breaks), overridden per package to map its commands into Play's `match_rounds` / `match_points` vocabulary (§6.4). Two known wrinkles to solve there, both derivable from the log by replay rather than needing new columns: **round boundaries** for Taekwondo, and the **penalty rung** for Karate, which is a count of prior awarded penalties per side rather than a stored value.

**Phase 2 — post-match video (Tier A)**
Recorder enrolment reusing the court-screen pairing. Continuous recording per mat. Cut on `commit`, upload, publish, timeline push. Video surfaces on the bout, both competitors' profiles, and the event page.

**Phase 3 — live (Tier B)**
Program stream per mat with bout labelling. LAN review player reading the live HLS playlist.

**Phase 4 — jury review (Tier C)**
Formal challenge record with quota and outcome. Multi-angle schema, synced scrub, angle switching.

Each phase ships something usable alone. If the project stops after Phase 2, every competitor still gets a clip of their match with its highlights — which is most of the value.

---

## 10. Open questions

1. ~~**Cross-platform identity.**~~ **Decided (§3.1, 2026-08-19):** identity is not mirrored. Play is the media backend under a service account; takeone stays the front door and mints short-lived signed playback grants. What remains open is narrower: (a) does takeone-owned content ever surface on `video.takeone.bh` itself, or is Play invisible to takeone members? Recommend invisible to start — reversible. (b) Does a club's own recording (not from an enrolled event recorder) enter the same pipeline, and at which phase?
2. **Does `sports_matches` become read-only for integrated matches?** If someone edits competitor names on Play and takeone re-pushes, whose value wins? Recommend: takeone wins, and Play's editor is disabled for integrated matches.
3. **Are coach reviews public after the event?** "Red's coach challenged and lost" visible forever is a different product from showing it to the two clubs involved.
4. **Retention.** How long do masters live? Per-match clips? Who pays for the storage — platform or club?
5. **Venue reality.** Does the LAN + power exist at each mat in the halls actually used? Tier C is fiction without it. Worth confirming against a real venue before Phase 3.
6. **Multi-angle now or later?** Recommend later — a schema change on a running platform for a feature not yet proven single-camera.
7. ~~**Do athletes see their own coach's notes?**~~ **Decided (2026-08-19): yes, and permanently.** `match_annotations.visibility` defaults to `side` — the fighter reads the notes written about their own bout, and their guardian does too where the athlete is a minor. `coach` is a per-note exception for staff-only discussion. The fighter **keeps that access after leaving the club**: they are authorised by having *been* that competitor in that bout, not by current membership, so no affiliation change withdraws it. Coaches, authorised by membership, do lose access on leaving. See §5.5.
8. **Does a club retain access to its notes after the event?** Recommend yes, indefinitely: the note is the club's coaching record, not event ephemera. Confirm against the retention policy (§10.4).

---

## 11. Connection reference

- Host `192.168.0.31` (`video`), project root `/var/www/videoplatform`, its own `CLAUDE.md` at the repo root.
- Credentials are **not** recorded here or anywhere in the repo. Use key-based SSH (`ssh videoplatform` once `~/.ssh/config` is set up).
