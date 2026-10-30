<?php

namespace App\Support\Promote;

use App\Events\Models\ClubEvent;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

/**
 * Carries out a Planner's decisions: writes the staging rows production lacks
 * into a production database, every reference rewritten to its new id.
 *
 * Everything happens in ONE transaction that holds the database's write lock
 * from the moment the plan is read until it is committed, so nothing can
 * change underneath it. Before committing it checks that it created no broken
 * reference (PRAGMA foreign_key_check, compared with how the database was
 * before), that the database is intact, and that it wrote exactly what the
 * plan said. Any failure rolls the whole thing back: production is then
 * exactly as it was.
 *
 * Nothing that already exists is updated or deleted. The only rows this
 * touches after inserting them are its own new rows (links to rows written
 * later in the same run), and its own bookkeeping table, MAP_TABLE, which
 * remembers what each staging row became so the next run adds only what is new.
 */
final class Applier
{
    public const MAP_TABLE = 'promotion_map';

    private PDO $dst;

    /** @var array<string, array<int|string, int>> table => staging key => production id */
    private array $map = [];

    /** @var array<int, array<string, mixed>> links settled after every row is written */
    private array $patches = [];

    /** @var array<string, int> */
    private array $written = [];

    /** @var array<string, string> relative path => disk folder ("app/public/") */
    private array $files = [];

    public function __construct(
        private string $sourcePath,
        private string $targetPath,
        private array $exclude = [],
        private ?string $sourceStorage = null,
        private ?string $targetStorage = null,
    ) {}

