<?php

namespace App\Platform\Commands;

use Illuminate\Console\Command;
use PDO;
use Throwable;

/**
 * Copies every row of the SQLite database into the PostgreSQL database named
 * by the `pgsql_import` connection — the move off SQLite.
 *
 * It is one transaction on each side:
 *
 *  - SQLite is opened READ-ONLY and read inside one transaction, so the copy is
 *    a single point in time even while the site keeps writing.
 *  - PostgreSQL truncates, loads, re-numbers its sequences and checks every
 *    foreign key inside ONE transaction. Anything wrong — a value Postgres will
 *    not accept, an orphaned row, a count that does not match — and the whole
 *    thing rolls back, leaving the target exactly as it was.
 *
 * By default it is a REHEARSAL: it does all of that and then rolls back, so it
 * proves the load without keeping it. `--apply` commits.
 *
 * Refuses to touch a target the app is currently reading from, and refuses a
 * target that already holds people unless `--replace` says that is intended —
 * the Postgres server is shared, and a truncate there is the whole platform.
 */
class CopyToPostgres extends Command
{
    protected $signature = 'takeone:copy-to-pgsql
                            {--apply : Commit the copy (default is a rehearsal that rolls back)}
                            {--replace : Allow replacing a target that already holds data}';

    protected $description = 'Copy the SQLite database into PostgreSQL (rehearsal by default; --apply commits)';

    public function handle(): int
    {
        $target = config('database.connections.pgsql_import');

        if (empty($target['host']) || empty($target['database'])) {
            $this->error('The pgsql_import connection is not configured (PG_IMPORT_* in .env).');

            return self::FAILURE;
        }

        $live = config('database.connections.'.config('database.default'));

        if (($live['driver'] ?? null) === 'pgsql'
            && ($live['host'] ?? null) === $target['host']
            && ($live['database'] ?? null) === $target['database']) {
            $this->error('REFUSING: the app is currently running on this PostgreSQL database. Copying would wipe live data.');

            return self::FAILURE;
        }

        $sqlitePath = (string) config('database.connections.sqlite.database');
        $src = new PDO('sqlite:'.$sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
        ]);
        $src->exec('PRAGMA busy_timeout = 30000');

        $dst = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', $target['host'], $target['port'], $target['database'], $target['sslmode']),
            $target['username'], $target['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $occupied = (int) $dst->query('select count(*) from users')->fetchColumn();

        if ($occupied > 0 && ! $this->option('replace')) {
            $this->error("REFUSING: the target already holds {$occupied} users. Pass --replace if replacing it is intended.");

            return self::FAILURE;
        }

        $tables = $src->query("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name")
            ->fetchAll(PDO::FETCH_COLUMN);

        $targetColumns = [];
        foreach ($dst->query("select table_name, column_name from information_schema.columns where table_schema = 'public'") as $r) {
            $targetColumns[$r['table_name']][] = $r['column_name'];
        }

        foreach ($tables as $t) {
            $cols = array_column($src->query('pragma table_info("'.$t.'")')->fetchAll(PDO::FETCH_ASSOC), 'name');
            if (! isset($targetColumns[$t]) || array_diff($cols, $targetColumns[$t])) {
                $this->error("Schema mismatch on {$t}: the target is missing the table or some of its columns. Migrate the target first.");

                return self::FAILURE;
            }
        }

        $this->info(($this->option('apply') ? 'APPLY' : 'REHEARSAL').": {$sqlitePath} → {$target['host']}/{$target['database']}");

        $src->beginTransaction();
        $dst->beginTransaction();

        try {
            // Every FK becomes deferrable for the load, checked in one go at the
            // end, and put back exactly as it was before the commit.
            $fks = $dst->query("select conrelid::regclass::text as tbl, conname from pg_constraint where contype = 'f' and connamespace = 'public'::regnamespace and not condeferrable")
                ->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fks as $fk) {
                $dst->exec(sprintf('alter table %s alter constraint "%s" deferrable', $fk['tbl'], $fk['conname']));
            }
            $dst->exec('set constraints all deferred');

            $all = implode(', ', array_map(fn ($t) => '"'.$t.'"', array_keys($targetColumns)));
            $dst->exec("truncate {$all} restart identity");

            $counts = [];
            foreach ($tables as $t) {
                $counts[$t] = $this->copyTable($src, $dst, $t);
            }

            $this->resetSequences($dst);

            $dst->exec('set constraints all immediate');
            foreach ($fks as $fk) {
                $dst->exec(sprintf('alter table %s alter constraint "%s" not deferrable', $fk['tbl'], $fk['conname']));
            }

            $mismatch = 0;
            foreach ($counts as $t => $n) {
                $got = (int) $dst->query('select count(*) from "'.$t.'"')->fetchColumn();
                if ($got !== $n) {
                    $this->error("Count mismatch on {$t}: sqlite {$n}, postgres {$got}");
                    $mismatch++;
                }
            }
            if ($mismatch) {
                throw new \RuntimeException("{$mismatch} table(s) did not copy exactly.");
            }

            $src->commit();

            if ($this->option('apply')) {
                $dst->commit();
                $this->info(sprintf('Committed: %d tables, %d rows, %d foreign keys verified.', count($counts), array_sum($counts), count($fks)));
            } else {
                $dst->rollBack();
                $this->info(sprintf('Rehearsal passed and was rolled back: %d tables, %d rows, %d foreign keys verified.', count($counts), array_sum($counts), count($fks)));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            if ($dst->inTransaction()) {
                $dst->rollBack();
            }
            if ($src->inTransaction()) {
                $src->rollBack();
            }
            $this->error('FAILED, nothing was changed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function copyTable(PDO $src, PDO $dst, string $table): int
    {
        $copied = 0;
        $batch = [];
        $cols = null;

        $flush = function () use (&$batch, &$cols, $dst, $table) {
            if (! $batch) {
                return;
            }
            $row = '('.implode(', ', array_fill(0, count($cols), '?')).')';
            $sql = sprintf('insert into "%s" (%s) values %s', $table,
                implode(', ', array_map(fn ($c) => '"'.$c.'"', $cols)),
                implode(', ', array_fill(0, count($batch), $row)));
            $stmt = $dst->prepare($sql);
            $i = 1;
            foreach ($batch as $values) {
                foreach ($values as $v) {
                    // Everything travels as text and Postgres casts it to the
                    // column's type — which is what turns SQLite's 0/1 into a
                    // boolean and rejects anything that is not a real uuid/json.
                    $stmt->bindValue($i++, $v === null ? null : (string) $v, $v === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            $batch = [];
        };

        foreach ($src->query('select * from "'.$table.'"', PDO::FETCH_ASSOC) as $r) {
            $cols ??= array_keys($r);
            $batch[] = array_values($r);
            $copied++;
            if (count($batch) * count($cols) >= 30000) {
                $flush();
            }
        }
        $flush();

        $this->line(sprintf('  %-55s %8d', $table, $copied));

        return $copied;
    }

    private function resetSequences(PDO $dst): void
    {
        $seqs = $dst->query(<<<'SQL'
            select c.table_name, c.column_name, pg_get_serial_sequence(quote_ident(c.table_name), c.column_name) as seq
            from information_schema.columns c
            where c.table_schema = 'public' and c.column_default like 'nextval(%'
        SQL)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($seqs as $s) {
            $max = $dst->query(sprintf('select max("%s") from "%s"', $s['column_name'], $s['table_name']))->fetchColumn();
            $dst->query(sprintf("select setval('%s', %d, %s)", $s['seq'], $max ?: 1, $max ? 'true' : 'false'));
        }
    }
}
