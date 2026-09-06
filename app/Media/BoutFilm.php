<?php

namespace App\Media;

use App\Models\EventMatch;
use App\Models\EventRecording;
use Illuminate\Support\Collection;

/**
 * What footage exists of a bout, and where to play it from.
 *
 * A bout is not one video. A mat can carry four cameras — the main angle and
 * three corners — and each becomes its own recording against the same bout. This
 * resolves them all, in panel order, so a reviewer can switch angle without
 * leaving the moment they were watching.
 *
 * Two rules the rest of the platform relies on:
 *
 *   • HLS when it exists, the original otherwise. A transcoded ladder seeks
 *     instantly and adapts to the hall's wifi; the progressive file is the
 *     honest fallback while the ladder is still being built, not a failure.
 *
 *   • Only PLAYABLE media is offered. A clip still uploading or still encoding
 *     has no entry rather than a broken one — the same rule the bout page's
 *     watch button already follows.
 */
class BoutFilm
{
    /** Panel order: the main camera first, then the corners, then overhead. */
    private const ORDER = ['main' => 0, 'corner_a' => 1, 'corner_b' => 2, 'overhead' => 3];

    /**
     * Every angle of this bout that can actually be played.
     *
     * @return array<int, array<string, mixed>>
     */
    public function angles(EventMatch $match): array
    {
        return $this->fromRecordings($this->recordings($match));
    }

    /** The linked recordings of a bout that carry local media, newest first per angle. */
    public function recordings(EventMatch $match): Collection
    {
        return EventRecording::with('mediaFile')
            ->where('match_id', $match->id)
            ->where('status', EventRecording::STATUS_LINKED)
            ->whereNotNull('media_file_id')
            ->orderBy('id')
            ->get()
            // One entry per angle: a re-cut replaces its predecessor rather than
            // stacking a second "main" beside it.
            ->keyBy(fn (EventRecording $r) => $r->angle ?: 'main')
            ->values();
    }

    /**
     * Shape recordings into player-ready angles.
     *
     * @param  Collection<int, EventRecording>  $recordings
     * @return array<int, array<string, mixed>>
     */
    public function fromRecordings(Collection $recordings): array
    {
        $angles = $recordings
            ->filter(fn (EventRecording $r) => $r->mediaFile?->isPlayable())
            ->map(function (EventRecording $r) {
                $file = $r->mediaFile;
                $streamable = filled($file->hls_rel_path);

                return [
                    'recording_id' => $r->id,
                    'angle' => $r->angle ?: 'main',
                    'label' => $this->angleLabel($r->angle ?: 'main'),
                    'uuid' => $file->uuid,
                    // The ladder when there is one; the original always, because
                    // a browser without MSE (an older iOS webview) needs it and
                    // because it is what a download hands over.
                    // Named all the way to the playlist. The route defaults to
                    // it, but a URL ending in `/hls` tells a player nothing —
                    // every extension-sniffing consumer (the lightbox, a native
                    // HLS implementation, a share target) then treats a manifest
                    // as an opaque file and plays nothing at all.
                    'hls' => $streamable
                        ? route('media.hls', ['file' => $file->uuid, 'path' => 'playlist.m3u8'])
                        : null,
                    'mp4' => route('media.original', ['file' => $file->uuid]),
                    'poster' => $streamable ? route('media.poster', ['file' => $file->uuid]) : null,
                    'duration' => (int) ($file->duration_seconds ?? 0),
                    'width' => (int) ($file->width ?? 0),
                    'height' => (int) ($file->height ?? 0),
                    'bytes' => (int) ($file->bytes ?? 0),
                    // A ladder still building is worth saying out loud: the page
                    // plays the original meanwhile and can offer to re-check.
                    'transcoding' => ! $streamable,
                ];
            })
            ->sortBy(fn (array $a) => self::ORDER[$a['angle']] ?? 99)
            ->values()
            ->all();

        return $angles;
    }

    /** Does this bout have anything watchable at all? */
    public function exists(EventMatch $match): bool
    {
        return $this->angles($match) !== [];
    }

    private function angleLabel(string $angle): string
    {
        return match ($angle) {
            'corner_a' => __('events.bout_video_angle_corner_a'),
            'corner_b' => __('events.bout_video_angle_corner_b'),
            'overhead' => __('events.bout_video_angle_overhead'),
            default => __('events.bout_video_angle_main'),
        };
    }
}
