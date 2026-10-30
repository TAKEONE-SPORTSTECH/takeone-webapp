<?php

namespace App\Rankings\Schemes;

/**
 * The WKF World Ranking points table (WKF World Ranking Rules, 2026 edition),
 * applied to a national ranking.
 *
 *   points = (participation + bouts won + place [+ medal round, kata]) × factor
 *
 * Mapped onto how the platform runs a draw:
 *   • place 1/2/3 come from the stored podium;
 *   • place 4 is the loser of a bronze bout, which is what WKF calls 5th;
 *   • a quarter-finalist who is not on the podium is WKF's "quarterfinalist
 *     not in repechage" (20), a loser one round earlier its "9th–15th" (10).
 *     There is no repechage here, so WKF's 7th place cannot occur.
 */
final class Wkf
{
    public const KEY = 'wkf';

    public const PARTICIPATION = 5;

    public const PER_WIN = 10;

    public const MEDAL_ROUND = 10;   // kata only

    /** WKF decay: 100% in the first year, 50% in the second, then gone. */
    public const DECAY = [1 => 1.0, 2 => 0.5];

    public static function placePoints(int $place): int
    {
        return match ($place) {
            1 => 100,
            2 => 70,
            3 => 40,
            4 => 30,
            default => 0,
        };
    }

    /**
     * Points for going out of a knockout before the podium, by how many rounds
     * before the final the athlete lost (2 = quarter-final, 3 = round before).
     */
    public static function eliminatedPoints(int $roundsBeforeFinal): int
    {
        return match ($roundsBeforeFinal) {
            2 => 20,
            3 => 10,
            default => 0,
        };
    }
}
