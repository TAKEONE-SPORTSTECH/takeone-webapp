<?php

namespace App\Events\Support;

use Illuminate\Support\Str;

/**
 * The line that says WHAT an event is: "BRAZILIAN JIU-JITSU CHAMPIONSHIP".
 *
 * One implementation, because two surfaces print it — the poster's band and the
 * member event page's, which are the same band since 2026-09-08 — and they had
 * a copy each. The copies disagreed, which is the whole reason this class
 * exists:
 *
 *   poster  "Bjj Jiu-Jitsu Championship"          (ucfirst of the column, and
 *                                                  the type already said the sport)
 *   member  "Brazilian Jiu-Jitsu Championship"    (the schema's label)
 *
 * Both were built by the same rule — prefix the sport onto the type unless the
 * type already names it — and that rule was a plain substring test, which
 * cannot see a PARTIAL overlap. The two payloads also disagree about the type
 * itself: an event package names its sport in its own label ("Jiu-Jitsu
 * Championship") while the generic schema does not ("Championship"). So the
 * rule has to work word by word.
 */
class EventClassification
{
    /**
     * Sport + type, with whatever the type already says removed from the sport.
     *
     * "Brazilian Jiu-Jitsu" + "Jiu-Jitsu Championship" → "Brazilian Jiu-Jitsu
     * Championship": the shared word is dropped from the PREFIX, never from the
     * type, so the type is always printed exactly as its package wrote it.
     */
    public static function line(?string $sport, ?string $type): string
    {
        $type = trim((string) $type);
        $sport = trim((string) $sport);

        if ($sport === '') {
            return $type;
        }

        if ($type === '') {
            return $sport;
        }

        // What the type already says, compared on words rather than substrings.
        $said = collect(preg_split('/[\s\-–—]+/u', Str::lower($type), -1, PREG_SPLIT_NO_EMPTY))->all();

        $keep = collect(preg_split('/(\s+)/u', $sport, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY))
            ->reject(function (string $part) use ($said) {
                if (trim($part) === '') {
                    return false;   // keep the spacing between words we keep
                }

                $words = preg_split('/[\-–—]+/u', Str::lower($part), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                // A word of the sport is dropped only when the type says ALL of
                // it — "Jiu-Jitsu" goes when the type carries jiu AND jitsu, so
                // a sport whose name merely shares one word with the type keeps
                // its own name intact.
                return $words !== [] && collect($words)->every(fn ($w) => in_array($w, $said, true));
            })
            ->implode('');

        $prefix = trim($keep);

        if ($prefix === '') {
            return $type;
        }

        /*
         * ⚠️ Splicing a REMNANT of the sport onto the type only works in a
         * language that puts its adjectives first.
         *
         * English: "Brazilian Jiu-Jitsu" + "Jiu-Jitsu Championship" drops the
         * shared words and yields "Brazilian Jiu-Jitsu Championship" — right.
         * Portuguese: "Jiu-Jitsu Brasileiro" + "Campeonato de Jiu-Jitsu" leaves
         * "Brasileiro", and prefixing it gives "Brasileiro Campeonato de
         * Jiu-Jitsu", which no Portuguese speaker would write.
         *
         * There is no general rule for where a leftover adjective belongs in an
         * arbitrary language, and guessing produces exactly the broken-sounding
         * output this whole effort exists to avoid. So outside the language the
         * labels are WRITTEN in, a type that already names the sport is left to
         * speak for itself: "Campeonato de Jiu-Jitsu" is complete and correct.
         *
         * English is untouched — same call, same output, character for
         * character.
         */
        if ($prefix !== $sport && app()->getLocale() !== config('app.fallback_locale', 'en')) {
            return $type;
        }

        return $prefix.' '.$type;
    }
}
