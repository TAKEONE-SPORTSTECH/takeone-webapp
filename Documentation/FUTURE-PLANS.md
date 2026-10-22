# Future Plans

Problems found while running real events, kept here until they are fixed in the
product. Each entry says what happened, why, what was done on the day, and what
the permanent fix should be.

---

## 1. Bout numbering breaks when a division grows after the schedule is made

**Found:** 16 Oct 2026, Bahrain Energy Zone Combat 4 (event 105), live on Mat 1.

### What happened
- **Byes kept a number.** Eight first-round bouts had one competitor and an
  empty corner nobody would ever fill, yet they were numbered like real bouts
  (#13, #20, #40, #41, #43, #45, #49, #52). The lone athlete was never carried
  into the next round, so the following bout showed an empty corner too
  (e.g. Group M final had only one name while Ismael waited in "bout #13").
- **Finals were numbered before their own semi-finals.** Group F final #26 vs
  semis #47/#52; Group K final #31 vs #46/#51; Group Q final #36 vs #45/#50;
  Group R semis #15/#16 and final #37 vs quarter-finals #40–#43. Calling the
  numbers in order would reach a final with nobody in it.
- **Gaps** after a division was removed mid-event (25, 48, 53).
- The organiser's own "Renumber bouts" button cannot help: it refuses once any
  bout has been fought, which is exactly when this is noticed.

### Why
- The day's schedule (`event_run_slots.parts`) was set when Groups F, K and Q
  had two entrants (`finals` only) and Group R had no quarter-finals. When more
  athletes were added, the new semi/quarter-final bouts were not in any
  scheduled part, so the Scheduler appended them at the end of the mat —
  after the finals they feed. Group T (14-15y, 77.6kg) had no slot at all.
- One-sided first-round bouts created by a manual draw were not written as
  byes (`won_by_bye`, winner set) and `Advancement::propagate()` was never run
  for them, so the Scheduler (which only drops numbers from DECIDED byes)
  numbered them.

### Done on the day (by hand, backups `db-20261016-143506` and `db-20261016-144403`)
- Group E (9-10y, 24.5-31.5kg) removed; its 4 entrants released, not withdrawn.
- The 8 byes resolved the engine's way (winner + `won_by_bye`, then
  `propagate()`), numbers removed.
- Played bouts kept 1–22 untouched; unplayed bouts renumbered 23–47 with no
  gaps, every bout after the bouts that feed it. All Mat 1 numbers pinned so an
  automatic pass cannot reshuffle them during the event.

### Permanent fix (to build)
1. **Growing a division updates its schedule parts.** When a bracket gains a
   round (2 → 3/4 entrants adds semis, 5+ adds quarter-finals), add that part
   to its run slot — or have the Scheduler always place a feeder round before
   the round it feeds, whatever the slot says.
2. **A manual draw writes its byes as byes.** Any first-round bout with one
   competitor and no feeder is marked won-by-bye and propagated the moment the
   draw is saved (the same `advanceByes()` the automatic draw already runs).
3. **"Renumber unplayed bouts" for a running event.** The organiser's button
   should, once bouts are fought, keep played numbers and renumber only the
   unplayed ones (feeders first, byes unnumbered) instead of refusing.
4. **A guard on the running order**: warn the organiser when any bout is
   numbered before a bout that feeds it, or a bye carries a number.
5. **Duplicate entries across divisions**: Abdulkarim Abdulla and Nasser
   Mohamed are in both Group Q and Group T (14-15y, 77.6kg); Mohamed Moosa
   Husain is in Group M and Group Q. The desk should flag a person entered
   twice in the same discipline.
6. Orphan run slots with no division (ids 137, 139 on event 105) should be
   cleaned up when their division is deleted.

---

## 2. Deleting divisions leaves "Untitled part" rows on the schedule

**Found:** 17 Oct 2026, Bahrain Karate Premier League 2026 (Version10 - Phase 1)
(club event 104, federation event `81b7c48f-…`), live, on the federation
Schedule page.

### What happened
The day's running order showed a long list of **"Untitled part"** rows: 37 of
them, under the "Mornning Session" group. None of them could be named, timed
or matched to a division, and the real 24 divisions (108 bouts) were not on the
schedule at all.

