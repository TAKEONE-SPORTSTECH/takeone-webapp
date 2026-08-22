<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Issue / list / revoke the personal token a TAKEONE user pastes into TAKEONE
 * Play to link their account for the match-video lookup form.
 *
 * The token carries ONE ability — `play-integration` — so it unlocks the
 * read-only lookup routes and nothing else on the platform. It identifies a
 * real person, which is the entire point: every lookup Play makes is answered
 * with exactly what that person could already see in the web UI.
 */
class PlayToken extends Command
{
    protected $signature = 'play:token
        {user : User email or numeric id}
        {--name=play : A label for the token}
        {--list : List this user\'s existing Play tokens instead of creating one}
        {--revoke= : Revoke a token by its id}
        {--read-only : Issue a lookup-only token that cannot write photos back}';

    protected $description = 'Issue, list or revoke a personal token for linking a TAKEONE account to TAKEONE Play';

    /** Read scope — the lookups. Always granted. */
    private const ABILITY = 'play-integration';

    /** Write scope — pushing a headshot or crest back onto a TAKEONE profile. */
    private const ABILITY_WRITE = 'play-write';

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
            $tokens = $user->tokens()
                ->where('abilities', 'like', '%'.self::ABILITY.'%')
                ->get(['id', 'name', 'last_used_at', 'created_at']);

            if ($tokens->isEmpty()) {
                $this->info('No Play tokens for this user.');

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

            return $deleted ? self::SUCCESS : self::FAILURE;
        }

        // Both scopes by default: the form reads people AND can push a photo back.
        // --read-only issues a token that can never write.
        $abilities = $this->option('read-only')
            ? [self::ABILITY]
            : [self::ABILITY, self::ABILITY_WRITE];

        $token = $user->createToken((string) $this->option('name'), $abilities);

        $this->newLine();
        $this->info("Play token for {$user->name} <{$user->email}>:");
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->comment('Scope: '.implode(', ', $abilities));
        $this->newLine();
        $this->comment('Paste this into TAKEONE Play → Settings → Connect TAKEONE.');
        $this->comment('It is shown once. Revoke with: php artisan play:token '.$user->id.' --revoke=<id>');

        return self::SUCCESS;
    }
}
