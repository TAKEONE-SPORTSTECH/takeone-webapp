<?php

namespace App\Media;

use App\Jobs\RenderBoutShare;
use App\Media\Models\MediaFile;
use App\Support\StoragePath;
use Illuminate\Support\Facades\Cache;

/**
 * A bout as a file to send — for WhatsApp and every other app that will never
 * have the bout page's player, its score bar or its highlights panel.
 *
 * Two kinds: the WHOLE bout, and the HIGHLIGHTS (each scoring moment with a
 * few seconds either side). Both carry the score bar burned into the picture
 * and the points as chapters; the message that travels with the file carries
 * the same points as text, because most messaging apps ignore chapters.
 *
 * ── What this decides, and what it does not ───────────────────────────────
 *
 * It does NOT decide who may have one. The caller has already established
 * that the viewer may watch this bout, and the file is only ever handed out
 * through a door that asks again (BoutVideoController::shareFile).
 *
 * It DOES decide that the same bout is never rendered twice. A file's name is
 * a hash of the footage and everything drawn on it, so a second request for an
 * unchanged bout is an instant answer, and a re-scored bout is a new file
 * rather than a stale one. The files live under `cache/` — disposable, rebuilt
 * on the next request.
 */
class BoutShare
{
    public const FULL = 'full';
    public const HIGHLIGHTS = 'highlights';

    public const KINDS = [self::FULL, self::HIGHLIGHTS];

    /** Bumped whenever the drawing changes, so old files are not handed out as new. */
    private const VERSION = 1;

    /** How long "it is being made" is believed without a finished file. */
    private const WORKING_TTL = 3600;

    public function __construct(
        private Ffmpeg $ffmpeg,
        private BoutShareRenderer $renderer,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('media.share_export.enabled', false) && $this->ffmpeg->available();
    }

    /**
     * Can this kind be made for this bout at all? A highlights reel of a bout
     * with no scoring moments would be an empty file.
     */
    public function possible(string $kind, array $msb, int $duration): bool
    {
        if (! in_array($kind, self::KINDS, true)) {
            return false;
        }

        return $kind === self::FULL || $this->renderer->points($msb, (float) max(1, $duration)) !== [];
    }

    /**
     * Where a bout stands for this kind, without starting anything.
     *
     * @return array{state: string, bytes: ?int, error: ?string}
     */
    public function status(MediaFile $media, string $kind, array $msb): array
    {
        $key = $this->key($media, $kind, $msb);
        $path = $this->path($media, $key);

        if (is_file($path) && filesize($path) > 0) {
            return ['state' => 'ready', 'bytes' => (int) filesize($path), 'error' => null];
        }

        $mark = Cache::get($this->cacheKey($key));

        return match ($mark['state'] ?? null) {
            'working' => ['state' => 'working', 'bytes' => null, 'error' => null],
            'failed' => ['state' => 'failed', 'bytes' => null, 'error' => $mark['error'] ?? null],
            default => ['state' => 'idle', 'bytes' => null, 'error' => null],
        };
    }

    /**
     * Ask for the file. Idempotent: a finished file is answered at once, one
     * being made is left alone, and only an idle (or failed) one is queued.
     *
     * @param  array{event: string, match_no: int, user_id: int}  $notify  who to tell, and about what
     * @return array{state: string, bytes: ?int, error: ?string}
     */
    public function request(MediaFile $media, string $kind, array $msb, string $title, array $notify): array
    {
        $status = $this->status($media, $kind, $msb);

        if (in_array($status['state'], ['ready', 'working'], true)) {
            return $status;
        }

        $key = $this->key($media, $kind, $msb);

        Cache::put($this->cacheKey($key), ['state' => 'working'], self::WORKING_TTL);

        RenderBoutShare::dispatch($media->uuid, $kind, $msb, $title, $key, $notify)->onQueue('media');

        return ['state' => 'working', 'bytes' => null, 'error' => null];
    }

    /** Called by the job when it is done, either way. */
    public function settle(string $key, bool $ok, ?string $error = null): void
    {
        if ($ok) {
            Cache::forget($this->cacheKey($key));

            return;
        }

        // Remembered briefly, so the person asking sees WHY rather than a
        // button that silently starts again; then it may be asked for anew.
        Cache::put($this->cacheKey($key), ['state' => 'failed', 'error' => $error], 600);
    }

    /** The finished file, or null. */
    public function file(MediaFile $media, string $kind, array $msb): ?string
    {
        $path = $this->path($media, $this->key($media, $kind, $msb));

        return is_file($path) && filesize($path) > 0 ? $path : null;
    }

    /** Where a rendered file lives (absolute). */
    public function path(MediaFile|string $media, string $key): string
    {
        $uuid = $media instanceof MediaFile ? $media->uuid : $media;

        return rtrim((string) config('media.local_root'), '/').'/'.StoragePath::boutShare($uuid, $key);
    }

    /** The name the file is sent under — the fighters, the bout, the kind. */
    public function filename(array $msb, string $kind, int $matchNo): string
    {
        $slug = fn (string $s) => trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-');

        $red = $slug((string) ($msb['red']['name'] ?? '')) ?: 'Red';
        $blue = $slug((string) ($msb['blue']['name'] ?? '')) ?: 'Blue';

        return mb_substr($red.'-vs-'.$blue.'-bout-'.$matchNo.($kind === self::HIGHLIGHTS ? '-highlights' : ''), 0, 120).'.mp4';
    }

    /**
     * The words that travel with the file: who fought, and every scoring
     * moment with its time — the chapters, written out for an app that will
     * never show them.
     */
    public function message(array $msb, string $title, int $duration, string $link): string
    {
        $lines = [$title];

        foreach ($this->renderer->momentLines($msb, (float) max(1, $duration)) as $line) {
            $lines[] = $line;
        }

        if ($link !== '') {
            $lines[] = '';
            $lines[] = $link;
        }

        return implode("\n", $lines);
    }

    /** The bout's heading: "Red vs Blue — Event". */
    public function title(array $msb, string $event): string
    {
        $red = ($msb['red']['name'] ?? '') ?: 'Red';
        $blue = ($msb['blue']['name'] ?? '') ?: 'Blue';

        return trim($red.' vs '.$blue.($event !== '' ? ' — '.$event : ''));
    }

    /** A hash of the footage, the kind, everything drawn and how it is encoded. */
    public function key(MediaFile|string $media, string $kind, array $msb): string
    {
        $uuid = $media instanceof MediaFile ? $media->uuid : $media;

        return sha1(json_encode([
            self::VERSION,
            $uuid,
            $kind,
            $msb,
            config('media.share_export'),
        ]));
    }

    private function cacheKey(string $key): string
    {
        return 'bout-share:'.$key;
    }
}
