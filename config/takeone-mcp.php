<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TAKEONE MCP Server
    |--------------------------------------------------------------------------
    |
    | Configuration for the general-purpose Model Context Protocol (MCP) server
    | that exposes the TAKEONE platform to external systems (Claude, n8n, other
    | services). The server is registered in routes/ai.php over two transports:
    |
    |   • HTTP  — POST /mcp  (remote; authenticated with a Sanctum bearer token)
    |   • stdio — `php artisan mcp:start takeone`  (local operator process)
    |
    | Every tool runs AS the authenticated user and inherits that user's roles,
    | tenant scope and policies. Reads and writes a member/admin could not do in
    | the UI are equally forbidden through the MCP.
    |
    */

    // Master switch. When false, the HTTP route and stdio handle are not
    // registered at all (routes/ai.php checks this).
    'enabled' => (bool) env('MCP_ENABLED', true),

    /*
    | The stdio transport has no HTTP request, so there is no bearer token to
    | identify a user. Anyone who can run `php artisan` on the box already has
    | full database access, so the local server acts AS this user id. Leave it
    | null to disable the local (stdio) server's ability to act on data — its
    | tools will then refuse with an "unauthenticated" error.
    */
    'stdio_user_id' => env('MCP_STDIO_USER_ID') ? (int) env('MCP_STDIO_USER_ID') : null,

    /*
    | Global kill-switch for write tools. When false, the server still exposes
    | and serves every READ tool, but any write tool refuses. Useful to expose a
    | read-only integration without redeploying a different tool set.
    */
    'allow_writes' => (bool) env('MCP_ALLOW_WRITES', true),

    /*
    | Default page size / hard cap for list tools, so a single call can never
    | pull an unbounded result set out of the database.
    */
    'page_size' => (int) env('MCP_PAGE_SIZE', 25),
    'max_page_size' => (int) env('MCP_MAX_PAGE_SIZE', 100),
];
