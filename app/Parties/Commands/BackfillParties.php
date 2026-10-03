<?php

namespace App\Parties\Commands;

use App\Parties\Backfill;
use Illuminate\Console\Command;

/**
 * Copy today's ownership / role / membership sources into party_relations.
 * DRY RUN by default; `--apply` writes, in one transaction. Never touches the
 * old sources. Take a verified `takeone:backup` before `--apply` (RULE #2).
 */
class BackfillParties extends Command
{
    protected $signature = 'parties:backfill {--apply : write the rows (default is a dry run)}';

    protected $description = 'Copy owners, roles, staff, memberships and hosts into party_relations (dry run unless --apply)';

    public function handle(Backfill $backfill): int
    {
        $apply = (bool) $this->option('apply');
        $counts = $backfill->run($apply);

        $this->table(['source', $apply ? 'rows created' : 'rows that would be created'],
            collect($counts)->map(fn ($n, $s) => [$s, $n])->values()->all());
        $this->line(($apply ? 'Created ' : 'Would create ').array_sum($counts).' rows.'.($apply ? '' : ' Dry run — nothing written. Re-run with --apply.'));

        return self::SUCCESS;
    }
}
