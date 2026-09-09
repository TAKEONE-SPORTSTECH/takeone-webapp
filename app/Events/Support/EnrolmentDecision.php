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
        // A refusal the DESK can settle: the entry is fine, something simply
        // is not known yet and weigh-in will supply it. See deny().
        public readonly bool $deferrable = false,
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
     * @param  bool  $deferrable  true when weigh-in can settle it — see below
     */
    public static function deny(string $code, string $message, bool $offerSpectator = false, bool $deferrable = false): self
    {
        return new self(allowed: false, code: $code, message: $message, offerSpectator: $offerSpectator, deferrable: $deferrable);
    }

    /**
     * A refusal that only stands until the desk resolves it.
     *
     * Some refusals are about the PERSON — barred, wrong age group, no class
     * being run — and no amount of standing on a scale changes them. One is
     * about a missing FACT: nobody has recorded a weight yet. That is not a
     * reason to keep an athlete out of a competition where everyone is weighed
     * on the day; it is the thing weigh-in exists to answer.
     *
     * A package marks such a refusal deferrable, and the entry service admits it
     * for a CLUB entry — a coach committing their own squad — while self-entry
     * still asks the member to fill their profile in first.
     */
    public static function defer(string $code, string $message): self
    {
        return self::deny($code, $message, deferrable: true);
    }

    /**
     * The same verdict, routed into a division somebody chose by hand.
     *
     * An organiser placing a competitor into a named division — Gi rather than
     * No-Gi, this weight group rather than the one their profile implies — is
     * exercising the same authority that arranges a draw. So the division is
     * replaced and NOTHING else is: `allowed`, the code, the message, the
     * recorded weight and `deferrable` all survive, because the gate's answer
     * about the PERSON is not the organiser's to overrule.
     *
     * Returns a new decision rather than mutating this one — every property
     * here is readonly, and a gate's verdict is a fact about one question.
     */
    public function intoCategory(EventCategory $category): self
    {
        return new self(
            allowed: $this->allowed,
            code: $this->code,
            message: $this->message,
            category: $category,
            weight: $this->weight,
            offerSpectator: $this->offerSpectator,
            deferrable: $this->deferrable,
        );
    }
}
