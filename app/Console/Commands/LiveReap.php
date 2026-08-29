<?php

namespace App\Console\Commands;

use App\Jobs\IngestLiveRecording;
use App\Models\LiveStream;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Ask the media server what is actually on air, and correct anything that is not.
 *
 * ── The gap this closes ────────────────────────────────────────────────────
 *
 * The lifecycle hooks are reliable while the media server is running: a phone
 * that loses signal produces an unpublish, and the recording is picked up. What
 * they cannot survive is the media server itself going away mid-broadcast — a
 * restart, a deploy, the box rebooting. Then a row is left claiming a mat is live
 * with nothing ever coming to say otherwise, and the last thing anybody sees on
 * the console is a mat permanently on air.
 *
 * So the state is reconciled against the one authority that cannot be wrong: the
 * server's own list of publishing paths. Anything the database thinks is live and
 * the server has never heard of is ended — AND its recording is picked up, which
 * matters more than the status, because those segments are a bout that was fought.
 */
class LiveReap extends Command
{
    protected $signature = 'live:reap {--dry-run : Report what would be ended and change nothing}';

    protected $description = 'End live streams the media server is no longer publishing, and keep their recordings';

    public function handle(): int
    {
        $believed = LiveStream::where('status', LiveStream::STATUS_LIVE)->get();

        if ($believed->isEmpty()) {
            return self::SUCCESS;       // nothing claims to be live; say nothing
        }

        $publishing = $this->publishingPaths();

        if ($publishing === null) {
            // The media server is unreachable. That is NOT grounds for ending
            // every broadcast: it may be restarting while a competition is still
            // filming, and marking eight mats ended would be a worse lie than the
            // one being corrected. Report and leave them alone.
            $this->warn('The media server could not be reached — leaving '.$believed->count().' stream(s) as they are.');

            return self::FAILURE;
        }

        $ended = 0;

        foreach ($believed as $stream) {
            if (in_array($stream->mediaPath(), $publishing, true)) {
                // Genuinely on air. Refresh the heartbeat while we are here.
                $this->option('dry-run') or $stream->touchSeen();

                continue;
            }

            $this->line("  ending {$stream->public_id} ({$stream->label}) — the media server is not publishing it");

            if ($this->option('dry-run')) {
                continue;
            }

            $stream->markEnded();

            // The point of the exercise: whatever was recorded before the server
            // went away is still a bout, and it is still wanted.
            if (! $stream->media_file_id) {
                IngestLiveRecording::dispatch($stream->id)->onQueue('media');
            }

            $ended++;
        }

        if ($ended > 0) {
            $this->info("Ended {$ended} stale stream(s); their recordings are being picked up.");
        }

        return self::SUCCESS;
    }

    /**
     * The paths the media server currently has a publisher on.
     *
     * @return array<int,string>|null  null when the server cannot be reached
     */
    private function publishingPaths(): ?array
    {
        try {
            $res = Http::timeout(4)->get(rtrim((string) config('live.api_url'), '/').'/v3/paths/list');

            if (! $res->successful()) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return collect($res->json('items') ?? [])
            ->filter(fn ($item) => (bool) ($item['ready'] ?? false))
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }
}
