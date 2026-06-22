# Per-Sport Competition Event Types — Design Spec

**Status:** Draft / agreed design. No code written yet.
**Started:** 2026-06-20
**Scope of this doc:** Taekwondo championship, built on a shared "combat bracket" engine that other combat sports plug into later.

---

## 1. Concept

Each **sport** is its own `event_type` on `ClubEvent`, with its **own create/edit form**, its **own stored data**, and its **own bracket/standings layout**. It is *not* one generic "sports event" with a type dropdown.

Under the hood, sports that share a competitive shape **share one engine**:

| Engine family | Sports | Competitor | Engine output |
|---|---|---|---|
| **Combat / individual bracket** | Taekwondo *(first)*, Karate, Judo, BJJ, Boxing, Wrestling | individual member | divisions → seeded draw + byes → single-elim + **repechage** → `{gold, silver, 2×bronze}` |
| **Team league/tournament** *(deferred)* | Football, Basketball, Volleyball… | team | round-robin standings + fixtures, or knockout |

**Decision:** Shared engine, per-sport forms. Build **Taekwondo end-to-end first**; other combat sports plug in by adding a form + weight tables + scoring config, **zero engine changes**. Team sports are out of scope for now.

---

## 2. Event-type registry

`ClubEvent.event_type` is the discriminator. Each value maps to a definition declaring:

- **label / icon / sport**
- **form schema** — fields the create/edit form shows + validates
- **storage** — columns / JSON keys / sub-tables written
- **engine** — `combat_bracket` | `team_league` | `judged` | `none`
- **views** — which mobile + desktop Blade files render it

Adding a sport = one definition + its form + its view. Others untouched.

---

## 3. Taekwondo championship — full spec

### 3.1 Locked decisions
- **Kyorugi (sparring) brackets ONLY** — no Poomsae/judged forms for now (can be added later as a `judged` engine on the same container).
- **All competitors are platform members** (`user_id`). No free-text athletes. `EventMatch.a_*/b_*` are a display cache of the member's profile (name, country, club, photo).
- **Open self-registration** up to division capacity — no owner approval.
- **Self-declared weight** at registration (trust-based, no weigh-in) → auto-routes member to the matching weight category. Age + gender pulled from profile.
- **Fully-random draw** (no platform ranking yet) + byes to next power of 2.
- **Scope** set by owner at creation — only gates eligibility:
  `internal → open/inter-club → nationwide → regional → worldwide`.

### 3.2 Entities

**Championship — `ClubEvent`** (`event_type = martial_arts_championship`, `sport = taekwondo`)
- dates, venue, `courts` (number of mats), fees (participant/spectator), `scope`.

**Division — `EventCategory`** (one row per age × gender × weight)
- `age_division` (cadet / junior / senior / U21), `gender`
- `weight_min`, `weight_max`, `weight_label` (e.g. `−58`, `+87`) — decimal-exact (`−58` = ≤ 58.0)
- `capacity`, `status` (open → drawn → in-progress → done)
- `podium` = `{ gold, silver, bronze: [a, b] }`
- = a self-contained sub-tournament.

**Registration — `ClubEventRegistration`**
- `user_id` → division (`category_id`), `role` (participant/spectator), `paid`, self-declared `weight` (in `meta`).
- Self-registration routes to division by (age, gender, declared weight).

**Bout — `EventMatch`** (within a division)
- two `user_id` slots + `a_*`/`b_*` display cache, `round` (R16/QF/SF/F), `bracket` (`main` | `repechage`), round scores, `winner`, `court`, `scheduled_time`.

### 3.3 WT weight tables (Kyorugi, kg)

**Senior (17+), 8 each**
- Men: −54, −58, −63, −68, −74, −80, −87, +87
- Women: −46, −49, −53, −57, −62, −67, −73, +73

**Olympic (4 each):** Men −58/−68/−80/+80 · Women −49/−57/−67/+67

**Junior (15–17), 8 each**
- Boys: −45, −48, −51, −55, −59, −63, −68, +68
- Girls: −42, −44, −46, −49, −52, −55, −59, +59

**Cadet (12–14), 8 each**
- Boys: −33, −37, −41, −45, −49, −53, −57, +57
- Girls: −29, −33, −37, −41, −44, −47, −51, +51

> Age cutoffs are by **year of birth**, not exact birthday.

### 3.4 Match (bout) structure
- Best-of-3 rounds × 2 min (1 min rest); win 2 rounds.
- PSS electronic points: body kick 2, head kick 3, spin bonus +2, punch 1; penalty = **Gam-jeom** (point to opponent).
- Tie → **Golden Point** sudden-death, then superiority.

---

