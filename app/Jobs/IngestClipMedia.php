<?php

namespace App\Jobs;

use App\Media\MediaIngest;
use App\Media\MediaVaults;
use App\Models\ClubEvent;
use App\Models\EventCameraClip;
use App\Models\EventMatch;
use App\Models\EventRecording;
use App\Models\MediaFile;
use App\Support\StoragePath;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Models\MediaFileSubject;
use App\Models\ClubEventRegistration;

/**
 * One clip, from this server's scratch disk into our own storage.
 *
 * Take the assembled upload, put it somewhere permanent — an attached vault or
 * this server's disk, decided by MediaVaults — then link it to the bout it
 * belongs to. This is the only destination; the external video platform this
 * used to be able to forward to has been disconnected.
 *
 * The link is written into `event_recordings`, so everything downstream that
 * already asks "does this bout have a recording?" keeps working.
 */
class IngestClipMedia implements ShouldQueue
{
    use Queueable;

    /** Long enough to move a bout onto a NAS over a hall's network. */
    public int $timeout = 1800;

    public int $tries = 3;

    /** Minutes apart: a share that has gone away does not come back in ten seconds. */
    public array $backoff = [60, 300];

    /** A camera's numbered position, in the vocabulary `event_recordings` speaks. */
    private const ANGLES = [1 => 'main', 2 => 'corner_a', 3 => 'corner_b', 4 => 'overhead'];

    public function __construct(public int $clipId, public string $scratchPath) {}

    /** One ingest per clip in flight, however many times it is asked for. */
    public function uniqueId(): string
    {
        return 'ingest-clip-'.$this->clipId;
    }

    public function handle(MediaIngest $ingest, MediaVaults $vaults): void
    {
        $clip = EventCameraClip::find($this->clipId);

        if ($clip === null) {
            $this->cleanUp();

            return;
        }

        if ($clip->media_file_id) {
            // Already stored. A retry after a lost reply must not produce a
            // second copy of the same bout.
            $this->cleanUp();

            return;
        }

        if (! is_file($this->scratchPath)) {
            $clip->forceFill([
                'play_status' => EventCameraClip::PLAY_FAILED,
                'upload_error' => 'the uploaded file was no longer on the server',
            ])->save();

            return;
        }

        $clip->forceFill(['play_status' => EventCameraClip::PLAY_UPLOADING])->save();

        $event = $clip->event_id ? ClubEvent::find($clip->event_id) : null;
        $match = $clip->match_id ? EventMatch::find($clip->match_id) : null;

        if ($event === null) {
            // A clip whose event has been deleted. Nothing to file it under, and
            // nowhere it would ever be found — say so rather than inventing a
            // home for it.
            $clip->forceFill([
                'play_status' => EventCameraClip::PLAY_FAILED,
                'upload_error' => 'the event this clip belongs to no longer exists',
            ])->save();

            $this->cleanUp();

            return;
        }

        // Filed by WHERE AND WHEN IT WAS FILMED, not by the bout it is believed
        // to show. A stream gets re-pointed to a different match mid-session
        // (live_streams.match_repointed), and a path naming the match would
        // then have to move gigabytes and invalidate every URL already issued.
        // Which bout this depicts lives on the media_files row, and is free to
        // be corrected there.
        $directory = StoragePath::capture($event, $clip->court, $clip->started_at);

        $file = $ingest->fromFile(
            scratchAbs: $this->scratchPath,
            directory: $directory,
            owner: $clip,
            eventId: $clip->event_id,
            kind: MediaFile::KIND_CLIP,
            attributes: [
                'original_name' => $this->name($clip),
                'created_by' => $clip->camera?->claimed_by,
            ],
        );

        if ($file === null || $file->status === MediaFile::STATUS_FAILED) {
            $clip->forceFill([
                'play_status' => EventCameraClip::PLAY_FAILED,
                'upload_error' => 'the clip could not be written to storage',
            ])->save();

            // Leave the scratch file for the retry — it is the only copy on this
            // side, and the phone may already have deleted its own.
            throw new \RuntimeException('Media ingest failed for clip '.$clip->id);
        }

        $clip->forceFill([
            // `processing` is honest: the bytes are safe, the ladder is not built
            // yet. TranscodeMedia flips it to ready.
            'play_status' => EventCameraClip::PLAY_PROCESSING,
            'media_file_id' => $file->id,
            'uploaded_at' => now(),
            'upload_error' => null,
        ])->save();

        // Consumed by the ingest (moved onto its storage), but a copy can survive
        // a cross-filesystem move — so ask again rather than assume.
        $this->cleanUp();

        $this->describe($vaults, $file, $event, $match, $clip);

        $this->link($clip, $file);
    }

