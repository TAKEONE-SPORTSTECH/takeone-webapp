<?php

use App\DevBoard\Mcp\NotesServer;
use App\Mcp\Servers\TakeOneServer;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

/*
|--------------------------------------------------------------------------
| MCP Servers
|--------------------------------------------------------------------------
|
| The general-purpose TAKEONE MCP server, exposed over two transports:
|
|   • HTTP  — POST /mcp, authenticated with a Sanctum bearer token. Every tool
|             then runs AS that token's owner and inherits their roles/policies.
|             Issue tokens with `php artisan mcp:token {user} --name=...`.
|
|   • stdio — `php artisan mcp:start takeone`, for a local operator process
|             (e.g. Claude Desktop/Code on the server). It acts AS the user in
|             config('takeone-mcp.stdio_user_id').
|
| Toggle the whole server with MCP_ENABLED (see config/takeone-mcp.php).
|
*/

if (config('takeone-mcp.enabled', true)) {
    // A notes-only token (ability `notes`) must not open the full server.
    Mcp::web('mcp', TakeOneServer::class)
        ->middleware(['auth:sanctum', CheckForAnyAbility::class.':mcp:use', 'throttle:60,1']);

    Mcp::local('takeone', TakeOneServer::class);

    // Notes only (/me/notes), for connecting another AI to a notebook:
    // `php artisan mcp:token {user} --notes` issues a token for this and nothing else.
    if (config('devboard.enabled') && config('devboard.takenote')) {
        Mcp::web('mcp/notes', NotesServer::class)
            ->middleware(['auth:sanctum', CheckForAnyAbility::class.':notes', 'throttle:60,1']);

        Mcp::local('takeone-notes', NotesServer::class);
    }
}
