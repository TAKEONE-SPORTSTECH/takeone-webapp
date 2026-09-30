<?php

namespace App\Sports\Combat;

use App\Platform\Models\PlatformSetting;

/**
 * Which side of the screens each corner stands on, per sport.
 *
 * The one place a screen asks. A sport declares its convention
 * (AbstractCombatSport::redCornerSide()); a super-admin can override it per
 * sport from Platform Settings, in case the convention was got wrong — the
 * override wins, and clearing it returns the sport to its own default.
 * Anything unknown, or a sport that never said, is the red corner on the
 * left — exactly what every screen drew before this existed.
 */
final class CornerSide
{
    private const KEY = 'hall_screens.red_side.';

    /** 'left' or 'right' — where the red corner stands for this sport. */
    public static function red(?string $sport): string
    {
        $override = $sport ? self::override($sport) : null;

        return $override ?? self::sportDefault($sport);
    }

    /** True when the red corner is drawn on the right. */
    public static function redRight(?string $sport): bool
    {
        return self::red($sport) === 'right';
    }

    /** What the sport itself declares, ignoring any override. */
    public static function sportDefault(?string $sport): string
    {
        $impl = app(SportRegistry::class)->get($sport);
        $side = $impl && method_exists($impl, 'redCornerSide') ? $impl->redCornerSide() : 'left';

        return $side === 'right' ? 'right' : 'left';
    }

    /** The super-admin's override for this sport, or null when there is none. */
    public static function override(string $sport): ?string
    {
        $v = PlatformSetting::get(self::KEY.$sport);

        return in_array($v, ['left', 'right'], true) ? $v : null;
    }

    /**
     * Set or clear the override. `null` (or the sport's own default) clears it,
     * so a sport whose convention later changes is not pinned to a stale choice.
     */
    public static function setOverride(string $sport, ?string $side): void
    {
        abort_unless(app(SportRegistry::class)->has($sport), 422);
        $side = in_array($side, ['left', 'right'], true) ? $side : null;
        PlatformSetting::set(self::KEY.$sport, $side === null || $side === self::sportDefault($sport) ? '' : $side);
    }

    /**
     * Every combat sport with its default, override and the side in effect —
     * what the settings page and the MCP tool show.
     *
     * @return array<int, array{sport: string, label: string, default: string, override: ?string, side: string}>
     */
    public static function all(): array
    {
        return collect(app(SportRegistry::class)->all())
            ->map(fn ($impl, $key) => [
                'sport' => $key,
                'label' => $impl->label(),
                'default' => self::sportDefault($key),
                'override' => self::override($key),
                'side' => self::red($key),
            ])->values()->all();
    }
}
