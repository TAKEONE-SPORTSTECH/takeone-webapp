# Data promotion (`takeone:promote`)

Copies rows from one SQLite database into another without overwriting anything:
every row is either **linked** to a row the target already has, or **added** with
a new id and every reference to it rewritten. Code: `app/Support/Promote/`
(`Policy` = what is copied and how rows are recognised, `Planner` = the decision
per row, `Applier` = one transaction that writes it), command
`app/Console/Commands/PromoteData.php`.

## How a row is recognised

In order: promoted on an earlier run (target's `promotion_map`) → **came from the
target on an earlier run (source's `promotion_map`)** → same uuid → same person
(uuid / email / mobile + name / same name in the same event) → same club slug,
catalogue slug or unique key → otherwise added. `promotion_map` itself is never
copied; each side keeps its own.

## The 30 Oct 2026 round trip, and why the second rule exists

1. 15:50: staging was promoted into production (staging row S became production row P).
2. 17:18: production was promoted back into staging.
3. Staging, now holding both, was moved to the shared PostgreSQL database.

In step 2, events were matched by uuid, but divisions, entries, bouts and their
logs have no uuid, so they were added again. The result: five events showed every
weight class, entry and podium twice; two hidden copies of the Selection Trials
existed; translation strings, fee lines and profile medals were doubled.

Cleaned up on 2026-10-31 after a verified backup (`db-20261031-072254.dump`),
removing only rows identical to their original (pairs from S → P → T):

| Table | Removed |
|---|---|
| interface_translations | 35,670 |
| event_match_events | 4,602 |
| user_notifications | 1,562 |
| event_registration_fee_lines | 474 |
| bjj_match_events | 389 |
| club_event_registrations | 356 |
| event_matches | 322 |
| performance_results | 252 |
| event_club_entrants | 209 |
| event_notifications_sent | 98 |
| event_categories | 82 |
| event_camera_clips | 63 |
| event_visits, event_officials, club_events (#108, #109), event_expenses, event_recordings, event_screen_media, user_roles, devboard_activity | 10, 8, 2, 2, 2, 2, 2, 2 |

Rows only: each copy pointed at its original's files. Kept for review: 5 user
accounts that are real, different accounts; 2,013 notifications and 933 activity
rows whose pairing did not match; Selection Trials 2 (#32)'s own repeated
division names, which did not come through this route.

The Planner now links rows that came from the target ("came from here on an
earlier run"). Replaying production back into staging with the fix links 110
divisions, 422 entries and 406 bouts instead of adding them. Test:
`tests/Unit/PromoteRoundTripTest.php`.

## Limitations

- **SQLite only.** Both staging and production now share one PostgreSQL database,
  so there is nothing left to promote between them. The tool remains for
  importing another SQLite database.
- **Whole database in memory.** The Planner reads every table into PHP. Fine for
  ~100k rows; a much larger import would need table-by-table streaming.
