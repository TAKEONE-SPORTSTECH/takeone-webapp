<?php

namespace App\Support;

/**
 * The drawn stand-in for a person with no picture of their own.
 *
 * One rule, in one place, because three surfaces need the identical answer: the
 * scoring console's corners, its Bouts list, and anything else that draws a
 * competitor. It is the same rule `<x-gender-avatar>` has always used on the
 * member lists — anything that is not female gets the male artwork — so a
 * person without a photograph looks the same everywhere in the product.
 *
 * Note what this is NOT: it is not a claim about somebody's gender. A great
 * many competitors are entered by staff who are asked for a name and nothing
 * else (see "Who Fills The Form Decides What It Demands"), so an unknown
 * gender is the normal case rather than missing data. This is the picture drawn
 * in the absence of one, and the surfaces that use it dim it so it never reads
 * as a photograph somebody actually took.
 */
class Avatar
{
    /** A stand-in portrait for this gender. Never null — there is always a picture. */
    public static function placeholder(?string $gender): string
    {
        $female = in_array(strtolower((string) $gender), ['f', 'female', 'woman', 'girl'], true);
        $name = $female ? 'female' : 'male';

        // The supplied artwork when it is there, otherwise the plain silhouette
        // that shipped with the app. Checked rather than assumed, so a screen
        // never draws a broken image while the artwork is being replaced.
        // file_exists is served from PHP's stat cache, so this costs nothing.
        $file = "images/avatars/{$name}.jpg";

        if (! file_exists(public_path($file))) {
            $file = "images/avatars/{$name}.svg";
        }

        // Stamped with the file's own mtime, the way profile pictures are:
        // these sit behind a cache, and replacing the artwork without a new URL
        // leaves every screen on the old bytes until the edge expires.
        return asset($file).'?v='.(@filemtime(public_path($file)) ?: 0);
    }
}
