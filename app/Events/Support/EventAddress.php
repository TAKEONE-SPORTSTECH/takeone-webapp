<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use Illuminate\Support\Facades\Route;

/**
 * Where an event, and each of its sections, lives.
 *
 * One address for every reader: `/me/events/{uuid}` and the sections under
 * it. `/e/{uuid}` was retired on 2026-09-27 (owner's decision) — old links to
 * it answer "not found" — so there is no second door to choose between any
 * more; the poster and the section pages ask this rather than naming a route,
 * so a section's address is decided in one place.
 */
final class EventAddress
{
    /** Section key → the member route that serves it. */
    private const SECTIONS = [
        'draw' => 'me.events.bracket.manage',
        'officials' => 'me.events.officiating',
        'gallery' => 'me.events.gallery',
        'participants' => 'me.events.participants',
        'results' => 'me.events.results.page',
        'table' => 'me.events.table',
    ];

    public function __construct(private ClubEvent $event) {}

    /** The event's own page. */
    public function page(): string
    {
        return route('me.events.show', ['event' => $this->event->uuid]);
    }

    /**
     * One section of it — draw · officials · gallery · participants ·
     * results · table.
     *
     * A route not registered yet (a route cache built before it existed)
     * falls back to the nearest page that is: the organiser's roster for the
     * entry list, the event page otherwise. Never a throw on a live page.
     */
    public function section(string $section): string
    {
        $name = self::SECTIONS[$section] ?? null;

        if ($name && Route::has($name)) {
            return route($name, ['event' => $this->event->uuid]);
        }

        if ($section === 'participants' && Route::has('me.events.people')) {
            return route('me.events.people', ['event' => $this->event->uuid]);
        }

        return $this->page();
    }
}
