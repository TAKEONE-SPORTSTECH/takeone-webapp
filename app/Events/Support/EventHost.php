<?php

namespace App\Events\Support;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Members\Models\User;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\Surfaces\HostsEvents;

/**
 * WHO an event belongs to, whatever kind of organiser that is (2026-09-28).
 *
 * A club's event is hosted by its club — every event there was before this
 * date, and every one a club makes now. A federation's competition is hosted
 * by the federation, which is not a club and must never be drawn as one. The
 * event code used to read `$event->tenant` for the host's name, mark, country,
 * currency, time zone and map pin; it reads THIS instead, which answers from
 * the club when there is one and from the hosting module when there is not.
 *
 * For a club's event every field is the club's own, unchanged — so an event
 * that has a club reads exactly what it always read.
 */
final class EventHost
{
    /**
     * Remembered PER EVENT OBJECT, not per id: a page asks one event for its
     * host many times, but an id outlives the object that held it — a queue
     * worker or a test run sees many different events under the same id, and
     * a static cache keyed by id would hand one the other's host. A WeakMap
     * forgets the answer the moment the event object is gone.
     */
    private static ?\WeakMap $memo = null;

    public function __construct(
        public readonly string $kind,          // 'club' | a module's host kind ('federation') | 'none'
        public readonly ?int $id,
        public readonly string $name,
        public readonly ?string $logo,         // a URL, ready to print
        public readonly ?string $country,
        public readonly ?string $currency,
        public readonly ?string $timezone,
        public readonly ?float $lat,
        public readonly ?float $lng,
        public readonly ?string $color = null,
        public readonly ?Tenant $club = null,  // the club itself, only when the host IS one
        public readonly ?string $uuid = null,
    ) {}

    public static function of(ClubEvent $event): self
    {
        self::$memo ??= new \WeakMap;
        $sig = ($event->tenant_id ?? '-').':'.($event->host_type ?? '-').':'.($event->host_id ?? '-');
        $hit = self::$memo[$event] ?? null;
        if ($hit !== null && $hit[0] === $sig) {
            return $hit[1];
        }

        $host = $event->tenant_id ? self::club($event) : self::hosted($event);
        self::$memo[$event] = [$sig, $host];

        return $host;
    }

    public function isClub(): bool
    {
        return $this->kind === 'club';
    }

    /** May this user run the event as its host's own people? A club's are decided by EventAccess, as always. */
    public function manages(ClubEvent $event, User $user): bool
    {
        if ($this->isClub() || ! $event->host_type || ! $event->host_id) {
            return false;
        }
        $module = self::moduleFor($event->host_type);

        return $module ? (bool) rescue(fn () => $module->managesEventsOf($event->host_type, (int) $event->host_id, $user), false, report: true) : false;
    }

    /** Tell a non-club host its event was edited here (HostsEvents::hostedEventChanged). */
    public static function changed(ClubEvent $event): void
    {
        if ($event->tenant_id || ! $event->host_type) {
            return;
        }
        $module = self::moduleFor((string) $event->host_type);
        if ($module) {
            rescue(fn () => $module->hostedEventChanged($event), null, report: true);
        }
    }

    /** Tell a non-club host an entry request on its event was decided (HostsEvents::hostedEntryDecided). */
    public static function entryDecided(ClubEvent $event, \App\Events\Models\EventPublicEntry $entry): void
    {
        if ($event->tenant_id || ! $event->host_type) {
            return;
        }
        $module = self::moduleFor((string) $event->host_type);
        if ($module) {
            rescue(fn () => $module->hostedEntryDecided($event, $entry), null, report: true);
        }
    }

    /** Tell a host that follows its entrants the event's list changed (FollowsHostedEntrants). */
    public static function entrantChanged(\App\Events\Models\ClubEventRegistration $registration, string $change): void
    {
        $event = $registration->event_id ? ClubEvent::find($registration->event_id) : null;
        if (! $event || $event->tenant_id || ! $event->host_type) {
            return;
        }
        $module = self::moduleFor((string) $event->host_type);
        if ($module instanceof \App\Support\Modules\Surfaces\FollowsHostedEntrants) {
            rescue(fn () => $module->hostedEntrantChanged($event, $registration, $change), null, report: true);
        }
    }

