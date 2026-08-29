<?php

namespace App\Jobs;

use App\Media\Ffmpeg;
use App\Media\MediaIngest;
use App\Models\LiveStream;
use App\Models\MediaFile;
use App\Support\StoragePath;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * The broadcast is over. Keep the fight.
 *
 * ── Why this job is the point of recording at all ──────────────────────────
 *
 * A live stream that vanishes when the phone stops is a bout lost in real time —
 * watched by whoever happened to be watching, gone for everybody else, and gone
 * for the athlete who wants to see it again. So the media server records every
 * broadcast, and this turns that recording into an ordinary media file: filed
 * under the same bout, on the same attached storage, transcoded by the same
 * pipeline, served by the same authorised route.
 *
 * The result is that "somebody streamed mat 2" and "somebody filmed mat 2" end
 * up in exactly the same place. Nothing downstream has to know which it was.
 *
 * ── Why it stitches segments together ──────────────────────────────────────
 *
 * MediaMTX records a live session as a series of fMP4 segments — that is what
 * makes recording survive a crash, since each segment is closed as it is written.
 * A viewer wants one file. ffmpeg concatenates them without re-encoding (`-c
 * copy`), which is near-instant even for a long session and loses nothing.
 */
class IngestLiveRecording implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 3;

    /**
     * Minutes apart, and the FIRST delay matters: the media server closes the
     * last segment slightly after the publisher disappears, so an immediate run
     * would stitch a recording that is still being written.
     */
    public array $backoff = [60, 300];

    public function __construct(public int $streamId) {}

    public function uniqueId(): string
    {
        return 'ingest-live-'.$this->streamId;
    }

    public function handle(MediaIngest $ingest, Ffmpeg $ffmpeg): void
    {
        $stream = LiveStream::with(['event', 'match'])->find($this->streamId);

        if ($stream === null) {
            return;
        }

        if ($stream->media_file_id) {
            return;     // already ingested; a retry must not produce a second copy
        }

        if ($stream->event === null) {
            $this->giveUp($stream, 'the event this stream belonged to no longer exists');

            return;
        }

        $segments = $this->segments($stream);

        if ($segments === []) {
            // Nothing was recorded. Ordinary rather than alarming: somebody
            // pressed Go Live, thought better of it, and stopped before a frame
            // was sent. Do not retry forever over a broadcast that never was.
            Log::info('live: nothing recorded for this stream', ['stream' => $stream->public_id]);

            $this->release_or_stop($stream);

            return;
        }

        if (! $ffmpeg->available()) {
            $this->giveUp($stream, 'this server has no ffmpeg, so the recording could not be assembled');

            return;
        }

        $scratch = $this->stitch($segments, $ffmpeg);

        if ($scratch === null) {
            $this->giveUp($stream, 'the recorded segments could not be assembled into one file');

            return;
        }

        /* Where it belongs, and this distinction matters.
         *
         * A broadcast started FOR a bout is that bout's video. But the ordinary
         * case is a phone left running on a mat while six bouts happen — filing
         * an hour of that under whichever bout was up when somebody pressed Go
         * Live would be a lie, and it would put the wrong video on an athlete's
         * record. So a mat-level broadcast goes under the MAT, where anybody
         * looking for that afternoon's footage will actually find it. */
        /* A broadcast that stayed on ONE bout is that bout's video. Three tests,
         * and the third is the one that is easy to miss:
         *
         *   · it has to name a bout at all;
         *   · it must not have been repointed as the draw advanced;
         *   · and it has to be SHORT ENOUGH to be a bout.
         *
         * Without the last test, a camera propped on a mat at nine in the morning
         * and stopped at noon — never repointed, because nobody had reason to —
         * is filed and LINKED as the bout that happened to be up when it started.
         * Three hours of fourteen children's fights on one child's record, with
         * nothing anywhere reading as an error. */
        $seconds = $stream->started_at && $stream->ended_at
            ? $stream->started_at->diffInSeconds($stream->ended_at)
            : null;

        $tooLongForABout = $seconds !== null && $seconds > (int) config('live.bout_max_seconds', 1200);

        $isBoutVideo = filled($stream->match_id)
            && ! $stream->match_repointed
            && ! $tooLongForABout;

        if ($tooLongForABout && filled($stream->match_id)) {
            Log::info('live: session too long to be one bout, filing under the mat', [
                'stream' => $stream->public_id,
                'seconds' => $seconds,
                'court' => $stream->court,
            ]);
        }

        // One path either way: the mat and the day it was filmed. Whether this
        // is one bout or an hour of a mat is a question about the RECORDING,
        // answered by media_files.owner_id — not by which folder it sits in.
        $directory = StoragePath::capture($stream->event, $stream->court, $stream->started_at);

        // The same door every other clip comes through: uuid filename, checksum,
        // vault decision, transcode dispatch.
        $file = $ingest->fromFile(
            scratchAbs: $scratch,
            directory: $directory,
            owner: $stream,
            eventId: $stream->event_id,
            kind: MediaFile::KIND_RECORDING,
            attributes: [
                'original_name' => $this->name($stream),
                'created_by' => $stream->created_by,
                'meta' => [
                    'source' => 'live',
                    'live_stream' => $stream->public_id,
                    'court' => $stream->court,
                    'started_at' => $stream->started_at?->toIso8601String(),
                    'ended_at' => $stream->ended_at?->toIso8601String(),
                ],
            ],
        );

        if ($file === null || $file->status === MediaFile::STATUS_FAILED) {
            $this->giveUp($stream, 'the recording could not be written to storage');

            throw new \RuntimeException('Live recording ingest failed for stream '.$stream->public_id);
        }

        $stream->forceFill(['media_file_id' => $file->id, 'recording_error' => null])->save();

        // A bout-specific broadcast joins the same table a camera clip does, so
        // everything downstream that asks "does this bout have a recording?" gets
        // the same answer whether it was filmed or streamed.
        //
        // Only when the session really was one bout. And never destructively: a
        // row that already carries a video from the other platform keeps it —
        // this fills a gap, it does not overwrite somebody else's answer.
        if ($isBoutVideo) {
            $recording = \App\Models\EventRecording::firstOrNew([
                'event_id' => $stream->event_id,
                'match_id' => $stream->match_id,
                'angle' => 'main',
            ]);

            if (blank($recording->media_file_id) && blank($recording->play_url)) {
                $recording->fill([
                    'court' => $stream->court,
                    'media_file_id' => $file->id,
                    'anchor_at' => $stream->started_at,
                    'started_at' => $stream->started_at,
                    'ended_at' => $stream->ended_at,
                    'status' => \App\Models\EventRecording::STATUS_LINKED,
                ])->save();
            } else {
                Log::info('live: bout already has a recording, leaving it alone', [
                    'match' => $stream->match_id,
                    'file' => $file->uuid,
                ]);
            }
        }

        // The raw segments have served their purpose. Deleted only now, with the
        // assembled file already safely on its storage.
        $this->cleanUp($segments);

        Log::info('live: recording kept', [
            'stream' => $stream->public_id,
            'file' => $file->uuid,
            'bytes' => $file->bytes,
            'vault' => $file->vault?->name ?? 'local',
        ]);
    }

    /**
     * Every segment this stream's session wrote, oldest first.
     *
     * The media server is configured to record into a folder per stream path, so
     * this is a glob rather than a guess — and the ordering is the filename's,
     * which is a timestamp, which is why it is the right ordering.
     *
     * @return array<int,string>
     */
    private function segments(LiveStream $stream): array
    {
        $dir = rtrim((string) config('live.record_path'), '/').'/'.$stream->mediaPath();

        if (! is_dir($dir)) {
            return [];
        }

        $files = array_filter(
            glob($dir.'/*.mp4') ?: [],
            // Skip anything still open: a zero-byte or actively-growing segment
            // would poison the concat.
            fn ($path) => is_file($path) && filesize($path) > 0
        );

        sort($files);

        return array_values($files);
    }

    /**
     * Concatenate the segments into one mp4, without re-encoding.
     *
     * `-c copy` through the concat demuxer: the streams are already H.264/AAC
     * from the same session with the same parameters, so this is a remux. A
     * re-encode here would cost GPU minutes and quality for nothing — the
     * TRANSCODE step that follows is where the HLS ladder is made.
     */
    private function stitch(array $segments, Ffmpeg $ffmpeg): ?string
    {
        $out = sys_get_temp_dir().'/live_'.uniqid().'.mp4';

        // A single segment needs no concat at all.
        if (count($segments) === 1) {
            return @copy($segments[0], $out) ? $out : null;
        }

        $list = sys_get_temp_dir().'/live_concat_'.uniqid().'.txt';
        $body = '';

        foreach ($segments as $path) {
            // The concat demuxer's own quoting: single quotes, escaped.
            $body .= "file '".str_replace("'", "'\\''", $path)."'\n";
        }

        file_put_contents($list, $body);

        $cmd = implode(' ', [
            escapeshellcmd((string) $ffmpeg->ffmpeg()),
            '-hide_banner -loglevel error -y',
            '-f concat -safe 0',
            '-i '.escapeshellarg($list),
            '-c copy',
            // Live segments start at an arbitrary timestamp; without this the
            // assembled file can begin with a huge offset and players show a
            // duration measured in hours.
            '-fflags +genpts',
            '-movflags +faststart',
            escapeshellarg($out),
            '2>&1',
        ]);

        exec($cmd, $output, $code);
        @unlink($list);

        if ($code !== 0 || ! is_file($out) || filesize($out) === 0) {
            Log::error('live: could not assemble the recording', [
                'tail' => implode(' ', array_slice($output, -5)),
            ]);

            @unlink($out);

            return null;
        }

        return $out;
    }

    /** A name a person would recognise in a list. Never used as a path. */
    private function name(LiveStream $stream): string
    {
        $parts = array_filter([
            $stream->court ? 'Mat '.$stream->court : null,
            $stream->match_id ? 'Bout '.$stream->match_id : null,
            'live',
        ]);

        return implode(' · ', $parts).'.mp4';
    }

    private function cleanUp(array $segments): void
    {
        foreach ($segments as $path) {
            @unlink($path);
        }

        // The stream's folder, if it is now empty.
        if ($segments !== []) {
            @rmdir(dirname($segments[0]));
        }
    }

    /**
     * A broadcast that recorded nothing yet.
     *
     * Released once in case the last segment is still being closed, then let go —
     * silence is a legitimate outcome and must not become a job that retries
     * forever.
     */
    private function release_or_stop(LiveStream $stream): void
    {
        if ($this->attempts() < 2) {
            $this->release(120);

            return;
        }

        $stream->forceFill(['recording_error' => 'nothing was recorded'])->save();
    }

    private function giveUp(LiveStream $stream, string $why): void
    {
        Log::warning('live: recording not kept', ['stream' => $stream->public_id, 'why' => $why]);

        $stream->forceFill(['recording_error' => $why])->save();
    }

    public function failed(\Throwable $e): void
    {
        LiveStream::where('id', $this->streamId)->update([
            'recording_error' => mb_substr($e->getMessage(), 0, 200),
        ]);
    }
}
