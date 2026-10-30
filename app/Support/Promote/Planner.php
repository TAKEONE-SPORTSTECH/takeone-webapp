<?php

namespace App\Support\Promote;

use App\Support\MorphMap;
use PDO;

/**
 * Decides, for every row in the staging database, what promoting it into
 * production means — without writing anything (see Policy for the rules).
 *
 *   link     production already has it (same uuid, same person, same club,
 *            same unique key) → nothing is added; its children point at it
 *   add      production lacks it → it is added with a NEW id, every reference
 *            in it rewritten
 *   test     marked as test data, or the rule says so (demo accounts)
 *   cascade  it belongs to something that is not being copied
 *   dangling it points at a row that does not exist and cannot be cleared
 *
 * A reference that cannot be kept but may be empty is CLEARED instead of
 * taking the row down with it (counted per column in the report).
 */
final class Planner
{
    private PDO $src;

    private PDO $dst;

    /** @var array<int, string> */
    private array $tables = [];

    /** @var array<string, array<string, array{notnull: bool}>> */
    private array $cols = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $refs = [];

    /** @var array<string, array<int, array<int, string>>> */
    private array $uniques = [];

    /** @var array<string, string> morph type value => table */
    private array $morphs = [];

    /** @var array<string, array<int|string, string>> table => source key => state */
    private array $state = [];

    /** @var array<string, array<int|string, int>> table => source id => production id */
    private array $map = [];

    /** @var array<string, array<string, int>> */
    private array $how = [];

    /** @var array<string, int> */
    private array $cleared = [];

    /** @var array<string, array{count: int, samples: array<int, string>}> */
    private array $conflicts = [];

    /** @var array<int, string> */
    private array $unknown = [];

    /** @var array<int, string> */
    private array $cycles = [];

    /** @var array<int, string> */
    private array $possibleSamePerson = [];

    /** @var array<string, int> path => bytes */
    private array $files = [];

    /** @var array<string, int> */
    private array $nextId = [];

    /** @var array<string, bool> tables already decided */
    private array $decided = [];

    /**
     * Optional links, checked once every table is decided — a link to a
     * table decided later is not a missing link.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $deferred = [];

    /** @var array<string, int> "table.column" => copies given a new value */
    private array $renamed = [];

    /** @var array<string, bool> "table.type_column = value" no vocabulary places */
    private array $unknownMorphs = [];

    /** @var array<int, string> staging people whose only production account is a deleted one */
    private array $deletedAccounts = [];

    /**
     * Staging member id => production member id, for people entered under the
     * same name in the same event on both sides (an event both servers hold).
     *
     * @var array<int, int>
     */
    private array $sharedEventPeople = [];

    /**
     * @param  array{tenants?: array<int, string>, club_events?: array<int, string>, users?: array<int, string>}  $exclude
     */
    public function __construct(string $sourcePath, string $targetPath, private array $exclude = [], private ?string $storageRoot = null, ?PDO $target = null)
    {
        $open = function (string $path): PDO {
            $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $db->exec('PRAGMA query_only = ON');

            return $db;
        };
        $this->src = $open($sourcePath);
        // The Applier passes its own (writable, locked) connection so the plan
        // is read inside the same transaction that writes it. The planner
        // itself only ever reads from it.
        $this->dst = $target ?? $open($targetPath);
    }

    /** @var array<int, string> */
    private array $order = [];

    /** @var array<string, array<int|string, int>> table => staging key => production id, from earlier runs */
    private array $promoted = [];

    /** @var array<string, array<int|string, bool>> rows to add as a separate copy of a record both sides hold */
    private array $copies = [];

    /** @return array<string, mixed> */
    public function plan(): array
    {
        $this->loadSchema();
        $this->loadMorphs();
        $this->loadSharedEventPeople();
        $this->loadPromoted();

        foreach ($this->order = $this->order() as $table) {
            $this->decide($table);
            $this->decided[$table] = true;
        }
        $this->settleDeferred();

        return $this->report();
    }

