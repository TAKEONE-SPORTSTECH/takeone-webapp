<?php

namespace App\Media\Contracts;

/**
 * What every place we can keep media has to be able to do.
 *
 * Deliberately small. A driver moves bytes and answers questions about them; it
 * knows nothing about bouts, events, HLS or authorisation. Everything above it
 * talks to `MediaVaults`, never to a driver directly, so adding S3 or SFTP later
 * is one class and no edits anywhere else.
 *
 * ── The one distinction that shapes everything ─────────────────────────────
 *
 * `servesInPlace()`. A mounted volume can be read where it lies, so a video is
 * streamed off the NAS and never touches our disk. An SMB share cannot, so the
 * file has to be copied down before anybody can watch it. Callers ask this
 * question rather than assuming, because the answer decides whether "don't store
 * video on our storage" is actually being honoured.
 */
interface VaultDriver
{
    /**
     * Is this place usable right now?
     *
     * Must be CHEAP and must never hang for long — it is called on write paths
     * while somebody waits. Implementations cache their own answer.
     */
    public function reachable(): bool;

    /** Copy a local file in. Returns false rather than throwing on failure. */
    public function put(string $localAbsPath, string $relPath): bool;

    /** Copy a file out to a local absolute path. */
    public function get(string $relPath, string $localAbsPath): bool;

    public function exists(string $relPath): bool;

    /**
     * The stored size in bytes, or null when the driver cannot tell.
     *
     * This is the verification primitive. A migration must never delete a source
     * on the strength of "the copy command returned 0" — a truncated transfer
     * does that too. It deletes only once the destination reports the same
     * number of bytes the source had, which every driver here can answer.
     */
    public function size(string $relPath): ?int;

    /** Best-effort. A file that is already gone is a success, not an error. */
    public function delete(string $relPath): void;

    /** Create a directory and every parent of it. */
    public function mkdirp(string $relPath): void;

    /**
     * Can bytes be read straight out of this place?
     *
     * True for local disk and mounted volumes. False for anything we speak a
     * protocol to, one file at a time.
     */
    public function servesInPlace(): bool;

    /**
     * The absolute filesystem path of a stored file, when there is one.
     *
     * Only meaningful when `servesInPlace()` is true; everything else returns
     * null and the caller falls back to fetching a local copy.
     */
    public function absolutePath(string $relPath): ?string;

    /**
     * Free and total bytes, when the driver can tell.
     *
     * @return array{free: ?int, total: ?int}
     */
    public function usage(): array;

    /**
     * A human-readable connection check for the admin page.
     *
     * @return array{ok: bool, message: string}
     */
    public function probe(): array;
}
