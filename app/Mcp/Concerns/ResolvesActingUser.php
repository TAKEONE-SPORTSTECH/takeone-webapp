<?php

namespace App\Mcp\Concerns;

use App\Models\User;
use Laravel\Mcp\Request;

/**
 * Resolves the user a tool is acting as.
 *
 * Over HTTP the MCP route is protected by `auth:sanctum`, so the bearer token's
 * owner is returned by $request->user(). Over stdio there is no HTTP request, so
 * we fall back to the configured operator user id (see config/takeone-mcp.php).
 */
trait ResolvesActingUser
{
    protected ?User $resolvedActingUser = null;

    protected bool $actingUserResolved = false;

    protected function actingUser(Request $request): ?User
    {
        if ($this->actingUserResolved) {
            return $this->resolvedActingUser;
        }

        $this->actingUserResolved = true;

        $user = $request->user();

        if ($user instanceof User) {
            return $this->resolvedActingUser = $user;
        }

        // stdio transport: no bearer token — fall back to the configured operator.
        $stdioUserId = config('takeone-mcp.stdio_user_id');

        if ($stdioUserId) {
            return $this->resolvedActingUser = User::find($stdioUserId);
        }

        return $this->resolvedActingUser = null;
    }
}
