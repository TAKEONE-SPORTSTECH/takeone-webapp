<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;

/**
 * HOW an event takes its competitors — the organiser's choice (owner,
 * 2026-10-03): individuals entering themselves (each billed for their own
 * entry), clubs entering their athletes as a squad (the club billed for all
 * of them), or both. Later a federation entering a national team.
 *
 * `club_events.entry_channels` holds the organiser's list. NULL — every event
 * that never chose — means exactly what the platform always did: a club's or
 * a person's event takes both. (An event whose host takes its athletes from
 * clubs — a federation's competition — is closed to individuals by
 * EventHost::entryDoor(), which still applies on top of this.)
 *
 * Switching a channel off refuses NEW entries through it. Nobody already
 * entered is touched. The people running the event may always add anybody.
 */
final class EntryChannels
{
    public const INDIVIDUAL = 'individual';

    public const CLUB = 'club';

    /** In display order. `federation` joins this list when national-team entry is built. */
    public const ALL = [self::INDIVIDUAL, self::CLUB];

    /** @return list<string> */
    public function allowed(ClubEvent $event): array
    {
        $chosen = $event->entry_channels;
        if (is_array($chosen) && $chosen !== []) {
            return array_values(array_intersect(self::ALL, $chosen));
        }

        return $this->defaults($event);
    }

    public function allows(ClubEvent $event, string $channel): bool
    {
        return in_array($channel, $this->allowed($event), true);
    }

    /** Has the organiser chosen, or is the event on the platform's default? */
    public function chosen(ClubEvent $event): bool
    {
        return is_array($event->entry_channels) && $event->entry_channels !== [];
    }

    /**
     * The organiser's choice. At least one channel; only known ones. Passing
     * exactly the default stores NULL, so the event keeps following the
     * platform default rather than freezing a copy of it.
     *
     * @param  list<string>  $channels
     */
    public function set(ClubEvent $event, array $channels): void
    {
        $channels = array_values(array_intersect(self::ALL, array_unique($channels)));
        if ($channels === []) {
            throw new \InvalidArgumentException('An event must take entries through at least one channel.');
        }

        $event->forceFill([
            'entry_channels' => $channels === $this->defaults($event) ? null : $channels,
        ])->save();
    }

    /**
     * Both. A host that takes its athletes from clubs (a federation) is NOT
     * restated here: EventHost::entryDoor() is the one rule for that, and it
     * already applies on top of this — a second copy would drift from it.
     *
     * @return list<string>
     */
    private function defaults(ClubEvent $event): array
    {
        return self::ALL;
    }
}
