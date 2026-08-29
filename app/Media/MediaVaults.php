<?php

namespace App\Media;

use App\Media\Contracts\VaultDriver;
use App\Media\Drivers\LocalDriver;
use App\Models\MediaFile;
use App\Models\MediaVault;
use App\Support\StoragePath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The one thing that decides where media goes and how it comes back.
 *
 * Everything above this — ingest, transcode, playback, deletion — asks this
 * class and never touches a driver. Which is what makes storage attachable: the
 * rest of the platform contains no opinion about NAS at all.
 *
 * ── The rule, in one paragraph ─────────────────────────────────────────────
 *
 * There is NO NAS by default. With nothing attached, `writeVault()` returns null
 * and media is written to local disk — a complete, supported configuration.
 * Attach one or more vaults and new media goes to the highest-priority one that
 * is reachable and not read-only. Reads NEVER re-decide: a file records the
 * vault it was written to and is read from exactly there, which is why attaching
 * a second vault or retiring the first cannot strand anything.
 *
 * ── Why writes fall back to local instead of failing ───────────────────────
 *
 * A competition does not stop because a share went offline. A clip that cannot
 * reach the NAS is written locally and marked as such, and the drain command
 * moves it up when the vault returns. The alternative — refusing the upload —
 * loses the bout, and a bout is not recoverable.
 */
class MediaVaults
{
    /** How long a vault's reachability answer is trusted. */
    private const REACH_TTL = 120;

    private ?LocalDriver $local = null;

    /* ──────────────────────────────────────────────────────────────────────
     | What is attached
     ────────────────────────────────────────────────────────────────────── */

    /** Every attached vault, most-preferred first. Empty is the default state. */
    public function attached(): Collection
    {
        return MediaVault::query()->attached()->orderByDesc('priority')->orderBy('id')->get();
    }

    /** True when the platform is running on nothing but its own disk. */
    public function localOnly(): bool
    {
        return ! MediaVault::query()->attached()->exists();
    }

    /**
     * The vault that new media should be written to, or null for local disk.
     *
     * Reachability is cached per vault, so a dead NAS costs one probe every two
     * minutes rather than one per upload.
     */
    public function writeVault(): ?MediaVault
    {
        foreach (MediaVault::query()->writable()->orderByDesc('priority')->orderBy('id')->get() as $vault) {
            if ($this->isReachable($vault)) {
                return $vault;
            }

            Log::warning('media vault unreachable, trying the next', ['vault' => $vault->uuid, 'name' => $vault->name]);
        }

        return null;
    }

    public function isReachable(MediaVault $vault): bool
    {
        return Cache::remember(
            'media_vault_reachable:'.$vault->uuid,
            self::REACH_TTL,
            fn () => $vault->driver()->reachable()
        );
    }

