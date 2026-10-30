# TAKEONE MCP Server

A single, general-purpose **Model Context Protocol** server that exposes the
TAKEONE platform to external systems (Claude Desktop/Code, n8n, other services,
your own scripts). It is built on the official `laravel/mcp` package and plugs
directly into the app's models, Sanctum auth, roles and policies.

**Core guarantee:** every tool runs **AS the authenticated user** and enforces
exactly the same tenant scope and authorization the web UI does
(super-admin → club owner → approved chain owner → club-admin → guardian/self; the chain owner was added 2026-10-03 to match the web). The MCP can never read
or write anything the acting user could not do in the app.

---

## Transports

| Transport | Endpoint / command | Auth | Use for |
|-----------|--------------------|------|---------|
| **HTTP** | `POST /mcp` | Sanctum bearer token | Remote systems — Claude web/desktop, n8n, other servers |
| **stdio** | `php artisan mcp:start takeone` | acts as `MCP_STDIO_USER_ID` | A local operator process on the server itself |

Registered in `routes/ai.php`. The whole server is toggled by `MCP_ENABLED`.

---

## Connecting over HTTP (remote)

1. **Issue a token** for the user the integration should act as:
   ```bash
   php artisan mcp:token owner@club.com --name="n8n integration"
   # prints a one-time bearer token
   ```
   List / revoke:
   ```bash
   php artisan mcp:token owner@club.com --list
   php artisan mcp:token owner@club.com --revoke=<tokenId>
   ```

2. **Point your MCP client** at the endpoint with the token. Example for a
   client that takes a JSON server config:
   ```json
   {
     "mcpServers": {
       "takeone": {
         "url": "https://takeone.bh/mcp",
         "headers": { "Authorization": "Bearer 1|xxxxxxxx..." }
       }
     }
   }
   ```

The bearer token identifies the user; the tools inherit that user's roles and
club scope. Give an integration its own dedicated user for a clean audit trail.

## Connecting over stdio (local)

Set `MCP_STDIO_USER_ID` in `.env` (the user the local server acts as), then run:
```bash
php artisan mcp:start takeone
```
Or point a local client (Claude Desktop) at `php artisan mcp:start takeone`. If
`MCP_STDIO_USER_ID` is unset, stdio tools refuse with an "unauthenticated" error.

---

## Tools

