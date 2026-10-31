# Live upgrade plan — bringing takeone.bh up to stage

> Asked for 28 Oct 2026 ("plan the live upgrade") so that TakeNote — and everything
> built since August — can run on the live site. Every step that touches the live
> server needs the owner's go-ahead for that step (RULE #1, RULE #2, the standing
> production rule).

> **Status (31 Oct 2026): the upgrade itself is DONE, by a different route than the one
> below.** Read §0 first; §1–§4 are kept as the record of the plan and for the items still
> open. Nothing in §3 was run as written.

## 0. What actually happened (30–31 Oct 2026)

| Plan step | What was done instead | Evidence |
|---|---|---|
| Fix the deploy route (Phase 4) | The GitHub runners (unregistered since 31 Aug) were re-registered and production's code files re-owned; the first pipeline ship ran ~two months of migrations on live with no new errors | af02a503, efb16177, f41c7b05 (30 Oct) |
| Decision 1 = B, "stage is the real one" | Not by copying stage's SQLite file: `takeone:promote --apply` copied staging's real data INTO production (renumbered, linked, nothing of production deleted) after a verified backup — ~89,700 rows + 440 files | 5a1a1a6e; `docs/platform/data-promotion.md` |
| The database question as a whole | Staging and production now SHARE one PostgreSQL database (`takeone`); stage cut over 30 Oct ~19:23. There is no longer a "live DB" and a "stage DB" to reconcile | acc9d67d |
| Feature switches at launch (decision 2) | Production's switches now mirror staging on every ship (server keys excepted) | 32d1a0ef, 11614c3b |
| TakeNote friends' notes (decision 3) | Moot: with one shared database both sites read the same notebooks | acc9d67d |
| Uploaded files | `ship.sh` copies the uploads production lacks on every deploy (`--ignore-existing`, never deletes); `UPLOADS_ROOT` exists for a shared folder (off until set) | 8e97ad9f, 64f9446b |
| How releases go out now | "Ship it" = `scripts/ship.sh` → `scripts/deploy-direct.sh` straight onto the production container (backup → code archive → down → rsync → migrate/caches → up → check → clean-up); GitHub only with `--via-github` | 7ffa6a36 (31 Oct) |

**Still open from this plan** (not verified as done; check before relying on them):
- **Phase 2 programs on production** — `ffmpeg`, `chromium`, `fonts-noto-core` (match video,
  printables, bracket/participant PDFs). `docs/platform/house-cleaning.md` notes production
  needs chromium + noto before `EVENT_BRACKET_PDF` is on there. Not confirmed installed.
- **The ~576 errors a day on live** (suspected `horizon:snapshot` with no Horizon) — not
  confirmed fixed by the new code.
- **Off-box backup** (`BACKUP_DISK`) — still a CLAUDE.md Pre-Launch Runbook blocker.
- **Queue worker and scheduler as `www-data`/`actionsrunner` instead of root** — still a
  Pre-Launch Runbook item.
- **One-off stage commands since 22 Aug** (Phase 0 list) — largely moot because the data now
  lives in one shared database, but uploads moved by commands such as
  `takeone:gather-person-photos` reach production only through the uploads copy in `ship.sh`.
- Production `.env` server keys (`DB_PERSISTENT`, cache/session/Redis) are set by hand, not by
  a ship — see `docs/platform/performance.md`.


## 1. Where things stood (measured 28 Oct 2026)

| | Live (takeone.bh) | Stage (stage.takeone.bh) |
|---|---|---|
| Code | git `ead2a2a`, 5 Aug 2026 (files hand-copied 22 Aug) | branch `printables-designer`, 3 commits past `main` + **243 uncommitted files** |
| Layout | old `app/Models` | modules (`app/Members`, `app/Events`, … — landed 7 Sep) |
| Commits between | — | **146 on `main` since 22 Aug**, plus the uncommitted work |
| Database migrations run | **262** (last: `2026_08_29_140000_create_media_file_subjects_table`) | **399** → **137 to run on live** |
| Data | 1,030 users · 9 clubs · 2 events · 18 entries · 1,882 notifications · 0 invoices · DB 24 MB | 2,072 users · DB 50 MB · TakeNote friends' real notes |
| Activity | **no user created or changed since 22 Aug** | in daily use (owner, friends) |
| Programs | php 8.2.35, node, composer · **no ffmpeg, no Chromium, no poppler** | all present |
| Workers | supervisor `queue:work` as **root**; cron `schedule:run` as **root** | same |
| Errors | **~576 a day, flat** (a scheduled job failing every few minutes) | — |
| Disk | 64 GB free of 98 GB; backups on the same disk only | — |
| Settings | 61 keys; none of the ~50 feature flags stage uses | — |

Two things this table settles:

1. **This is a ten-week jump, not "add TakeNote".** TakeNote sits on the module
   layout, which the live code does not have.
2. **The normal route is broken.** The GitHub Actions deploy has been red since
   August (memory: manual deploy gotchas), so the upgrade is a careful hand-copy.

## 2. Decisions for the owner (one at a time)

