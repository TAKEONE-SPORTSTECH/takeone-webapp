<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use Illuminate\Support\Carbon;

/**
 * When an event's entries open and close — the ONE place that decides.
 *
 * ── Why this class exists ────────────────────────────────────────────────────
 *
 * `enrollment_starts_at` and `enrollment_ends_at` are calendar DATES an
 * organiser picked, cast to `date` and stored at midnight. Turning a date into
 * an instant needs a timezone, and until 2026-09-10 five separate call sites
 * each did it themselves with `now()->startOfDay()` — which is midnight in
 * `app.timezone`, i.e. UTC.
 *
 * That was open all day on the deadline and shut at 00:00 UTC, which in Bahrain
 * is 03:00 the following morning: entries stayed live for three hours after the
 * day the organiser named had ended. The user asked for the cut to land at
 * midnight where the competition actually is (2026-09-10).
 *
 * So the day boundary is resolved HERE, in one method, against a configured
 * timezone — and every gate calls it. Five copies of a date comparison is five
 * chances to disagree about whether somebody may still enter, and the entry
 * list is what an event day is run from.
 *
 * ── The timezone ────────────────────────────────────────────────────────────
 *
 * `config('events.entry_timezone')`, defaulted to Asia/Bahrain — the platform's
 * home, and where every event on it is currently run.
 *
 * ⚠️ Deliberately NOT `app.timezone`. That governs how every timestamp in the
 * product is stored and rendered, and re-pointing it to move one deadline would
 * silently shift every clock, every report and every stored date on a live
 * system (RULE #1). This is a separate, narrower decision: which midnight an
 * organiser means when they name a day.
 *
 * When events start running in other countries the right answer is the HOST
 * CLUB's country rather than one platform-wide value. That is a bigger change
 * (a country needs mapping to a zone, and an event needs to keep the zone it
 * was created under so a club moving country cannot retroactively re-close a
 * past event's entries), so it is not guessed at here — but every caller
 * already goes through `for()`, which is where it would land.
 */
class EntryWindow
{
    /** The timezone an event's named days are read in. */
    public static function timezone(ClubEvent $event): string
    {
        $zone = (string) config('events.entry_timezone', 'Asia/Bahrain');

        // A misconfigured zone must not take entries down. Fall back to the
        // app's own, which is what every one of these comparisons used before.
        return in_array($zone, timezone_identifiers_list(), true)
            ? $zone
            : (string) config('app.timezone', 'UTC');
    }

    /**
     * The instant entries CLOSE — the very end of the named day, locally.
     *
     * Null when the organiser named no deadline, which means "no deadline" and
     * not "closed".
     */
    public static function closesAt(ClubEvent $event): ?Carbon
    {
        return self::endOfNamedDay($event, $event->enrollment_ends_at);
    }

    /** The instant entries OPEN — the very start of the named day, locally. */
    public static function opensAt(ClubEvent $event): ?Carbon
    {
        $day = $event->enrollment_starts_at;

        if (! $day) {
            return null;
        }

        return Carbon::parse($day->format('Y-m-d').' 00:00:00', self::timezone($event));
    }

    /** True once the deadline day is over where the competition is. */
    public static function hasClosed(ClubEvent $event): bool
    {
        $closes = self::closesAt($event);

        return $closes !== null && now()->greaterThan($closes);
    }

    /** True while the opening day has not yet begun where the competition is. */
    public static function hasNotOpened(ClubEvent $event): bool
    {
        $opens = self::opensAt($event);

        return $opens !== null && now()->lessThan($opens);
    }

    /**
     * The end of a named day, as an instant.
     *
     * The stored value is read as a DATE and rebuilt in the local zone —
     * `->timezone()` on the stored midnight would shift the day itself (a UTC
     * midnight becomes 03:00 the same morning in Bahrain, which is right, but a
     * negative-offset zone would move it to the previous evening and close the
     * window a day early).
     */
    private static function endOfNamedDay(ClubEvent $event, ?Carbon $day): ?Carbon
    {
        if (! $day) {
            return null;
        }

        return Carbon::parse($day->format('Y-m-d').' 23:59:59', self::timezone($event));
    }
}
