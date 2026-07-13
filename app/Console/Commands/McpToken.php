<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Issue / list / revoke Sanctum bearer tokens for the MCP HTTP transport.
 * The token identifies the user every MCP tool then acts as.
 */
class McpToken extends Command
{
    protected $signature = 'mcp:token
        {user : User email or numeric id}
        {--name=mcp : A label for the token}
        {--list : List the user\'s existing MCP tokens instead of creating one}
        {--revoke= : Revoke a token by its id}';

    protected $description = 'Issue, list or revoke a Sanctum bearer token for connecting to the TAKEONE MCP server over HTTP';

    public function handle(): int
    {
        $ref = (string) $this->argument('user');

        $user = User::query()
            ->where('email', $ref)
            ->orWhere('id', is_numeric($ref) ? (int) $ref : 0)
            ->first();

        if (! $user) {
            $this->error("No user found for [{$ref}].");

            return self::FAILURE;
        }

        if ($this->option('list')) {
            $tokens = $user->tokens()->get(['id', 'name', 'last_used_at', 'created_at']);

            if ($tokens->isEmpty()) {
                $this->info('No tokens for this user.');

                return self::SUCCESS;
            }

            $this->table(
                ['ID', 'Name', 'Last used', 'Created'],
                $tokens->map(fn ($t) => [
                    $t->id,
                    $t->name,
                    optional($t->last_used_at)->diffForHumans() ?? 'never',
                    $t->created_at->toDateTimeString(),
                ])->all(),
            );

            return self::SUCCESS;
        }

        if ($revokeId = $this->option('revoke')) {
            $deleted = $user->tokens()->where('id', $revokeId)->delete();
            $this->info($deleted ? "Revoked token #{$revokeId}." : "No token #{$revokeId} for this user.");

            return self::SUCCESS;
        }

        $token = $user->createToken($this->option('name'), ['mcp:use']);

        $this->newLine();
        $this->info("MCP token created for {$user->email} (user #{$user->id}).");
        $this->newLine();
        $this->line('  Bearer token (shown once — copy it now):');
        $this->line('  <fg=yellow>'.$token->plainTextToken.'</>');
        $this->newLine();
        $this->line('  Endpoint:  '.rtrim(config('app.url'), '/').'/mcp');
        $this->line('  Header:    Authorization: Bearer <token>');
        $this->newLine();

        return self::SUCCESS;
    }
}
