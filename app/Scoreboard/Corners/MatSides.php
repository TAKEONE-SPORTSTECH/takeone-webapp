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

    /** True when this mat's red corner is drawn on the right. */
    public static function redRight(ClubEvent $event, string $court, string $sport): bool
    {
        $sides = ((array) $event->scoreboard_settings)[self::KEY] ?? [];
        $side = is_array($sides) ? ($sides[$court] ?? null) : null;

        return in_array($side, ['left', 'right'], true)
            ? $side === 'right'
            : CornerSide::redRight($sport);
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

        $event->forceFill(['scoreboard_settings' => $settings])->saveQuietly();
    }

    /** The two keys a sport's persistSettings() must carry through untouched. */
    public static function carriedKeys(): array
    {
        return [self::KEY, CornerLook::KEY];
    }
}