> **Decided 28 Oct 2026 — decision 1: B.** The owner: "stage is the real one". The live
> site becomes a copy of stage — code, database and uploaded files. This removes the
> 137-migration run on live data, and adds one hard requirement before anything moves:
> **a table-by-table comparison of the two databases** listing every row that exists
> ONLY on live (created or changed there since the two diverged), each one carried
> over or knowingly dropped by the owner. Phase 1 becomes that comparison plus a
> rehearsal of the switch; Phase 3 copies stage's database (`VACUUM INTO`, never `cp`)
> and stage's upload folders instead of migrating.

1. **Which database is the truth after the upgrade?**
   - *A. Upgrade the live database in place* (run the 137 migrations on it). Keeps
     every live row. Stage's data stays on stage. **Recommended** unless the live
     data is known to be a stale copy.
   - *B. Make stage's database the live one.* Simpler, but anything only the live
     database holds is lost, and stage's test/demo rows go live. Needs a row-by-row
     comparison first.
2. **Which features switch on at launch.** Every new feature ships behind a flag
   that is OFF unless set. Proposed: TakeNote (+ friends) and email safety net ON;
   everything else OFF and switched on one at a time afterwards.
3. **TakeNote friends' notes.** They live on stage. Move them to live during the
   upgrade (export their notebooks + files), or leave TakeNote friends on stage.
4. **The maintenance window.** A quiet hour; the site shows a maintenance page for
   about 20–40 minutes (measured in the rehearsal).

## 3. The plan

### Phase 0 — Freeze a release (stage, no live changes)
- Stop new work on stage for the release. Commit the 243 files to a release branch
  and tag it (owner says when; nothing is pushed without asking).
- Run the full suite with `scripts/test-per-file.sh`; compare with the known
  failures (two DevBoard `in_notebook` tests, the route-count tripwires,
  ModuleBoundary, …). No new failures allowed.
- **List every one-off command run on stage since 22 Aug** that changed data
  outside migrations (e.g. `takeone:gather-person-photos`, storage moves, morph-map
  data, translation backfills). Each becomes a step in Phase 3 or is proven unneeded.

### Phase 1 — Dress rehearsal on a copy (stage box, no live changes)
- Copy last night's live backup (`db-*.sqlite` + uploads) into a scratch folder.
- Run the release code against it: the 137 migrations, then the one-off commands.
- Check: no migration fails; row counts of users/clubs/events/subscriptions/
  notifications are unchanged; key pages render (`/tmp/takeone-smoke.php`, login,
  a club page, an event, /me, TakeNote); emails queue; no new log errors.
- Time every step — this sets the maintenance window.
- Rehearse the rollback too: restore the snapshot and prove the old site comes back.

### Phase 2 — Prepare the live server (outside the window, nothing user-visible)
- Install what the new code needs: `ffmpeg`, `chromium`, `poppler-utils`,
  `fonts-noto-core` (match video, printables, bracket/participant PDFs).
- Find and fix the ~576 errors a day (most likely `horizon:snapshot` scheduled with
  no Horizon installed) — or confirm the new code already fixes it.
- Add the new settings to `.env`, all flags OFF except those in decision 2.
- An off-box copy of the latest backup (decision: where — `BACKUP_DISK`).

### Phase 3 — The window (live)
1. `php artisan down` with a maintenance message.
2. **Backup**: `takeone:backup`, verified (integrity + row counts), plus a tar of
   the whole current code folder for rollback.
3. Copy the release code with anchored excludes and checksums (`-c`), never
   touching `storage/`, `.env`, `database/database.sqlite*`.
4. As `actionsrunner`: `composer install --no-dev --optimize-autoloader`,
   built assets (`public/build`) from the release.
5. `php artisan migrate --force` (the 137), then the one-off commands from Phase 0.
6. As `actionsrunner`: `config:cache`, `route:cache`, `event:cache`, `view:clear`
   then `view:cache`; `queue:restart`; storage ownership checked.
7. Smoke checks (the rehearsal's list) while still down; then `php artisan up`.
8. Watch the log and the email health for an hour.

**Rollback** (if any check fails): `down`, restore the code tar and the verified
database backup, clear caches, `up`. Rehearsed in Phase 1.

### Phase 4 — After
- TakeNote friends: move their notebooks if decided (decision 3), then point the
  invite links at takeone.bh.
- Switch features on one at a time, each with a quick check.
- Fix the GitHub Actions pipeline so the next release is not a hand-copy; run the
  queue worker and the scheduler as `www-data`/`actionsrunner` instead of root.

## 4. Risks to keep in view
- **A migration that assumes stage-shaped data** fails on live data — caught by
  the rehearsal, which is why it runs on a real copy.
- **One-off data commands forgotten** — the Phase 0 list.
- **Stale workers** keep old job code in memory — `queue:restart` in step 6.
- **Root-owned cache files** lock Apache out — every artisan call as `actionsrunner`.
- **The mail safety-net patch already on live** is part of the release, so the copy
  replaces it with the same code; checked in the smoke list.
