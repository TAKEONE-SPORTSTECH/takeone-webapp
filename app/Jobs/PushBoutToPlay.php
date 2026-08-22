<?php

namespace App\Jobs;

use App\Models\ClubEvent;
use App\Models\EventMatch;
use App\Models\EventRecording;
use App\Play\BoutPayload;
use App\Play\PlayClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Send one bout's competition truth to TAKEONE Play.
 *
 * Queued, never inline: an event day must not wait on the video platform, and the
 * database is the source of truth either way (Match Sync Contract, Flow A). A
 * failed push is visible in event_recordings.sync_error and retried; it never
 * blocks scoring or a page render.
 *
 * A bout with no linked recording is a silent no-op — the ordinary case, since
 * most bouts are never filmed. Not an error, not a retry, not a log line.
 */
class PushBoutToPlay implements ShouldQueue
{
    use Queueable;

    /** Spread the retries: Play being briefly down should not burn all three at once. */
    public array $backoff = [10, 60, 300];

    public int $tries = 4;

    public function __construct(public int $matchId) {}

    /**
     * One job per bout in flight.
     *
     * Ten edits in a minute should be one push, not ten — and the last one wins,
     * because each push carries the whole record rather than a delta.
     */
    public function uniqueId(): string
    {
        return 'push-bout-'.$this->matchId;
    }

    public function handle(PlayClient $play): void
    {
        if (! $play->enabled()) {
            return;
        }

        $match = EventMatch::with(['category', 'competitorA.user', 'competitorB.user'])->find($this->matchId);

        if ($match === null) {
            return;
        }

        $recording = EventRecording::where('match_id', $match->id)
            ->where('status', EventRecording::STATUS_LINKED)
            ->whereNotNull('play_video_key')
            ->latest('id')
            ->first();

        // No video: nothing to push to. The normal case.
        if ($recording === null) {
            return;
        }

        $event = ClubEvent::find($match->event_id);

        if ($event === null) {
            return;
        }

        // One higher than the last accepted push, so Play can refuse anything that
        // arrives out of order.
        $revision = (int) ($recording->play_revision ?? 0) + 1;

        $result = $play->pushMatch(
            $recording->play_video_key,
            BoutPayload::for($event, $match, $revision)
        );

        if ($result === null || ($result['ok'] ?? false) !== true) {
            $recording->forceFill(['sync_error' => 'push failed'])->save();

            // Let the queue retry; the payload is rebuilt from current state each
            // time, so a later attempt sends fresher data rather than stale.
            throw new \RuntimeException('Play refused or could not be reached for bout '.$match->id);
        }

        $recording->forceFill([
            'play_revision' => (int) ($result['revision'] ?? $revision),
            'pushed_at' => now(),
            'sync_error' => null,
        ])->save();
    }
}
