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

        return self::place($zone).' (GMT'.($short === '+00' ? '' : preg_replace('/^([+-])0/', '$1', $short)).')';
    }

    /**
     * The zone's name in the reader's language — "巴林时间", "heure : Bahreïn".
     * The identifier itself ("Asia/Bahrain") is English, and it is all a
     * non-English page used to show. English keeps the identifier, exactly as
     * before; so does any server without the intl extension.
     */
    private static function place(string $zone): string
    {
        $locale = app()->getLocale();

        if (! str_starts_with($locale, 'en') && class_exists(\IntlTimeZone::class)) {
            $name = \IntlTimeZone::createTimeZone($zone)
                ?->getDisplayName(false, \IntlTimeZone::DISPLAY_GENERIC_LOCATION, $locale);

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return str_replace('_', ' ', $zone);
    }
}
