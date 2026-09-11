<?php

namespace App\Translation;

use Illuminate\Http\Request;

/**
 * The language somebody is reading ONE EVENT in — and nothing else.
 *
 * ⚠️ This exists to keep two decisions apart that were being made by the same
 * switch, which was a real defect:
 *
 *   "I read this platform in Arabic."          ← the member's own preference
 *   "Show me THIS competition in Portuguese."  ← a visitor to one poster
 *
 * An event page is a public door. A Brazilian coach opens a link, picks
 * Português to read the competition, and that must not silently re-language the
 * organiser's own admin panel — nor follow a signed-in member back to /me and
 * to every other device they own, which is what writing `users.locale` did.
 *
 * So an event's language lives in the SESSION, keyed by the event's uuid, and
 * is applied only while the reader is inside `/e/{uuid}`. Nothing here ever
 * touches `session('locale')` or `users.locale`; the platform preference is set
 * in one place, the member's own settings, and this cannot reach it.
 *
 * Keyed by uuid rather than a single value so two events open in two tabs do
 * not fight over one slot — a coach comparing two competitions in two languages
 * gets both.
 */
class EventLocale
{
    /** Where the per-event choices live in the session. */
    private const KEY = 'event_locale';

    /** How many events' choices to remember before dropping the oldest. */
    private const REMEMBER = 20;

    /**
     * The event uuid this request is reading, or null.
     *
     * Matched on the path rather than the route, so it is right for every URL
     * under an event — the poster, its sections, the entry form, the sealed
     * management mirror — without each of them having to opt in.
     */
    public static function uuidFor(Request $request): ?string
    {
        $segments = $request->segments();

        if (($segments[0] ?? null) !== 'e' || ! isset($segments[1])) {
            return null;
        }

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $segments[1])
            ? strtolower($segments[1])
            : null;
    }

    /** The language chosen for this event in this session, if any. */
    public static function get(Request $request, string $uuid): ?string
    {
        $all = (array) $request->session()->get(self::KEY, []);

        $locale = $all[strtolower($uuid)] ?? null;

        return is_string($locale) ? $locale : null;
    }

    /**
     * Remember that this session reads this event in this language.
     *
     * The caller has already validated the locale against the served list —
     * see LocaleController, which is the only writer.
     */
    public static function set(Request $request, string $uuid, string $locale): void
    {
        $all = (array) $request->session()->get(self::KEY, []);

        // Re-insert at the end so the most recently chosen survives the trim.
        unset($all[strtolower($uuid)]);
        $all[strtolower($uuid)] = $locale;

        if (count($all) > self::REMEMBER) {
            $all = array_slice($all, -self::REMEMBER, null, true);
        }

        $request->session()->put(self::KEY, $all);
    }

    /**
     * The language to render this request in, or null to leave it alone.
     *
     * Called by SetLocale. Returns a value only inside an event, and only when
     * that event has a language chosen for it — so every other page on the
     * platform resolves exactly as it always did.
     */
    public static function forRequest(Request $request): ?string
    {
        $uuid = self::uuidFor($request);

        if ($uuid === null) {
            return null;
        }

        $locale = self::get($request, $uuid);

        if ($locale === null) {
            return null;
        }

        /*
         * The organiser's allow-list, applied to a choice already made.
         *
         * A session outlives a setting: somebody reading a poster in Farsi when
         * the organiser takes Farsi off it would otherwise go on reading it —
         * on their next page, tomorrow, and on whatever they shared the link
         * into. So the choice is re-checked where it is APPLIED, not only where
         * it is written, and a language no longer offered is forgotten rather
         * than merely ignored: it is not coming back, and leaving it in the
         * session means paying for this lookup on every request forever.
         *
         * ONE column read, on `/e/{uuid}` only, and only when a choice is
         * actually stored — so a first-time visitor, and every URL on the rest
         * of the platform, cost nothing. Memoised for the request because
         * middleware and the page both ask.
         */
        if (! self::stillOffered($uuid, $locale)) {
            self::forget($request, $uuid);

            return null;
        }

        return $locale;
    }

    /** @var array<string, bool> uuid|locale → offered, for this request only */
    private static array $checked = [];

    private static function stillOffered(string $uuid, string $locale): bool
    {
        $key = $uuid.'|'.$locale;

        if (array_key_exists($key, self::$checked)) {
            return self::$checked[$key];
        }

        $event = \App\Models\ClubEvent::where('uuid', $uuid)->first(['id', 'uuid', 'source_locale', 'offered_locales']);

        // No event, no opinion. An unknown uuid is somebody else's 404 to
        // raise; forgetting a language here would be this class deciding
        // something about a page it knows nothing about.
        return self::$checked[$key] = $event === null
            || \App\Translation\Translations::offers($event, $locale);
    }

    /** Drop this session's choice for one event. */
    public static function forget(Request $request, string $uuid): void
    {
        $all = (array) $request->session()->get(self::KEY, []);

        unset($all[strtolower($uuid)]);

        $request->session()->put(self::KEY, $all);
    }
}
