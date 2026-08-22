<?php

namespace App\Console\Commands;

use App\Models\EventRecording;
use App\Play\MirrorBoutTimeline;
use App\Play\PlayClient;
use Illuminate\Console\Command;

/**
 * Pull bout timelines from TAKEONE Play into takeone's mirror.
 *
 * Step 1 of the Match Sync Contract, where the pull is still driven by hand or
 * by a schedule. Step 3 adds Play's notification so this runs on edit instead;
 * this command stays useful after that as the way to repair a mirror and to
 * verify the connection before an event day.
 */
class PlayPullTimeline extends Command
{
    protected $signature = 'play:pull-timeline
                            {recording? : One event_recordings id; omit with --all}
                            {--all : Every linked recording}';

    protected $description = "Mirror bout timelines from TAKEONE Play (Play owns them; this never writes back)";

    public function handle(PlayClient $play, MirrorBoutTimeline $mirror): int
    {
        if (! $play->enabled()) {
            // Not a failure: the flag being off is the documented default, and
            // saying so plainly beats a silent no-op that looks like success.
            $this->warn('PLAY_INTEGRATION_ENABLED is off — nothing was pulled.');

            return self::SUCCESS;
        }

        $recordings = $this->option('all')
            ? EventRecording::where('status', EventRecording::STATUS_LINKED)->orderBy('id')->get()
            : EventRecording::where('id', (int) $this->argument('recording'))->get();

        if ($recordings->isEmpty()) {
            $this->warn('No matching recording.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($recordings as $recording) {
            $result = ($mirror)($recording);

            $label = 'recording '.$recording->id.' (bout '.($recording->match_id ?? '?').', video '.($recording->play_video_key ?? '—').')';

            if (($result['reason'] ?? null) === 'no_video') {
                $this->line('  · '.$label.' — no video, skipped');

                continue;
            }

            if (! $result['ok']) {
                $failed++;
                $this->error('  ✗ '.$label.' — '.($result['reason'] ?? 'failed'));

                continue;
            }

            $this->info('  ✓ '.$label.' — '.$result['rounds'].' rounds, '.$result['points'].' points');
        }

        // Non-zero on failure so a scheduled run is visibly broken rather than
        // quietly stale.
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
