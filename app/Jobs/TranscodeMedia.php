<?php

namespace App\Jobs;

use App\Media\Ffmpeg;
use App\Media\MediaVaults;
use App\Models\MediaFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Make one stored file watchable.
 *
 * The source is never touched: it stays wherever it was put — the NAS, usually —
 * and this writes a DERIVED HLS ladder beside nothing at all, on local disk.
 * That split is deliberate and load-bearing:
 *
 *   · The source is the irreplaceable thing. It belongs on attached storage,
 *     and it is what a backup or an archive is about.
 *   · The ladder is regenerable. It is also read as dozens of small files per
 *     minute of playback, and pulling six-second segments over SMB is how you
 *     make a hall watch a spinner. So it lives here, and deleting it to reclaim
 *     disk is always safe.
 *
 * A vault that cannot be read in place (SMB) means fetching the source first;
 * that copy is dropped again at the end, so a transcode does not quietly leave a
 * second copy of every bout on the event server.
 */
class TranscodeMedia implements ShouldQueue
{
    use Queueable;

    /** A long bout on a busy GPU. Generous, but not unbounded. */
    public int $timeout = 3600;

    public int $tries = 2;

    public array $backoff = [60];

    public function __construct(public string $mediaUuid) {}

    /** One transcode per file in flight, however many times it is asked for. */
    public function uniqueId(): string
    {
        return 'transcode-'.$this->mediaUuid;
    }

    public function handle(MediaVaults $vaults, Ffmpeg $ffmpeg): void
    {
        $file = MediaFile::where('uuid', $this->mediaUuid)->first();

        if ($file === null) {
            return;     // deleted while queued — nothing to make watchable
        }

        if ($file->status === MediaFile::STATUS_READY && filled($file->hls_rel_path)) {
            return;     // already done; a retry must not re-encode a competition
        }

        if (! $ffmpeg->available()) {
            // Not a failure of this file. The source is stored and downloadable;
            // it simply cannot be streamed until the server has ffmpeg.
            Log::warning('media: no ffmpeg on this server, leaving the file un-transcoded', ['file' => $file->uuid]);

            return;
        }

        $file->forceFill(['status' => MediaFile::STATUS_PROCESSING, 'error' => null])->save();

        // Was the source already here, or did we have to fetch it? Only a fetched
        // copy gets cleaned up — deleting an in-place file would delete the
        // source itself.
        $inPlace = $vaults->inPlacePath($file);
        $source = $inPlace ?? $vaults->ensureLocal($file);

        if ($source === null) {
            $file->forceFill([
                'status' => MediaFile::STATUS_MISSING,
                'error' => 'The source file could not be read from its storage.',
            ])->save();

            return;
        }

        // Probe now if ingest could not (a clip stored while the NAS was the only
        // copy, say) — a listing wants a duration whether or not the encode works.
        if ($file->duration_seconds === null || $file->height === null) {
            $probe = $ffmpeg->probe($source);

            $file->forceFill(array_filter([
                'duration_seconds' => $probe['duration'],
                'width' => $probe['width'],
                'height' => $probe['height'],
            ], fn ($v) => $v !== null))->save();
        }

        $hlsRel = $vaults->hlsPathFor($file);
        $hlsAbs = rtrim(config('media.local_root'), '/').'/'.$hlsRel;

        $result = $ffmpeg->hls($source, $hlsAbs, $file->height);

        if (! $result['ok']) {
            $file->forceFill([
                'status' => MediaFile::STATUS_FAILED,
                'error' => mb_substr((string) $result['error'], 0, 240),
            ])->save();

            if ($inPlace === null) {
                $vaults->forgetLocalCopy($file);
            }

            throw new \RuntimeException('Transcode failed for '.$file->uuid.': '.$result['error']);
        }

        // A poster, from a second into the bout. Cheap, and a video with no
        // thumbnail reads as broken in every list it appears in.
        $posterRel = $hlsRel.'/poster.jpg';
        $ffmpeg->poster($source, rtrim(config('media.local_root'), '/').'/'.$posterRel, 1);

        $file->forceFill([
            'status' => MediaFile::STATUS_READY,
            'hls_rel_path' => $hlsRel,
            'error' => null,
            'meta' => array_merge((array) $file->meta, [
                'variants' => $result['variants'],
                'transcoded_at' => now()->toIso8601String(),
            ]),
        ])->save();

        // The fetched copy has done its job. The source is still on its vault.
        if ($inPlace === null) {
            $vaults->forgetLocalCopy($file);
        }

        Log::info('media: transcoded', [
            'file' => $file->uuid,
            'variants' => $result['variants'],
            'vault' => $file->vault?->name ?? 'local',
        ]);
    }

    public function failed(\Throwable $e): void
    {
        MediaFile::where('uuid', $this->mediaUuid)->update([
            'status' => MediaFile::STATUS_FAILED,
            'error' => mb_substr($e->getMessage(), 0, 240),
        ]);
    }
}
