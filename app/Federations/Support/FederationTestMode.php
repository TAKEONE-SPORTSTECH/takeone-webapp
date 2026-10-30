<?php

namespace App\Federations\Support;

use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationTransfer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Test mode for one federation event.
 *
 * While an event's `test_mode_at` is set, every request that works on that
 * event (its invitation link, and the staff actions aimed at its test rows —
 * see RecordFederationTest) runs with the recorder ACTIVE. The recorder:
 *
 *  - writes every row Eloquent CREATES into `federation_test_records` as
 *    'created' — clubs, officials, athletes, their accounts and photos,
 *    entries, notifications, the platform club an approval makes, all of it;
 *  - before Eloquent first CHANGES or DELETES a row that is not a test row,
 *    keeps a copy of it as it was ('snapshot').
 *
 * Switching test mode off (purge()) puts every snapshot back, deletes every
 * created row and the files only they pointed at, and empties the ledger —
 * the event is left as it stood before the test began.
 *
 * Everything happens inside the request's own transaction, so a failed
 * submission leaves no ledger rows either. Writes that bypass Eloquent (raw
 * DB::table, mass ->update()) are not seen — none of them touches a real row
 * on the paths this is switched on for (checked 2026-09-27).
 */
final class FederationTestMode
{
    public const TABLE = 'federation_test_records';

    private static ?int $eventId = null;

    private static bool $listening = false;

    /** @var array<string, bool> "table|key" known to be a test row or already snapshotted */
    private static array $seen = [];

    /** Tables never recorded: the ledger itself. */
    private const IGNORED = [self::TABLE];

    public static function isOn(?FederationEvent $e): bool
    {
        return $e !== null && $e->test_mode_at !== null;
    }

    public static function isActive(): bool
    {
        return self::$eventId !== null;
    }

    public static function activate(FederationEvent $e): void
    {
        if (! self::isOn($e)) {
            return;
        }
        self::listen();
        self::$eventId = $e->id;
    }

    public static function deactivate(): void
    {
        self::$eventId = null;
        self::$seen = [];
    }

    /** "[TEST] " while recording, so a code or a notification says what it is. */
    public static function label(): string
    {
        return self::isActive() ? '[TEST] ' : '';
    }

    private static function listen(): void
    {
        if (self::$listening) {
            return;
        }
        self::$listening = true;

        Event::listen('eloquent.created: *', function (string $name, array $data) {
            if (self::$eventId !== null && ($m = $data[0] ?? null) instanceof Model) {
                self::noteCreated($m);
            }
        });
        foreach (['updating', 'deleting'] as $hook) {
            Event::listen("eloquent.{$hook}: *", function (string $name, array $data) {
                if (self::$eventId !== null && ($m = $data[0] ?? null) instanceof Model) {
                    self::noteSnapshot($m);
                }
            });
        }
    }

    private static function noteCreated(Model $m): void
    {
        $table = $m->getTable();
        $key = $m->getKey();
        if (in_array($table, self::IGNORED, true) || $key === null || is_array($key)) {
            return;
        }
        DB::table(self::TABLE)->insertOrIgnore([
            'federation_event_id' => self::$eventId,
            'kind' => 'created',
            'table_name' => $table,
            'key_name' => $m->getKeyName(),
            'record_key' => (string) $key,
            'created_at' => now(),
        ]);
        self::$seen[$table.'|'.$key] = true;
    }

