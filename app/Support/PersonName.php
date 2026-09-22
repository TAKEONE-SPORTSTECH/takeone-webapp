<?php

namespace App\Support;

/**
 * A person's name, shortened to the two parts a reader actually needs.
 *
 * Competitors write their own names into the platform, and at a championship a
 * good number of them write all of it: "Mohammed Abdulla Mansoor Almaqabi" is
 * four parts and 33 characters, and it shares a hall board row with a photo, a
 * flag, a country, a crest, a club and a centre plate. Something has to give.
 *
 * Ellipsis is not the answer here. CLAUDE.md's "Names are never truncated" is
 * about never CUTTING a name to fit a box — "Mohammed Abdulla Man…" tells a
 * reader nothing and looks broken from the back of a hall. This does the
 * opposite: it drops the middle parts and keeps two WHOLE names, which is how
 * a competitor is announced, printed on a bracket and called to the mat.
 *
 * ⚠️ Shortening is a DISPLAY decision and belongs only where a wall or a card
 * is too narrow for the whole thing. Nothing here is stored, nothing is
 * searched against it, and the full name stays on the record — a certificate,
 * a medal list and a member's own profile all print it whole.
 */
class PersonName
{
    /**
     * The particles that belong to the name AFTER them, not on their own.
     *
     * "Ali Mohammed Al Mughniy." shortens to "Ali Al Mughniy.", not to "Ali
     * Mughniy." — the article is part of the family name, and dropping it
     * renames the family. Latin particles are here for the same reason: van
     * der Berg, de Souza, Mac Donald.
     *
     * @var array<int, string>
     */
    private const PARTICLES = [
        // Arabic, as people transliterate it
        'al', 'el', 'as', 'ash', 'abd', 'abdul', 'abu', 'abo', 'bin', 'ben',
        'ibn', 'bint', 'umm', 'ould',
        // Arabic, as people write it
        'ال', 'آل', 'بن', 'ابن', 'بنت', 'أبو', 'ابو', 'عبد', 'أم',
        // Latin
        'van', 'von', 'der', 'den', 'de', 'del', 'della', 'di', 'da', 'das',
        'dos', 'du', 'la', 'le', 'lo', 'mac', 'mc', 'saint', 'st', 'ter', 'ten',
    ];

    /**
     * First name and family name, with everything between them dropped.
     *
     * A name already two parts or fewer comes back untouched — this never
     * invents a split and never reorders anything. Whatever it is handed that
     * is not a name (an empty slot, a placeholder a package wrote) survives it
     * unchanged, because a one- or two-word string is returned as given.
     */
    public static function short(?string $name): string
    {
        // Every kind of space people paste in, including the non-breaking one
        // Word leaves behind and the Arabic comma-space, collapsed to one.
        $clean = trim(preg_replace('/[\s\x{00A0}\x{200B}\x{202F}]+/u', ' ', (string) $name) ?? '');

        if ($clean === '') {
            return '';
        }

        $parts = explode(' ', $clean);

        if (count($parts) <= 2) {
            return $clean;
        }

        $first = array_shift($parts);
        $last = array_pop($parts);

        // Walk back through any particles immediately before the family name
        // and bring them with it. Bounded by the parts that are left, so a
        // name made entirely of particles cannot loop or swallow the first.
        while ($parts) {
            $fold = self::fold(end($parts));

            // A part that is nothing but punctuation — "Hussein al - Sahlawi"
            // is really written that way — carries on rather than stopping the
            // walk, or the particle behind it would be dropped and the family
            // renamed.
            if ($fold !== '' && ! in_array($fold, self::PARTICLES, true)) {
                break;
            }

            $last = array_pop($parts).' '.$last;
        }

        return $first.' '.$last;
    }

    /** A part reduced to what a particle comparison should see. */
    private static function fold(string $part): string
    {
        // Trailing punctuation is common in this data ("Ramadan..", "Hamad.")
        // and must not stop a particle matching.
        return mb_strtolower(trim($part, ".,'`-·"), 'UTF-8');
    }
}