| Tool | Kind | Access | What it does |
|------|------|--------|--------------|
| `who_am_i` | read | any | Acting user's identity, roles, accessible clubs. **Call first.** |
| `list_clubs` | read | scoped | Clubs the user can access (search + paginate) |
| `get_club` | read | scoped | Full details + counts for one club (id or slug) |
| `list_members` | read | scoped | Members of a club (search + paginate) |
| `get_member` | read | gated | One member profile (uuid or id) — super-admin/self/guardian/club-admin only. Includes `medals` (club-awarded + club-**verified** tournament medals), `skills` (provenance-backed, **verified** only — activity/club/since/proficiency; never self-reported/pending), `certifications` (self-managed) and `work_history` (self-managed, current roles first) |
| `club_financials` | read | admin | Income, expenses, net, cash-to-collect for a club — scoped to the club's current Test/Live mode |
| `list_club_owners` | read | admin | A club's owners with shares (percent), ownership history, and any open ownership proposal with its tally. Only while `PARTIES_ENABLED` |
| `propose_club_ownership` | write | owner | Set a club's owners and shares (must total 100%). The majority owner's change applies at once; anyone else's goes to a share-weighted vote (more than 50% passes). Owners and super-admins only |
| `vote_club_ownership` | write | owner | Vote for/against an open ownership proposal; only the club's owners vote, weighted by share |
| `club_staff` | read | admin | A club's staff (instructors, secretaries, operators, cleaners, ...) with staff type, compensation, and active status. Read-only — hiring/terminating staff is not exposed over MCP |
| `search_people` | read | any | Club-scoped discoverable-member search (confirmed club-mates of the acting user only, never platform-wide) — **safe public fields only** |
| `record_transaction` | write | admin | Log a manual income/expense for a club |
| `notify_member` | write | admin/guardian | Send an in-app + live (MQTT) notification |
| `enroll_members` | write | admin | Batch-enroll active members into a package, marked as already paid |
| `list_events` | read | scoped | Discover events the acting user may see, and get the `uuid` every other event tool needs. Defaults to `state=open` — not started yet **plus** running right now; also `live`, `upcoming`, `past`, `all`. Searchable by title/club/location/sport. Archived + cancelled events are never returned; visibility mirrors `App\Events\Support\EventAccess` exactly, so it can never list an event whose detail page would 403. Each event carries its `timezone` (2026-09-29): every `date`/`start_time`/`end_time` it returns is wall-clock in that zone |
| `list_event_people` | read | scoped | An event's competitors and the clubs behind them — name, division, weight class, country, club, and each club's squad size. Reading only for EVERYONE, organisers included: no registration ids, weights, payment or weigh-in state, and no spectators. Built by the same `App\Events\Support\RosterPeople` as the "Who's joined" screen so the two cannot drift. Unseeable events answer "Event not found" |
| `list_event_documents` | read | scoped | Files attached to an event (rulebook, entry form, schedule) — title, type, size and a download link. Metadata only: the bytes are served by the web route, which re-runs the same `EventAccess::visible` check, and a storage path is never returned. Unseeable events answer "Event not found" rather than confirming the uuid exists |
| `list_event_photos` | read | scoped | The photographs in an event's gallery (EVENT_PHOTOS) — size, upload time, thumbnail and full image URLs. The bytes are served by `events.photos.show`, which re-runs the same visibility check per request; a storage path is never returned. Unseeable events answer "Event not found"; empty while the flag is off |
| `list_event_exhibitions` | read | scoped | An event's side exhibition bouts (EX1, EX2…) — two entrants put on a mat outside the draw from the Karate console's Arcade tab: mat, names, scores, winner, status (running / done / abandoned / linked), `linked_match_id`. For an organiser each finished one also carries `link_candidates` (the draw bouts it could be: `exact` = same pair, undecided · `recorded` = same pair, result already typed in — linkable only when `agrees`, writes the real score, winner unchanged · `seat` = later-round bout waiting on earlier results). Same audience as the console: `EventAccess::canScore`, otherwise "Event not found" |
| `link_event_exhibition` | write | scoped | File a finished exhibition into the draw bout it really was (`App\Events\Support\ExhibitionLink`, same door as the console's Exhibitions tab): result recorded through the event type's `recordOutcome()`, winner advances, exhibition marked `linked`. With `attach_only` any bout may be chosen: the exhibition, its footage and score log are attached, the draw is not changed (one exhibition per bout). Refuses a decided bout with the other winner, a bout holding somebody else, a first-round bye, and a competition not yet started. Organisers only (`EventAccess::canManage`), otherwise "Event not found" |
| `unlink_event_exhibition` | write | scoped | Undo a link made by mistake (`ExhibitionLink::unlink`): an attachment is removed; a replaced score is restored; a filed result is cleared through `recordOutcome()` and seated corners taken out — refused when the winner has already fought the next bout. The exhibition, its clips and score log go back to being an exhibition. Organisers only, otherwise "Event not found" |
| `enter_event_athletes` | write | admin | Enter your club's athletes into an event (omit ids to preview the roster + eligibility). Same gate as self-entry: scope, bans, window, capacity, weight division. `division_ids` names WHICH ACTIVITIES to enter — one entry per activity, since an event may run several (Gi and No-Gi) and each is drawn and paid for separately; an athlete already holding several entries must be given one, or they are refused rather than having an entry silently moved |
| `get_event_bracket` | read | scoped | An event's knockout draw — every division, its rounds, each bout (both competitors, seeds, scores, winner, mat, time), the podium, and entrants not currently placed in the draw. Works for any bracketed event type; visibility mirrors the web screen exactly (`App\Events\Support\EventAccess`) |
| `list_event_videos` | read | scoped | The bouts of an event that were filmed, grouped by division (weight class) — competitors, mat, score, how many camera angles, and the URL to watch. Metadata and links only: the bytes are served by the media routes, which authorise the playlist AND every segment, and a storage path is never returned. Unseeable events answer "Event not found" |
| `get_bout_video` | read | scoped | One bout's footage — every camera angle, the scoring timeline and the coach notes. The timeline is DERIVED (`App\Media\BoutTimeline`): the officiating log replayed against the recording's anchor, so `at_seconds` is seconds into the video and nothing is a hand-typed annotation. Points logged at the same instant collapse into ONE moment carrying the score after the whole exchange. ⚠️ Access is wider than the event's: the two athletes who fought a bout reach their OWN bout whatever the event's scope and after it is archived — but not the event-wide gallery |
| `bout_stats` | read | scoped | One bout's statistics (`App\Media\BoutStats`), read from the mat's own log: score and winner, first score and when, lead changes, comeback, when it was decided, each corner's points by value, penalties, time in the lead, biggest lead, longest run, the bout in thirds, stoppages, every scoring action with its bout-clock time, and `pdf_url` (the bout page's stat-sheet PDF door, which re-checks the session). Same access as `get_bout_video` |
| `share_bout_video` | write | scoped | Make (or check on) a bout as a FILE to send to WhatsApp and other apps (`App\Media\BoutShare`): `kind` = `full` or `highlights`; the score bar is burned into the picture and every point is a chapter. Idempotent — a file already made or being made is reported as it stands; only an idle one is queued (`RenderBoutShare`, `media` queue). Returns `state` (working/ready/failed), `download_url` once ready (the web door, which re-checks the session) and the points written out as `message`. Same access as `get_bout_video`. Off (error) while `MEDIA_SHARE_EXPORT` is false |
| `get_event_translation` | read | scoped | **An event in any language.** Its title, description, location, prize, requirements, divisions and fee lines as the public page would show them — an organiser writes the event once in their own language and `App\Translation` rewrites it into the reader's. Omit `locale` to list which languages the event already has. Anything not translated yet comes back in the source language and is NAMED in `untranslated_fields`, so a caller composing a message knows before it sends one English line inside a Japanese paragraph. **Never starts a translation** — a read that silently costs money is a trap; use `translate_event` |
| `translate_event` | write | organiser | Write an event into one or more languages ahead of anybody asking — the night before a Brazilian squad arrives, so the first of them to open the link waits for nothing. Queues and returns immediately (roughly 10–40s per language); poll `get_event_translation`. Languages already done, and the language the event was written in, are skipped and say so. **The expensive tool**: every accepted locale can start a generation at a paid provider, so it is capped at 12 per call and refused to anyone who does not manage the event. `force` redoes MACHINE rows only |
| `set_event_translation` | write | organiser | Correct one field's wording in one language — the same act as typing into the Languages sheet in the console. Marked `human` and **never overwritten by a later automatic run**, whatever happens to the source text or the provider. This is how a federation's official name for a division is made to stick. The field must be one the event actually publishes (read `get_event_translation` first for the exact keys); an empty value drops the correction and lets the machine's stand again |
| `translation_health` | read | super-admin | **Can the platform translate right now?** Each AI translator's state (`ok`, `unreachable`, `model_missing`, `refused`, `unprobed`), every interface language run and its verdict (running, resumed, stalled, waiting for a translator, needs a person), the translation queue, what the auto-fixer (`translate:doctor`, every 5 min, `TRANSLATION_AUTOFIX`) repaired, and what still needs a person. `fresh=true` re-diagnoses now — probes only; resumes, downloads and notifies nothing |
| `arrange_event_bracket` | write | organiser | Move a competitor within a division's **first round**, or in/out of the draw — the MCP equivalent of dragging on the bracket screen. Organiser only, and refused once the event starts (the draw is final from the first bout). Later rounds are never arrangeable |
| `shuffle_event_division` | write | organiser | Re-draw one division **fairly** — the board's Shuffle button (`App\Events\Support\Tournament\FairDraw`): highest belts seeded apart, teammates kept apart, the rest at random. Replaces hand moves in that division. Organiser only, before the start, never on a division with results |
| `renumber_event_bouts` | write | organiser | Number every bout again from the running order, mat by mat — the **Renumber bouts** button (`App\Events\Support\BoutNumbering`). Hand-set numbers are kept; brackets and hall screens refresh. Refused once a bout has been fought |
| `set_event_one_division` | write | organiser | Switch **one division per person** on or off (`App\Events\Support\OneDivisionRule`, `club_events.one_division_per_person`, default off). On, nobody can be placed in a second division's bracket — `arrange_event_bracket` and `transfer_event_competitor` refuse it too. Turning it on returns `conflicts` — everybody already in two divisions, each with the division they fit (`OneDivisionRule::conflicts`) |
| `transfer_event_competitor` | write | organiser | Take a competitor from one division's draw into **another division** — the split-view drag. `move` takes their entry out of the old division (refused if they have fought there); `copy` makes a second entry, `charge` true = entry price unpaid, false = free entry. Optional first-round corner in the new draw. Behind `EVENT_DRAW_TRANSFER` |
| `set_running_order_group` | write | organiser | Read a day's running-order **groups**, or set how one numbers its bouts: `class` one class at a time, `round` round by round (World Taekwondo), `manual` keeps the hand-set bout order. Each group finishes all its bouts before the next. Not renumbered once anything is fought. Behind `EVENT_SESSION_MODES` |
| `get_event_readiness` | read | officials | The run-day checklist and whether the event has started: each item with who cleared it and when, how many are outstanding, the scheduled vs actual start, and whether the organiser started with items still open (plus the event's `timezone`; `scheduled_start` carries its offset). Restricted to the organiser and appointed officials (`EventAccess::canOfficiate`) — it is the organiser's preparation notes and it names the people who signed each item off, so a competitor gets "Event not found" |
| `list_court_screens` | read | organiser | The hall screens paired to an event: which mat each shows, whether it has reported in recently (`live`), when it was last seen, plus the event's mats and a count of `dark` screens. Organiser only (`EventAccess::canManage`). **Read-only by design** — pairing means reading a code off a wall in the room, and a write tool would turn a deliberately public code into a remotely usable one. The event type answers (`EventType::hallScreens`), so a type with no wall boards reports `supported: false` |
| `get_bjj_scoreboard` | read | scoped | A Brazilian jiu-jitsu mat's live state: the two corners (blue and white — a BJJ mat has no red corner), the running clock reported as `remaining`/`running`/`as_of` so the reader derives the live figure rather than being handed a stale one, the match state, and the score as **three separate counters** — points, advantages, penalties — plus who leads and which counter decided it. Advantages are never folded into points. One mat or all of them. Visibility mirrors the web screen (`EventAccess::visible`); the corner is reduced to name/club/country, so a competitor's photo path never travels with a score |
| `list_bjj_match_events` | read | organiser | One BJJ match's officiating log, newest first: every action, the corner it went to, what it was worth, the position it came from, and any correction. **The score is derived by replaying this**, and the tool returns that replay alongside the entries so the log and the score can never tell two different stories. Corrections appear as reversals naming the entry they undo, with a reason — nothing is erased, so a reversed entry is still listed and flagged. Requires `EventAccess::canScore` or `canManage`, NOT mere visibility, because every entry names the official who made it |
| `list_activity_catalog` | read | any | The global activity directory — shared platform-wide catalog of activities (EN/AR) any club can reuse. Read-only, non-sensitive (search + paginate). Each entry includes its curated `videos` (validated YouTube `{id,title,source}`) |
| `verify_achievement` | write | admin | Confirm/reject a member self-claimed record that names your club — a tournament medal (`type: achievement`) or an acquired skill (`type: skill`), bound by uuid. Only an admin/owner of the named club may act — mirrors the web verification queue (medals + skills) |
| `add_certification` | write | self/guardian | Add a self-managed certification/qualification (name, issuer, dates, credential id/url) to a member — super-admin/self/guardian only (not club-admins) |
| `add_work_history` | write | self/guardian | Add a self-managed work/coaching history entry (role, org, dates, type) to a member; null end date = current — super-admin/self/guardian only |
| `manage_member_photo` | write | self/guardian | A profile holds several pictures; one is the avatar. Promote an existing picture (`action: set_avatar`) or delete one (`action: delete`, which purges the file and hands the avatar to the next picture), addressed by photo uuid and scoped to that member — super-admin/self/guardian only. **Uploading is deliberately not exposed** (raw image bytes belong to the app's own validated uploader); read the list from `get_member.photos` |
| `get_event_featuring` | read | event manager | Where an event's paid featuring stands (featured / waiting / rejected + reason) and the current fee. **Sending the proof is not exposed** (it is a file upload, done in the event console) |
| `get_event_budget` | read | event manager | An event's Budget & profit: planned costs, medals counted from the real divisions, net result, break-even fee, athletes needed, suggested fee, scenarios and the real numbers so far. Optional `fee`/`entrants`/`medals_mode`/`sponsor` return a `what_if` without saving. Behind `EVENT_BUDGET` |
| `manage_event_budget_item` | write | event manager | Add a planned cost (no `item`), change one (`item` uuid), or remove one (`item` + `remove`). `basis` fixed \| per_entrant. Returns the recomputed budget |
| `set_event_budget` | write | event manager | Set the plan's fee, turnout, profit target, reserve %, rounding, medal cost, medal rule and sponsor. **Never changes the event's real fee** — approving a scenario in the console does |
| `event_budget_toolbox` | read | event manager | Runs one of the in-app advisor's own tools (`get_overview`, `list_costs`, `get_divisions`, `list_scenarios`, `solve_for_goal`, `ways_to_save`, `ways_to_earn_more`, `lowest_fee`, `what_if`) — the same code the advisor calls, exact numbers. Nothing saved |
| `ask_event_budget_advisor` | read | event manager | Ask the Budget & profit advisor anything (`message`, optional `history`, `screen`); it reads the budget with its tools and answers, possibly with a proposal. Behind `EVENT_BUDGET_ADVISOR`. Nothing saved |
| `list_event_entry_consents` | read | event manager | Who accepted an event's rules before registering ("Register your club" → `/rules`): when, the statements as shown, the language, whether the rules changed since (`stale`), and the club they then registered. Newest 200. Recorded by `App\Events\Support\ClubEntry\EntryConsent` |
| `list_event_feature_requests` | read | super-admin | Every paid featuring request (club and federation events) with its status, amount, requester and proof link, plus the current flat fee |
| `decide_event_feature_request` | write | super-admin | Approve a pending request, reject it, or revoke an approved one (reason required for the last two, sent to the organiser). Same rules as `/admin/featured-events` (`App\Events\Support\EventFeaturing`) |
| `get_sport_rule_book` | read | any signed-in user (an `event` must be visible to them) | A combat sport's official styles at version 1 — age groups, belts, medal rule, officials, corners, weight tables per kind — or the rule book one event runs under (incl. a host's custom edition). Optional classify (gender/age/weight/kind → division), identical to the entry desk (`App\Sports\Package\SportPackages`) |
| `list_activity_ranks` | read | club admin · the activity's coach | A club's ranked activities (their sport has belts), the ladder, and each member's belt, since, and readiness for the next belt (`App\Ranks`). Only while `RANKS_ENABLED` |
| `set_member_rank` | write | club admin · the activity's coach | Set or correct a member's belt in a ranked activity — validated against the ladder, kept in history, member notified |
| `list_corner_sides` | read | super-admin | Per combat sport: which side of the hall screens and scoring tables the red corner stands on — the sport's default, any override, the side in effect (`App\Sports\Combat\CornerSide`) |
| `set_corner_side` | write | super-admin | Put a sport's red corner `left` / `right`, or `default` to clear the override. Same as Platform Settings → Hall screens |
| `set_mat_corner_side` | write | `EventAccess::canScore` | One mat's red corner `left` / `right` (`default` = the sport's convention) for a karate, taekwondo or BJJ event — the mat's PHYSICAL layout on its screens and consoles, never who fights in which corner. Same as the console's Corners → "Swap corner colours on this mat"; stored per mat (`App\Scoreboard\Corners\MatSides`) and redraws the mat at once. Karate: optional `screen` (`control`, `board`, `upcoming`) sets just that screen — Combat's per-screen swap (`MatSides::flip`); the answer lists every screen's side |
| `set_corner_look` | write | `EventAccess::canScore` | An event's corner NAMES and COLOURS, keyed by the sport's own corner ids (karate/taekwondo `aka`/`ao`, BJJ `blue`/`white`), each `{label, colour}`; null = the sport's own. Every mat, wall screen, VS screen, celebration and upcoming board of the event follows; only the look changes. Same as the console's Corners → "Corner names & colours" (`App\Scoreboard\Corners\CornerLook`) |
| `set_event_rulebook` | write | event manager | The event's rule book: list the category systems (age groups × weight classes — WKF, Shotokan, the host's custom ones) and belt systems it may run under (Karate belts: WKF, Kyokushin (IKO), Shotokan (JKA), Goju-ryu (IOGKF), Shito-ryu (Shitokai), Wado-ryu (Wado-kai), 2026-10-07), or change them. `list_events` also returns `style` — the style an event runs under as a reader names it ("Kyokushin", "Shotokan"), null for the sport's default. A category change re-sorts every entrant (hand placements kept) and is refused once a draw exists. `list_events` now also returns each event's `host` (club or federation) and its `category_system` / `belt_system` |
| `set_event_entry_channels` | write | organiser | Read or set how athletes enter an event: `individual` (self-entry, billed to the person), `club` (squads, billed to the club), or both. Only new entries are affected; organisers can always add anyone. Not for federation-hosted events |
| `set_event_visibility` | write | organiser | Read or set whether an event is `listed` (Explore, club page, federation, announcements) or `hidden` (in no list, no announcements; anybody with its link may open it). Organisers and entrants still see a hidden event in their own lists. `list_events` returns `visibility` |
| `list_event_club_payments` | read | payment verifier | The payment proofs clubs sent for their athletes on a club-hosted event ("Register your club"): club, amount, athletes covered, state, rejection reason. The image is viewed in the console |
| `decide_event_club_payment` | write | payment verifier | Approve (marks every covered athlete paid) or reject with a reason the club reads. Only a proof still waiting for review |
| `list_event_certificates` | read | event manager | An event's certificates (drafts, issued, revoked — holder, number, what for, revoke reason), its settings (participation / podium, when drafts are made) and how many it would issue now, with the divisions still running. Addressed by uuid. Same scope as the console (`EventCertificates::canManage` = `EventAccess::canManage`) |
| `set_event_certificate_settings` | write | event manager | Whether the event issues participation, podium, officials' thank-you and/or participating clubs' thank-you certificates, and when drafts are made (`division` · `event` · `manual`) |
| `generate_event_certificates` | write | event manager | Draft every certificate earned so far — participation for everyone who fought in a finished division, podium for places 1–3 off the stored podium. Idempotent; drafts reach nobody |
| `approve_event_certificates` | write | event manager | Approve drafts — the uuids given, or the next batch — 15 per call (notifies the holder; the PDF is drawn and stored the first time it is opened); returns how many remain |
| `event_certificate_bundle` | write | event manager | Every issued certificate of one design (participation / podium) as ONE PDF for the print shop — `build: true` starts it in the background; returns idle / building (done of total) / ready with size and the console's download address |
| `event_badges` | write | event manager | Every official's badge (A6, the event's badge design): name, role, club, whether a photo prints — and the print-shop PDF, four badges per A4 sheet with cut lines; `build: true` starts it in the background. Drawn from the officials as they stand |
| `delete_event_certificate_drafts` | write | event manager | Delete drafts — the uuids given, or every draft — 200 per call; returns how many remain. Never an issued certificate (those are revoked) |
| `revoke_certificate` | write | event manager | Revoke an issued certificate with a reason (3–300 chars); its QR page then says revoked, and when. The holder may NOT revoke their own |
| `list_my_certificates` | read | self | The acting user's issued (and revoked, marked so) certificates with the public verification link. Never drafts |
| `list_duplicate_findings` | read | super-admin | What the duplicate watch (`app/Duplicates`, `duplicates:watch`) found — people, clubs, typed event clubs, spare entries — with reasons and strength (certain / likely), filtered by status and kind. Names and public ids only |
| `list_forms` | read | super-admin | Every form / questionnaire (`app/Forms`): title EN/AR, type, open, owner, questions, pages, answer count, last answer, public link. Never the visit link |
| `get_form_results` | read | super-admin | One form's analytics as `/admin/forms/{uuid}` shows them — per-question counts, scale averages, grid scores, ranking points, latest free text, highlights and proposed decisions (now / next / later / show); `responses=true` adds the latest 50 answers in full |
| `resolve_duplicate_finding` | write | super-admin | `dismiss` a finding (a person pair is recorded as two people and never flagged again) or `fix` it: a certain one exactly as the watch would, a likely typed event club linked to the one platform club named. Likely PEOPLE are merged on the members screen, never here |

