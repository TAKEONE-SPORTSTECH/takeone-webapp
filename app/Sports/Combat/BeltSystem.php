<?php

namespace App\Sports\Combat;

/**
 * One rule book's belts and grades — a sport's own ladder, or an organiser's
 * (2026-09-28). The ladder is exactly BeltLadder's shape, so every belt chip,
 * icon and picker draws it unchanged.
 */
final class BeltSystem
{
    /** @param  array<int, array>  $ladder  BeltLadder::rank() rows */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $ladder,
        public readonly bool $custom = false,
    ) {}

    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'custom' => $this->custom, 'ladder' => $this->ladder];
    }
}
