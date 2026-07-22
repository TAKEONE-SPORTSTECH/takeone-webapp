<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Resets the platform to its clean baseline: keeps ONLY the global activity
 * catalog, the roles/permissions, and the super-admin user — deletes every club,
 * package, member and all member/user-generated data — then re-seeds the baseline
 * (super-admin + activity catalog + the "TAKEONE SportsTech" club with realistic
 * packages) via DatabaseSeeder.
 *
 * Destructive. Requires an explicit confirmation (or --force). On SQLite it takes
 * a timestamped VACUUM backup first.
 */
class ResetBaseline extends Command
{
    protected $signature = 'takeone:reset-baseline {--force : Skip the confirmation prompt} {--no-backup : Do not take a SQLite backup first} {--no-seed : Wipe only; do not re-seed}';

    protected $description = 'Wipe all clubs/packages/members (keep activities + super-admin) and re-seed the TAKEONE SportsTech baseline';

    /** Rows in these tables are preserved; everything else is wiped. */
    private array $keep = [
        'activity_catalog',
        'roles', 'permissions', 'role_permission',
        'migrations',
        'realtime_settings', 'platform_settings', 'ai_providers', 'event_categories',
        'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs',
        'password_reset_tokens', 'personal_access_tokens', 'push_tokens',
        'users', 'user_roles',
    ];

    public function handle(): int
    {
        $keepIds = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->pluck('id')->all();
        if (empty($keepIds)) {
            $this->error('No super-admin user found — aborting so we never wipe without a keeper.');
            return self::FAILURE;
        }

        $this->warn('This DELETES every club, package, member and all member data.');
        $this->line('Keeps: global activity catalog, roles/permissions, and super-admin user id(s) '.implode(',', $keepIds).'.');
        if (! $this->option('force') && ! $this->confirm('Proceed?')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $this->backupSqlite();

        // 1) Force-delete tenants through the model so cleanup hooks + file deletion fire.
        $this->info('Force-deleting clubs (with file cleanup)…');
        Tenant::withTrashed()->cursor()->each(function ($t) {
            try { $t->forceDelete(); } catch (\Throwable $e) { $this->line("  club {$t->id}: {$e->getMessage()}"); }
        });

        // 2) Wipe every remaining member/user-generated table; keep super-admin.
        $tables = array_map(fn ($r) => $r->name,
            DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));

        DB::statement('PRAGMA foreign_keys = OFF');
        DB::transaction(function () use ($tables, $keepIds) {
            foreach ($tables as $t) {
                if (in_array($t, $this->keep, true)) { continue; }
                DB::table($t)->delete();
            }
            DB::table('users')->whereNotIn('id', $keepIds)->delete();
            DB::table('user_roles')->whereNotIn('user_id', $keepIds)->delete();
        });
        DB::statement('PRAGMA foreign_keys = ON');

        $violations = count(DB::select('PRAGMA foreign_key_check'));
        $this->info("Wipe complete. FK violations: {$violations}.");

        // 3) Clear orphaned proof/media folders (kept dirs + .gitignore).
        $this->clearUploadFolders();

        // 4) Re-seed the baseline.
        if (! $this->option('no-seed')) {
            $this->info('Re-seeding baseline…');
            $this->call('db:seed', ['--force' => true]);
        }

        $this->newLine();
        $this->info('Baseline reset done.');
        $this->table(['metric', 'count'], [
            ['users', User::count()],
            ['clubs', Tenant::withTrashed()->count()],
            ['packages', DB::table('club_packages')->count()],
            ['club_activities', DB::table('club_activities')->count()],
            ['activity_catalog (kept)', DB::table('activity_catalog')->count()],
        ]);

        return self::SUCCESS;
    }

    private function backupSqlite(): void
    {
        if ($this->option('no-backup')) { return; }
        $conn = config('database.default');
        if (config("database.connections.$conn.driver") !== 'sqlite') { return; }
        $db = config("database.connections.$conn.database");
        if (! is_string($db) || ! is_file($db)) { return; }
        $dest = $db.'.bak-reset-'.now()->format('Ymd-His');
        try {
            DB::statement("VACUUM INTO '".str_replace("'", "''", $dest)."'");
            $this->info("SQLite backup: {$dest}");
        } catch (\Throwable $e) {
            $this->warn('Backup failed ('.$e->getMessage().') — continuing.');
        }
    }

    private function clearUploadFolders(): void
    {
        foreach (['payment-proofs', 'order-proofs', 'payment-screenshots'] as $dir) {
            foreach ([storage_path("app/private/$dir"), storage_path("app/public/$dir")] as $path) {
                if (! is_dir($path)) { continue; }
                foreach (glob($path.'/*') as $f) {
                    if (is_file($f) && ! str_ends_with($f, '.gitignore')) { @unlink($f); }
                }
            }
        }
    }
}
