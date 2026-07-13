<?php

use App\Mcp\Servers\TakeOneServer;
use Laravel\Mcp\Facades\Mcp;

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
    Mcp::web('mcp', TakeOneServer::class)
        ->middleware(['auth:sanctum', 'throttle:60,1']);

    Mcp::local('takeone', TakeOneServer::class);
}
