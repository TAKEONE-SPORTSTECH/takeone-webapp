<?php

namespace App\Console\Commands;

use App\Support\Promote\Applier;
use App\Support\Promote\Planner;
use Illuminate\Console\Command;

/**
 * Step 1 of bringing staging's real data into production: the REPORT.
 *
 * Reads a staging database snapshot and a production one (both opened
 * read-only) and says, table by table, what promoting would add, what it
 * would link to records production already has, and what it would leave out
 * as test data — plus every conflict and unclassified column to settle first.
 * Without --apply it writes nothing to either database. With --apply it
 * writes the plan into the target (App\Support\Promote\Applier), refusing the
 * application's own live database unless --production is given.
 *
 *   php artisan takeone:promote staging.sqlite production.sqlite \
 *       --exclude=storage/app/private/promote/exclude.json --json=report.json
 *
 * The exclude file names the test data: {"tenants": [slug…],
 * "club_events": [uuid…], "users": [uuid…]}. It holds no personal data, but
 * it is reviewed by the owner, so it lives outside git.
 */
class PromoteData extends Command
{
    protected $signature = 'takeone:promote
        {source : the staging database snapshot (SQLite file)}
        {target : the production database snapshot (SQLite file)}
        {--exclude= : JSON file naming the test data to leave out}
        {--storage= : staging storage/ folder, to count the files that travel with the rows}
        {--json= : write the full report here}
        {--apply : WRITE the plan into the target database (one transaction; rolled back on any failed check)}
        {--files-to= : with --apply, the target storage/ folder the added rows\' files are copied into}
        {--production : allow --apply on the database this application is running on}';

    protected $description = 'Report what promoting staging data into production would do (read-only)';

    public function handle(): int
    {
        foreach (['source', 'target'] as $arg) {
            if (! is_file($this->argument($arg))) {
                $this->error("No such file: {$this->argument($arg)}");

                return self::FAILURE;
            }
        }

        $exclude = [];
        if ($path = $this->option('exclude')) {
            $exclude = json_decode((string) @file_get_contents($path), true);
            if (! is_array($exclude)) {
                $this->error("Cannot read the exclude file: $path");

                return self::FAILURE;
            }
        }

        if ($this->option('apply')) {
            return $this->applyPlan($exclude);
        }

        $report = (new Planner($this->argument('source'), $this->argument('target'), $exclude, $this->option('storage')))->plan();

        $this->info('Promote report — nothing has been written.');
        $this->table(['add', 'link to existing', 'test', 'left out with test data', 'cannot copy'], [array_values($report['totals'])]);

        $rows = [];
        foreach ($report['tables'] as $t => $r) {
            $c = $r['counts'];
            $rows[] = [$t, $c['add'] ?? 0, $c['link'] ?? 0, ($c['test'] ?? 0) + ($c['cascade'] ?? 0), $c['dangling'] ?? 0];
        }
        $this->table(['table', 'add', 'link', 'test', 'cannot copy'], $rows);

        $this->line(sprintf('Files travelling with added rows: %d (%.1f MB)', $report['files']['count'], $report['files']['bytes'] / 1048576));
        foreach (['conflicts', 'unknown_columns', 'cycles'] as $section) {
            if ($report[$section]) {
                $this->warn(strtoupper(str_replace('_', ' ', $section)).': '.count($report[$section]));
            }
        }

        if ($path = $this->option('json')) {
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line("Full report: $path");
        }

        return self::SUCCESS;
    }

    /**
     * Write it. Into a COPY unless --production says otherwise: the database
     * this application runs on is refused by default, so a rehearsal can
     * never land on the real thing by a slip of the path.
     */
    private function applyPlan(array $exclude): int
    {
        $target = realpath($this->argument('target'));
        $live = realpath((string) config('database.connections.'.config('database.default').'.database')) ?: null;

        if ($target === $live && ! $this->option('production')) {
            $this->error('That is the live database of this application. Rehearse on a copy, or pass --production after a verified backup.');

            return self::FAILURE;
        }

        $result = (new Applier($this->argument('source'), $target, $exclude, $this->option('storage'), $this->option('files-to')))->apply();

        $this->info('Written and committed — every check passed.');
        $this->table(['table', 'rows added'], collect($result['written'])->map(fn ($n, $t) => [$t, $n])->values()->all());
        $this->line(sprintf('Files: %d copied (%.1f MB), %d already there, %d missing',
            $result['files']['copied'], $result['files']['bytes'] / 1048576, $result['files']['already_there'], $result['files']['missing']));

        return self::SUCCESS;
    }
}