    /**
     * Leave a human-readable note beside the footage.
     *
     * Somebody will eventually open this storage in a file browser looking for a
     * particular fight — at a federation's request, for a coach, to hand a club
     * its own recordings. Folders named by uuid and bout id make that a database
     * exercise; two small JSON files make it a five-second one.
     *
     * Labels only, and only what is already visible in the video itself.
     */
    private function describe(
        MediaVaults $vaults,
        MediaFile $file,
        ClubEvent $event,
        ?EventMatch $match,
        EventCameraClip $clip,
    ): void {
        $vaults->putMeta($file->vault, StoragePath::event($event), [
            'event' => $event->uuid,
            'title' => $event->title,
            'date' => $event->date?->toDateString(),
            'host_club' => $event->tenant?->name,
        ]);

        if ($match === null) {
            return;
        }

        // Who is in this footage, recorded rather than inferred.
        //
        // The library currently works this out by walking the draw, which finds
        // competitors and only competitors — and gives a different answer after
        // the draw is re-cut. A row survives that, and leaves somewhere to name
        // the coach or official who is also on camera.
        $this->rememberSubjects($file, $match);

        $vaults->putMeta($file->vault, StoragePath::match($event, $match), array_filter([
            'bout' => $match->getKey(),
            'mat' => $clip->court,
            'round' => $match->round ?? null,
            'blue' => $match->a_name ?? null,
            'red' => $match->b_name ?? null,
            'fought_at' => $clip->started_at?->toIso8601String(),
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Attach the media to the bout, in the table the rest of the app reads.
     *
     * A clip filmed with nothing loaded on the mat has no bout to attach to, and
     * that is not an error — the footage is still worth having, and an organiser
     * can match it up later.
     */
    private function link(EventCameraClip $clip, MediaFile $file): void
    {
        if (! $clip->match_id || ! $clip->event_id) {
            Log::info('media: clip stored with no bout attached', ['clip' => $clip->id, 'file' => $file->uuid]);

            return;
        }

        $match = EventMatch::find($clip->match_id);

        if ($match === null) {
            return;
        }

        EventRecording::updateOrCreate(
            [
                'event_id' => $clip->event_id,
                'match_id' => $clip->match_id,
                'angle' => self::ANGLES[$clip->angle] ?? 'main',
            ],
            [
                'court' => $clip->court,
                'media_file_id' => $file->id,
                // The camera's own clock is what the timeline is measured from.
                'anchor_at' => $clip->started_at,
                'started_at' => $clip->started_at,
                'ended_at' => $clip->ended_at,
                'status' => EventRecording::STATUS_LINKED,
            ]
        );

        Log::info('media: clip linked to its bout', [
            'clip' => $clip->id,
            'match' => $clip->match_id,
            'file' => $file->uuid,
        ]);

        $this->announce($clip->event_id);
    }

    /**
     * Tell anyone looking at the event that its gallery just grew.
     *
     * A refresh signal, not the clip itself: who may open a given bout differs
     * per viewer, so each console re-fetches what IT is allowed to see rather
     * than being handed a tile it cannot play. Best-effort — the footage is
     * already stored and the page is correct on its next load either way.
     */
    private function announce(int $eventId): void
    {
        rescue(function () use ($eventId) {
            $event = \App\Models\ClubEvent::find($eventId);

            if ($event === null) {
                return;
            }

            $audience = \App\Models\ClubEventRegistration::where('event_id', $eventId)
                ->pluck('user_id')
                ->push($event->created_by)
                ->filter()
                ->unique()
                ->values();

            if ($audience->isEmpty()) {
                return;
            }

            \Realtime()->publishMany($audience->map(fn ($id) => [
                'topic' => \Realtime()->userTopic((int) $id, 'events'),
                'payload' => ['action' => 'gallery', 'event' => $event->uuid],
            ])->all());
        }, null, false);
    }

    /** A name a person would recognise in a list. Never used as a path. */
    private function name(EventCameraClip $clip): string
    {
        $parts = array_filter([
            $clip->court ? 'Mat '.$clip->court : null,
            $clip->match_id ? 'Bout '.$clip->match_id : null,
            $clip->angle ? 'Angle '.$clip->angle : null,
        ]);

        return ($parts ? implode(' · ', $parts) : 'Clip '.$clip->id).'.mp4';
    }

    private function cleanUp(): void
    {
        if (is_file($this->scratchPath)) {
            @unlink($this->scratchPath);
        }
    }

    public function failed(\Throwable $e): void
    {
        EventCameraClip::where('id', $this->clipId)->update([
            'play_status' => EventCameraClip::PLAY_FAILED,
            'upload_error' => mb_substr($e->getMessage(), 0, 200),
        ]);
    }

    /**
     * Note the competitors of this bout as subjects of the file.
     *
     * Sourced from the draw, so it may be re-asserted whenever the draw changes
     * — MediaFileSubject::remember() refuses to overwrite a claim a human made
     * by hand.
     */
    private function rememberSubjects(MediaFile $file, EventMatch $match): void
    {
        $entryIds = array_filter([$match->a_competitor_id, $match->b_competitor_id]);

        if ($entryIds === []) {
            return;
        }

        $userIds = ClubEventRegistration::whereIn('id', $entryIds)
            ->pluck('user_id')
            ->filter()
            ->unique();

        foreach ($userIds as $userId) {
            // Best-effort, exactly like the meta above: a bout's footage must
            // never fail to file because a side table would not take a row.
            rescue(fn () => MediaFileSubject::remember(
                (int) $file->getKey(),
                (int) $userId,
                MediaFileSubject::ROLE_COMPETITOR,
            ), null, false);
        }
    }
}