## 4. The three engines (shared, sport-agnostic)

1. **Auto-draw** — on "Generate Draw": lock registrations, **shuffle fully random**, pad to next power of 2 with byes, build the main single-elim `EventMatch` tree.
2. **Repechage** — once semifinal losers are known, auto-build the two-bronze path (SF losers seeded straight to the two bronze matches, opposite sides; other finalists' victims fight up to meet them). Settle `podium`.
3. **Scheduler** — distribute each division's bouts across `courts` and days.

---

## 5. UX flows

**Owner:** create championship (sport, scope, dates, courts, fees) → define divisions (or auto-generate from WT weight tables for the chosen age/gender set) → registrations come in → "Generate Draw" per division → record bout results → repechage + podium auto-settle → schedule across courts/days.

**Member:** browse championship (visible if eligible by scope) → self-register, enter weight → auto-placed in division → see own bracket, next bout, court/time → **live results push over MQTT** → final podium on profile.

---

## 6. Implementation notes / project rules
- **Separate mobile + desktop Blade files** (strict rule) — the bracket view is the meaty one.
- **MQTT** for all live updates (results, advancing, podium) — no polling/reload.
- **No toasts** for this feature — writes succeed **silently** and update the UI in place (patch the DOM / re-render the affected section from the JSON response). No success/error toast popups. Confirmations still use `window.confirmAction` where a destructive action needs a guard.
- Reuse the Blade component library (cards, modals, stat-cards, dropdowns).
- Throttle middleware on write routes.

---

## 7. Proposed build order
0. ✅ **Scope field (DONE 2026-06-20)** — `scope` column on `club_events`; `config/event_schema.php` `scopes` catalog (internal/inter_club/nationwide/regional/worldwide); picker card in `personal/event-create.blade.php`; `PersonalEventController` stores it, broadens `index()` discovery and gates `register`/`ticket`/visibility via `isEligible()` (host-club members always; wider scopes admit other clubs' members; nationwide/regional = member belongs to a club in the host club's country). **Caveat:** `regional` currently mirrors `nationwide` until a region taxonomy exists; `inter_club`≈`worldwide` in reach (open platform-wide) but kept distinct in intent. Admin desktop club-events form (`ClubEventController`/`EventRequest`) NOT yet given scope — parity TODO.
1. ✅ **Weight capture + auto-route (DONE 2026-06-20)** — `weight` column on `club_event_registrations`; TKD join captures self-declared weight (bottom-sheet, prefilled from latest health record); `register()` classifies via `classifyTaekwondo(gender, age, weight)` and find-or-creates the matching division (`routeToTaekwondoDivision`), setting `category_id`. Participant display classifies through the helper (gender-correct; ignores stored category). Unpaid joiners tagged "Pending payment".
2. ✅ **Auto-draw engine (DONE 2026-06-20)** — `EventCategory.draw_state` (provisional|final) + `draw_count`; `EventMatch.a_provisional/b_provisional`. `buildDraw()` spreads entrants by stable hash + standard seed positions (`seedOrder`), pads to power of 2 with byes (walkovers auto-advance), generates full round tree. `ensureDraws()` runs lazily on bracket open: **before start** → provisional draw including unpaid (flagged "imaginary"), rebuilt when entrant set changes; **at/after start** (`hasStarted`) → rebuilt paid-only and locked `final`. Hand-built brackets (no draw_state but existing matches) never touched. Bracket view renders provisional entrants ghosted + "unpaid" tag + a provisional-draw banner. Scoped to `sport === 'taekwondo'`.
3. Migrations remaining: structured weight bounds on `EventCategory` (gender/age/min/max as columns vs name-encoded), decimal scores on `EventMatch`, `courts` on `ClubEvent`.
2. Event-type registry + Taekwondo definition.
3. Owner: create championship form + division setup (auto-generate from weight tables).
4. Member: self-registration + auto-routing by weight.
5. Auto-draw engine.
6. Bracket views (desktop + mobile) + result entry.
7. Repechage + podium settlement.
8. Scheduler (courts/days).
9. MQTT live wiring.
10. Generalize: plug in next combat sport (form + weight tables + scoring only).

---

## 8. Other combat sports (engine generalization)

**Headline:** the same engine — divisions (age × gender × weight) → seeded/random draw + byes → single-elim + **repechage two-bronze** → podium — holds for Judo, Karate, Boxing (amateur) and Wrestling with **only the weight tables and scoring config changing**. **BJJ is the one that adds structure** (a belt-rank axis + an "absolute/open-weight" division + points-based match scoring). So each sport's plug-in = `{ weight_tables, scoring_rules, extra_division_axes?, has_absolute? }`.

### Judo (IJF) — same skeleton, repechage 2 bronzes
- **Men (7):** −60, −66, −73, −81, −90, −100, +100
- **Women (7):** −48, −52, −57, −63, −70, −78, +78
- **Scoring:** Ippon = instant win; two Waza-ari = Ippon. Penalties = Shido.
- **Format:** single-elim + repechage (quarterfinal-loser repechage) → two bronzes. Engine fits as-is.

### Karate (WKF Kumite) — same skeleton
- **Men (5):** −60, −67, −75, −84, +84
- **Women (5):** −50, −55, −61, −68, +68
- **Scoring:** Ippon (3) / Waza-ari (2) / Yuko (1); bouts ~3 min. Elimination + repechage → two bronzes. Engine fits.

### Wrestling (UWW Freestyle) — same skeleton, Nordic repechage
- **Men (10):** 57, 61, 65, 70, 74, 79, 86, 92, 97, 125
- **Women (10):** 50, 53, 55, 57, 59, 62, 65, 68, 72, 76
- **Scoring:** points (takedown/exposure/etc.), win by fall/tech-superiority/points.
- **Format:** single-elim; only those who lost to the **two finalists** enter repechage → two bronzes. Same engine.

### Boxing (amateur/Olympic) — same skeleton, NO box-off
- Amateur divisions (e.g. men up to ~92 kg / +92 super-heavy; lighter classes down to ~46–48 kg). Pro boxing is single bouts, **not** a club-bracket use case — model amateur only.
- **Format:** single-elim; **both semifinal losers take bronze** (no bronze match). Engine fits — just suppress the repechage/bronze-match step and award both SF losers bronze.

### BJJ (IBJJF Gi) — needs the extra axes
- Division = **belt × age × weight** (belt is an extra required axis). Plus an **Absolute / Open-weight** division per belt.
- **Men's adult (gi), 9:** Rooster −57.5, Light-feather −64, Feather −70, Light −76, Middle −82.3, Medium-heavy −88.3, Heavy −94.3, Super-heavy −100.5, Ultra-heavy (no max). *(weighed in the gi.)*
- **Women's adult (gi), 8:** Rooster −48.5, Light-feather −53.5, Feather −58.5, Light −64, Middle −69, Medium-heavy −74, Heavy −79.3, Super-heavy (no max).
- **Scoring:** points (mount 4, back 4, guard pass 3, sweep/takedown 2, knee-on-belly 2) + submissions/advantages; small brackets often single-elim with a 3rd-place match rather than full repechage.
- **Engine impact:** add an optional `rank/belt` division axis + an `is_absolute` flag on `EventCategory`; otherwise reuses the bracket engine.

### Generalization summary
| Sport | Weight classes (M/F) | Extra axis | Repechage / bronze | Match scoring |
|---|---|---|---|---|
| Taekwondo | 8 / 8 | — | repechage, 2 bronze | PSS points, 3×2min |
| Judo | 7 / 7 | — | repechage, 2 bronze | Ippon/Waza-ari |
| Karate | 5 / 5 | — | repechage, 2 bronze | Ippon/Waza-ari/Yuko |
| Wrestling | 10 / 10 | — | Nordic repechage, 2 bronze | points |
| Boxing (am.) | ~10 / varies | — | no box-off, 2 bronze (both SF losers) | 10-point must |
| BJJ | 9 / 8 | **belt + absolute** | 3rd-place match (small) | points/submissions |

So the engine needs three configurable knobs to cover all six: **(a)** per-sport weight tables, **(b)** bronze rule (`repechage` | `both_sf_losers` | `third_place_match`), **(c)** optional extra division axes (belt, absolute) for BJJ. Scoring is per-sport form/display only — it doesn't affect bracket progression.

### Sources
- [Taekwondo weight classes (WT)](https://en.wikipedia.org/wiki/Taekwondo_weight_classes) · [WT 2012 Olympic bracket + repechage](https://en.wikipedia.org/wiki/Taekwondo_at_the_2012_Summer_Olympics_%E2%80%93_Men%27s_58_kg)
- [Judo weight divisions & scoring](https://en.wikipedia.org/wiki/Judo)
- [WKF Kumite weight categories](https://en.wikipedia.org/wiki/Karate_World_Championships) · [WKF rules](https://www.wkf.net/sport-modalities-rules)
- [UWW wrestling weight classes](https://www.flowrestling.org/articles/5968209-uww-reveals-10-weight-classes)
- [Boxing weight classes](https://en.wikipedia.org/wiki/Boxing_weight_classes)
- [IBJJF BJJ weight classes](https://www.kingz.com/blogs/news/the-ultimate-guide-to-bjj-weight-classes)
