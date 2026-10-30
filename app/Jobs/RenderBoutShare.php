<?php

namespace App\Jobs;

use App\Media\BoutShare;
use App\Media\BoutShareRenderer;
use App\Media\MediaVaults;
use App\Media\Models\MediaFile;
use App\Members\Models\UserNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Render one bout as a file to send (App\Media\BoutShare).
 *
 * Runs on the `media` queue beside the transcodes. The footage is read where it
 * lies (or fetched once and the copy dropped afterwards, as TranscodeMedia
 * does); the source is never touched. When it finishes — either way — the
 * person who asked is told over realtime, so their button turns into "Send"
 * without anybody polling.
 */
class RenderBoutShare implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /** A long bout on a busy machine. Generous, not unbounded. */
    public int $timeout = 3600;

    /** One attempt: a failure is shown to the person waiting, who may ask again. */
    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $msb  the bout page's scoreboard payload
     * @param  array{event: string, match_no: int, user_id: int}  $notify
     */
    public function __construct(
        public string $mediaUuid,
        public string $kind,
        public array $msb,
        public string $title,
        public string $key,
        public array $notify,
    ) {}

    /** The same file is never rendered twice at once. */
    public function uniqueId(): string
    {
        return 'bout-share-'.$this->key;
    }

    public function uniqueFor(): int
    {
        return $this->timeout;
    }

    public function handle(MediaVaults $vaults, BoutShareRenderer $renderer, BoutShare $share): void
    {
        $media = MediaFile::where('uuid', $this->mediaUuid)->first();

        if ($media === null) {
            $this->finish($share, false, 'The footage has been removed.');

            return;
        }

        $out = $share->path($media, $this->key);

        if (is_file($out) && filesize($out) > 0) {
            $this->finish($share, true);

            return;
        }

        $inPlace = $vaults->inPlacePath($media);
        $source = $inPlace ?? $vaults->ensureLocal($media);

        if ($source === null) {
            $this->finish($share, false, 'The footage could not be read from its storage.');

            return;
        }

        try {
            $result = $renderer->render($source, $this->msb, $this->kind, $out, $this->title);
        } finally {
            if ($inPlace === null) {
                $vaults->forgetLocalCopy($media);
            }
        }

        Log::info('bout-share: rendered', [
            'media' => $media->uuid,
            'kind' => $this->kind,
            'ok' => $result['ok'],
            'bytes' => $result['bytes'],
            'error' => $result['error'],
        ]);

        $this->finish($share, $result['ok'], $result['error']);
    }

    /** A crash is a failure the person waiting should hear about, not silence. */
    public function failed(?\Throwable $e): void
    {
        $this->finish(app(BoutShare::class), false, 'The video could not be made.');
    }

    private function finish(BoutShare $share, bool $ok, ?string $error = null): void
    {
        $share->settle($this->key, $ok, $error);

        // A refresh signal, to the one person who asked: their page re-asks the
        // status door, which re-checks who they are. Nothing about the file
        // travels in the payload.
        $userId = (int) ($this->notify['user_id'] ?? 0);

        if ($userId <= 0) {
            return;
        }

        rescue(fn () => \Realtime()->publishToUser($userId, 'events', [
            'action' => 'bout-share',
            'event' => (string) ($this->notify['event'] ?? ''),
            'match_no' => (int) ($this->notify['match_no'] ?? 0),
            'kind' => $this->kind,
        ]), null, false);

        // A finished file is worth a real notification: making one takes about
        // as long as the bout, and nobody waits on a page for five minutes. It
        // opens the bout with the send sheet already up on this cut.
        if ($ok) {
            rescue(fn () => UserNotification::notifyUser($userId, 'event', __('events::events.bout_share_ready_title'), [
                'body' => $this->title,
                'icon' => 'bi-send-fill',
                'action_url' => route('me.events.bout.video', [
                    'event' => (string) ($this->notify['event'] ?? ''),
                    'matchNo' => (int) ($this->notify['match_no'] ?? 0),
                ]).'?send='.$this->kind,
            ]), null, false);
        }
    }
}
