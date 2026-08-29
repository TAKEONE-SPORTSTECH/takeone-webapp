# Patch for TAKEONE Play — video ingest from the matside cameras

**Not applied.** These files belong in the `videoplatform` repo
(`192.168.0.31:/var/www/videoplatform`) and are staged here because writing to that
live platform is a decision to take deliberately, not a side effect of building this
side.

Play already accepts a bout's competition truth — `PUT /api/v1/matches/{video}`, written
from here by `App\Play\PlayClient::pushMatch`, which is how
[video 11vu7R](https://video.takeone.bh/videos/11vu7R) got its competitors, clubs,
officials, result and timeline. The one thing missing is a way for the **file** to
arrive: today somebody must upload the video by hand in a browser before takeone can
annotate it. This patch closes that gap.

## What it adds

| File | Change |
|---|---|
| `app/Http/Controllers/Api/V1/UploadController.php` | New. Chunked, resumable ingest that creates a `match` video and hands it to the ordinary pipeline. |
| `routes/api.php` | Four routes inside the existing `v1` group, behind a new `video:write` ability. See `routes-api.patch`. |

## Why chunked rather than one POST

- `post_max_size` on Play is **512M** and nginx caps the body at the same. A four-minute
  bout at 1080p30 is ~480MB; a five-minute one at 60fps is over a gigabyte. Single-shot
  would fail on exactly the bouts people care about — the long ones.
- The other end is a phone on a competition hall's wifi, which drops. An upload that
  cannot resume never finishes.

## What it does not do

- It can only ever create `type = match` (RULE #3). No parameter widens that.
- It touches no shared upload code — it reuses `CompressVideoJob`, which chains
  `GenerateHlsJob`, exactly as a browser upload does.
- New videos are **public** — an uploaded bout is meant to be found, and neither side
  can publish one after the fact yet. The consent gate that should sit in front of this
  (competitors, and minors especially — `VIDEO-INTEGRATION.md` §5.4) is **not built**;
  until it is, an organiser choosing to upload is the whole of the decision.
- Uploads are scoped to the calling token: one service client cannot resume another's.

## Applying it

```bash
# 1. the controller
scp Documentation/play-patch/UploadController.php \
    videoplatform:/var/www/videoplatform/app/Http/Controllers/Api/V1/UploadController.php

# 2. the routes
scp Documentation/play-patch/routes-api.patch videoplatform:/tmp/
ssh videoplatform 'cd /var/www/videoplatform && patch -p1 < /tmp/routes-api.patch'

# 3. the token needs the new ability — this prints a NEW token, once
ssh videoplatform 'cd /var/www/videoplatform && php artisan takeone:integration-token \
    --rotate --abilities=match:read,match:write,video:write'

# 4. put that token in THIS side's .env as PLAY_API_TOKEN, then
php artisan config:cache
```

Rotating the token invalidates the current one, so step 4 is not optional — between
steps 3 and 4 the integration cannot authenticate at all.

## Rolling it back

Delete the controller, revert the four routes, and re-mint the token without
`video:write`. Nothing else on Play is touched, and no existing video, table or column
changes — so a rollback is those three commands and nothing else.

## After applying, verify

```bash
php artisan play:health           # should report the three abilities
```

Then upload one clip from a camera and check that: the video appears on Play as
`unlisted`, its status goes `processing → ready`, HLS is generated, and the bout data
(competitors, clubs, result, timeline) lands on it through the existing push.

Then, per that repo's RULE #3, confirm a **music** video and a **generic** video still
play and edit normally. This patch adds routes rather than changing shared code, so
they should be untouched — but "should be" is not the standard that rule sets.
