# Merging small divisions

Officials' handbook (rules, verbatim sources, worked examples):
https://claude.ai/code/artifact/2dcd41d0-2a95-43ad-9f4b-9c2664f8326a

## Where it lives

| Part | File |
|---|---|
| Engine (judge, suggest, merge) | `app/Events/Support/DivisionMerge.php` |
| Endpoints (organiser only, behind the flag) | `app/Events/Controllers/DivisionMergeController.php` → `me.events.divisions.merge{,.suggest,.check}` |
| Club / personal event UI | `<x-division-merge>` on `personal/event-divisions`; `<x-event-divisions>` re-reads on `division-merge-done` |
| Federation dashboard UI | `app/Federations/resources/patches/div-manage.js` (`dmMg*`) + `event-divisions.html`; door `divisions.merge` |
| AI advisor | `merge_safety` tool in `Budget/AdvisorToolbox.php`; rule in `BudgetAdvisor` system prompt |
| Handbook PDF (same "Fight night" set as the draw / entry-list PDFs) | `MergeHandbookPdf` + `events::merge-handbook-pdf` → `me.events.divisions.merge.handbook` (organiser + officials). Offered in the event ⋯ menu, the merge sheet and the federation merge modal. Text is in the view — keep it in step with the online handbook. |
| Tests | `tests/Feature/Events/DivisionMergeTest.php` |

## Limits — `config('events.division_merge')`

| Key | Default | Source |
|---|---|---|
| `enabled` (`EVENT_DIVISION_MERGE`) | `false` | RULE #1 |
| `below` | 3 | WUKF Art. 2.1 |
| `consent_gap` | 12 % | Youth wrestling (Madison pairing) |
| `max_gap` | 15 % | Ontario Judo, Part V s.4 |
| `max_age_months` | 24 | Ontario Boxing, Part V s.4 |

## Grades

- **green** — same gender, same age group, weight gap ≤ 12 %.
- **amber** — one adjacent age group, gap 12–15 %, or missing weight/birthdate/gender. Merge only with `consent: true` (organiser confirms written guardian consent).
- **red** — mixed gender, minor with adult, fought bouts, event started, age groups not adjacent, > 24 months across groups, gap > 15 %, or a person already in both. Never merged.

Gaps are measured between the athletes who MOVE and those already in the kept group (furthest opponent), not across the whole pool. The server always re-judges; a client grade is never trusted.

## What a merge writes

Registrations move to the kept group with `category_source = manual`; emptied groups and their unfought matches are deleted (same as deleting a division); the kept group's draw is rebuilt via `EventType::onEntrantsChanged`; a realtime `{action:'draw'}` goes to the event's audience; an `activity_log` row `events.divisions_merged` records actor, grade, reasons and consent.

## Start again: delete all · auto-place (`DivisionReset`)

`me.events.divisions.delete-all` (needs `confirm: true`; optional `then_place`) and `me.events.divisions.auto-place`, organiser only, on with `EVENT_DIVISION_MERGE`. Delete-all removes every division and its unfought draw (entries stay, unplaced) and stores the old layout in `activity_log` (`events.divisions_deleted_all`). Auto-place runs `CategorySystems::placeCreating` for everyone in no division — creating only the rule-book divisions somebody fits, never moving a hand placement, one entry per person — and puts new divisions in reading order when the event had none. Both refuse once the event has started, a bout was fought/filmed, or the event is entered in separate parts (streams). Empty divisions are hidden in both UIs ("Show N empty"). Tests: `DivisionResetTest`.
