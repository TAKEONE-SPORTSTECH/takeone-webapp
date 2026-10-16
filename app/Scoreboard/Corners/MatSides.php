<?php

namespace App\Scoreboard\Corners;

use App\Events\Models\ClubEvent;
use App\Sports\Combat\CornerSide;

/**
 * Which side of ONE mat the red corner physically stands on — for every
 * combat scoreboard. The sport has a convention (App\Sports\Combat\
 * CornerSide), but a hall is set up however the hall is set up, and the
 * screens and consoles must show each corner where it actually stands.
 *
 * Only the GEOMETRY moves; who is in which corner is untouched. Stored on the
 * event, per mat (`club_events.scoreboard_settings.redSide`) — each sport's
 * MatState must carry the key through its own persistSettings(). A mat with
 * nothing stored follows the sport's convention.
 */
final class MatSides
{
    public const KEY = 'redSide';

    /**
     * One mat's SCREENS may face differently (owner, 2026-10-16: "sometimes I
     * want the score control swapped but the scoreboard not, and the upcoming
     * different too"). `redSideBy[court][screen]` overrides `redSide[court]`
     * for that screen only; a screen with no entry follows the mat.
     */
    public const KEY_BY = 'redSideBy';

    /** The screens a mat's side can be set for, one by one. */
    public const SCREENS = ['control', 'board', 'upcoming'];

    /**
     * True when this mat's red corner is drawn on the right — for one SCREEN
     * when `$screen` is given (its own side if it has one), else the mat's.
     */
    public static function redRight(ClubEvent $event, string $court, string $sport, ?string $screen = null): bool
    {
        if ($screen !== null) {
            $by = ((array) $event->scoreboard_settings)[self::KEY_BY] ?? [];
            $own = is_array($by) ? (($by[$court] ?? [])[$screen] ?? null) : null;
            if (in_array($own, ['left', 'right'], true)) {
                return $own === 'right';
            }
        }

        $sides = ((array) $event->scoreboard_settings)[self::KEY] ?? [];
        $side = is_array($sides) ? ($sides[$court] ?? null) : null;

        return in_array($side, ['left', 'right'], true)
            ? $side === 'right'
            : CornerSide::redRight($sport);
    }

    /** @return array<string, bool>  screen => red on the right, for every screen of this mat */
    public static function screens(ClubEvent $event, string $court, string $sport): array
    {
        $out = [];
        foreach (self::SCREENS as $screen) {
            $out[$screen] = self::redRight($event, $court, $sport, $screen);
        }

        return $out;
    }

    /**
     * Swap the corners on SOME screens of a mat — each listed screen flips from
     * where it stands now; the others stay. When every screen ends up on the
     * same side the per-screen entries fold back into the mat's one side, so a
     * mat that is consistent again reads exactly as before this existed.
     *
     * @param  list<string>  $screens
     */
    public static function flip(ClubEvent $event, string $court, array $screens, string $sport): void
    {
        $now = self::screens($event, $court, $sport);
        foreach (array_intersect(self::SCREENS, $screens) as $screen) {
            $now[$screen] = ! $now[$screen];
        }

        $settings = (array) $event->scoreboard_settings;
        $by = (array) ($settings[self::KEY_BY] ?? []);

        if (count(array_unique(array_values($now))) === 1) {
            unset($by[$court]);
            $event->forceFill(['scoreboard_settings' => self::withBy($settings, $by)])->saveQuietly();
            self::set($event->fresh(), $court, reset($now), $sport);

            return;
        }

        $by[$court] = array_map(fn (bool $right) => $right ? 'right' : 'left', $now);
        $event->forceFill(['scoreboard_settings' => self::withBy($settings, $by)])->saveQuietly();
    }

    private static function withBy(array $settings, array $by): array
    {
        if ($by) {
            $settings[self::KEY_BY] = $by;
        } else {
            unset($settings[self::KEY_BY]);
        }

        return $settings;
    }

    /**
     * Set this mat's side. Matching the sport's convention clears the entry,
     * so the mat follows the convention again if it is later changed.
     */
    public static function set(ClubEvent $event, string $court, bool $redRight, string $sport): void
    {
        $settings = (array) $event->scoreboard_settings;
        $sides = (array) ($settings[self::KEY] ?? []);

        if ($redRight === CornerSide::redRight($sport)) {
            unset($sides[$court]);
        } else {
            $sides[$court] = $redRight ? 'right' : 'left';
        }

        if ($sides) {
            $settings[self::KEY] = $sides;
        } else {
            unset($settings[self::KEY]);
        }

        // The whole mat set at once: every screen of it follows, so any one
        // screen's own side (flip) is cleared for this mat.
        $by = (array) ($settings[self::KEY_BY] ?? []);
        if (isset($by[$court])) {
            unset($by[$court]);
            $settings = self::withBy($settings, $by);
        }

        $event->forceFill(['scoreboard_settings' => $settings])->saveQuietly();
    }

    /** The two keys a sport's persistSettings() must carry through untouched. */
    public static function carriedKeys(): array
    {
        return [self::KEY, self::KEY_BY, CornerLook::KEY];
    }
}