    // ── schema ────────────────────────────────────────────────────────────

    private function loadSchema(): void
    {
        $list = fn (PDO $db) => $db->query("select name from sqlite_master where type='table' and name not like 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        $this->tables = array_values(array_diff(array_intersect($list($this->src), $list($this->dst)), array_keys(Policy::SKIP)));
        sort($this->tables);

        foreach ($this->tables as $t) {
            foreach ($this->src->query("pragma table_info(\"$t\")") as $c) {
                // NOT NULL is required even with a default: a link that cannot
                // be kept cannot be emptied either (found in rehearsal 2026-10-30).
                $this->cols[$t][$c['name']] = ['notnull' => (bool) $c['notnull']];
            }
            foreach ($this->dst->query("pragma index_list(\"$t\")") as $ix) {
                if ($ix['unique'] && $ix['origin'] !== 'pk') {
                    $this->uniques[$t][] = array_column($this->dst->query("pragma index_info(\"{$ix['name']}\")")->fetchAll(), 'name');
                }
            }
            $this->nextId[$t] = (int) ($this->has($t, 'id') ? $this->dst->query("select coalesce(max(id),0) from \"$t\"")->fetchColumn() : 0) + 1;
        }

        foreach ($this->tables as $t) {
            $fks = [];
            foreach ($this->src->query("pragma foreign_key_list(\"$t\")") as $fk) {
                $fks[$fk['from']] = $fk['table'];
            }
            foreach (array_keys($this->cols[$t]) as $c) {
                $key = "$t.$c";
                $req = $this->cols[$t][$c]['notnull'];
                if ($c === 'id' || in_array($key, Policy::VALUES, true)) {
                    continue;
                }
                if (isset($fks[$c])) {
                    $this->refs[$t][] = ['col' => $c, 'to' => $fks[$c], 'kind' => 'fk', 'req' => $req];
                } elseif (isset(Policy::REFS[$key])) {
                    $this->refs[$t][] = ['col' => $c, 'to' => Policy::REFS[$key], 'kind' => 'named', 'req' => $req];
                } elseif (isset(Policy::CONDITIONAL[$key])) {
                    [$on, $targets] = Policy::CONDITIONAL[$key];
                    $this->refs[$t][] = ['col' => $c, 'on' => $on, 'targets' => $targets, 'kind' => 'conditional', 'req' => $req];
                } elseif (str_ends_with($c, '_by') && ! in_array($key, Policy::BY_EXCEPTIONS, true)) {
                    $this->refs[$t][] = ['col' => $c, 'to' => 'users', 'kind' => 'by', 'req' => $req];
                } elseif (str_ends_with($c, '_id') && isset($this->cols[$t][substr($c, 0, -3).'_type'])) {
                    $this->refs[$t][] = ['col' => $c, 'type' => substr($c, 0, -3).'_type', 'kind' => 'morph', 'req' => $req];
                } elseif (str_ends_with($c, '_id')) {
                    $this->unknown[] = $key;
                }
            }
            foreach (Policy::JSON as $key => $keys) {
                [$jt, $jc] = explode('.', $key);
                if ($jt === $t && isset($this->cols[$t][$jc])) {
                    $this->refs[$t][] = ['col' => $jc, 'keys' => $keys, 'kind' => 'json', 'req' => false];
                }
            }
        }
    }

    /**
     * Every name a polymorphic *_type column may hold: the platform's morph
     * map, the parties' own vocabulary (App\Parties\Kinds — 'person',
     * 'federation', 'event'…), and full class names from before the map.
     * A type found in the data that none of these places is UNKNOWN: its link
     * is never copied with a staging id in it (that id means a different row
     * in production) — it is cleared, or the row is left out, and reported.
     */
    private function loadMorphs(): void
    {
        foreach (MorphMap::map() + \App\Parties\Kinds::map() as $alias => $class) {
            $table = (new $class)->getTable();
            $this->morphs[$alias] ??= $table;
            $this->morphs[$class] = $table;
        }

        foreach ($this->tables as $t) {
            foreach ($this->refs[$t] ?? [] as $r) {
                if ($r['kind'] !== 'morph') {
                    continue;
                }
                foreach ($this->src->query("select distinct \"{$r['type']}\" from \"$t\" where \"{$r['type']}\" is not null and \"{$r['type']}\" <> ''")->fetchAll(PDO::FETCH_COLUMN) as $type) {
                    if (isset($this->morphs[$type])) {
                        continue;
                    }
                    if (class_exists($type) && is_subclass_of($type, \Illuminate\Database\Eloquent\Model::class)) {
                        $this->morphs[$type] = (new $type)->getTable();
                    } else {
                        $this->unknownMorphs["$t.{$r['type']} = $type"] = true;
                    }
                }
            }
        }
    }

    /**
     * Strong evidence two accounts are one person: the same name, entered in
     * the same event, on both servers. A name alone never links anyone.
     */
    private function loadSharedEventPeople(): void
    {
        $sql = 'select e.uuid, u.id, u.full_name from club_event_registrations r
                join club_events e on e.id = r.event_id join users u on u.id = r.user_id';
        $norm = fn ($s) => preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $s)));

        $prod = [];
        foreach ($this->dst->query($sql.' where u.deleted_at is null') as $r) {
            $prod[$r['uuid']][$norm($r['full_name'])][] = (int) $r['id'];
        }
        foreach ($this->src->query($sql) as $r) {
            $hits = array_unique($prod[$r['uuid']][$norm($r['full_name'])] ?? []);
            if ($norm($r['full_name']) !== '' && count($hits) === 1) {
                $this->sharedEventPeople[(int) $r['id']] = $hits[0];
            }
        }
    }

    /**
     * What earlier runs already brought over (Applier::MAP_TABLE). A row
     * promoted before is LINKED to what it became, so shipping again adds only
     * what is new — never a second copy.
     */
    private function loadPromoted(): void
    {
        $exists = $this->dst->query("select 1 from sqlite_master where type='table' and name='".Applier::MAP_TABLE."'")->fetchColumn();
        if (! $exists) {
            return;
        }
        foreach ($this->dst->query('select source_table, source_key, target_id from '.Applier::MAP_TABLE) as $r) {
            $this->promoted[$r['source_table']][$r['source_key']] = (int) $r['target_id'];
        }
    }

    /**
     * Everything the Applier needs to carry the plan out.
     *
     * @return array<string, mixed>
     */
    public function decisions(): array
    {
        return [
            'order' => $this->order,
            'cols' => $this->cols,
            'refs' => $this->refs,
            'state' => $this->state,
            'map' => $this->map,
            'copies' => $this->copies,
            'morphs' => $this->morphs,
        ];
    }

    public function source(): PDO
    {
        return $this->src;
    }

    private function has(string $table, string $col): bool
    {
        return isset($this->cols[$table][$col]);
    }

    /**
     * Tables in an order where every REQUIRED link of a row is decided before
     * the row. Optional links (nullable columns, ids in JSON) do not order
     * anything: they are checked at the end (settleDeferred).
     */
    private function order(): array
    {
        $deps = [];
        foreach ($this->tables as $t) {
            $deps[$t] = [];
            foreach ($this->refs[$t] ?? [] as $r) {
                if ($r['kind'] === 'json') {
                    continue;
                }
                foreach ($this->targetsOf($t, $r) as $to) {
                    if ($to !== $t && in_array($to, $this->tables, true)) {
                        // true = required. Optional links order too, so a unique
                        // key holding one (persons.user_id) is matched with the
                        // link already translated — found in rehearsal 2026-10-30.
                        $deps[$t][$to] = ($deps[$t][$to] ?? false) || $r['req'];
                    }
                }
            }
        }

        $order = [];
        while ($deps) {
            // Ready: nothing it points at is still waiting.
            $ready = array_keys(array_filter($deps, fn ($d) => ! array_intersect_key($d, $deps)));
            if (! $ready) {
                // A loop. Break it where only OPTIONAL links wait (settled at the
                // end); a loop of required links is reported.
                $pick = null;
                foreach ($deps as $t => $d) {
                    if (! in_array(true, array_intersect_key($d, $deps), true)) {
                        $pick = $t;
                        break;
                    }
                }
                if ($pick === null) {
                    $pick = array_key_first($deps);
                    $this->cycles[] = $pick.' ← '.implode(', ', array_keys(array_intersect_key($deps[$pick], $deps)));
                }
                $ready = [$pick];
            }
            foreach ($ready as $t) {
                $order[] = $t;
                unset($deps[$t]);
            }
        }

        return $order;
    }

    /** @return array<int, string> */
    private function targetsOf(string $table, array $r): array
    {
        return match ($r['kind']) {
            'conditional' => array_values($r['targets']),
            'json' => array_values($r['keys']),
            'morph' => array_values(array_unique(array_filter(array_map(
                fn ($type) => $this->morphs[$type] ?? null,
                $this->src->query("select distinct \"{$r['type']}\" from \"$table\" where \"{$r['type']}\" is not null")->fetchAll(PDO::FETCH_COLUMN),
            )))),
            default => [$r['to']],
        };
    }

    // ── deciding each row ─────────────────────────────────────────────────

    private function decide(string $t): void
    {
        $key = $this->has($t, 'id') ? 'id' : 'rowid';
        $rows = $this->src->query('select '.($key === 'rowid' ? 'rowid as __key, ' : 'id as __key, ')."* from \"$t\"")->fetchAll();
        $selfRefs = [];

        $dstUuid = $this->has($t, 'uuid') ? $this->dst->query("select uuid, id from \"$t\" where uuid is not null")->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $before = $key === 'id' ? ($this->promoted[$t] ?? []) : [];
        $stillThere = $before ? array_flip($this->dst->query("select id from \"$t\" where id in (".implode(',', array_map('intval', $before)).')')->fetchAll(PDO::FETCH_COLUMN)) : [];

        foreach ($rows as $row) {
            $id = $row['__key'];

            if (isset($before[$id])) {
                if (isset($stillThere[$before[$id]])) {
                    $this->set($t, $id, 'link', 'promoted on an earlier run');
                    $this->map[$t][$id] = $before[$id];
                } else {
                    // Brought over once and since removed in production: that
                    // was somebody's decision, and it is not undone.
                    $this->set($t, $id, 'cascade', 'removed from production after an earlier run');
                }

                continue;
            }

            if ($this->isTest($t, $row)) {
                $this->set($t, $id, 'test', 'marked as test');

                continue;
            }

            [$ok, $remapped, $why] = $this->resolveRefs($t, $row, $selfRefs);
            if (! $ok) {
                $this->set($t, $id, $why === 'cascade' ? 'cascade' : 'dangling', $why);

                continue;
            }

            if ($match = $this->identity($t, $row, $dstUuid, $remapped)) {
                [$dstId, $how] = $match;
                if ($dstId === -1) {
                    $this->set($t, $id, 'dangling', $how);

                    continue;
                }
                $this->set($t, $id, 'link', $how);
                if ($key === 'id') {
                    $this->map[$t][$id] = $dstId;
                }

                continue;
            }

            $this->set($t, $id, 'add', 'added');
            if ($key === 'id') {
                $this->map[$t][$id] = $this->nextId[$t]++;
            }
            $this->collectFiles($t, $row);
        }

        // A row pointing at another row of its own table, now that all are decided.
        foreach ($selfRefs as [$id, $col, $value, $req]) {
            if (in_array($this->state[$t][$value] ?? 'missing', ['add', 'link'], true)) {
                continue;
            }
            if (($this->state[$t][$id] ?? null) !== 'add') {
                continue;
            }
            if ($req) {
                $this->state[$t][$id] = 'dangling';
                $this->how[$t]['added']--;
                $this->how[$t]["points at a row of its own table that is not copied ($col)"] = ($this->how[$t]["points at a row of its own table that is not copied ($col)"] ?? 0) + 1;
            } else {
                $this->cleared["$t.$col"] = ($this->cleared["$t.$col"] ?? 0) + 1;
            }
        }
    }

    private function set(string $t, int|string $id, string $state, string $how): void
    {
        $this->state[$t][$id] = $state;
        $this->how[$t][$how] = ($this->how[$t][$how] ?? 0) + 1;
    }

    /**
     * @return array{0: bool, 1: array<string, mixed>, 2: string}
     */
    private function resolveRefs(string $t, array $row, array &$selfRefs): array
    {
        $remapped = [];

        foreach ($this->refs[$t] ?? [] as $r) {
            $value = $row[$r['col']] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if ($r['kind'] === 'json') {
                $this->deferred[] = ['t' => $t, 'id' => $row['__key'], 'col' => $r['col'], 'json' => $r['keys'], 'value' => $value];

                continue;
            }

            $to = match ($r['kind']) {
                'conditional' => $r['targets'][$row[$r['on']] ?? ''] ?? null,
                'morph' => $this->morphs[$row[$r['type']] ?? ''] ?? null,
                default => $r['to'],
            };

            if ($to === null) {
                // A type nothing places (see loadMorphs): never copied with a
                // staging id in it — cleared if it may be empty, else the row
                // cannot be copied.
                $to = '(unknown type)';
            }

            if ($to === $t) {
                $selfRefs[] = [$row['__key'], $r['col'], $value, $r['req']];

                continue;
            }

            $known = ! in_array($to, $this->tables, true) || isset($this->decided[$to]);

            if (! $r['req'] || ! $known) {
                // Optional, or required inside a cycle: settled at the end.
                $this->deferred[] = ['t' => $t, 'id' => $row['__key'], 'col' => $r['col'], 'to' => $to, 'value' => $value, 'req' => $r['req']];
                if ($known) {
                    $remapped[$r['col']] = $this->map[$to][$value] ?? null;
                } else {
                    $remapped['__unknown'][$r['col']] = true;
                }

                continue;
            }

            $state = in_array($to, $this->tables, true) ? ($this->state[$to][$value] ?? 'missing') : 'skipped';

            if ($state === 'add' || $state === 'link') {
                $remapped[$r['col']] = $this->map[$to][$value] ?? null;

                continue;
            }

            return [false, [], in_array($state, ['test', 'cascade'], true)
                ? 'cascade'
                : "needs {$r['col']} → $to, which is ".($state === 'skipped' ? 'not copied' : $state)];
        }

        return [true, $remapped, ''];
    }

    /**
     * Every link left for the end, now that every row is decided. Only rows
     * being ADDED matter — a linked or left-out row is not written at all.
     */
    private function settleDeferred(): void
    {
        $kept = fn (string $to, $value) => in_array($this->state[$to][$value] ?? 'missing', ['add', 'link'], true);

        foreach ($this->deferred as $d) {
            if (($this->state[$d['t']][$d['id']] ?? null) !== 'add') {
                continue;
            }

            if (isset($d['json'])) {
                $data = json_decode($d['value'], true);
                if (is_array($data)) {
                    array_walk_recursive($data, function ($v, $k) use ($d, $kept) {
                        if (is_string($k) && isset($d['json'][$k]) && is_numeric($v) && ! $kept($d['json'][$k], (int) $v)) {
                            $this->cleared["{$d['t']}.{$d['col']}→$k"] = ($this->cleared["{$d['t']}.{$d['col']}→$k"] ?? 0) + 1;
                        }
                    });
                }

                continue;
            }

            if (in_array($d['to'], $this->tables, true) && $kept($d['to'], $d['value'])) {
                continue;
            }

            if ($d['req']) {
                // A required link inside a cycle that did not survive.
                $this->state[$d['t']][$d['id']] = 'dangling';
                $this->how[$d['t']]['added']--;
                $why = "needs {$d['col']} → {$d['to']}, which is not copied";
                $this->how[$d['t']][$why] = ($this->how[$d['t']][$why] ?? 0) + 1;
            } else {
                $this->cleared["{$d['t']}.{$d['col']}"] = ($this->cleared["{$d['t']}.{$d['col']}"] ?? 0) + 1;
            }
        }
    }

    private function isTest(string $t, array $row): bool
    {
        return match ($t) {
            'tenants' => str_starts_with((string) $row['slug'], 'demo-') || in_array($row['slug'], $this->exclude['tenants'] ?? [], true),
            'club_events' => in_array($row['uuid'] ?? '', $this->exclude['club_events'] ?? [], true),
            'users' => str_ends_with(strtolower((string) $row['email']), '@demo.takeone.bh') || in_array($row['uuid'] ?? '', $this->exclude['users'] ?? [], true),
            default => false,
        };
    }

    /**
     * Does production already have this row? [production id, how], or [-1, why]
     * when a unique key collides with a DIFFERENT record, or null when it is new.
     *
     * @return array{0: int, 1: string}|null
     */
    private function identity(string $t, array $row, array $dstUuid, array $remapped): ?array
    {
        if ($t === 'users') {
            return $this->samePerson($row);
        }
        if ($t === 'tenants') {
            $id = $this->dst->prepare('select id from tenants where slug = ?');
            $id->execute([$row['slug']]);
            if ($found = $id->fetchColumn()) {
                return [(int) $found, 'same club (slug)'];
            }
        }
        if (isset(Policy::NATURAL_KEYS[$t])) {
            $c = Policy::NATURAL_KEYS[$t];
            $q = $this->dst->prepare("select id from \"$t\" where \"$c\" = ?");
            $q->execute([$row[$c] ?? null]);
            if ($found = $q->fetchColumn()) {
                return [(int) $found, "same $c"];
            }
        }
        if ($t === 'club_events' && isset($row['uuid'], $dstUuid[$row['uuid']])) {
            // Both servers ran this event and edited it apart. Production's
            // stays exactly as it is; staging's is added beside it as its own
            // UNLISTED event (new uuid), so both versions exist and nobody
            // sees the event twice in a list.
            $this->renamed['club_events: copied beside production\'s own, unlisted'] = ($this->renamed['club_events: copied beside production\'s own, unlisted'] ?? 0) + 1;
            $this->copies[$t][$row['__key']] = true;

            return null;
        }
        if ($dstUuid && isset($row['uuid'], $dstUuid[$row['uuid']])) {
            return [(int) $dstUuid[$row['uuid']], 'same uuid'];
        }

        foreach ($this->uniques[$t] ?? [] as $cols) {
            if (array_intersect($cols, array_keys($remapped['__unknown'] ?? []))) {
                continue;
            }
            if ($this->renameable($t, $cols, $row)) {
                continue;
            }
            $values = [];
            foreach ($cols as $c) {
                $v = array_key_exists($c, $remapped) ? $remapped[$c] : ($row[$c] ?? null);
                if ($v === null) {
                    continue 2;
                }
                $values[] = $v;
            }
            $q = $this->dst->prepare('select '.($this->has($t, 'id') ? 'id' : 'rowid')." from \"$t\" where ".implode(' and ', array_map(fn ($c) => "\"$c\" = ?", $cols)).' limit 1');
            $q->execute($values);
            $found = $q->fetchColumn();
            if ($found === false) {
                continue;
            }
            // A table with its own uuid names its rows by it: a different uuid
            // with the same unique key is a different record that collides.
            if ($dstUuid || $t === 'tenants') {
                $this->conflict($t, implode('+', $cols), $row);

                return [-1, 'unique key ('.implode('+', $cols).') taken by a different record'];
            }

            return [(int) $found, 'same unique key ('.implode('+', $cols).')'];
        }

        // A link table with no id and no unique key: present when an IDENTICAL
        // row is (its links translated). Otherwise a second run adds it again.
        if (! $this->has($t, 'id') && ! array_intersect(array_keys($this->cols[$t]), array_keys($remapped['__unknown'] ?? []))) {
            $cols = array_keys($this->cols[$t]);
            $values = array_map(fn ($c) => array_key_exists($c, $remapped) ? $remapped[$c] : ($row[$c] ?? null), $cols);
            $q = $this->dst->prepare("select rowid from \"$t\" where ".implode(' and ', array_map(fn ($c) => "\"$c\" is ?", $cols)).' limit 1');
            $q->execute($values);
            if (($found = $q->fetchColumn()) !== false) {
                return [(int) $found, 'identical row'];
            }
        }

        return null;
    }

    /** @return array{0: int, 1: string}|null */
    private function samePerson(array $row): ?array
    {
        $find = function (string $sql, array $args) {
            $q = $this->dst->prepare($sql);
            $q->execute($args);

            return $q->fetch() ?: null;
        };

        /*
         * ACTIVE accounts only. Production keeps deleted copies of people
         * (merged duplicates — one person had five accounts under one email,
         * four of them deleted), and linking to one of those hands the
         * person's events to an account that cannot sign in (found in
         * rehearsal 2026-10-30). Only when production holds nothing but a
         * deleted copy of the SAME account (same uuid) is that linked — it is
         * the account production chose to delete — and it is listed.
         */
        $active = 'deleted_at is null';
        if (! empty($row['uuid']) && $hit = $find("select id from users where uuid = ? and $active", [$row['uuid']])) {
            return [(int) $hit['id'], 'same person (uuid)'];
        }
        if (! empty($row['email']) && $hit = $find("select id from users where lower(email) = lower(?) and $active order by id desc", [$row['email']])) {
            return [(int) $hit['id'], 'same person (email)'];
        }
        if (isset($this->sharedEventPeople[(int) $row['id']])) {
            return [$this->sharedEventPeople[(int) $row['id']], 'same person (same name, same event)'];
        }
        if (! empty($row['mobile'])) {
            $hit = $find("select id, full_name from users where mobile = ? and $active order by id desc", [$row['mobile']]);
            if ($hit && $this->sameName($hit['full_name'], $row['full_name'] ?? '')) {
                return [(int) $hit['id'], 'same person (phone + name)'];
            }
            if ($hit) {
                $this->possibleSamePerson[] = "{$row['full_name']} (staging #{$row['id']}) shares a phone with {$hit['full_name']} (production #{$hit['id']}) — added as a separate person";
            }
        }
        foreach ($this->uniques['users'] ?? [] as $cols) {
            if ($cols === ['email'] || $cols === ['uuid'] || $this->renameable('users', $cols, $row)) {
                continue;
            }
            $values = array_map(fn ($c) => $row[$c] ?? null, $cols);
            if (in_array(null, $values, true) || in_array('', $values, true)) {
                continue;
            }
            $q = $this->dst->prepare('select id from users where '.implode(' and ', array_map(fn ($c) => "\"$c\" = ?", $cols)));
            $q->execute($values);
            if ($q->fetchColumn() !== false) {
                $this->conflict('users', implode('+', $cols), $row);

                return [-1, 'unique key ('.implode('+', $cols).') taken by a different person'];
            }
        }

        if (! empty($row['uuid']) && $hit = $find('select id from users where uuid = ? and deleted_at is not null', [$row['uuid']])) {
            $this->deletedAccounts[] = "{$row['full_name']} (staging #{$row['id']}) is production account #{$hit['id']}, which production DELETED — linked to it, so it stays deleted";

            return [(int) $hit['id'], 'same person (uuid) — deleted in production'];
        }

        return null;
    }

    /**
     * A single derived column (Policy::RENAMEABLE): when production already
     * uses the value, the copy will take the next free one — counted, never
     * a reason to refuse the row.
     */
    private function renameable(string $t, array $cols, array $row): bool
    {
        if (count($cols) !== 1 || ! in_array("$t.{$cols[0]}", Policy::RENAMEABLE, true)) {
            return false;
        }
        $value = $row[$cols[0]] ?? null;
        if ($value !== null && $value !== '') {
            $q = $this->dst->prepare("select 1 from \"$t\" where \"{$cols[0]}\" = ? limit 1");
            $q->execute([$value]);
            if ($q->fetchColumn()) {
                $this->renamed["$t.{$cols[0]}"] = ($this->renamed["$t.{$cols[0]}"] ?? 0) + 1;
            }
        }

        return true;
    }

    private function sameName(?string $a, ?string $b): bool
    {
        $n = fn ($s) => preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $s)));

        return $n($a) !== '' && $n($a) === $n($b);
    }

    private function conflict(string $t, string $index, array $row): void
    {
        $this->conflicts["$t ($index)"]['count'] = ($this->conflicts["$t ($index)"]['count'] ?? 0) + 1;
        if (count($this->conflicts["$t ($index)"]['samples'] ?? []) < 5) {
            $label = $row['full_name'] ?? $row['name'] ?? $row['title'] ?? $row['club_name'] ?? $row['slug'] ?? ('#'.$row['__key']);
            $this->conflicts["$t ($index)"]['samples'][] = (string) $label;
        }
    }

    private function collectFiles(string $t, array $row): void
    {
        if (! $this->storageRoot) {
            return;
        }
        foreach ($row as $col => $value) {
            if (! is_string($value) || $value === '' || ! preg_match(Policy::FILE_COLUMN, $col)
                || str_contains($value, '://') || ! str_contains($value, '/') || str_starts_with($value, '/') || str_contains($value, '..')) {
                continue;
            }
            foreach (['app/public/', 'app/private/', 'app/'] as $disk) {
                $file = $this->storageRoot.'/'.$disk.$value;
                if (is_file($file)) {
                    $this->files[$disk.$value] = filesize($file);
                    break;
                }
            }
        }
    }

    // ── the report ────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function report(): array
    {
        $tables = [];
        $totals = ['add' => 0, 'link' => 0, 'test' => 0, 'cascade' => 0, 'dangling' => 0];
        foreach ($this->state as $t => $rows) {
            $counts = array_count_values($rows);
            foreach ($counts as $s => $n) {
                $totals[$s] += $n;
            }
            $tables[$t] = ['counts' => $counts, 'how' => array_filter($this->how[$t] ?? [])];
        }
        ksort($tables);

        $candidates = [
            'clubs' => $this->src->query("select slug, club_name, (select count(*) from memberships m where m.tenant_id = tenants.id) members from tenants order by id")->fetchAll(),
            'events' => $this->src->query("select e.uuid, e.title, substr(e.date,1,10) date, (select count(*) from club_event_registrations r where r.event_id = e.id) entries from club_events e order by e.id")->fetchAll(),
        ];

        return [
            'totals' => $totals,
            'tables' => $tables,
            'skipped_tables' => Policy::SKIP,
            'cleared_links' => $this->cleared,
            'renamed' => $this->renamed,
            'unknown_morph_types' => array_keys($this->unknownMorphs),
            'linked_to_deleted_accounts' => $this->deletedAccounts,
            'conflicts' => $this->conflicts,
            'unknown_columns' => $this->unknown,
            'cycles' => $this->cycles,
            'possible_same_person' => $this->possibleSamePerson,
            'files' => ['count' => count($this->files), 'bytes' => array_sum($this->files)],
            'test_candidates' => $candidates,
        ];
    }
}