    /** Tell a host that follows its event something it shows changed — bracket, results, officials (FollowsHostedEntrants). */
    public static function contentChanged(ClubEvent $event): void
    {
        if ($event->tenant_id || ! $event->host_type) {
            return;
        }
        $module = self::moduleFor((string) $event->host_type);
        if ($module instanceof \App\Support\Modules\Surfaces\FollowsHostedEntrants) {
            rescue(fn () => $module->hostedContentChanged($event), null, report: true);
        }
    }

    /**
     * Where this user's club enters them, when the host takes its entries from
     * clubs rather than from members themselves (HostsEvents::hostedEntryDoor).
     * Null for a club's event — members enter themselves there, as always.
     *
     * @return array{url: string, enters: bool}|null
     */
    public function entryDoor(ClubEvent $event, User $user): ?array
    {
        if ($this->isClub() || ! $event->host_type || ! $event->host_id) {
            return null;
        }
        $module = self::moduleFor($event->host_type);
        $door = $module ? rescue(fn () => $module->hostedEntryDoor($event, $user), null, report: true) : null;

        return is_array($door) && is_string($door['url'] ?? null) ? ['url' => $door['url'], 'enters' => (bool) ($door['enters'] ?? false)] : null;
    }

    /** @return array<int, int> the host's own people who run it (not a club's — EventAccess/AudienceResolver own those). */
    public function managerIds(ClubEvent $event): array
    {
        if ($this->isClub() || ! $event->host_type || ! $event->host_id) {
            return [];
        }
        $module = self::moduleFor($event->host_type);

        return $module ? array_map('intval', (array) rescue(fn () => $module->eventHostManagers($event->host_type, (int) $event->host_id), [], report: true)) : [];
    }

    private static function club(ClubEvent $event): self
    {
        $t = $event->tenant;

        return new self(
            kind: 'club',
            id: $t?->id ?? (int) $event->tenant_id,
            name: (string) ($t?->club_name ?? ''),
            logo: $t?->logo ? file_url($t->logo) : null,
            country: $t?->country,
            currency: $t?->currency,
            timezone: $t?->timezone,
            lat: $t?->gps_lat ? (float) $t->gps_lat : null,
            lng: $t?->gps_long ? (float) $t->gps_long : null,
            club: $t,
            uuid: $t?->slug,
        );
    }

    private static function hosted(ClubEvent $event): self
    {
        $module = $event->host_type ? self::moduleFor($event->host_type) : null;
        $h = $module && $event->host_id ? rescue(fn () => $module->eventHost($event->host_type, (int) $event->host_id), null, report: true) : null;

        if (! is_array($h)) {
            return new self('none', null, '', null, null, null, null, null, null);
        }

        return new self(
            kind: (string) $event->host_type,
            id: (int) $event->host_id,
            name: (string) ($h['name'] ?? ''),
            logo: $h['logo'] ?? null,
            country: $h['country'] ?? null,
            currency: $h['currency'] ?? null,
            timezone: $h['timezone'] ?? null,
            lat: isset($h['lat']) ? (float) $h['lat'] : null,
            lng: isset($h['lng']) ? (float) $h['lng'] : null,
            color: $h['color'] ?? null,
            uuid: $h['uuid'] ?? null,
        );
    }

    private static function moduleFor(string $type): ?HostsEvents
    {
        return app(ModuleRegistry::class)->contributingTo(HostsEvents::class)
            ->first(fn (HostsEvents $m) => in_array($type, $m->eventHostTypes(), true));
    }

    /** Forget what this request learned (tests, and a host renamed mid-request). */
    public static function flush(): void
    {
        self::$memo = null;
    }
}
