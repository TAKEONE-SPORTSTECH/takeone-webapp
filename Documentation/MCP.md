# TAKEONE MCP Server

A single, general-purpose **Model Context Protocol** server that exposes the
TAKEONE platform to external systems (Claude Desktop/Code, n8n, other services,
your own scripts). It is built on the official `laravel/mcp` package and plugs
directly into the app's models, Sanctum auth, roles and policies.

**Core guarantee:** every tool runs **AS the authenticated user** and enforces
exactly the same tenant scope and authorization the web UI does
(super-admin → club owner → club-admin → guardian/self). The MCP can never read
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
| `club_staff` | read | admin | A club's staff (instructors, secretaries, operators, cleaners, ...) with staff type, compensation, and active status. Read-only — hiring/terminating staff is not exposed over MCP |
| `search_people` | read | any | Club-scoped discoverable-member search (confirmed club-mates of the acting user only, never platform-wide) — **safe public fields only** |
| `record_transaction` | write | admin | Log a manual income/expense for a club |
| `notify_member` | write | admin/guardian | Send an in-app + live (MQTT) notification |
| `enroll_members` | write | admin | Batch-enroll active members into a package, marked as already paid |
| `list_events` | read | scoped | Discover events the acting user may see, and get the `uuid` every other event tool needs. Defaults to `state=open` — not started yet **plus** running right now; also `live`, `upcoming`, `past`, `all`. Searchable by title/club/location/sport. Archived + cancelled events are never returned; visibility mirrors `App\Events\Support\EventAccess` exactly, so it can never list an event whose detail page would 403 |
| `list_event_people` | read | scoped | An event's competitors and the clubs behind them — name, division, weight class, country, club, and each club's squad size. Reading only for EVERYONE, organisers included: no registration ids, weights, payment or weigh-in state, and no spectators. Built by the same `App\Events\Support\RosterPeople` as the "Who's joined" screen so the two cannot drift. Unseeable events answer "Event not found" |
| `list_event_documents` | read | scoped | Files attached to an event (rulebook, entry form, schedule) — title, type, size and a download link. Metadata only: the bytes are served by the web route, which re-runs the same `EventAccess::visible` check, and a storage path is never returned. Unseeable events answer "Event not found" rather than confirming the uuid exists |
| `enter_event_athletes` | write | admin | Enter your club's athletes into an event (omit ids to preview the roster + eligibility). Same gate as self-entry: scope, bans, window, capacity, weight division |
| `get_event_bracket` | read | scoped | An event's knockout draw — every division, its rounds, each bout (both competitors, seeds, scores, winner, mat, time), the podium, and entrants not currently placed in the draw. Works for any bracketed event type; visibility mirrors the web screen exactly (`App\Events\Support\EventAccess`) |
| `arrange_event_bracket` | write | organiser | Move a competitor within a division's **first round**, or in/out of the draw — the MCP equivalent of dragging on the bracket screen. Organiser only, and refused once the event starts (the draw is final from the first bout). Later rounds are never arrangeable |
| `get_event_readiness` | read | officials | The run-day checklist and whether the event has started: each item with who cleared it and when, how many are outstanding, the scheduled vs actual start, and whether the organiser started with items still open. Restricted to the organiser and appointed officials (`EventAccess::canOfficiate`) — it is the organiser's preparation notes and it names the people who signed each item off, so a competitor gets "Event not found" |
| `list_court_screens` | read | organiser | The Raspberry Pi hall screens paired to an event: which mat each shows, whether it has reported in recently (`live`), when it was last seen, plus the event's mats and a count of `dark` screens. Organiser only (`EventAccess::canManage`). **Read-only by design** — pairing means reading a code off a wall in the room, and a write tool would turn a deliberately public code into a remotely usable one. The event type answers (`EventType::hallScreens`), so a type with no wall boards reports `supported: false` |
| `list_activity_catalog` | read | any | The global activity directory — shared platform-wide catalog of activities (EN/AR) any club can reuse. Read-only, non-sensitive (search + paginate). Each entry includes its curated `videos` (validated YouTube `{id,title,source}`) |
| `verify_achievement` | write | admin | Confirm/reject a member self-claimed record that names your club — a tournament medal (`type: achievement`) or an acquired skill (`type: skill`), bound by uuid. Only an admin/owner of the named club may act — mirrors the web verification queue (medals + skills) |
| `add_certification` | write | self/guardian | Add a self-managed certification/qualification (name, issuer, dates, credential id/url) to a member — super-admin/self/guardian only (not club-admins) |
| `add_work_history` | write | self/guardian | Add a self-managed work/coaching history entry (role, org, dates, type) to a member; null end date = current — super-admin/self/guardian only |
| `manage_member_photo` | write | self/guardian | A profile holds several pictures; one is the avatar. Promote an existing picture (`action: set_avatar`) or delete one (`action: delete`, which purges the file and hands the avatar to the next picture), addressed by photo uuid and scoped to that member — super-admin/self/guardian only. **Uploading is deliberately not exposed** (raw image bytes belong to the app's own validated uploader); read the list from `get_member.photos` |

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
