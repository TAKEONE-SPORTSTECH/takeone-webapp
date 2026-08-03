<?php

namespace App\Events\Support;

use App\Models\EventCategory;

/**
 * The answer an event-type package gives when asked "may this member enter?".
 *
 * Packages never write registrations themselves and never emit HTTP responses —
 * they return one of these and the calling controller does the rest. That keeps
 * the gate logic testable in isolation and identical for every caller (member
 * self-service, club admin, MCP, walk-in).
 */
final class EnrolmentDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $code = null,
        public readonly ?string $message = null,
        public readonly ?EventCategory $category = null,
        public readonly ?float $weight = null,
        public readonly bool $offerSpectator = false,
    ) {}

    /** Member may enter — optionally routed into a division at a recorded weight. */
    public static function allow(?EventCategory $category = null, ?float $weight = null): self
    {
        return new self(allowed: true, category: $category, weight: $weight);
    }

    /**
     * Member may not enter.
     *
     * @param  string  $code  stable machine code the UI can branch on (no_weight, no_division, …)
     * @param  bool  $offerSpectator  true when a spectator ticket is the sensible fallback
     */
    public static function deny(string $code, string $message, bool $offerSpectator = false): self
    {
        return new self(allowed: false, code: $code, message: $message, offerSpectator: $offerSpectator);
    }
}
