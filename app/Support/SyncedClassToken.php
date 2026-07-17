<?php

namespace App\Support;

/**
 * URL-safe token identifying a club class weekly slot: paId|day|startTime.
 * Shared so any feature linking into `me.schedule.synced` (member profile
 * attendance list, PersonalMobileController's own schedule cards, …) encodes/
 * decodes it identically.
 */
class SyncedClassToken
{
    public static function encode(int $paId, string $day, string $start): string
    {
        return rtrim(strtr(base64_encode("$paId|$day|$start"), '+/', '-_'), '=');
    }

    /** @return array{0:?int,1:?string,2:string} [package_activity_id, day, start_time] */
    public static function decode(string $token): array
    {
        $parts = explode('|', (string) base64_decode(strtr($token, '-_', '+/')));
        if (count($parts) < 2) {
            return [null, null, null];
        }

        return [(int) $parts[0], $parts[1], $parts[2] ?? ''];
    }
}
