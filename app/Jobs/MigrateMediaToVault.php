<?php

namespace App\Jobs;

use App\Media\MediaVaults;
use App\Models\MediaFile;
use App\Models\MediaVault;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * A NAS was attached. Move everything onto it, and leave nothing behind.
 *
 * ── Why this is automatic ──────────────────────────────────────────────────
 *
 * Because the alternative is a platform in two halves. The normal way this
 * happens is: run on local disk for a while, accumulate a few competitions'
 * footage, then buy storage and attach it. If only NEW video went to the NAS,
 * the old footage would sit on the application server forever — which is the
 * exact arrangement attaching a NAS was meant to end, and nobody would notice
 * until the disk filled.
 *
 * So attaching storage migrates what is already here, and the record follows the
 * bytes. No link ever breaks, because the pointer is a database column that is
 * updated only after the copy is verified (MediaVaults::moveTo).
 *
 * ── Why it re-dispatches itself instead of looping ─────────────────────────
 *
 * A library is gigabytes and a queue worker has a timeout. Each run moves a
 * batch, then queues the next one — so progress survives a restart, a deploy, or
 * a worker being killed mid-competition, and the work is resumable by
 * construction. It also means the batch can be small enough that a migration
 * never saturates the link a hall is using.
 *
 * Idempotent throughout: a file already on the target is skipped, so running
 * this twice costs a query and nothing else.
 */
class MigrateMediaToVault implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3000;

    /** One retry. A vault that is genuinely gone should stop, not thrash. */
    public int $tries = 2;

    public array $backoff = [120];

    /** Files per run. Small enough to stay responsive, big enough to progress. */
    private const BATCH = 10;

    /**
     * @param  string  $vaultUuid  the vault to fill
     * @param  int  $movedSoFar  carried across runs, for the log and the progress line
     */
    public function __construct(
        public string $vaultUuid,
        public int $movedSoFar = 0,
        public int $failedSoFar = 0,
    ) {}

    /** One migration per vault in flight, however many times it is asked for. */
    public function uniqueId(): string
    {
        return 'migrate-media-'.$this->vaultUuid;
    }

    public function handle(MediaVaults $vaults): void
    {
        $vault = MediaVault::where('uuid', $this->vaultUuid)->first();

        if ($vault === null || ! $vault->enabled || $vault->read_only) {
            // Detached, disabled, or being retired while this was queued. Stop —
            // and do not treat it as a failure: the operator changed their mind,
            // and everything already moved is safely pointed at the vault.
            $this->finish($vault, 'stopped');

            return;
        }

        if (! $vaults->isReachable($vault)) {
            Log::warning('media migration: vault unreachable, will retry', ['vault' => $vault->name]);

            // Retry through the queue's own backoff rather than spinning here.
            $this->release(300);

            return;
        }

        // Everything still on local disk. Ordered oldest-first so a migration
        // makes visible progress through the archive rather than jumping around.
        //
        // Files still arriving or mid-transcode are skipped: ffmpeg holds the
        // source open BY PATH, and moving it out from under a running encode
        // fails that encode for no reason. The next pass picks them up, which is
        // what the hourly `media:migrate --auto` is for.
        $batch = MediaFile::query()
            ->whereNull('vault_id')
            ->whereNotIn('status', [MediaFile::STATUS_UPLOADING, MediaFile::STATUS_PROCESSING, MediaFile::STATUS_MISSING])
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get();

        if ($batch->isEmpty()) {
            $this->finish($vault, 'done');

            return;
        }

        $moved = $this->movedSoFar;
        $failed = $this->failedSoFar;

        foreach ($batch as $file) {
            $vaults->moveTo($file, $vault) ? $moved++ : $failed++;
        }

        $remaining = MediaFile::whereNull('vault_id')
            ->whereNotIn('status', [MediaFile::STATUS_UPLOADING, MediaFile::STATUS_PROCESSING, MediaFile::STATUS_MISSING])
            ->count();

        $this->progress($vault, ['state' => 'running', 'moved' => $moved, 'failed' => $failed, 'remaining' => $remaining]);

        // A batch where NOTHING moved means the problem is the vault, not the
        // files — stop rather than walking the whole library failing on each one
        // and deleting nothing.
        if ($moved === $this->movedSoFar) {
            Log::error('media migration: a whole batch failed, stopping', [
                'vault' => $vault->name,
                'failed' => $failed,
            ]);

            $this->finish($vault, 'stalled', $moved, $failed);

            return;
        }

        Log::info('media migration: batch complete', [
            'vault' => $vault->name,
            'moved' => $moved,
            'failed' => $failed,
            'remaining' => $remaining,
        ]);

        // Next batch. A short delay keeps a large migration from monopolising the
        // worker while a competition is filing clips.
        self::dispatch($this->vaultUuid, $moved, $failed)
            ->onQueue('media')
            ->delay(now()->addSeconds(5));
    }

    /**
     * Close out the migration, and PROVE it.
     *
     * The last thing a migration does is check its own work: anything still
     * sitting on local disk is counted and named in the log. "It said it was
     * finished" is not the same as "nothing was left behind", and this is the
     * difference.
     */
    private function finish(?MediaVault $vault, string $state, ?int $moved = null, ?int $failed = null): void
    {
        $leftBehind = MediaFile::whereNull('vault_id')
            ->whereNotIn('status', [MediaFile::STATUS_UPLOADING, MediaFile::STATUS_PROCESSING, MediaFile::STATUS_MISSING])
            ->count();

        $missing = MediaFile::where('status', MediaFile::STATUS_MISSING)->count();

        $this->progress($vault, [
            'state' => $state,
            'moved' => $moved ?? $this->movedSoFar,
            'failed' => $failed ?? $this->failedSoFar,
            'remaining' => $leftBehind,
            'missing' => $missing,
            'finished_at' => now()->toIso8601String(),
        ]);

        $level = ($leftBehind === 0 && ($failed ?? $this->failedSoFar) === 0) ? 'info' : 'warning';

        Log::$level('media migration: '.$state, [
            'vault' => $vault?->name,
            'moved' => $moved ?? $this->movedSoFar,
            'failed' => $failed ?? $this->failedSoFar,
            'left_on_local_disk' => $leftBehind,
            'records_with_no_bytes' => $missing,
        ]);
    }

    /**
     * Where the admin page reads progress from.
     *
     * Cache rather than a column: it is a running commentary on a job, not a
     * fact about the vault, and it must not add a write to the vault row on every
     * batch. A page that loads after it expires simply shows the file counts,
     * which are the real answer anyway.
     */
    private function progress(?MediaVault $vault, array $data): void
    {
        if ($vault === null) {
            return;
        }

        Cache::put('media_migration:'.$vault->uuid, $data, now()->addHours(6));
    }

    /** What the page shows while this is running. */
    public static function progressFor(MediaVault $vault): ?array
    {
        return Cache::get('media_migration:'.$vault->uuid);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('media migration failed', ['vault' => $this->vaultUuid, 'error' => $e->getMessage()]);

        if ($vault = MediaVault::where('uuid', $this->vaultUuid)->first()) {
            Cache::put('media_migration:'.$vault->uuid, [
                'state' => 'failed',
                'moved' => $this->movedSoFar,
                'failed' => $this->failedSoFar,
                'error' => mb_substr($e->getMessage(), 0, 160),
            ], now()->addHours(6));
        }
    }
}
