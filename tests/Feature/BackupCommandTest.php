<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The backup command.
 *
 * What matters is not "a file appeared" — it is that the artifact is READABLE,
 * that a bad one is rejected rather than counted, that rotation never leaves you
 * with nothing, and that a missing off-server destination is impossible to
 * overlook.
 *
 * NOTE ON COVERAGE: the SQLite snapshot path (`VACUUM INTO`) cannot run under
 * this suite — RefreshDatabase holds an open transaction on the default
 * connection and VACUUM is illegal inside one. That refusal is itself tested
 * below; the snapshot-and-verify path is exercised against the real database by
 * running `php artisan takeone:backup`, whose artifacts were restore-tested
 * independently (integrity ok, 215 migrations, real rows). Everything the two
 * paths share — directory creation, verification-before-success, off-server
 * copy, rotation, loud failure — is covered here via the uploads artifact.
 */
class BackupCommandTest extends TestCase
{
    private string $dir;

    private string $uploads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/takeone-backups-'.uniqid();
        $this->uploads = sys_get_temp_dir().'/takeone-uploads-'.uniqid();

        @mkdir($this->uploads.'/nested', 0775, true);
        file_put_contents($this->uploads.'/nested/proof.png', 'payment proof bytes');

        config([
            'backup.path' => $this->dir,
            'backup.disk' => null,
            'backup.uploads' => [$this->uploads],
            'backup.max_uploads_mb' => 2048,
            'backup.retain_days' => 14,
            'backup.keep_minimum' => 3,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);

        @unlink($this->uploads.'/nested/proof.png');
        @rmdir($this->uploads.'/nested');
        @rmdir($this->uploads);

        parent::tearDown();
    }

    private function artifacts(string $pattern = 'uploads-*'): array
    {
        return glob($this->dir.'/'.$pattern) ?: [];
    }

    /* ---------------- The artifact is real ---------------- */

    public function test_the_uploads_archive_can_actually_be_read_back(): void
    {
        $this->artisan('takeone:backup', ['--uploads-only' => true])->assertSuccessful();

        $archives = $this->artifacts();
        $this->assertCount(1, $archives);

        exec('tar -tzf '.escapeshellarg($archives[0]), $listing, $status);
        $this->assertSame(0, $status, 'a backup that cannot be listed is not a backup');
        $this->assertNotEmpty(preg_grep('/proof\.png$/', $listing), 'the actual upload is inside it');
    }

    public function test_it_creates_the_backup_directory_when_missing(): void
    {
        $this->assertDirectoryDoesNotExist($this->dir);

        $this->artisan('takeone:backup', ['--uploads-only' => true])->assertSuccessful();

        $this->assertDirectoryExists($this->dir);
    }

    public function test_it_succeeds_quietly_when_there_are_no_upload_folders(): void
    {
        config(['backup.uploads' => ['/nonexistent/path']]);

        $this->artisan('takeone:backup', ['--uploads-only' => true])
            ->expectsOutputToContain('nothing to archive')
            ->assertSuccessful();
    }

    /* ---------------- Off-server ---------------- */

    public function test_it_warns_when_backups_sit_on_the_same_disk_as_the_data(): void
    {
        // Local-only survives a bad migration but not a dead server — that must
        // never be a silent state.
        $this->artisan('takeone:backup', ['--uploads-only' => true])
            ->expectsOutputToContain('No BACKUP_DISK configured')
            ->assertSuccessful();
    }

    public function test_it_copies_the_artifact_off_server_when_a_disk_is_configured(): void
    {
        Storage::fake('offsite');
        config(['backup.disk' => 'offsite']);

        $this->artisan('takeone:backup', ['--uploads-only' => true])->assertSuccessful();

        $remote = Storage::disk('offsite')->files('backups');
        $this->assertCount(1, $remote);
        $this->assertGreaterThan(0, Storage::disk('offsite')->size($remote[0]));

        // Removed locally only once it is safely elsewhere.
        $this->assertCount(0, $this->artifacts());
    }