    /** @return array<string, mixed> */
    public function apply(): array
    {
        $this->dst = new PDO('sqlite:'.$this->targetPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->dst->exec('PRAGMA busy_timeout = 30000');
        // Rows are written in dependency order and the few links to rows
        // written later are patched at the end, so enforcement is replaced by
        // a full check before commit (no cascade can fire on an insert).
        $this->dst->exec('PRAGMA foreign_keys = OFF');
        $this->ensureMapTable();
        $brokenBefore = $this->brokenReferences();

        $this->dst->exec('BEGIN IMMEDIATE');

        try {
            $planner = new Planner($this->sourcePath, $this->targetPath, $this->exclude, $this->sourceStorage, $this->dst);
            $report = $planner->plan();
            $d = $planner->decisions();

            foreach ($d['state'] as $t => $rows) {
                foreach ($rows as $key => $state) {
                    if ($state === 'link' && isset($d['map'][$t][$key])) {
                        $this->map[$t][$key] = $d['map'][$t][$key];
                    }
                }
            }

            foreach ($d['order'] as $t) {
                $this->writeTable($planner->source(), $t, $d);
            }
            $this->settlePatches($d);
            $this->remember($d);

            $this->verify($report, $brokenBefore);
            $this->dst->exec('COMMIT');
        } catch (\Throwable $e) {
            $this->dst->exec('ROLLBACK');

            throw $e;
        }

        $copied = $this->copyFiles();

        return ['report' => $report, 'written' => $this->written, 'files' => $copied];
    }

    private function ensureMapTable(): void
    {
        $this->dst->exec('CREATE TABLE IF NOT EXISTS '.self::MAP_TABLE.' (
            source_table varchar(64) NOT NULL,
            source_key varchar(64) NOT NULL,
            target_id integer NOT NULL,
            promoted_at datetime NOT NULL,
            PRIMARY KEY (source_table, source_key)
        )');
    }

    /** @return array<string, int> "table → parent" => count */
    private function brokenReferences(): array
    {
        $out = [];
        foreach ($this->dst->query('PRAGMA foreign_key_check') as $r) {
            $k = $r['table'].' → '.$r['parent'];
            $out[$k] = ($out[$k] ?? 0) + 1;
        }

        return $out;
    }

    private function writeTable(PDO $src, string $t, array $d): void
    {
        $hasId = isset($d['cols'][$t]['id']);
        $adding = array_filter($d['state'][$t] ?? [], fn ($s) => $s === 'add');
        if (! $adding) {
            return;
        }

        $rows = $src->query('select '.($hasId ? 'id' : 'rowid')." as __key, * from \"$t\"")->fetchAll();
        $cols = array_values(array_diff(array_keys($d['cols'][$t]), $hasId ? ['id'] : []));
        $insert = $this->dst->prepare("insert into \"$t\" (".implode(', ', array_map(fn ($c) => "\"$c\"", $cols)).') values ('.implode(', ', array_fill(0, count($cols), '?')).')');

        foreach ($rows as $row) {
            $key = $row['__key'];
            if (! isset($adding[$key])) {
                continue;
            }
            $values = $row;
            $later = [];

            foreach ($d['refs'][$t] ?? [] as $r) {
                $col = $r['col'];
                $v = $row[$col] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
                if ($r['kind'] === 'json') {
                    $later[] = ['col' => $col, 'json' => $r['keys'], 'value' => $v];

                    continue;
                }
                $to = match ($r['kind']) {
                    'conditional' => $r['targets'][$row[$r['on']] ?? ''] ?? null,
                    'morph' => $d['morphs'][$row[$r['type']] ?? ''] ?? null,
                    default => $r['to'],
                };
                if ($to === null) {
                    // A type nothing places: the Planner left this row out if
                    // the link is required, so here it may only be emptied —
                    // never kept, a staging id is a different row in production.
                    $values[$col] = null;

                    continue;
                }
                if (isset($this->map[$to][$v])) {
                    $values[$col] = $this->map[$to][$v];

                    continue;
                }
                $pending = in_array($to, $d['order'], true) && ($d['state'][$to][$v] ?? null) === 'add';
                if ($pending) {
                    // Written later in this run (its own table, or a cycle).
                    if ($r['req']) {
                        throw new RuntimeException("$t.$col must point at $to #$v, which is not written yet");
                    }
                    $later[] = ['col' => $col, 'to' => $to, 'value' => $v];
                }
                $values[$col] = null;
            }

            $this->rename($t, $values, $d, $key);
            $this->noteFiles($values);

            $insert->execute(array_map(fn ($c) => $values[$c] ?? null, $cols));
            $newKey = (int) $this->dst->lastInsertId();
            if ($hasId) {
                $this->map[$t][$key] = $newKey;
            }
            foreach ($later as $p) {
                $this->patches[] = ['t' => $t, 'rowid' => $newKey] + $p;
            }
            $this->written[$t] = ($this->written[$t] ?? 0) + 1;
        }
    }

    /** Derived values that would collide, and copies of a record both sides hold. */
    private function rename(string $t, array &$values, array $d, int|string $key): void
    {
        if ($t === 'users' && ! empty($values['slug'])) {
            $base = $values['slug'];
            $n = 1;
            $q = $this->dst->prepare('select 1 from users where slug = ?');
            while (true) {
                $q->execute([$values['slug']]);
                if (! $q->fetchColumn()) {
                    break;
                }
                $values['slug'] = $base.'-'.(++$n);
            }
        }

        if (isset($d['copies'][$t][$key]) && $t === 'club_events') {
            $values['uuid'] = (string) Str::uuid();
            $values['visibility'] = ClubEvent::HIDDEN;
        }
    }

    private function noteFiles(array $values): void
    {
        if (! $this->sourceStorage) {
            return;
        }
        foreach ($values as $col => $value) {
            if (! is_string($col) || ! is_string($value) || $value === '' || ! preg_match(Policy::FILE_COLUMN, $col)
                || str_contains($value, '://') || ! str_contains($value, '/') || str_starts_with($value, '/') || str_contains($value, '..')) {
                continue;
            }
            foreach (['app/public/', 'app/private/', 'app/'] as $disk) {
                if (is_file($this->sourceStorage.'/'.$disk.$value)) {
                    $this->files[$value] = $disk;
                    break;
                }
            }
        }
    }

    private function settlePatches(array $d): void
    {
        foreach ($this->patches as $p) {
            if (isset($p['json'])) {
                $data = json_decode($p['value'], true);
                if (! is_array($data)) {
                    continue;
                }
                $walk = function (array $node) use (&$walk, $p): array {
                    foreach ($node as $k => $v) {
                        if (is_array($v)) {
                            $node[$k] = $walk($v);
                        } elseif (is_string($k) && isset($p['json'][$k]) && is_numeric($v)) {
                            $node[$k] = $this->map[$p['json'][$k]][(int) $v] ?? null;
                        }
                    }

                    return $node;
                };
                $value = json_encode($walk($data));
            } else {
                $value = $this->map[$p['to']][$p['value']] ?? null;
            }
            $this->dst->prepare("update \"{$p['t']}\" set \"{$p['col']}\" = ? where rowid = ?")->execute([$value, $p['rowid']]);
        }
    }

    private function remember(array $d): void
    {
        $now = now()->toDateTimeString();
        $q = $this->dst->prepare('insert or ignore into '.self::MAP_TABLE.' (source_table, source_key, target_id, promoted_at) values (?, ?, ?, ?)');
        foreach ($this->map as $t => $rows) {
            foreach ($rows as $key => $id) {
                $q->execute([$t, (string) $key, $id, $now]);
            }
        }
    }

    /** Refuse to commit anything that is not exactly the plan, cleanly. */
    private function verify(array $report, array $brokenBefore): void
    {
        foreach ($report['tables'] as $t => $r) {
            $planned = $r['counts']['add'] ?? 0;
            if (($this->written[$t] ?? 0) !== $planned) {
                throw new RuntimeException("$t: planned $planned, wrote ".($this->written[$t] ?? 0));
            }
        }

        $new = [];
        foreach ($this->brokenReferences() as $k => $n) {
            if ($n > ($brokenBefore[$k] ?? 0)) {
                $new[] = "$k (+".($n - ($brokenBefore[$k] ?? 0)).')';
            }
        }
        if ($new) {
            throw new RuntimeException('would leave broken references: '.implode(', ', $new));
        }

        $integrity = $this->dst->query('PRAGMA integrity_check')->fetchColumn();
        if ($integrity !== 'ok') {
            throw new RuntimeException("integrity check: $integrity");
        }
    }

    /**
     * The files that belong to the added rows, after the commit. Never
     * overwrites a file that is already there.
     *
     * @return array{copied: int, already_there: int, bytes: int, missing: int}
     */
    private function copyFiles(): array
    {
        $out = ['copied' => 0, 'already_there' => 0, 'bytes' => 0, 'missing' => 0];
        if (! $this->targetStorage) {
            return $out;
        }
        foreach ($this->files as $path => $disk) {
            $from = $this->sourceStorage.'/'.$disk.$path;
            $to = $this->targetStorage.'/'.$disk.$path;
            if (is_file($to)) {
                $out['already_there']++;

                continue;
            }
            if (! is_file($from)) {
                $out['missing']++;

                continue;
            }
            @mkdir(dirname($to), 0775, true);
            if (copy($from, $to)) {
                $out['copied']++;
                $out['bytes'] += filesize($to);
            } else {
                $out['missing']++;
            }
        }

        return $out;
    }
}
