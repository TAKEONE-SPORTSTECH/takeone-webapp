<?php

namespace App\Console\Commands;

use App\Support\DemoManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Removes exactly the rows (and uploaded files) recorded by `demo:seed`, using the
 * manifest at storage/app/demo/manifest.json. Deletes files first, then rows in
 * reverse dependency order. Never touches anything outside the manifest, so real
 * imported members and the super-admin account are safe.
 */
class DemoPurge extends Command
{
    protected $signature = 'demo:purge {--force : Skip confirmation}';

    protected $description = 'Remove all demo data created by demo:seed (files first, FK-safe), using the recorded manifest.';

    /** Children → parents. Anything in the manifest but absent here is deleted first as a safety net. */
    private array $order = [
        'club_transactions',
        'product_reviews', 'club_products', 'club_product_categories',
        'event_matches', 'event_categories', 'club_event_registrations', 'club_events',
        'challenge_participations', 'duels', 'challenges',
        'user_post_poll_votes', 'user_post_comments', 'user_post_likes', 'user_posts', 'user_stories',
        'club_timeline_post_comments', 'club_timeline_post_likes', 'club_timeline_posts',
        'user_follows', 'user_schedule_sessions',
        'club_member_subscriptions', 'memberships',
        'club_package_activities', 'club_packages', 'club_activities', 'club_instructors', 'club_facilities',
        'club_gallery_images', 'user_roles', 'tenants', 'businesses', 'users',
    ];

    /** table => [ [column, disk, 'single'|'array'], ... ] — files purged before the rows. */
    private array $fileColumns = [
        'tenants'                   => [['logo', 'public', 'single'], ['cover_image', 'public', 'single'], ['favicon', 'public', 'single']],
        'users'                     => [['profile_picture', 'public', 'single']],
        'club_products'             => [['image_path', 'public', 'single']],
        'club_member_subscriptions' => [['proof_of_payment', 'local', 'single'], ['refund_proof', 'local', 'single']],
        'club_facilities'           => [['photo', 'public', 'single'], ['images', 'public', 'array']],
        'club_packages'             => [['cover_image', 'public', 'single']],
        'club_activities'           => [['picture_url', 'public', 'single']],
        'club_timeline_posts'       => [['image_path', 'public', 'single']],
        'user_posts'                => [['images', 'public', 'array']],
        'user_stories'              => [['image_path', 'public', 'single']],
        'club_gallery_images'       => [['image_path', 'public', 'single']],
    ];

    public function handle(): int
    {
        $manifest = DemoManifest::load();
        if (! $manifest) {
            $this->error('No demo manifest found at ' . DemoManifest::path() . ' — nothing to purge.');
            return self::FAILURE;
        }

        $tables = $manifest['tables'] ?? [];
        $totalRows = array_sum(array_map('count', $tables));
        $this->warn("This will permanently delete {$totalRows} demo rows across " . count($tables) . ' tables (seeded ' . ($manifest['seeded_at'] ?? '?') . ').');

        if (! $this->option('force') && ! $this->confirm('Proceed?', true)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        // 1) Delete explicitly-recorded files.
        $fileCount = 0;
        foreach (($manifest['files'] ?? []) as $f) {
            if (Storage::disk($f['disk'])->exists($f['path'])) {
                Storage::disk($f['disk'])->delete($f['path']);
                $fileCount++;
            }
        }

        // 2) Defensive: delete files referenced by file-bearing columns on the rows we're about to remove.
        foreach ($this->fileColumns as $table => $cols) {
            $ids = $tables[$table] ?? [];
            if (! $ids || ! Schema::hasTable($table)) {
                continue;
            }
            foreach ($cols as [$col, $disk, $type]) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }
                foreach (DB::table($table)->whereIn('id', $ids)->whereNotNull($col)->pluck($col) as $val) {
                    foreach ($this->paths($val, $type) as $path) {
                        if ($path && Storage::disk($disk)->exists($path)) {
                            Storage::disk($disk)->delete($path);
                            $fileCount++;
                        }
                    }
                }
            }
        }

        // 3) Delete rows in reverse-dependency order. Anything unlisted goes first as a safety net.
        $ordered = array_merge(array_diff(array_keys($tables), $this->order), $this->order);
        $deleted = [];
        DB::transaction(function () use ($tables, $ordered, &$deleted) {
            foreach ($ordered as $table) {
                $ids = $tables[$table] ?? [];
                if (! $ids || ! Schema::hasTable($table)) {
                    continue;
                }
                $n = 0;
                foreach (array_chunk($ids, 500) as $chunk) {
                    $n += DB::table($table)->whereIn('id', $chunk)->delete();
                }
                if ($n) {
                    $deleted[$table] = $n;
                }
            }
        });

        DemoManifest::delete();

        $this->newLine();
        $this->info("✅ Demo purged. Files removed: {$fileCount}.");
        foreach ($deleted as $table => $n) {
            $this->line('   ' . str_pad($table, 28) . $n);
        }
        return self::SUCCESS;
    }

    /** Normalize a column value into a list of storage paths. */
    private function paths(mixed $val, string $type): array
    {
        if ($type === 'array') {
            $arr = is_array($val) ? $val : (json_decode((string) $val, true) ?: []);
            return array_values(array_filter(array_map(fn ($v) => is_string($v) ? $v : null, $arr)));
        }
        $s = (string) $val;
        // Ignore absolute URLs (e.g. placeholder picture_url) — only real stored paths.
        if ($s === '' || str_starts_with($s, 'http://') || str_starts_with($s, 'https://')) {
            return [];
        }
        return [$s];
    }
}
