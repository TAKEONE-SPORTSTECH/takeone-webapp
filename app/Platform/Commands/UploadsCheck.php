<?php

namespace App\Platform\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Proves the uploads folder is the one it should be.
 *
 * Staging and production read ONE database, so they must read one set of
 * files too — UPLOADS_ROOT points both at a folder they share. The danger is a
 * folder that is configured but not mounted: the box then writes pictures onto
 * its own disk, where the other box never sees them, and nothing says so. That
 * is exactly how members lost their pictures on 30 Oct.
 *
 * So, when UPLOADS_ROOT is set:
 *  - the folder must carry the marker `.takeone-shared`, which exists only on
 *    the shared volume (an unmounted mount point is an empty directory);
 *  - a probe file is written, read back and removed.
 *
 * `--audit` also counts the files the database names that are not in the
 * folder; `--missing=FILE` writes their paths out, one per line.
 *
 * Unset UPLOADS_ROOT is today's single-box layout and passes quietly.
 */
class UploadsCheck extends Command
{
    protected $signature = 'takeone:uploads-check
                            {--audit : Also count files the database names that are missing}
                            {--missing= : With --audit, write the missing paths to this file}';

    protected $description = 'Check the shared uploads folder is mounted and writable (and optionally that every referenced file exists)';

    public const MARKER = '.takeone-shared';

    /** Columns that hold a path inside the uploads root. */
    private const COLUMNS = [
        'users.profile_picture', 'user_photos.path',
        'club_event_registrations.photo', 'event_officials.photo',
        'event_photos.path', 'event_photos.thumb_path',
        'tenants.logo', 'tenants.cover_image', 'tenants.favicon', 'tenants.registration_splash_image',
        'club_affiliations.logo', 'event_clubs.logo', 'club_packages.cover_image',
        'club_products.image_path', 'club_perks.image_path', 'club_timeline_posts.image_path',
        'businesses.logo', 'federations.logo',
        'federation_events.logo', 'federation_events.art_portrait', 'federation_events.art_landscape',
        'federation_event_documents.path', 'federation_documents.path', 'federation_uploads.path',
        'federation_club_applications.logo', 'printable_assets.path',
        'orders.payment_proof_path', 'order_items.image_path',
        'member_certifications.image_path', 'goals.before_proof',
    ];

    public function handle(): int
    {
        $root = rtrim((string) config('filesystems.disks.local.root'), '/');
        // Not env(): it reads nothing once the config is cached.
        $shared = $root !== rtrim(storage_path('app'), '/');

        $this->line('  root      '.$root.($shared ? '  (shared)' : '  (this box only)'));

        if ($shared && ! $this->provesShared($root)) {
            return self::FAILURE;
        }

        if ($this->option('audit')) {
            return $this->audit($root);
        }

        return self::SUCCESS;
    }

    private function provesShared(string $root): bool
    {
        if (! is_file($root.'/'.self::MARKER)) {
            $this->error("No {$root}/".self::MARKER.' — the shared folder is not mounted here. Uploads would land on this box only.');

            return false;
        }

        $probe = $root.'/.probe-'.gethostname().'-'.Str::random(8);
        $token = Str::random(32);
        $ok = @file_put_contents($probe, $token) !== false && @file_get_contents($probe) === $token;
        @unlink($probe);

        if (! $ok || is_file($probe)) {
            $this->error("Cannot write, read back and remove a file in {$root}.");

            return false;
        }

        $this->line('  <info>✓</info> marker present, write/read/delete ok');

        return true;
    }

    private function audit(string $root): int
    {
        $missing = [];
        $checked = 0;

        foreach (self::COLUMNS as $ref) {
            [$table, $column] = explode('.', $ref);
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            $paths = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->distinct()->pluck($column);
            foreach ($paths as $path) {
                $path = preg_replace('#^/?(storage|file)/#', '', (string) $path);
                if (preg_match('#^(https?:|/?images/)#i', $path)) {
                    continue;
                }
                $checked++;
                if (! is_file($root.'/'.ltrim($path, '/'))) {
                    $missing[$ref][] = $path;
                }
            }
        }

        $count = array_sum(array_map('count', $missing));
        foreach ($missing as $ref => $list) {
            $this->line(sprintf('  <comment>%5d</comment> missing  %s', count($list), $ref));
        }
        $this->line("  {$checked} referenced files checked, {$count} missing");

        if ($file = $this->option('missing')) {
            file_put_contents($file, implode("\n", array_unique(array_merge([], ...array_values($missing)))).($count ? "\n" : ''));
            $this->line("  list written to {$file}");
        }

        return $count === 0 ? self::SUCCESS : self::FAILURE;
    }
}