    /** Forget a cached answer — after attaching, editing or testing a vault. */
    public function forgetReachability(?MediaVault $vault = null): void
    {
        if ($vault) {
            Cache::forget('media_vault_reachable:'.$vault->uuid);

            return;
        }

        foreach (MediaVault::all() as $each) {
            Cache::forget('media_vault_reachable:'.$each->uuid);
        }
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Drivers
     ────────────────────────────────────────────────────────────────────── */

    /** The driver for a vault, or for local disk when given null. */
    public function driver(?MediaVault $vault): VaultDriver
    {
        return $vault ? $vault->driver() : $this->localDriver();
    }

    public function localDriver(): LocalDriver
    {
        return $this->local ??= new LocalDriver(config('media.local_root'));
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Putting bytes somewhere
     ────────────────────────────────────────────────────────────────────── */

    /**
     * Store a local file as media and record where it went.
     *
     * The source file is CONSUMED (moved when possible) — callers hand over a
     * scratch file they are finished with. On a total failure the row is still
     * created, marked failed, so an operator can see that a bout was lost rather
     * than having it vanish silently.
     *
     * @param  array<string,mixed>  $attributes  extra columns for the media_files row
     */
    public function store(string $localAbsPath, string $relPath, array $attributes = []): MediaFile
    {
        $bytes = is_file($localAbsPath) ? (int) filesize($localAbsPath) : null;
        $vault = $this->writeVault();

        $stored = $this->driver($vault)->put($localAbsPath, $relPath);

        // The NAS refused mid-competition. Keep the bytes rather than the
        // arrangement: local disk, flagged, drained to the vault later.
        if (! $stored && $vault !== null) {
            Log::warning('media vault write failed, keeping the file locally', [
                'vault' => $vault->uuid,
                'path' => $relPath,
            ]);

            $this->forgetReachability($vault);
            $vault = null;
            $stored = $this->localDriver()->put($localAbsPath, $relPath);
        }

        $file = new MediaFile(array_merge([
            'kind' => MediaFile::KIND_CLIP,
            'status' => $stored ? MediaFile::STATUS_STORED : MediaFile::STATUS_FAILED,
            'bytes' => $bytes,
        ], $attributes, [
            'vault_id' => $vault?->id,
            'rel_path' => $relPath,
        ]));

        if (! $stored) {
            $file->error = 'Could not write the file to any storage.';
        }

        $file->save();

        return $file;
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Getting bytes back
     ────────────────────────────────────────────────────────────────────── */

    /**
     * An absolute path the file can be read from IN PLACE, or null.
     *
     * Non-null for local disk and mounted vaults — the good case, where nginx
     * can send the bytes and nothing is copied. Null for anything that has to be
     * fetched first; use `ensureLocal()` then.
     */
    public function inPlacePath(MediaFile $file): ?string
    {
        $driver = $this->driver($file->vault);

        return $driver->servesInPlace() ? $driver->absolutePath($file->rel_path) : null;
    }

    /**
     * A local absolute path for the file, fetching it from the vault if needed.
     *
     * Used by anything that must actually read the bytes here — transcoding,
     * thumbnailing, a download from an SMB vault. Returns null when the file
     * cannot be produced, which callers must treat as "the video is not
     * available", never as an empty file.
     */
    public function ensureLocal(MediaFile $file): ?string
    {
        if ($direct = $this->inPlacePath($file)) {
            return $direct;
        }

        $cache = $this->cachePath($file);

        if (is_file($cache) && filesize($cache) > 0) {
            return $cache;
        }

        if (! $this->driver($file->vault)->get($file->rel_path, $cache)) {
            Log::warning('media: could not fetch file from its vault', [
                'file' => $file->uuid,
                'vault' => $file->vault?->uuid,
            ]);

            return null;
        }

        return $cache;
    }

    /**
     * Where a fetched copy is parked.
     *
     * Under the local root but in its own `cache/` tree, so it is obvious that
     * everything in there is regenerable and can be deleted to reclaim disk.
     */
    public function cachePath(MediaFile $file): string
    {
        return rtrim(config('media.local_root'), '/').'/'
            .StoragePath::fetched($file->uuid).'/'.basename($file->rel_path);
    }

    /** Drop a fetched copy. The source on the vault is untouched. */
    public function forgetLocalCopy(MediaFile $file): void
    {
        $dir = dirname($this->cachePath($file));

        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($dir);
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Moving and removing
     ────────────────────────────────────────────────────────────────────── */

    /**
     * Move one file's bytes to another vault (null = local disk).
     *
     * ── The order is the whole safety argument ─────────────────────────────
     *
     *   1. read the source           — if it cannot be read, stop and say so
     *   2. copy it to the destination
     *   3. VERIFY the destination — byte count, and checksum where both ends
     *      can be read in place
     *   4. repoint the record       (one UPDATE, so no reader ever sees a gap)
     *   5. delete the source        — last, and only after 3 and 4 succeeded
     *
     * Nothing is deleted on the strength of "the copy command returned 0": a
     * truncated transfer returns 0 too. And because the record is repointed
     * BEFORE the old copy goes, an interruption at any single step leaves the
     * file readable from wherever the record currently says it is. There is no
     * moment in this sequence that produces a broken link.
     */
    public function moveTo(MediaFile $file, ?MediaVault $target): bool
    {
        if (($file->vault_id ?? null) === ($target->id ?? null)) {
            return true;       // already there; not an error, just nothing to do
        }

        $source = $this->ensureLocal($file);

        if ($source === null || ! is_file($source)) {
            // The bytes are not where the record says. Flag it rather than
            // "moving" nothing and reporting success — this is exactly the
            // condition a verify pass exists to surface.
            $file->forceFill([
                'status' => MediaFile::STATUS_MISSING,
                'error' => 'The source file could not be read from its storage.',
            ])->save();

            return false;
        }

        $expectedBytes = (int) filesize($source);
        $sourceHash = $this->hash($source);
        $driver = $this->driver($target);

        // A copy into scratch first, never handing `put()` the live file: put()
        // may rename its input away, and its input here is the only copy that
        // exists.
        $scratch = sys_get_temp_dir().'/vaultmove_'.$file->uuid;

        if (! @copy($source, $scratch)) {
            Log::warning('media move: could not stage the file', ['file' => $file->uuid]);

            return false;
        }

        $put = $driver->put($scratch, $file->rel_path);

        if (is_file($scratch)) {
            @unlink($scratch);
        }

        if (! $put) {
            Log::warning('media move: destination refused the write', [
                'file' => $file->uuid,
                'to' => $target?->name ?? 'local',
            ]);

            // A refused write can still have left something behind — a full disk
            // produces a truncated file, not no file. Clear it, so the share is
            // not littered with zero-byte bouts and the next attempt starts from
            // nothing rather than from debris that already "exists".
            $driver->delete($file->rel_path);

            return false;
        }

        // ── Verify before anything is destroyed ──────────────────────────
        $landed = $driver->size($file->rel_path);

        if ($landed === null || $landed !== $expectedBytes) {
            Log::error('media move: destination size does not match, leaving the source alone', [
                'file' => $file->uuid,
                'expected' => $expectedBytes,
                'landed' => $landed,
            ]);

            // Remove the bad copy so a later attempt is not confused by a
            // half-written file that already "exists".
            $driver->delete($file->rel_path);

            return false;
        }

        // A checksum on top, whenever the destination can be read in place —
        // which is free for local disk and a mounted NAS. Size alone catches a
        // truncated transfer; this also catches a corrupted one.
        if ($sourceHash !== null && $driver->servesInPlace()) {
            $landedAbs = $driver->absolutePath($file->rel_path);
            $landedHash = $landedAbs ? $this->hash($landedAbs) : null;

            if ($landedHash !== null && $landedHash !== $sourceHash) {
                Log::error('media move: checksum mismatch, leaving the source alone', ['file' => $file->uuid]);

                $driver->delete($file->rel_path);

                return false;
            }
        }

        // ── Repoint, then delete ────────────────────────────────────────
        $oldVault = $file->vault;

        $file->forceFill([
            'vault_id' => $target?->id,
            // A file that was already watchable stays watchable: its ladder is
            // local and did not move with it.
            'status' => $file->status === MediaFile::STATUS_READY
                ? MediaFile::STATUS_READY
                : MediaFile::STATUS_STORED,
            'checksum' => $sourceHash ?? $file->checksum,
            'bytes' => $expectedBytes,
            'error' => null,
        ])->save();

        $this->driver($oldVault)->delete($file->rel_path);
        $this->pruneEmptyDirs($file, $oldVault, true);
        $this->forgetLocalCopy($file);

        Log::info('media move: verified and repointed', [
            'file' => $file->uuid,
            'to' => $target?->name ?? 'local disk',
            'bytes' => $expectedBytes,
        ]);

        return true;
    }

    /**
     * Confirm a file's bytes are where its record says, without moving anything.
     *
     * The audit half of the guarantee: a migration proves each file as it goes,
     * and this proves the whole library afterwards. Returns the state it found so
     * a caller can report it — and marks a row missing when the bytes are not
     * there, because a record that silently points at nothing is the one failure
     * mode worth being loud about.
     *
     * @return array{ok: bool, reason: ?string}
     */
    public function verify(MediaFile $file, bool $deep = false): array
    {
        $driver = $this->driver($file->vault);
        $size = $driver->size($file->rel_path);

        if ($size === null) {
            $file->forceFill([
                'status' => MediaFile::STATUS_MISSING,
                'error' => 'The file is not present on '.($file->vault?->name ?? 'local disk').'.',
            ])->save();

            return ['ok' => false, 'reason' => 'missing'];
        }

        if ($file->bytes !== null && (int) $file->bytes !== $size) {
            return ['ok' => false, 'reason' => 'size mismatch: record says '.$file->bytes.', storage has '.$size];
        }

        if ($deep && filled($file->checksum) && $driver->servesInPlace()) {
            $abs = $driver->absolutePath($file->rel_path);
            $hash = $abs ? $this->hash($abs) : null;

            if ($hash !== null && $hash !== $file->checksum) {
                return ['ok' => false, 'reason' => 'checksum mismatch'];
            }
        }

        // A row previously flagged missing that is now readable is healthy again.
        if ($file->status === MediaFile::STATUS_MISSING) {
            $file->forceFill([
                'status' => filled($file->hls_rel_path) ? MediaFile::STATUS_READY : MediaFile::STATUS_STORED,
                'error' => null,
            ])->save();
        }

        return ['ok' => true, 'reason' => null];
    }

    /** sha256 of a local file, or null if it cannot be read. */
    private function hash(string $absPath): ?string
    {
        $hash = @hash_file('sha256', $absPath);

        return $hash === false ? null : $hash;
    }

    /** Delete the bytes and the row. Files already gone are not an error. */
    public function delete(MediaFile $file): void
    {
        $this->driver($file->vault)->delete($file->rel_path);

        if (filled($file->hls_rel_path)) {
            $this->localDriver()->delete($file->hls_rel_path);
        }

        $this->forgetLocalCopy($file);
        $this->pruneEmptyDirs($file, $file->vault, true);

        $file->delete();
    }

    /**
     * Tidy the folders a deleted file leaves behind.
     *
     * Not cosmetic on a NAS an operator browses by hand: without this, every
     * event that ever had footage leaves an empty tree behind it forever, and
     * finding the competition you want means opening twenty empty folders.
     * Stops at the first directory that still has something in it.
     */
    private function pruneEmptyDirs(MediaFile $file, ?MediaVault $vault = null, bool $explicit = false): void
    {
        $driver = $this->driver($explicit || $vault !== null ? $vault : $file->vault);

        if (! $driver->servesInPlace()) {
            return;     // a remote driver has no cheap way to ask, and no cost to leaving them
        }

        $dir = dirname($file->rel_path);

        while ($dir !== '' && $dir !== '.' && $dir !== '/') {
            $abs = $driver->absolutePath($dir) ?? rtrim(config('media.local_root'), '/').'/'.$dir;

            if (! is_dir($abs) || (glob($abs.'/*') ?: []) !== []) {
                return;
            }

            @rmdir($abs);
            $dir = dirname($dir);
        }
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Paths
     ────────────────────────────────────────────────────────────────────── */

    /**
     * A file's place inside a directory the CALLER named.
     *
     * The directory comes from App\Support\StoragePath — the one place the
     * platform's layout is decided — and the filename is the media's own uuid.
     * So no uploaded filename ever reaches a path, and this class stays ignorant
     * of what a bout or a club is.
     */
    public function pathFor(string $directory, string $uuid, string $extension): string
    {
        return StoragePath::file($directory, $uuid, $extension);
    }

    /**
     * Write the little `meta.json` that makes a folder legible on the NAS.
     *
     * A folder named by a uuid tells somebody standing at a file browser nothing.
     * This says which competition, which bout, when — enough to find the footage
     * without the database, and deliberately no more than that: it sits on
     * storage that is browsed by hand, so it carries labels, never records.
     *
     * Written to the SAME vault the media went to, and best-effort: a competition
     * is not held up because a caption file did not land.
     */
    public function putMeta(?MediaVault $vault, string $directory, array $data): bool
    {
        $scratch = tempnam(sys_get_temp_dir(), 'meta_');

        file_put_contents($scratch, json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        $ok = $this->driver($vault)->put($scratch, StoragePath::meta($directory));

        // put() may have moved it; only unlink what is still there.
        if (is_file($scratch)) {
            @unlink($scratch);
        }

        return $ok;
    }

    /** Where the HLS ladder for a file is written. Always local, always derived. */
    public function hlsPathFor(MediaFile $file): string
    {
        return StoragePath::hls($file->uuid);
    }
}
