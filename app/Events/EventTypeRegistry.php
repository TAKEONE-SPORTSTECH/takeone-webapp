<?php

namespace App\Events;

use App\Events\Contracts\EventType;
use App\Models\ClubEvent;

/**
 * Resolves the package that owns an event, from config/event_types.php.
 *
 * Generalises the combat-sport registry (App\Sports\Combat\SportRegistry) from
 * "which sport" to "which event type", per CLAUDE.md → "Events Are
 * Self-Contained Packages". Packages are asked in declaration order and the
 * first to claim the event wins, so specific packages must be listed above the
 * fallback.
 */
class EventTypeRegistry
{
    /** @var array<string, EventType>|null */
    private ?array $resolved = null;

    /** @return array<string, EventType> keyed by package key */
    public function all(): array
    {
        return $this->resolved ??= collect(config('event_types.types', []))
            ->map(fn ($class) => app($class))
            ->mapWithKeys(fn (EventType $t) => [$t->key() => $t])
            ->all();
    }

    /** A package by its key, or null when unknown. */
    public function get(?string $key): ?EventType
    {
        return $key ? ($this->all()[$key] ?? null) : null;
    }

    /**
     * The package that owns this event. Never returns null — an event whose
     * type has no dedicated package falls back to the generic package so the
     * app keeps working while types are ported one at a time.
     */
    public function for(ClubEvent $event): EventType
    {
        foreach ($this->all() as $type) {
            if ($type->owns($event)) {
                return $type;
            }
        }

        return $this->fallback();
    }

    /** The catch-all package used when nothing claims an event. */
    public function fallback(): EventType
    {
        $class = config('event_types.fallback');

        return app($class);
    }

    /** Packages a member may pick from when creating an event, for the type picker. */
    public function selectable(): array
    {
        return collect($this->all())
            ->map(fn (EventType $t) => ['key' => $t->key(), 'label' => $t->label()])
            ->values()->all();
    }
}
