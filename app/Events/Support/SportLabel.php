<?php

namespace App\Events\Support;

/**
 * A sport's name, in the reader's language.
 *
 * `config/event_schema.php` holds thirty sport labels as English literals —
 * "Brazilian Jiu-Jitsu", "Weightlifting", "Table Tennis". They are the right
 * place for the SCHEMA (which sports exist, what a division is called, whether
 * they use belts), and the wrong place for the WORDS, because config is not
 * translatable and a config value cannot be a `__()` call.
 *
 * The result was visible on the poster: "Brazilian Campeonato de Jiu-Jitsu" —
 * a translated event type with an untranslated sport welded to the front.
 *
 * So the label goes through a lang key when one exists (`events.sport_<key>`)
 * and falls back to the schema's English otherwise. Adding a sport still means
 * one line in config; adding its TRANSLATION means one key per language, and a
 * sport nobody has translated reads exactly as it does today.
 */
class SportLabel
{
    /**
     * The display name for a sport key, translated where possible.
     *
     * `$fallback` is what the caller already had — usually the schema's own
     * label — so this can be dropped into an existing expression without
     * changing what an English reader sees.
     */
    public static function for(?string $sport, ?string $fallback = null): ?string
    {
        if (! $sport) {
            return $fallback;
        }

        $key = 'events::events.sport_'.$sport;
        $translated = __($key);

        // Laravel returns the KEY itself when there is no entry for it.
        if ($translated !== $key && is_string($translated)) {
            return $translated;
        }

        return $fallback ?: (config('event_schema.sports.'.$sport.'.label') ?: ucfirst($sport));
    }

    /**
     * The sport's name with the STYLE the event runs under — "Kyokushin
     * Karate", "كاراتيه كيوكوشين" — or just the sport when it runs the sport's
     * default (CategorySystems::styleLabel). Word order is the language's own
     * (`events.sport_with_style`).
     */
    public static function forEvent(\App\Events\Models\ClubEvent $event, ?string $fallback = null): ?string
    {
        $sport = self::for($event->sport, $fallback);
        if (! $sport) {
            return $sport;
        }

        try {
            $style = app(CategorySystems::class)->styleLabel($event);
        } catch (\Throwable) {
            $style = null;   // a label must never break a page
        }

        return $style ? __('events::events.sport_with_style', ['sport' => $sport, 'style' => $style]) : $sport;
    }
}