### Why
- The running order was built on 14 Oct (`event_run_slots`, kind `division`,
  each pointing at a division by `category_id`).
- The organiser then used **"Delete every division" → "Auto-place"**
  (`DivisionReset::deleteAll()` / `autoPlace()`) on 15 Oct (three times) and
  16 Oct 05:27. The activity log shows the pairs. The fresh divisions got new
  ids (977–1024).
- `deleteAll()` deletes divisions with a **bulk query**
  (`EventCategory::where('event_id', …)->delete()`). It removes bouts and
  releases entries, but **never touches `event_run_slots`**. The FK is
  `ON DELETE SET NULL`, so every slot survived with `category_id = NULL`.
- `EventRunSlot::isDivision()` needs a `category_id`, so the slot falls through
  to the block branch of `displayName()`. It has no `label`, so it renders as
  `run_slot_untitled` ("Untitled part").
- Auto-place creates divisions but does not schedule them, so the new ones
  were never added to the day.
- **Same gap in two other paths:** `DivisionMerge` deletes the emptied source
  divisions the same way (`DivisionMerge.php` ~541), and
  `ImportKarateTrials::clearEvent()` too. Item 1.6 above (orphan slots 137, 139
  on event 105) is the same bug.
- Harmless to numbering: the `Scheduler` reads only slots with a
  `category_id` (`whereNotNull`), so orphans never changed a bout number. It is
  a display and planning defect, not a data one.

### Done on the day (by hand, backup `db-20261017-052025`)
- Deleted the 37 slots on event 104 matching `kind='division' AND
  category_id IS NULL AND label IS NULL`. No bout, division, break or group was
  touched, and no positions changed. The removed rows were saved as SQL INSERTs
  for instant restore.

### Permanent fix (to build)
1. **Delete a division's slots with it.** In `DivisionReset::deleteAll()`,
   `DivisionMerge` and `ImportKarateTrials::clearEvent()`, delete
   `EventRunSlot::where('category_id', …)` in the same transaction. Better
   still, do it once in an `EventCategory` `deleting` hook plus an explicit
   call in every bulk path, because bulk deletes fire no model events.
2. **A merge should hand its slot over, not drop it.** When source divisions
   merge into a target, re-point or remove their slots and keep the target's
   place on the day. Never leave two slots running the same people.
3. **Auto-place after "Delete every division" should rebuild the day** (or
   offer to): put the new divisions where the old ones stood, so the organiser
   is not left with an empty schedule plus orphans.
4. **Never render an orphan as "Untitled part".** A `division` slot whose
   division is gone should either be skipped or say "Deleted division —
   remove", with a one-tap remove on the board.
5. **One-off cleanup command** (dry run by default, `--apply` after a verified
   backup) to remove orphan division slots on every event. Check events 104 and
   105 first.

---

## 3. Byes left undecided after the day was arranged, and a lopsided bracket

**Found:** 17 Oct 2026, Bahrain Karate Premier League 2026 (club event 104), Mat 1,
just before the first bout, after the organiser arranged the day.

