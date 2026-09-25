<?php

namespace App\Federations\Commands;

use App\Federations\Models\FederationClubApplication;
use App\Federations\Services\FederationClubApproval;
use App\Members\Models\User;
use Illuminate\Console\Command;

/**
 * Approve or refuse a club that applied through an invitation link. The
 * designed dashboard has no screen for this yet; until it does, this is the
 * door. Approving creates the real platform club.
 */
class DecideClubApplication extends Command
{
    protected $signature = 'federation:club-application
        {application? : The application uuid (omit to list pending ones)}
        {--approve : Create the platform club and affiliate it}
        {--reject : Refuse the application}
        {--by= : Email of the person deciding}';

    protected $description = 'List, approve or refuse club applications made through federation invitations';

    public function handle(FederationClubApproval $approval): int
    {
        if (! $this->argument('application')) {
            $rows = FederationClubApplication::with('federation')->where('status', FederationClubApplication::STATUS_PENDING)->oldest()->get();
            $this->table(['uuid', 'club', 'coach', 'email', 'federation', 'applied'], $rows->map(fn ($a) => [
                $a->uuid, $a->name, $a->coach, $a->email, $a->federation?->name, $a->created_at?->toDateTimeString(),
            ]));

            return self::SUCCESS;
        }

        $app = FederationClubApplication::where('uuid', (string) $this->argument('application'))->first();
        if (! $app) {
            $this->error('No application with that uuid.');

            return self::FAILURE;
        }
        if ($this->option('approve') === $this->option('reject')) {
            $this->error('Pass exactly one of --approve or --reject.');

            return self::FAILURE;
        }
        $by = $this->option('by') ? User::where('email', $this->option('by'))->first() : null;

        try {
            if ($this->option('approve')) {
                $link = $approval->approve($app, $by);
                $this->info("{$app->name} is now a platform club (tenant {$link->tenant_id}), affiliated with {$app->federation->name}.");
            } else {
                $approval->reject($app, $by);
                $this->info("{$app->name}'s application was refused.");
            }
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        // Open dashboards show the decision without a refresh.
        \App\Federations\Support\FederationPulse::federation($app->federation);

        return self::SUCCESS;
    }
}
