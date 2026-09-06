<?php

namespace App\Scoreboard\Sports\BrazilianJiuJitsu\Mat;

/**
 * A jiu-jitsu score, as it stands after replaying the ledger.
 *
 * Three counters per corner, and they are NOT one number. Points decide the
 * match; advantages break a tie on points; penalties break a tie on advantages.
 * An advantage is never added to a points total, and neither is a penalty —
 * which is the single rule the whole design of both screens is built around
 * (points ≫ advantages/penalties, and ADV must never look added to points).
 *
 * Immutable on purpose: it is derived, so there is nothing here to set.
 */
final class Tally
{
    public function __construct(
        public readonly int $bluePoints = 0,
        public readonly int $whitePoints = 0,
        public readonly int $blueAdvantages = 0,
        public readonly int $whiteAdvantages = 0,
        public readonly int $bluePenalties = 0,
        public readonly int $whitePenalties = 0,
    ) {}

    /**
     * Who is ahead on the IBJJF tiebreak ladder: points, then advantages, then
     * penalties (FEWER penalties wins). Null when the two are level on all
     * three — which is not a result, it is the moment a referee decision is
     * required, and the console says so rather than inventing a winner.
     *
     * @return 'blue'|'white'|null
     */
    public function leader(): ?string
    {
        foreach ([
            [$this->bluePoints, $this->whitePoints],
            [$this->blueAdvantages, $this->whiteAdvantages],
            // Reversed: the corner with FEWER penalties is ahead.
            [$this->whitePenalties, $this->bluePenalties],
        ] as [$blue, $white]) {
            if ($blue !== $white) {
                return $blue > $white ? 'blue' : 'white';
            }
        }

        return null;
    }

    /** Which of the three counters actually decided it, for the winner screen. */
    public function decidedBy(): ?string
    {
        return match (true) {
            $this->bluePoints !== $this->whitePoints => 'points',
            $this->blueAdvantages !== $this->whiteAdvantages => 'advantages',
            $this->bluePenalties !== $this->whitePenalties => 'penalties',
            default => null,
        };
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'bluePoints' => $this->bluePoints,
            'whitePoints' => $this->whitePoints,
            'blueAdvantages' => $this->blueAdvantages,
            'whiteAdvantages' => $this->whiteAdvantages,
            'bluePenalties' => $this->bluePenalties,
            'whitePenalties' => $this->whitePenalties,
            'leader' => $this->leader(),
            'decidedBy' => $this->decidedBy(),
        ];
    }
}
