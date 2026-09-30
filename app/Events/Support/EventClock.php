<?php

namespace App\Events\Support;

use Carbon\Carbon;

/** How a competition's timezone is written for a reader: "Asia/Bahrain (GMT+3)". */
class EventClock
{
    public static function label(?string $zone): string
    {
        if (! $zone || ! in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            return (string) $zone;
        }

        $offset = Carbon::now($zone)->format('P');                 // +03:00
        $short = preg_replace('/:00$/', '', ltrim($offset, '+') === $offset ? $offset : '+'.ltrim($offset, '+'));

        return str_replace('_', ' ', $zone).' (GMT'.($short === '+00' ? '' : preg_replace('/^([+-])0/', '$1', $short)).')';
    }
}
