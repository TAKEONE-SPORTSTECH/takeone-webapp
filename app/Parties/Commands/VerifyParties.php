<?php

namespace App\Parties\Commands;

use App\Parties\Backfill;
use Illuminate\Console\Command;

/**
 * Report every place the old sources and party_relations disagree. Exit code
 * is non-zero on any drift, so it can gate a cut-over (PLAN.md Phase 3/4).
 * Read-only.
 */
class VerifyParties extends Command
{
    protected $signature = 'parties:verify {--limit=50 : lines to print}';

    protected $description = 'Check party_relations against the old ownership/role sources (read-only)';

    public function handle(Backfill $backfill): int
    {
        $drift = $backfill->drift();

        if ($drift === []) {
            $this->info('In step: no drift between the old sources and party_relations.');

            return self::SUCCESS;
        }

        foreach (array_slice($drift, 0, (int) $this->option('limit')) as $line) {
            $this->line($line);
        }
        $this->error(count($drift).' disagreement(s).');

        return self::FAILURE;
    }
}
