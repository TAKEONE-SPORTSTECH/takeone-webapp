<?php

namespace App\Support;

use App\Models\EventMatch;

/**
 * What stage of the competition a bout belongs to.
 *
 * Two columns hold pieces of this and neither is authoritative on its own:
 *
 *   `round` is the stage within the draw — "Final", "Semi-final", "Round Robin"
 *           — and in some draws just a number.
 *   `phase` is the FORMAT or the scheduling block — "Knockout", "group".
 *
 * A reader looking for the finals wants "Final", not "Knockout", so a named
 * round wins. A numeric round is a position, not a name, so the phase speaks
 * for it; and with neither, the number is rendered as "Round N".
 *
 * Centralised because three screens were each deciding this for themselves and
 * had already begun to disagree: the gallery tile said "Knockout" while the tab
 * it lived under said "Final".
 */
class BoutStage
{
    /** The stage as a person says it, or null when the bout has no stage. */
    public static function label(EventMatch $match): ?string
    {
        $round = trim((string) $match->round);
        $phase = trim((string) $match->phase);

        if ($round !== '' && ! is_numeric($round)) {
            return self::humanise($round);
        }

        if ($phase !== '') {
            return self::humanise($phase);
        }

        return $round !== '' ? __('events.bout_video_round', ['n' => (int) $round]) : null;
    }

    /** A stable machine key for the same stage: 'Semi-final' → 'semi-final'. */
    public static function key(EventMatch $match): ?string
    {
        $label = self::label($match);

        if ($label === null) {
            return null;
        }

        $key = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $label), '-'));

        return $key !== '' ? $key : null;
    }

    /** 'open_mat' → 'Open mat'. Leaves an already-worded stage alone. */
    private static function humanise(string $value): string
    {
        return str_contains($value, '_')
            ? ucfirst(str_replace('_', ' ', $value))
            : $value;
    }
}
