<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddCertificationTool;
use App\Mcp\Tools\AddWorkHistoryTool;
use App\Mcp\Tools\ArrangeEventBracketTool;
use App\Mcp\Tools\ClubFinancialsTool;
use App\Mcp\Tools\ClubStaffTool;
use App\Mcp\Tools\EnrollMembersTool;
use App\Mcp\Tools\EnterEventAthletesTool;
use App\Mcp\Tools\GetClubTool;
use App\Mcp\Tools\GetEventBracketTool;
use App\Mcp\Tools\GetMemberTool;
use App\Mcp\Tools\ListActivityCatalogTool;
use App\Mcp\Tools\ListClubsTool;
use App\Mcp\Tools\ListMembersTool;
use App\Mcp\Tools\NotifyMemberTool;
use App\Mcp\Tools\RecordTransactionTool;
use App\Mcp\Tools\SearchPeopleTool;
use App\Mcp\Tools\VerifyAchievementTool;
use App\Mcp\Tools\WhoAmITool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('TAKEONE Platform')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
This MCP server exposes the TAKEONE sports-club SaaS platform to external systems.

Every tool runs AS the authenticated user (the Sanctum token's owner over HTTP, or
the configured operator over stdio) and enforces exactly the same tenant scope and
authorization the web app does: super-admin → club owner → club-admin → guardian/self.
A tool will refuse anything the acting user could not do in the UI.

Getting started:
  1. Call `who_am_i` to see the acting user's identity, roles and accessible clubs.
  2. Use `list_clubs` / `get_club` to browse clubs the user can access.
  3. Use `list_members` / `get_member` for member data (private profiles are gated).
  4. Use `club_financials` for a club's money (admins only); `club_staff` lists instructors/staff and their compensation (admins only, read-only).
  5. `get_event_bracket` reads an event's knockout draw — divisions, rounds, bouts, scores, podium.
  6. `search_people` finds discoverable members who share a confirmed club membership with the acting user (never platform-wide; safe public fields only).

Write tools (may be globally disabled via server config):
  • `record_transaction` — log manual income/expense for a club (admins only).
  • `notify_member` — send an in-app + live (MQTT) notification (admins/guardians).
  • `enroll_members` — batch-enroll active members into a package, marked as already paid (admins only).
  • `add_certification` — add a self-managed certification to a member (super-admin/self/guardian).
  • `add_work_history` — add a self-managed work/coaching history entry to a member (super-admin/self/guardian).
  • `arrange_event_bracket` — move a competitor within a division's first round, or in/out of the draw (organiser only, before the event starts).

Identifiers: clubs accept a numeric id OR a slug; members accept a uuid (preferred)
or a numeric id. Amounts are in each club's own currency.
MARKDOWN)]
class TakeOneServer extends Server
{
    protected array $tools = [
        WhoAmITool::class,
        ListClubsTool::class,
        GetClubTool::class,
        ListMembersTool::class,
        GetMemberTool::class,
        ClubFinancialsTool::class,
        ClubStaffTool::class,
        SearchPeopleTool::class,
        RecordTransactionTool::class,
        NotifyMemberTool::class,
        EnrollMembersTool::class,
        EnterEventAthletesTool::class,
        GetEventBracketTool::class,
        ArrangeEventBracketTool::class,
        ListActivityCatalogTool::class,
        VerifyAchievementTool::class,
        AddCertificationTool::class,
        AddWorkHistoryTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
