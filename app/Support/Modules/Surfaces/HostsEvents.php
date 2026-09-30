<?php

namespace App\Support\Modules\Surfaces;

use App\Members\Models\User;

/**
 * A module that can HOST events — be the organiser an event belongs to — when
 * that organiser is not a club (2026-09-28).
 *
 * An event names its host by kind and id (`club_events.host_type`,
 * `host_id`); a club's event names none and keeps its `tenant_id`. The Events
 * module never names the module behind a kind: it asks this surface. Remove
 * the module and its events simply have no host to draw — nothing breaks.
 */
interface HostsEvents
{
    /** @return array<int, string> the host kinds this module answers for, e.g. ['federation'] */
    public function eventHostTypes(): array;

    /**
     * The host's public face, or null if it no longer exists:
     * ['name' => string, 'logo' => ?string (URL), 'country' => ?string (ISO-2),
     *  'currency' => ?string, 'timezone' => ?string, 'lat' => ?float, 'lng' => ?float,
     *  'color' => ?string (#hex), 'uuid' => ?string]
     */
    public function eventHost(string $type, int $id): ?array;

    /** May this user run events hosted by that host? (Organiser-level, never less than the host itself decides.) */
    public function managesEventsOf(string $type, int $id, User $user): bool;

    /** @return array<int, int> the user ids who run that host's events — told what its organisers are told. */
    public function eventHostManagers(string $type, int $id): array;

    /**
     * The event's own details were edited on the platform (the console's
     * Edit event). A host that keeps its own record of the event brings it
     * up to date; one that keeps none does nothing.
     */
    public function hostedEventChanged(\App\Events\Models\ClubEvent $event): void;

    /** The organiser accepted or declined an entry request on the host's event (the console's Entry requests). */
    public function hostedEntryDecided(\App\Events\Models\ClubEvent $event, \App\Events\Models\EventPublicEntry $entry): void;

    /**
     * Where an athlete's CLUB enters them in the host's event, when entry is
     * not the member's own to make (a federation takes its athletes from its
     * clubs). Null means members enter themselves, as at a club's event.
     *
     * @return array{url: string, enters: bool}|null  `enters` — this user runs a club that may use it
     */
    public function hostedEntryDoor(\App\Events\Models\ClubEvent $event, User $user): ?array;
}