    private static function noteSnapshot(Model $m): void
    {
        $table = $m->getTable();
        $key = $m->getKey();
        if (! $m->exists || in_array($table, self::IGNORED, true) || $key === null || is_array($key)) {
            return;
        }
        $id = $table.'|'.$key;
        if (isset(self::$seen[$id])) {
            return;
        }
        self::$seen[$id] = true;

        // A row this test created is simply deleted at the end; a row already
        // copied keeps its FIRST copy — the state before the test began.
        $known = DB::table(self::TABLE)->where('federation_event_id', self::$eventId)
            ->where('table_name', $table)->where('record_key', (string) $key)->exists();
        if ($known) {
            return;
        }
        DB::table(self::TABLE)->insertOrIgnore([
            'federation_event_id' => self::$eventId,
            'kind' => 'snapshot',
            'table_name' => $table,
            'key_name' => $m->getKeyName(),
            'record_key' => (string) $key,
            'original' => json_encode($m->getRawOriginal(), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => now(),
        ]);
    }

    // ------------------------------------------------------------ resolving

    /** The event a ledger row belongs to, when that row is a test row. */
    public static function eventOfTestRow(string $table, int|string|null $key): ?FederationEvent
    {
        if ($key === null) {
            return null;
        }
        $eventId = DB::table(self::TABLE)->where('kind', 'created')->where('table_name', $table)
            ->where('record_key', (string) $key)->value('federation_event_id');

        return $eventId ? FederationEvent::whereNotNull('test_mode_at')->find($eventId) : null;
    }

    /**
     * A staff request: which test-mode event (if any) it works on. Read-only —
     * the controller still authorises everything. An event named outright
     * counts when it is in test mode; anything else counts only when the row it
     * names was made by a test.
     */
    public static function eventForRequest(Request $request): ?FederationEvent
    {
        $uuid = fn ($v) => is_string($v) && Str::isUuid($v) ? $v : null;

        if ($u = $uuid($request->input('event'))) {
            $e = FederationEvent::where('uuid', $u)->first();
            if (self::isOn($e)) {
                return $e;
            }
        }

        $entries = array_filter(array_merge(
            [$uuid($request->input('entry'))],
            collect((array) $request->input('athletes', []))->map(fn ($r) => is_array($r) ? $uuid($r['entry'] ?? null) : null)->all(),
        ));
        foreach (array_slice(array_unique($entries), 0, 50) as $u) {
            $entry = FederationEntry::where('uuid', $u)->first();
            $e = $entry ? FederationEvent::find($entry->federation_event_id) : null;
            if (self::isOn($e)) {
                return $e;
            }
        }

        $club = $request->input('club');
        if (is_string($club) && preg_match('/^(fc|fa)-([0-9a-f-]{36})$/', $club, $m)) {
            $row = $m[1] === 'fc' ? FederationClub::where('uuid', $m[2])->first() : FederationClubApplication::where('uuid', $m[2])->first();
            if ($row && ($e = self::eventOfTestRow($row->getTable(), $row->getKey()))) {
                return $e;
            }
        }

        if ($u = $uuid($request->input('transfer'))) {
            $t = FederationTransfer::where('uuid', $u)->first();
            if ($t && ($e = self::eventOfTestRow($t->getTable(), $t->getKey()))) {
                return $e;
            }
        }

        $ids = array_merge(
            [$request->input('athlete'), $request->input('into')],
            array_filter((array) $request->input('athletes', []), 'is_string'),
        );
        foreach (array_slice(array_filter($ids, 'is_string'), 0, 50) as $tid) {
            $a = FederationAthlete::where('takeone_id', $tid)->first();
            if ($a && ($e = self::eventOfTestRow($a->getTable(), $a->getKey()))) {
                return $e;
            }
        }

        return null;
    }

    // ------------------------------------------------------------- the purge

    /**
     * Put the event back as it was before the test: restore every snapshot,
     * delete every row the test created and the files only those rows used,
     * and switch test mode off.
     *
     * @return array{created:int, restored:int, files:int}
     */
    public static function purge(FederationEvent $e): array
    {
        self::deactivate();
        $ledger = DB::table(self::TABLE)->where('federation_event_id', $e->id)->orderByDesc('id')->get();
        $created = $ledger->where('kind', 'created');
        $snaps = $ledger->where('kind', 'snapshot');
        $since = $e->test_mode_at ? $e->test_mode_at->getTimestamp() - 60 : time();

        // Files the test wrote: paths held by rows it created, and paths it put
        // onto real rows (present now, absent from the pre-test copy).
        $candidates = [];
        foreach ($created as $r) {
            if ($row = DB::table($r->table_name)->where($r->key_name, $r->record_key)->first()) {
                self::collectPaths((array) $row, $candidates);
            }
        }
        foreach ($snaps as $r) {
            if ($row = DB::table($r->table_name)->where($r->key_name, $r->record_key)->first()) {
                $now = [];
                $before = [];
                self::collectPaths((array) $row, $now);
                self::collectPaths((array) json_decode((string) $r->original, true), $before);
                $candidates += array_diff_key($now, $before);
            }
        }
        $tables = $ledger->pluck('table_name')->unique()->values()->all();

        $files = 0;
        // Foreign keys off for the sweep: the created rows reference each other
        // in every direction, and every one of them is going.
        Schema::withoutForeignKeyConstraints(function () use ($e, $created, $snaps, $candidates, $tables, $since, &$files) {
            DB::transaction(function () use ($e, $created, $snaps, $candidates, $tables, $since, &$files) {
                foreach ($snaps as $r) {
                    $original = (array) json_decode((string) $r->original, true);
                    if ($original === []) {
                        continue;
                    }
                    $q = DB::table($r->table_name)->where($r->key_name, $r->record_key);
                    $q->exists() ? $q->update($original) : DB::table($r->table_name)->insert($original);
                }
                $gone = [];
                foreach ($created as $r) {
                    DB::table($r->table_name)->where($r->key_name, $r->record_key)->delete();
                    $gone[$r->table_name][] = $r->record_key;
                }
                // Rows written without a model event (a role attach()) that hang
                // off a deleted test row: what the foreign key itself would do.
                self::cascade($gone);

                // Files before the rows are committed gone (CLAUDE.md → Delete
                // Files Before Records) — and only a file written during the
                // test that nothing left in the database still names.
                foreach (array_keys($candidates) as $path) {
                    foreach (['local', 'public'] as $disk) {
                        $fs = Storage::disk($disk);
                        if (! $fs->exists($path) || $fs->lastModified($path) < $since || self::referenced($path, $tables)) {
                            continue;
                        }
                        $fs->delete($path);
                        self::pruneEmptyDirs($fs->path(dirname($path)), $fs->path(''));
                        $files++;
                    }
                }

                DB::table(self::TABLE)->where('federation_event_id', $e->id)->delete();
                DB::table('federation_events')->where('id', $e->id)->update(['test_mode_at' => null]);
            });
        });

        return ['created' => $created->count(), 'restored' => $snaps->count(), 'files' => $files];
    }

    /** Every string in a row (JSON columns opened up) that looks like a stored file path. */
    private static function collectPaths(mixed $value, array &$out): void
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                self::collectPaths($v, $out);
            }