### No tool scores a match

Both BJJ tools are read-only, and that is a decision rather than an omission.

Scoring is the one action on this platform designed around *friction*: a 400 ms
lockout so a double-tap is not two points, a five-second undo, a hold to undo
past it, a modal for a correction, and a typed match number before a
disqualification. Every entry is attributed to the official who made it, and the
result is defensible because a human with authority in the room made each call.

An MCP tool cannot supply any of that. It has no operator standing at the mat, no
undo window a referee can see, and its token is not a person the log can name. A
tool that appended scoring events would let an automation change a live result
while a bout is running, with the audit trail saying only that a token did it.

The same reasoning already keeps `list_court_screens` read-only. If a scoring
integration is ever genuinely wanted, it should be a supervised one — proposals a
human confirms on the console — not a direct write.

- **Clubs** are addressed by numeric id **or** slug. **Members** by uuid (preferred) or id.
- Amounts are in each club's own currency.
- Read tools return scoped/empty results for users without access; write and
  admin tools return an explicit authorization error.
- **Test/Live mode.** Every club has an `is_test_mode` flag (admin-toggleable on the web
  financials page). `record_transaction` and `enroll_members` inherit that mode automatically
  — a `ClubTransaction`/`ClubMemberSubscription` created via MCP is tagged `is_test` to match
  the club's current mode, exactly like a web-created one. `club_financials` sums only rows
  matching the club's current mode, so MCP consumers see the same numbers the admin dashboard
  shows — never a mix of test and live data.

