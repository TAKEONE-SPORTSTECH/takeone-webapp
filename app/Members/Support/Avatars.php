<?php

namespace App\Members\Support;

/**
 * The drawn portrait a person falls back to when there is no photograph.
 *
 * One place, because the rule is one rule: a face container is 3:4 and a
 * missing face is the DRAWN AVATAR, never an icon and never initials
 * (CLAUDE.md → Profile Pictures Are Portrait 3:4). Resolved server-side so a
 * client-rendered row can simply point at one — an Alpine `x-for` cannot call
 * a Blade component per item.
 *
 * `.jpg` where one has been provided, `.svg` otherwise, with the file's own
 * mtime as the cache-buster so replacing the artwork does not leave every
 * browser holding the old one.
 */
class Avatars
{
    /** @return array<string, string> gender ('Male'|'Female') => url */
    public static function fallbacks(): array
    {
        $out = [];

        foreach (['Male', 'Female'] as $gender) {
            $name = $gender === 'Female' ? 'female' : 'male';
            $file = file_exists(public_path("images/avatars/{$name}.jpg"))
                ? "images/avatars/{$name}.jpg"
                : "images/avatars/{$name}.svg";

            $out[$gender] = asset($file).'?v='.@filemtime(public_path($file));
        }

        return $out;
    }
}
