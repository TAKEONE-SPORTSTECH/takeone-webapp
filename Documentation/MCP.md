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
| `get_member` | read | gated | One member profile (uuid or id) — super-admin/self/guardian/club-admin only |
| `club_financials` | read | admin | Income, expenses, net, cash-to-collect for a club |
| `search_people` | read | any | Platform-wide discoverable-member search — **safe public fields only** |
| `record_transaction` | write | admin | Log a manual income/expense for a club |
| `notify_member` | write | admin/guardian | Send an in-app + live (MQTT) notification |
| `enroll_members` | write | admin | Batch-enroll active members into a package, marked as already paid |

- **Clubs** are addressed by numeric id **or** slug. **Members** by uuid (preferred) or id.
- Amounts are in each club's own currency.
- Read tools return scoped/empty results for users without access; write and
  admin tools return an explicit authorization error.

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
