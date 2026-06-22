# Schedule mockup backup — 2026-06-22

Snapshot of `/me/schedule` **before** adding member-created personal schedule
entries. Captures the all-dummy state so it can be restored if needed.

## What `/me/schedule` was at this point

- Route: `GET /me/schedule` → `PersonalMobileController@schedule` (name `me.schedule`)
- Detail: `GET /me/schedule/{session}` → `PersonalMobileController@scheduleShow` (name `me.schedule.show`)
- **Live data was 100% DUMMY** — from `PersonalMobileController@demoSchedule()`:
  - 3 members: `me`, `sara` (daughter), `omar` (son) — each `{key,name,relation,initials,color}`
  - 8 hardcoded sessions, each with: `id, who, day, start, end, duration, title,
    discipline, icon, color, coach, location, intensity, focus[], notes,
    workout{warmup[], main[{name,sets,reps,note}], cooldown[]}`
- A second method `scheduleLegacy()` (NOT wired to any route) contained the REAL
  DB-backed projection: it reads `ClubMemberSubscription` (active/pending) →
  `package.packageActivities[].schedule` weekly slots → projects concrete session
  occurrences with done/remaining counts. This is the "synced from enrolled
  packages" logic.

## Files in this backup
- `schedule.blade.php` — week view (hero stats, Me/Family toggle, day strip, session cards)
- `schedule-show.blade.php` — session detail (cover, quick facts, focus chips, workout check-off)
- `PersonalMobileController.schedule-methods.php` — `schedule()`, `scheduleShow()`,
  `demoSchedule()`, and `scheduleLegacy()` exactly as they were.
