<?php

namespace App\Support\Modules\Surfaces;

use App\Events\Models\ClubEvent;

/**
 * A module that keeps artwork for some platform events in its OWN records —
 * a federation's event carries its posters and logo on its own rows, not in
 * `club_events.images` (owner, 2026-10-04).
 *
 * Asked by App\Events\Support\PublicEvent::photo() (the cover) and
 * App\Events\Support\ShareCard (the link preview) only when the event has no
 * image of its own.
 */
interface ProvidesEventArtwork
{
    /**
     * One shape of the event's artwork, or null when there is none of it.
     *
     * @param  string  $shape  'portrait' | 'landscape' — exact, no fallback
     * @return array{url: string, disk: string, path: string}|null
     *         `url` a reader may load; `disk`/`path` for drawing from it
     */
    public function eventArtwork(ClubEvent $event, string $shape): ?array;

    /**
     * The organiser's logo FILE, for drawing (the link preview card).
     *
     * @return array{disk: string, path: string}|null
     */
    public function eventLogoFile(ClubEvent $event): ?array;
}
