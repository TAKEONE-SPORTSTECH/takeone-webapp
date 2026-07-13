<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesClubAccess;
use App\Mcp\Concerns\ResolvesActingUser;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * Base class for every TAKEONE MCP tool. Provides the acting-user resolution,
 * club/member authorization helpers, and small guards for auth + write-mode so
 * individual tools stay focused on their own query/mutation.
 */
abstract class BaseTool extends Tool
{
    use AuthorizesClubAccess;
    use ResolvesActingUser;

    /** Set true on write tools so the global write kill-switch applies. */
    protected bool $isWrite = false;

    /**
     * Clean, stable tool name: snake_case of the class basename minus the
     * "Tool" suffix (WhoAmITool → who_am_i). An explicit #[Name] or $name wins.
     */
    public function name(): string
    {
        if (($attribute = $this->resolveAttribute(Name::class)) !== null) {
            return $attribute->value;
        }

        if ($this->name !== '') {
            return $this->name;
        }

        return Str::snake(preg_replace('/Tool$/', '', class_basename($this)));
    }

    /**
     * Resolve the acting user or return an MCP error Response. Usage:
     *
     *   $user = $this->guard($request);
     *   if ($user instanceof Response) return $user;
     */
    protected function guard(Request $request): User|Response
    {
        $user = $this->actingUser($request);

        if (! $user) {
            return Response::error(
                'Not authenticated. Provide a valid Sanctum bearer token (HTTP transport) '
                .'or set MCP_STDIO_USER_ID (stdio transport).'
            );
        }

        if ($this->isWrite && ! config('takeone-mcp.allow_writes', true)) {
            return Response::error('Write operations are disabled on this MCP server (MCP_ALLOW_WRITES=false).');
        }

        return $user;
    }

    /** Clamp a requested page size to the configured cap. */
    protected function pageSize(?int $requested): int
    {
        $default = (int) config('takeone-mcp.page_size', 25);
        $max = (int) config('takeone-mcp.max_page_size', 100);

        return max(1, min($requested ?: $default, $max));
    }
}
