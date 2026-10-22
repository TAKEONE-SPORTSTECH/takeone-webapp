<?php

namespace App\Federations\Support;

use Carbon\CarbonInterface;

/**
 * A national ID as a LIST shows it.
 *
 * A child's national ID is not something a roster needs to print in full: a
 * list, a participants table or an athlete card shows the last four digits
 * behind dots, which is enough to tell two people apart and to recognise one.
 * The full number is still kept, still matched by the server (duplicate
 * guard, import, invitation lookup — all by hash), and still opened in full
 * by staff who open that one athlete to edit them; that opening is logged.
 *
 * An age we do not know is treated as a child's: the birthdate is the field
 * people least often have, and a missing one must not be what exposes a minor
 * (CLAUDE.md, "a NULL birthdate reads as an ADULT").
 *
 * Adults are shown as before (decided 2026-09-26: the federation sees its
 * clubs' members in full).
 */
final class NationalIdMask
{
    public const DOT = '•';

    /** Whether this person's ID is masked in lists. */
    public static function applies(?CarbonInterface $birthdate): bool
    {
        return $birthdate === null || $birthdate->age < 18;
    }

    /** The ID as a list may show it — unchanged for an adult, "•••••5042" otherwise. */
    public static function forList(?string $id, ?CarbonInterface $birthdate): string
    {
        $id = trim((string) $id);
        if ($id === '' || $id === '—' || ! self::applies($birthdate)) {
            return $id;
        }

        return self::mask($id);
    }

    public static function mask(string $id): string
    {
        $chars = preg_split('//u', $id, -1, PREG_SPLIT_NO_EMPTY);
        $keep = count($chars) > 6 ? 4 : max(1, count($chars) - 3);

        return str_repeat(self::DOT, 5).implode('', array_slice($chars, -$keep));
    }

    /** A value that came back from a list (a masked ID) — never to be saved over the real one. */
    public static function isMasked(?string $value): bool
    {
        return $value !== null && str_contains($value, self::DOT);
    }
}
