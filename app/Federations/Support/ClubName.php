<?php

namespace App\Federations\Support;

/**
 * A club's name, compared the way names are actually written: case, spacing,
 * and Arabic letter forms (أ/إ/آ = ا, ة = ه, ى = ي, harakat and tatweel
 * dropped) — so "اكاديمية البحرين" and "أكاديمية البحرين" are one club.
 *
 * The ONE rule, used by the bulk import and by "Enroll athlete" alike.
 */
final class ClubName
{
    public static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);
        $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
        $s = preg_replace('/[.\-_]+/u', ' ', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }
}