    public function test_it_keeps_the_local_copy_when_asked(): void
    {
        Storage::fake('offsite');
        config(['backup.disk' => 'offsite']);

        $this->artisan('takeone:backup', ['--uploads-only' => true, '--keep-local' => true])->assertSuccessful();

        $this->assertCount(1, $this->artifacts());
    }

    /* ---------------- Rotation ---------------- */

    public function test_rotation_deletes_artifacts_past_the_window(): void
    {
        @mkdir($this->dir, 0775, true);
        config(['backup.retain_days' => 7, 'backup.keep_minimum' => 0]);

        $old = $this->dir.'/uploads-20200101-000000.tar.gz';
        file_put_contents($old, 'stale');
        touch($old, now()->subDays(30)->getTimestamp());

        $this->artisan('takeone:backup', ['--uploads-only' => true])->assertSuccessful();

        $this->assertFileDoesNotExist($old);
    }

    public function test_rotation_always_leaves_something_to_restore_from(): void
    {
        @mkdir($this->dir, 0775, true);
        config(['backup.retain_days' => 7, 'backup.keep_minimum' => 3]);

        // Ancient, well past the window — but they are all there is.
        foreach (range(1, 3) as $i) {
            $f = $this->dir."/uploads-2020010{$i}-000000.tar.gz";
            file_put_contents($f, 'stale');
            touch($f, now()->subDays(100 + $i)->getTimestamp());
        }

        $this->artisan('takeone:backup', ['--uploads-only' => true])->assertSuccessful();

        // A box that sat idle for months must not prune itself down to nothing.
        $this->assertGreaterThanOrEqual(3, count($this->artifacts()));
    }

    public function test_retention_of_zero_disables_pruning(): void
    {
        @mkdir($this->dir, 0775, true);
        config(['backup.retain_days' => 0]);

        $ancient = $this->dir.'/uploads-20200101-000000.tar.gz';
        file_put_contents($ancient, 'stale');
        touch($ancient, now()->subYears(3)->getTimestamp());

        $this->artisan('takeone:backup', ['--uploads-only' => true])->assertSuccessful();

        $this->assertFileExists($ancient);
    }

    /* ---------------- Failures are loud ---------------- */

    public function test_an_oversized_uploads_archive_is_discarded_and_reported(): void
    {
        // A cron must not fill the disk as media grows. Incompressible bytes, so
        // the gzipped archive really does exceed the 1 MB limit.
        file_put_contents($this->uploads.'/big.bin', random_bytes(2 * 1024 * 1024));
        config(['backup.max_uploads_mb' => 1]);

        $this->artisan('takeone:backup', ['--uploads-only' => true])->assertFailed();

        $this->assertCount(0, $this->artifacts(), 'the oversized archive is not left behind');

        @unlink($this->uploads.'/big.bin');
    }

    public function test_it_refuses_to_snapshot_the_database_inside_an_open_transaction(): void
    {
        // VACUUM is illegal inside a transaction. The command must say so rather
        // than silently producing nothing — which is exactly how a cron ends up
        // "working" for months while backing up nothing.
        $this->artisan('takeone:backup', ['--db-only' => true])
            ->expectsOutputToContain('inside an open transaction')
            ->assertFailed();
    }

    public function test_an_unsupported_database_driver_fails_loudly(): void
    {
        $original = config('database.default');
        config(['database.default' => 'pgsql', 'database.connections.pgsql.driver' => 'pgsql']);

        try {
            $this->artisan('takeone:backup', ['--db-only' => true])
                ->expectsOutputToContain('Unsupported database driver')
                ->assertFailed();
        } finally {
            // Leaving the default pointed at an undriveable connection breaks
            // RefreshDatabase's rollback and poisons the next test.
            config(['database.default' => $original]);
        }
    }

    public function test_a_partial_run_still_exits_non_zero(): void
    {
        // The uploads archive works; the database cannot be snapshotted here.
        // Partial success is still failure — the cron must shout.
        $this->artisan('takeone:backup')->assertFailed();

        $this->assertCount(1, $this->artifacts(), 'the part that did work is still kept');
    }
}
