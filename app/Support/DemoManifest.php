<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Records every row id (and uploaded file) created by `demo:seed` so that
 * `demo:purge` can remove exactly what was seeded — nothing more. The manifest
 * lives on the local disk at storage/app/demo/manifest.json.
 */
class DemoManifest
{
    public const DISK = 'local';

    public const FILE = 'demo/manifest.json';

    /**
     * Which manifest this is. Seeds that can coexist keep separate books, so
     * purging one never deletes rows another one is still responsible for —
     * `demo:seed` owns "manifest", `demo:competition` owns "competition".
     * Defaults preserve the original single-manifest behaviour everywhere.
     */
    public function __construct(private string $name = 'manifest') {}

    public static function fileFor(string $name = 'manifest'): string
    {
        return 'demo/'.$name.'.json';
    }

    /** @var array<string, array<int, int>> table => list of ids */
    private array $tables = [];

    /** @var array<int, array{disk:string,path:string}> */
    private array $files = [];

    public function track(string $table, int|string|null $id): void
    {
        if ($id === null) {
            return;
        }
        $this->tables[$table] ??= [];
        $this->tables[$table][] = (int) $id;
    }

    public function trackFile(string $disk, string $path): void
    {
        $this->files[] = ['disk' => $disk, 'path' => $path];
    }

    public function totals(): array
    {
        $out = [];
        foreach ($this->tables as $t => $ids) {
            $out[$t] = count($ids);
        }
        ksort($out);

        return $out;
    }

    public function save(): void
    {
        $payload = [
            'seeded_at' => now()->toIso8601String(),
            'tables' => array_map('array_values', $this->tables),
            'files' => $this->files,
        ];
        Storage::disk(self::DISK)->put(self::fileFor($this->name), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function exists(string $name = 'manifest'): bool
    {
        return Storage::disk(self::DISK)->exists(self::fileFor($name));
    }

    public static function load(string $name = 'manifest'): ?array
    {
        if (! self::exists($name)) {
            return null;
        }

        return json_decode(Storage::disk(self::DISK)->get(self::fileFor($name)), true);
    }

    public static function delete(string $name = 'manifest'): void
    {
        Storage::disk(self::DISK)->delete(self::fileFor($name));
    }

    public static function path(string $name = 'manifest'): string
    {
        return Storage::disk(self::DISK)->path(self::fileFor($name));
    }
}
