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
        Storage::disk(self::DISK)->put(self::FILE, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function exists(): bool
    {
        return Storage::disk(self::DISK)->exists(self::FILE);
    }

    public static function load(): ?array
    {
        if (! self::exists()) {
            return null;
        }

        return json_decode(Storage::disk(self::DISK)->get(self::FILE), true);
    }

    public static function delete(): void
    {
        Storage::disk(self::DISK)->delete(self::FILE);
    }

    public static function path(): string
    {
        return Storage::disk(self::DISK)->path(self::FILE);
    }
}