### What happened
- **11 byes were never advanced.** First-round bouts with one athlete and an
  empty corner nobody could ever fill (e.g. #1 Serah Elizabeth Thomas, #2 غلا
  حسام عيد, #12, #15, #23, #44, #45, #54, #75, #76, #85) were still `upcoming`
  with a number, and their athlete was not in the next round. Calling the
  numbers in order would have called bouts with nobody in the other corner.
  17 other byes in the same event *had* been advanced correctly.
- **Division 1002 (Under 14 Years Kumite Female -55 kg) was lopsided.** All 4
  athletes were in the top half of an 8-slot bracket (QF #65, #66). QF #67, #68
  and SF #70 had nobody and never would, and the final #71 could only ever have
  one athlete in it.

### Why — what is proven
- The automatic draw (`DrawEngine::build()` writes `winner` + `won_by_bye` for a
  one-sided first-round bout) and Arrange mode (`Arrangement::reflow()`
  resolves byes and `propagate()`s them) both handle byes correctly. So these
  byes were made **after** the draw: something emptied a corner later and did
  not re-derive the bracket.
- `PersonalEventController::updateDivisionMembers()` (the desk division editor)
  empties a leaving athlete's corner with a bulk `update()` and relies on
  `onEntrantsChanged()` to repair the bracket afterwards. That repair depends
  on the division's `draw_state` and on the event not being started. **Which
  exact path left these 11 behind is not traced yet.** Start with the desk
  division editor and the federation division manager (`div-manage.js`), then
  `Transfer`, `DivisionMerge` and withdrawals.
- No path checks the *shape* of a bracket. Nothing stopped an 8-slot draw with
  every athlete in one half (1002), which produces dead bouts and a final with
  no opponent.
- The day board's own renumbering (`RunOrder::renumber()`) does not resolve
  byes. The Scheduler leaves undecided one-sided bouts numbered, because only
  a DECIDED bye loses its number.

### Done on the day (by hand, backup `db-20261017-060201`)
- Division 1002 re-seeded inside its own bracket, one athlete per
  quarter-final: SF #57 Prisha Praveen v مريم صالح السميح, SF #58 Raima Manoj
  Krishna v Naira Fathima Eranhikkal, final #59. The organiser chose this. No
  other division's draw was touched.
- The 15 one-sided first-round bouts (11 + the 4 from 1002) resolved the
  engine's way (winner + `won_by_bye`, status done, then `propagate()`).
- `Scheduler::scheduleAndNumber()` re-run from the day's running order: 76
  bouts, #1–#76, no gaps, no bout numbered before a bout that feeds it, no
  decided bout numbered. No bout had been fought, so no result was affected.
  Brackets, walls and mats were told to re-read.
- The event's bouts as they were before are saved as REPLACE statements
  (`event104-matches-before-byes.sql`, session scratchpad).

### Permanent fix (to build)
1. **One "re-derive" after every change to who is in a bracket**: resolve
   one-sided first-round bouts as byes and propagate them, wherever the change
   came from (desk editor, federation manager, transfer, merge, withdrawal,
   enrolment). Not something each path has to remember. `Arrangement::reflow()`
   is the shape; make it the single door. Same lesson as 1.2.
2. **Renumbering resolves byes first.** "Renumber" on the day board, and every
   `scheduleAndNumber()`, should advance any undecided bye before numbering,
   so a number is never given to a bout nobody will fight.
3. **A bracket-shape guard.** Warn (or refuse) when a draw leaves a whole
   quarter or half empty: an empty bout that feeds another empty bout, or a
   final that can only have one side. Offer "spread them out" (`FairDraw`).
4. **A pre-start check on the day board**: "N byes not advanced, M empty bouts
   numbered", with a one-tap fix, before the first bout is called.
5. Trace the exact path that left these byes (see *Why*) and add a regression
   test for it.

---

## 4. Medals won at an event never reached the athlete's profile

**Found:** 17 Oct 2026, Bahrain Karate Premier League 2026 (club event 104).
اوس احمد الزهراني won gold in Under 10 Years Kumite Male -30 kg, and his
profile's medal chips still read 0.

### Why
- The profile's medal count (`PeopleController` → `$honourTally`) reads only
  club-awarded achievements and **verified** `tournament_events` with their
  `performance_results`.
- Entering an event already writes a verified, club-recorded tournament row
  (`ProfileHistorySync::syncRegistration`), but **no medal**. A decided podium
  lives only on `event_categories.podium`, and nothing carried it further.
  This affected every athlete at every event, not just him.
- Second trap: the profile row was found again by its **title**, and event 104
  was renamed after entries opened ("Phase 1 )" → "Phase 1)"). All 98 rows had
  the old title, so a title match would have created a duplicate tournament on
  every profile.

### Built on the day (backup `db-20261017-085036`)
- Migration `2026_10_17_070000_link_event_medals_to_profile` (additive,
  nullable): `tournament_events.club_event_id` (which event a row stands for)
  and `performance_results.source_category_id` (which division wrote a medal).
- `ProfileHistorySync::syncPodium()` replaces exactly the medals a division
  wrote before with its podium as it stands now (places 1–3; place 4 is a
  placing, not a medal). A corrected or cleared result corrects the profile,
  and a member's own typed-in medals are never touched. `tournamentFor()`
  finds the row by event id, else by member + day + club-recorded +
  title-ignoring-spacing, stamps the id, and only creates a row when none
  exists. `syncRegistration()` now stamps `club_event_id` on new rows.
- `App\Events\Observers\EventCategoryObserver`, one hook for every podium
  writer (each sport's Advancement, the championship Runner, reverts). It runs
  after commit and is best-effort, so it can never fail a result being
  recorded. It also removes a division's medals when the division is deleted.
- Flag `EVENT_PROFILE_MEDALS` (config `events.profile_medals`), OFF by default,
  switched ON on stage.
- `php artisan takeone:sync-event-medals [--event=] [--write]`, a dry run
  (rolled-back transaction) by default. Run on stage: 60 divisions, 196 medals
  across events 65, 104, 105 and 106. 0 tournament rows created, 191 rows
  linked to their event, and a second pass is a no-op.

### Still to do
1. **Tests** (not written. Running them needs `config:clear` on the live box,
   which was avoided mid-event): podium → medal rows; corrected podium replaces
   only `source_category_id` rows; cleared podium removes them; renamed event
   matches the existing row; hidden event writes nothing; a hand-typed medal
   survives; User A cannot see User B's medals by changing the id.
2. **Bulk deletes skip the observer.** `DivisionReset::deleteAll()`,
   `DivisionMerge` and `ImportKarateTrials::clearEvent()` delete divisions with
   a query, so their medals stay on profiles. Call
   `ProfileHistorySync::forgetPodium()` there, alongside the run-slot cleanup
   in item 2.1.
3. **A member editing a club-recorded tournament** replaces its
   `performance_results` wholesale (`MemberController` ~1413), dropping the
   `source_category_id` marker. A later podium change would then add a second
   medal instead of replacing it. Lock event-derived medals from member
   editing, or keep the marker.
4. **Production**: run the migration, set `EVENT_PROFILE_MEDALS=true`,
   `config:cache`, then `takeone:sync-event-medals` (dry run first, then
   `--write`) after a verified backup.
5. Update `Documentation/MCP.md` if `GetMemberTool` should label event-derived
   medals distinctly from self-reported ones.

---

## 5. Corrections while the event is running: "correction mode"

**Raised:** 17 Oct 2026, by the owner, after events 105 (16 Oct) and 104
(17 Oct).

### The problem
The draw locks once the event starts. That rule was the owner's own order, and
it is right as a default. But on both days real situations needed changes
after bouts had been fought:
- **A wrong winner was declared** at the table. The bout must be reopened and
  fought again, and the result it fed corrected.
- **An urgent change to a bout** that the final standings depend on.
- **An athlete in the wrong division** by human error, discovered on the day
  (see item 6).
- **A competitor needs replacing** in a bout that has not been fought.

Today the only answers are hand-written database repairs during a live event
(see items 1 and 3), which is exactly what should never be needed.

### Proposed design (agreed in principle with the owner, not built)
- **The lock stays on by default.** Corrections are a deliberate mode, never
  an "off" switch.
- **Unlock per division, with a reason** ("wrong winner declared"). Organiser
  and appointed officials only; a scoring tablet never. It relocks by itself
  after a few minutes.
- **Actions:**
  1. *Remove a winner* → the bout reopens as `upcoming` and is fought again.
  2. *Change the winner* → swap, then `propagate()`.
  3. *Replace or swap a competitor* in a bout not yet fought.
  4. *Move an athlete to the correct division* (item 6).
- **Show the knock-on effect before saving.** If the wrong winner has
  already fought later, list every bout that depends on that result, and offer
  "reset that chain too" or cancel. Never silently overwrite a result fought
  later. (`Advancement::propagate()` already voids downstream results; the
  preview and the confirmation are what is missing.)
- **Everything downstream follows automatically:** bracket, walls, mats,
  podium, profile medals (item 4 already does this through
  `EventCategoryObserver`). Certificates already issued are flagged for
  re-issue or revoke.
- **Bout numbers stay put.** Fought numbers are kept; only unfought bouts are
  renumbered when new ones appear (same as item 1.3).
- **Audit and undo:** every correction is logged (who, when, before/after,
  reason) and reversible from the log.
- **Build order:** remove/change a winner → move an athlete to the correct
  division → replace a competitor.

---

## 6. An athlete in the wrong division, found on the day

**Raised:** 17 Oct 2026, by the owner.

### The problem
Athletes are found in the wrong bracket (wrong age or weight by human error)
only on competition day, after the draw has locked. The existing
move/copy-between-divisions tool (`App\Events\Support\Tournament\Transfer`,
flag `EVENT_DRAW_TRANSFER`) works only before the event starts.

### Proposed design (part of correction mode, item 5)
- **The division they leave:**
  - Not fought yet → their opponent advances as a bye, or a waiting
    bench entrant takes the slot.
  - Fought and still in → their results are voided, and the athlete they last
    beat is reinstated in their place. The preview lists every bout reopened.
  - Already knocked out → they simply leave; fought results stand.
- **The division they join:**
  - Draw not started → placed and re-laid as today.
  - Draw running → they take an empty first-round slot, turning a bye into a
    real bout. With no free slot, a play-in bout against the bye-holder.
    Fought bouts are never disturbed.
- Only unfought bouts are renumbered; the table sees new bouts at once.
  Podium, medals and certificates follow.
- Extend `Transfer`, do not write a second mover (*Shared stays shared*). It
  must still obey `OneDivisionRule`.

---

## 7. "One division per person" is on, yet people are in two divisions

**Found:** 17 Oct 2026. Events 104 and 105 both have
`one_division_per_person = 1`.

### What happened
- Event 104: **10 athletes** hold entries in two divisions (e.g. ISHA SHAJI:
  U14 Female -50 kg *and* -35 kg; MUHAMMED AHZAAN: Cadets -63 kg *and* U10
  -45 kg). Event 105: **6 athletes** (e.g. Nasser Mohamed and Abdulkarim
  Abdulla: Group Q and Group T; Mohamed Moosa Husain: Group M and Group Q).
- On 104 each of them is **drawn in only one bracket**. The second entry is
  a leftover booking that nobody drew. Entry lists, division counts and the
  participants PDF still show them twice.

### Why
- `OneDivisionRule` stops a person standing in a SECOND bracket. By its own
  design, "an entry waiting in a basket is a booking, not a place", so it never
  looks at entries, only bouts.
- Switching the rule ON blocks new placements through the screens that ask
  `refusal()`. It does not detect or clean up the duplicate entries already
  there.
- Entries can still be created in a second division (auto-place, the desk
  division editor, imports) without asking the rule, because a booking is
  allowed.

### Proposed fix
1. **When the switch goes on, show "people in two divisions"**: the division
   they are drawn in, the other one, and which one their age/weight fits (the
   rule book, `CategorySystems`).
2. **Resolve per row:** keep the drawn division and release the other entry,
   or move them (item 6). The organiser decides; sometimes the second
   division is the right one.
3. **From then on, automatically:** while the rule is on, placing someone in a
   bracket releases their entries in other divisions. They are released
   (`category_id = NULL`), never deleted, and logged. Fees for a released entry
   are flagged, never charged twice (see `Documentation/EVENTS-ENTRY-BILLING.md`).
4. **Lists follow the rule:** entry lists, division counts, the participants
   PDF, certificates (participation) and the budget count a person once.
5. Clean up the existing leftovers on events 104 and 105 with that tool, not
   by hand.

---

## 8. Second entries came back as new divisions during the event

**Found:** 17 Oct 2026, Bahrain Karate Premier League 2026 (club event 104),
while the competition was running.

### What happened
- Five divisions appeared that nobody created by hand: Cadets Kumite Male
  +70 kg, U10 Male -45 kg, U12 Male -50 kg, U12 Male -55 kg and U14 Male
  +65 kg. They were created at **06:42 UTC**, and 116 entries were bulk-updated
  at **07:05 UTC**. No change made in this session happened at either time.
- They held the **second entries** clubs filed on 13 Oct for people entered
  in two weight classes (`category_source = auto`). Together with U12 Female
  +50 kg and U14 Female -35 kg (created 16 Oct), there were 7 such divisions,
  none with a draw. All 10 people involved were drawn in their first division,
  and 7 had already fought there.

### Why (most likely; there is no request log on this box to prove it)
- `CategorySystems::placeCreating()` places an unplaced entry by the rule book
  and **creates any division it needs**. It is called on its own by
  `FederationAthleteEditor` (saving an athlete in the federation app),
  `FederationPlatformEntries`, `PlatformClubEntry` and `DivisionReset`, at any
  time, including while the event runs.
- Nothing stops it for an athlete who is already drawn elsewhere when
  "one division per person" is on (see item 7), and nothing stops it creating
  divisions once the event has started.

### Done on the day (owner's instruction; backup `db-20261017-130406`)
- **The owner's rule:** no second entries. Delete the divisions that show only
  one entrant, keep the participants, and keep the pairs.
- **Deleted 4 single-entrant divisions:** U12 F +50 kg (1023), U14 F -35 kg
  (1024), U12 M -55 kg (1031) and U10 M -45 kg (1034). This was the same as
  the app's own delete-division action, plus their two day-schedule rows
  (run slots 169, 170) so no "Untitled part" appeared (item 2).
- The four entries (3002, 3004, 3016, 3017) were **kept**, with no division.
  Their athletes stay in the divisions they are drawn in.
- **Kept as instructed:** the three pairs, Cadets M +70 kg (LEE ANEL ALBONIA
  MANALO, MUHAMMED HUMRAAZ), U14 M +65 kg (Rudransh Dash, محمد عبدالله محمد
  العماري) and U12 M -50 kg (اسماعيل عبدالوهاب حمود, عبدالرحمن جبير حمد).
- Re-checked inside the transaction (one entrant, no bouts) before deleting.
  108 bouts before and after; every other schedule row unchanged. Restore
  file: `restore-four-divisions.sql` (session scratchpad).

### Permanent fix (to build)
1. **No automatic division creation once the event is running.** Once
   `started_at` is set, or any bout is fought, `placeCreating()` places into
   existing divisions only, or leaves the entry unplaced, and never calls
   `ensureDivision()`.
2. **Respect "one division per person" in placement.** While the rule is on,
   `placeCreating()` and every caller must skip an entry whose person is
   already drawn elsewhere (`OneDivisionRule::placedElsewhere`).
3. **Saving an athlete must not re-place their entries.** `FederationAthleteEditor`
   should re-place only when the edit changes what placement reads (age,
   weight, gender), and only for entries that are unplaced and not
   second entries.
4. **Log every automatic division creation** (who, which entry, which path).
   Today there was no way to tell what created these five.
5. **The released second entries can still come back** through any of these
   paths until 1–3 are built. The two-divisions tool in item 7 should offer
   "remove the second entry" as well as "release".

---

## Update, 17 Oct 2026: built during the event

- **Item 5 (correction mode), first step built:** `ResultCorrection` behind
  `EVENT_RESULT_CORRECTION` (ON on stage).
  - Removing or changing a winner from the bracket is refused while a later
    bout it feeds was fought or started; the message names those bouts,
    latest first.
  - Clearing a result resets the score so the bout is fought again, and keeps
    the old result in the officiating log (`result_cleared` /
    `result_changed`).
  - The podium is re-derived, and profile medals follow.
  - The tablet's draw door is covered by the same check.
- **Item 6 (wrong division), built:** `LiveMove` behind `EVENT_LIVE_MOVE`
  (ON on stage). It is in the organiser's athlete sheet as "Move to the
  correct division".
  - It plans both sides first and writes once, editing single corners only.
  - It never touches a fought bout, a bout loaded on a mat, numbers or the draw.
  - It refuses with a reason when the move is not safe. Play-in bouts are not
    built yet.
- **Unload bout** button added to the Shotokan scoring table (the engine's
  existing `clear` command, two taps). Other karate styles and the default
  karate table still lack it.
- **Likely cause of item 3's leftover byes:** "Enrol in groups"
  (`updateDivisionMembers`) blanks a drawn corner with a bulk update and
  nothing repairs the bracket mid-event. Use "Move to the correct division"
  during an event instead.
- **Not yet done:** automated tests for all of today's changes (need
  `config:clear` on the live box, so after the event).
