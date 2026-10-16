<?php

namespace App\Scoreboard\Sports\Karate\Styles;

use App\Events\Models\ClubEvent;
use App\Events\Support\CategorySystems;
use App\Scoreboard\Sports\Karate\Mat\Scoring;

/**
 * Which Karate STYLE's scoreboard a mat runs — Kyokushin, Shotokan,
 * Goju-ryu, Shito-ryu or Wado-ryu — and so which control screen, wall board
 * and scoring engine it gets.
 *
 * Each style owns its board design, its wording and its scoring rules
 * (CLAUDE.md → Shared Stays Shared: those are PRIVATE to a style). They all
 * began on 2026-10-07 as exact copies of the board every Karate event ran,
 * to be developed from there. The plumbing is NOT copied and never should
 * be: mat state, pairing, the live channel and saving a result stay in the
 * shared Karate base, so a fix there still lands once for every style.
 *
 * The style comes from the event's rule book (CategorySystems::styleKey —
 * its chosen official category or belt system). An event with no style, an
 * Open Mat, or the switch being off all get exactly the board as it was.
 *
 * Switch: `features.karate_style_boards` (KARATE_STYLE_BOARDS), default OFF.
 */
final class KarateStyles
{
    /** Style key => its folder of views and its scoring engine. */
    public const STYLES = [
        'kyokushin' => Kyokushin\Scoring::class,
        'shotokan' => Shotokan\Scoring::class,
        'goju_ryu' => GojuRyu\Scoring::class,
        'shito_ryu' => ShitoRyu\Scoring::class,
        'wado_ryu' => WadoRyu\Scoring::class,
        // Combat — its own copy of Shotokan (owner, 2026-10-16), changed from there.
        'combat' => Combat\Scoring::class,
    ];

    /** The style this event's mats run, or null for the shared board. */
    public static function of(?ClubEvent $event): ?string
    {
        if (! $event || ! config('features.karate_style_boards') || $event->sport !== 'karate') {
            return null;
        }

        $key = rescue(fn () => app(CategorySystems::class)->styleKey($event), null, false);

        return isset(self::STYLES[$key]) ? $key : null;
    }

    /**
     * The view for one of a mat's screens — `control`, `mat` — the style's own
     * copy when it has one, else the shared board's.
     */
    public static function view(?ClubEvent $event, string $screen): string
    {
        $style = self::of($event);
        $own = $style ? "scoreboard::karate.styles.{$style}.{$screen}" : null;

        return $own && view()->exists($own) ? $own : "scoreboard::karate.scoreboard.{$screen}";
    }

    /** The scoring engine for this event's mats. */
    public static function scoring(?ClubEvent $event): Scoring
    {
        $style = self::of($event);

        return app($style ? self::STYLES[$style] : Scoring::class);
    }
}