### Write kill-switch
Set `MCP_ALLOW_WRITES=false` to expose a **read-only** integration without
changing the tool set — every write tool then refuses while reads keep working.

---

## Adding a new tool

1. `php artisan make:mcp-tool DoThingTool` then change its parent to
   `extends App\Mcp\Tools\BaseTool` (gives you acting-user + authorization
   helpers + the write kill-switch).
2. In `handle()`, start with:
   ```php
   $user = $this->guard($request);
   if ($user instanceof \Laravel\Mcp\Response) return $user; // auth / write-mode guard
   ```
   Then use `$this->resolveAccessibleClub()`, `$this->canAdminClub()`,
   `$this->canViewMember()`, `$this->accessibleClubsQuery()` to enforce scope.
3. For a write tool set `protected bool $isWrite = true;`.
4. Register the class in `App\Mcp\Servers\TakeOneServer::$tools`.
5. Tool name is auto-derived (`DoThingTool` → `do_thing`); override with `#[Name]`.
6. Add a case to `tests/Feature/McpServerTest.php`.

---

## Files

- `routes/ai.php` — registers HTTP + stdio transports
- `config/takeone-mcp.php` — enabled / writes / stdio user / page size
- `app/Mcp/Servers/TakeOneServer.php` — the server + tool list + instructions
- `app/Mcp/Tools/*` — one class per tool (`BaseTool` is the shared base)
- `app/Mcp/Concerns/{ResolvesActingUser,AuthorizesClubAccess}.php` — auth/scoping
- `app/Console/Commands/McpToken.php` — `mcp:token` issue/list/revoke
- `tests/Feature/McpServerTest.php` — scope & authorization regression tests