            return;
        }
        if (! is_string($value) || $value === '') {
            return;
        }
        $first = $value[0];
        if (($first === '{' || $first === '[') && is_array($json = json_decode($value, true))) {
            self::collectPaths($json, $out);

            return;
        }
        if (strlen($value) <= 500 && str_contains($value, '/') && ! str_contains($value, '..') && ! str_starts_with($value, '/')
            && preg_match('#^[A-Za-z0-9_\-./]+\.(jpe?g|png|webp|gif|pdf|docx?|xlsx?|csv|mp4|mov)$#i', $value)) {
            $out[$value] = true;
        }
    }

    /** Does any row still in the database name this path? */
    private static function referenced(string $path, array $tables): bool
    {
        $where = [
            'users' => ['profile_picture'], 'user_photos' => ['path'],
            'club_event_registrations' => ['photo'], 'event_officials' => ['photo'],
            'federation_clubs' => ['officials'], 'federation_club_applications' => ['officials', 'logo'],
            'federation_entries' => ['officials'], 'tenants' => ['logo', 'cover_image', 'banner'],
        ];
        foreach ($tables as $t) {
            $where[$t] = array_unique(array_merge($where[$t] ?? [], Schema::getColumnListing($t)));
        }
        // As written, and as a JSON column stores it ("federations\/…").
        $likes = array_map(fn ($p) => '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $p).'%', array_unique([$path, str_replace('/', '\\/', $path)]));
        foreach ($where as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $cols = array_values(array_intersect($cols, Schema::getColumnListing($table)));
            if ($cols === []) {
                continue;
            }
            $hit = DB::table($table)->where(function ($q) use ($cols, $likes) {
                foreach ($cols as $c) {
                    foreach ($likes as $like) {
                        $q->orWhereRaw(\App\Support\Database\Sql::asText(DB::getQueryGrammar()->wrap($c)).' LIKE ? ESCAPE \'\\\'', [$like]);
                    }
                }
            })->exists();
            if ($hit) {
                return true;
            }
        }

        return false;
    }

    /**
     * ON DELETE CASCADE / SET NULL, done by hand for the rows just deleted
     * (foreign keys are off during the sweep). SQLite only — it is what
     * production runs; elsewhere the database's own keys apply.
     *
     * @param  array<string, array<int, string>>  $gone  table => deleted ids
     */
    private static function cascade(array $gone): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        $fks = [];
        foreach (DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'") as $t) {
            foreach (DB::select('select * from pragma_foreign_key_list(?)', [$t->name]) as $fk) {
                $fks[] = [$t->name, $fk->from, $fk->table, strtoupper((string) $fk->on_delete)];
            }
        }
        for ($depth = 0; $gone !== [] && $depth < 5; $depth++) {
            $next = [];
            foreach ($fks as [$child, $col, $parent, $action]) {
                if (empty($gone[$parent]) || ! in_array($action, ['CASCADE', 'SET NULL'], true)) {
                    continue;
                }
                foreach (array_chunk(array_unique($gone[$parent]), 500) as $ids) {
                    $q = DB::table($child)->whereIn($col, $ids);
                    if ($action === 'SET NULL') {
                        $q->update([$col => null]);

                        continue;
                    }
                    if (Schema::hasColumn($child, 'id')) {
                        $next[$child] = array_merge($next[$child] ?? [], array_map('strval', (clone $q)->pluck('id')->all()));
                    }
                    $q->delete();
                }
            }
            $gone = array_filter($next);
        }
    }

    private static function pruneEmptyDirs(string $dir, string $root): void
    {
        $root = rtrim($root, '/');
        while (str_starts_with($dir, $root.'/') && is_dir($dir) && count(scandir($dir)) === 2) {
            @rmdir($dir);
            $dir = dirname($dir);
        }
    }
}
