<?php

namespace App\Support\Modules\Surfaces;

use App\Events\Models\ClubEvent;
use App\Members\Models\User;

/**
 * A module whose bodies can be an event's ORGANISER in the shared event editor
 * (owner, 2026-10-03: "one editor for clubs, federations and people").
 *
 * The editor lists what `eventHostChoices()` returns beside the user's clubs
 * and "Myself"; saving with one of them hands the editor's validated input to
 * `createHostedEvent()`, which creates the event through the module's OWN
 * storage — so everything the module keeps about its events (a federation's
 * dashboard row, invitations, draws) exists exactly as if its own form had
 * made it — and returns the platform event.
 */
interface CreatesHostedEvents
{
    /**
     * The bodies this user may organise an event for.
     *
     * @return list<array{id: string, name: string, currency: ?string, timezone: ?string}>
     *         `id` is "<type>:<public id>", e.g. "federation:9f1c…"
     */
    public function eventHostChoices(User $user): array;

    /**
     * Create the event for the body named by `$choice` (one of
     * eventHostChoices()'s ids — re-checked here, never trusted).
     *
     * @param  array<string, mixed>  $input  the editor's validated input
     */
    public function createHostedEvent(string $choice, User $user, array $input): ?ClubEvent;
}
