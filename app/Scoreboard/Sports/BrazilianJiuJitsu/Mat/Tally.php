<?php

namespace App\Scoreboard\Sports\BrazilianJiuJitsu\Mat;

/**
 * A jiu-jitsu score, as it stands after replaying the ledger.
 *
 * Three counters per corner, and they are still read as three. Points decide
 * the match; advantages break a tie on points; penalties break a tie on
 * advantages (fewer wins).
 *
 * ⚠️ Changed 2026-09-12, at the organiser's instruction. An advantage now also
 * adds ONE POINT to the man who earned it, and a penalty adds one point to his
 * OPPONENT — so the points line is no longer scored actions alone. The ladders
 * are still kept and still shown separately, because they remain the tiebreak
 * and because a penalty count is what disqualifies; what changed is that each
 * of them also moves the score.
 *
 * This is NOT the IBJJF convention and the code used to say so in several
 * places. It is a rule-book decision, so it lives in this sport's package
 * (CLAUDE.md → Shared Stays Shared) and is applied in exactly one line each,
 * inside Ledger::tally(). Nothing else adds anything up.
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
