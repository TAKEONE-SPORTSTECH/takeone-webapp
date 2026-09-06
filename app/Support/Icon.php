<?php

namespace App\Support;

/**
 * Bootstrap-icon class strings, for the places where the icon NAME arrives as
 * data (an action, a milestone, a console tile) rather than being written into
 * the markup by hand.
 *
 * Its one job today is the bracket rule: `bi-diagram-3` is drawn as a top-down
 * org chart, but a knockout bracket runs left to right, so wherever the glyph
 * stands for a DRAW or a BRACKET it is turned a quarter turn clockwise. The
 * rotation itself lives in `.bracket-icon` (resources/css/app.css) — this only
 * decides when to ask for it.
 *
 * The name is whitelisted rather than trusted: it can originate from an event
 * package's action list, and it lands in a class attribute.
 */
final class Icon
{
    /**
     * The full class attribute for one Bootstrap icon.
     *
     * @param  string|null  $name   e.g. `bi-diagram-3-fill`
     * @param  string  $extra       further classes (size, colour) appended as given
     */
    public static function bi(?string $name, string $extra = '', string $fallback = 'bi-circle'): string
    {
        $icon = preg_match('/^bi-[a-z0-9-]+$/i', (string) $name) === 1 ? (string) $name : $fallback;

        $classes = ['bi', $icon];

        if (self::isBracket($icon)) {
            $classes[] = 'bracket-icon';
        }

        if ($extra !== '') {
            $classes[] = $extra;
        }

        return implode(' ', $classes);
    }

    /** Does this icon stand for a draw / bracket? */
    public static function isBracket(?string $name): bool
    {
        return str_starts_with((string) $name, 'bi-diagram-3');
    }
}
