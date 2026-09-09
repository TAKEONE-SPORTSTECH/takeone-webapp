<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Models\ClubEvent;
use App\Support\Cldr;
use App\Support\SportLabel;
use App\Translation\Translations;
use Illuminate\Support\Facades\Cache;

/**
 * Everything the cover's language carousel needs, in every language it offers.
 *
 * ── What the carousel does, and why this class exists ────────────────────────
 *
 * The cover is a strip of language cards. As a card reaches the centre, the
 * WHOLE screen re-labels itself into that language — the classification line,
 * the event's title, the date, and the three controls — before the visitor has
 * chosen anything. That is the point of the design: you find your language by
 * seeing the page become readable, not by recognising a word in a list.
 *
 * Which means the page has to arrive already knowing all sixty-eight versions
 * of itself. It cannot fetch one per scroll — the strip moves faster than a
 * network — so every language is rendered into the payload up front.
 *
 * ── How that stays cheap ────────────────────────────────────────────────────
 *
 * Built once per event per hour and cached whole. The pieces are:
 *
 *   · the three control labels — `trans(…, $code)`, one lang-group load per
 *     locale, memoised by the translator;
 *   · the event's own title and classification — from the ONE translation
 *     document that holds every language at once (App\Translation), so the
 *     sixty-eight titles cost a single row read;
 *   · the date — CLDR, no I/O at all.
 *
 * ⚠️ The key includes the event's `updated_at`, so renaming an event or
 * translating it into a new language shows up on the next render rather than
 * in an hour.
 */
class CoverLanguages
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for(ClubEvent $event, array $payload): array
    {
        $key = 'cover-langs:'.$event->id.':'.optional($event->updated_at)->timestamp;

        try {
            return Cache::remember($key, now()->addHour(), fn () => self::build($event, $payload));
        } catch (\Throwable $e) {
            // A cache backend having a bad day must not cost the cover.
            return self::build($event, $payload);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function build(ClubEvent $event, array $payload): array
    {
        $locales = Translations::locales();
        $was = app()->getLocale();

        $club = (string) ($payload['host'] ?? $payload['club'] ?? '');
        $date = $event->date;

        /*
         * ⚠️ The event TYPE has to be re-asked inside each locale.
         *
         * `$payload['type']` was rendered once, in whatever language this
         * request happens to be in, so pasting it into all sixty-eight rows
         * produced a classification line half in one language and half in
         * another — "Brazilian Jiu-Jitsu Kampionati i Jiu-Jitsu" on the Albanian
         * card. The type's label is a `__()` call; it must be made while the
         * locale is set, like every other string here.
         */
        $type = app(EventTypeRegistry::class)->for($event);

        $rows = [];

        try {
            foreach ($locales->all() as $code => $meta) {
                app()->setLocale($code);

                $tr = Translations::of($event, $code);

                /*
                 * The classification, per language, through the one shared
                 * implementation — so the carousel and the poster underneath it
                 * can never disagree about what this event is called
                 * (App\Events\Support\EventClassification).
                 */
                $sport = SportLabel::for(
                    $event->sport,
                    $event->sport ? (config('event_schema.sports.'.$event->sport.'.label') ?: ucfirst($event->sport)) : null,
                );

                $rows[] = [
                    'code' => $code,
                    // A flag is a recognition aid, never the name — and this
                    // list is the OWNER's, including decisions recorded in
                    // config/content_locales.php that a design draft must not
                    // silently overrule.
                    'flag' => $locales->flag($code),
                    'native' => $meta['native'],
                    'title' => $meta['name'],
                    'dir' => $meta['dir'] ?? 'ltr',

                    'tag' => EventClassification::line($sport, $type?->label()),
                    'ev_title' => (string) $tr->get('title', (string) $event->getRawOriginal('title')),
                    'ev_date' => trim(($date ? Cldr::skeleton($date, 'EEEdMMM', 'D j M') : '')
                        .($club !== '' ? ' · '.$club : '')),

                    'label_language' => (string) trans('events.cover_language', [], $code),
                    'label_search' => (string) trans('events.cover_search', [], $code),
                    'label_enter' => (string) trans('events.cover_enter', [], $code),
                ];
            }
        } finally {
            app()->setLocale($was);
        }

        return $rows;
    }
}
