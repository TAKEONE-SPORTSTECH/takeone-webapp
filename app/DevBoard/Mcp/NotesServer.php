<?php

namespace App\DevBoard\Mcp;

use App\DevBoard\Mcp\Tools\CreateNoteTool;
use App\DevBoard\Mcp\Tools\GetNoteTool;
use App\DevBoard\Mcp\Tools\ListNoteFoldersTool;
use App\DevBoard\Mcp\Tools\ListNotesTool;
use App\DevBoard\Mcp\Tools\UpdateNoteTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Notes only — the MCP server another AI connects to for /me/notes.
 *
 * Separate from TakeOneServer on purpose: a token issued for notes
 * (`php artisan mcp:token {user} --notes`, ability `notes`) opens this server
 * and nothing else, so an outside assistant given it can read and write the
 * person's notebook but cannot reach clubs, members, events or money. The same
 * tools are also on TakeOneServer for a full-access session.
 */
#[Name('TakeOne Notes')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
Your TakeOne notebook (/me/notes) — read, search and write notes.

  • `list_notes` — newest first, with snippets; `query` searches title and text, `folder` narrows to one folder.
  • `get_note` — one note in full (text + HTML) and its `version`.
  • `create_note` — a new note from Markdown; the first line is the title.
  • `update_note` — replace or append Markdown, and/or move to a folder. Always pass the `version` from `get_note`; a stale version changes nothing.
  • `list_note_folders` — folder uuids and names.

Notes cannot be deleted from here — do that in the app (it goes to the bin, where it can be restored).
MARKDOWN)]
class NotesServer extends Server
{
    protected array $tools = [
        ListNotesTool::class,
        GetNoteTool::class,
        CreateNoteTool::class,
        UpdateNoteTool::class,
        ListNoteFoldersTool::class,
    ];
}
