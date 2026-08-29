# TAKEONE — Uncommitted Work Preservation Bundle

**Created:** 2026-08-29 02:57:02 +03 (bundle id `20260829-025123`)
**Source repository:** `/var/www/takeone`
**Branch:** `development`
**HEAD commit:** `c12d1ada0696d589c39b73a001e4ea4667a973e7`

---

## ⚠️ What this bundle is

> **This bundle is a safety backup and review aid. It does not commit, push, deploy, reset,
> clean, stash, or alter the original repository.**

Nothing was staged. No git state was changed. The working tree at `/var/www/takeone` is
byte-for-byte as it was found.

## Dirty path count

| Measure | Count |
|---|---|
| `git status --short` before this phase | **300** |
| `git status --short` after this phase | **300** |
| Expanded to individual files (untracked dirs walked) | **411** |
| — tracked-modified | 175 |
| — tracked-deleted | 12 |
| — untracked | 224 |

`git status --short` collapses untracked directories to a single line, which is why 300
becomes 411 once each new directory is walked.

## Confirmation: no repository file was touched

- No file inside `/var/www/takeone` was created, modified, deleted, renamed, chmod'ed or
  chown'ed by this phase.
- No `git` command that changes state was run — no add, commit, push, stash, reset,
  restore, checkout, clean, merge, rebase or cherry-pick.
- No `php artisan`, Composer, NPM, Vite, PHPUnit, migration, seeder, queue, Horizon,
  Docker or database command was run.
- Only read-only inspection was used: `git status`, `git diff`, `git rev-parse`,
  `git ls-files`, plus `cat`/`grep`/`awk` and a read-only `tar`.
- No external service was contacted.
- Verified: `git diff --check` is clean before and after; the dirty count is identical.

## Files in this bundle

| File | Size | What it holds |
|---|---|---|
| `README.md` | — | This file |
| `WORKTREE_INVENTORY.md` | 68 KB | All 411 paths, classified by area; counts and key lists |
| `CHANGE_GROUPS.md` | 14 KB | 11 proposed commit groups, with dependencies and risk |
| `SENSITIVE_FILES_EXCLUDED.md` | 4.6 KB | What was left out, and why |
| `TRACKED_CHANGES.patch` | 1.2M | `git diff --binary HEAD` — all 187 tracked modifications and deletions |
| `UNTRACKED_SAFE_FILES.tar.gz` | 524K | 136 safe untracked source/doc/test/config files |
| `UNTRACKED_SAFE_FILES.sha256` | — | Checksum of the archive |

Helper files beginning with `.` (`.raw_status*.txt`, `.safe_list.txt`,
`.excluded_list.tsv`, `.rows.json`) are the intermediate data the reports were built from.
They contain paths only, no file contents.

**Archive checksum (SHA-256):**
```
2527acba13099e43134cf81cc81ce6fb50578a1a68314d1b9175ce8bae973fe3  UNTRACKED_SAFE_FILES.tar.gz
```

## Security

- **No secret value was read or recorded.** The `.env` was never opened; it is not among the
  uncommitted paths and so is not part of this bundle.
- **No `.env`, database file, `storage/`, upload, log, `vendor/`, `node_modules/`,
  `bootstrap/cache/` or build output appears anywhere in the dirty set** — verified by direct
  search, so none could have reached the archive or the patch.
- **The patch was never inspected for content**, per the brief — only paths were scanned for
  sensitive keywords. One hit (`…/mobile/backup.blade.php`) was judged benign and is flagged
  for a human skim in `SENSITIVE_FILES_EXCLUDED.md`.
- The archive was re-scanned after creation to confirm it contains no sensitive path.

## How to use this bundle

**To restore the tracked changes** onto a clean checkout of `c12d1ada`:
```bash
git apply --binary /path/to/TRACKED_CHANGES.patch     # add --check first to dry-run
```

**To restore the untracked files**, from the repository root:
```bash
sha256sum -c UNTRACKED_SAFE_FILES.sha256              # verify first
tar -xzf UNTRACKED_SAFE_FILES.tar.gz
```

## ⚠️ Three things the owner should know

1. **21 Flutter/Android source files are NOT in the archive.** `.dart`, `.kt`, `.kts` and
   `.properties` fall outside the extension allowlist this phase was given — they are not
   sensitive, just unlisted. They are **untracked**, so nothing else backs them up. See
   `SENSITIVE_FILES_EXCLUDED.md` §3 for a one-line command to preserve them.

2. **This bundle lives in `/tmp` and will not survive a reboot.** Copy it somewhere durable
   and off this box.

3. **`/var/www/takeone` is simultaneously the `Deploy Staging` rsync target.** A staging
   deploy overwrites uncommitted work — all 411 paths, including the +549-line live scoring
   change, are one deploy away from being lost. That risk is why this bundle exists, and it is
   not removed by the bundle's existence.
