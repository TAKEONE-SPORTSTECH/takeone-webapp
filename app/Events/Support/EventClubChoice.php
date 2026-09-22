<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Events\Models\EventClub;
use App\Clubs\Models\Tenant;

/**
 * "Which club is this person here with?" — asked of somebody who is NOT an
 * entrant, and answered the same way an entry answers it.
 *
 * An entry records the club an athlete competes for on the registration
 * (`EntryEditor::applyClub`). An OFFICIAL is the same question with none of an
 * entry's rules: nobody is claiming a membership in order to compete, so there
 * is no `representableClubs` check to pass — the organiser writing the sheet
 * simply names who the referee came with, or says nobody.
 *
 * Shared rather than re-implemented (CLAUDE.md → Shared Stays Shared) so both
 * halves keep the SAME two key shapes: a tenant slug, or an `event_clubs` uuid
 * for a visiting club that has no account on the platform. One key, one
 * resolution, whichever sheet asked.
 */
final class EventClubChoice
{
    /**
     * The clubs this event can name, plus the host — for a picker.
     *
     * `EntryEditor::clubOptions()` is the list and the dedup; the HOST is
     * unioned on because an event with no entries yet has none of the clubs
     * that list is derived from, and "the club running this" is the commonest
     * answer of all for an official.
     *
     * @return array<int, array{slug: string, name: string, logo: ?string}>
     */
    public static function options(ClubEvent $event): array
    {
        $out = app(EntryEditor::class)->clubOptions($event);

        $host = $event->tenant;

        if ($host && $host->status === 'active'
            && ! collect($out)->contains(fn ($c) => $c['slug'] === $host->slug)) {
            array_unshift($out, [
                'slug' => (string) $host->slug,
                'name' => (string) $host->club_name,
                'logo' => $host->logo ? file_url($host->logo) : null,
            ]);
        }

        return $out;
    }

    /**
     * Turn the posted key into the two columns that record it.
     *
     * Anything unrecognised resolves to independent rather than failing: a key
     * naming a club that has since been removed from the event is a stale
     * form, not an attack, and "no club" is the honest reading of it.
     *
     * @return array{representing_tenant_id: ?int, event_club_id: ?int}
     */
    public static function resolve(ClubEvent $event, mixed $key): array
    {
        $none = ['representing_tenant_id' => null, 'event_club_id' => null];

        $key = is_string($key) ? trim($key) : null;

        if ($key === null || $key === '') {
            return $none;
        }

        // This event's own list first — the only place a club with no account
        // can be named at all.
        $onEvent = EventClub::query()
            ->where('event_id', $event->id)
            ->where('state', '!=', 'declined')
            ->where('uuid', $key)
            ->first(['id', 'tenant_id']);

        if ($onEvent !== null && ! $onEvent->tenant_id) {
            return ['representing_tenant_id' => null, 'event_club_id' => (int) $onEvent->id];
        }

        $tenant = $onEvent !== null
            ? Tenant::where('id', $onEvent->tenant_id)->where('status', 'active')->first(['id'])
            : Tenant::where('slug', $key)->where('status', 'active')->first(['id']);

        return $tenant
            ? ['representing_tenant_id' => (int) $tenant->id, 'event_club_id' => null]
            : $none;
    }

    /**
     * How a recorded club reads back: the key the picker re-selects, the name
     * the sheet prints, and the logo beside it. Null when independent.
     *
     * @return array{slug: ?string, name: string, logo: ?string}|null
     */
    public static function describe(?int $tenantId, ?int $eventClubId): ?array
    {
        if ($tenantId) {
            $t = Tenant::find($tenantId, ['id', 'slug', 'club_name', 'logo']);

            return $t ? [
                'slug' => (string) $t->slug,
                'name' => (string) $t->club_name,
                'logo' => $t->logo ? file_url($t->logo) : null,
            ] : null;
        }

        if ($eventClubId) {
            $c = EventClub::find($eventClubId, ['id', 'uuid', 'name', 'logo']);

            return $c ? [
                'slug' => (string) $c->uuid,
                'name' => (string) $c->name,
                'logo' => $c->logo ? file_url($c->logo) : null,
            ] : null;
        }

        return null;
    }
}
