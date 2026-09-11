<?php

namespace App\Translation\Controllers;

use App\Events\Support\PublicEvent;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Translation\Services\ContentLocales;
use App\Translation\Services\Translator;
use App\Translation\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The visitor's half: "I would like to read this in my language."
 *
 * Two endpoints and nothing else — start it, and ask how it is going. The
 * picker calls the first once and the second every couple of seconds until it
 * hears back, which is why the second must stay trivially cheap: one indexed
 * read, no work.
 *
 * ⚠️ This is the expensive door of the whole module. It is open to anybody with
 * the link, by necessity — a stranger deciding whether to enter a competition
 * has no account — so everything that guards it is here or one layer down:
 *
 *   · the event must actually be public (the same 404 an unknown uuid gets, so
 *     this cannot be used to discover unpublished events);
 *   · the locale must be one of ours, or it never reaches the module;
 *   · `throttle:translate` bounds one caller;
 *   · `Translator::ensure()` collapses a crowd into one job per language;
 *   · a failed language is not retried on every knock.
 */
class PublicTranslationController extends Controller
{
    public function __construct(
        private Translator $translator,
        private ContentLocales $locales,
        private PublicEvent $publisher,
    ) {}

    /**
     * Start (or join) the translation of this event into a language.
     *
     * Answers immediately with where things stand — never waits for the model.
     * A visitor holding a phone gets a progress state, not a hanging request,
     * and a request that hangs for thirty seconds is one a mobile browser will
     * abandon anyway.
     */
    public function prepare(Request $request, ClubEvent $event): JsonResponse
    {
        $this->assertPublic($event);

        $locale = $this->locales->normalise($request->input('locale'));

        // A language this event does not OFFER is answered exactly like one we
        // have never heard of. Two reasons, and the second is the expensive
        // one: the picker's list is not a security boundary (anybody can post
        // this endpoint by hand), and this is the door that starts paid work —
        // so an organiser who took a language off their poster must not be
        // billed for a stranger asking for it anyway. See Translations::offers.
        if ($locale !== null && ! Translations::offers($event, $locale)) {
            $locale = null;
        }

        if ($locale === null) {
            // Deliberately the same shape as a success. Which languages exist
            // is not a secret, but there is no reason for this endpoint to be a
            // way to probe anything.
            return $this->state($event, (string) $request->input('locale'), 'unavailable');
        }

        $status = $this->translator->ensure($event, $locale);

        return $this->state($event, $locale, $status);
    }

    /** Where a language stands. Polled; must stay cheap. */
    public function status(Request $request, ClubEvent $event, string $locale): JsonResponse
    {
        $this->assertPublic($event);

        $clean = $this->locales->normalise($locale);

        // Same rule as prepare(), so a language cannot be polled into existence
        // after it has been taken off the poster.
        if ($clean !== null && ! Translations::offers($event, $clean)) {
            $clean = null;
        }

        if ($clean === null) {
            return $this->state($event, $locale, 'unavailable');
        }

        return $this->state($event, $clean, $this->translator->status($event, $clean));
    }

    /**
     * One shape for every answer, so the client has one thing to read.
     *
     * `ready` is the only value that means "go" — everything else, including
     * every failure, leaves the visitor on a page that still works in the
     * language it was written in.
     */
    private function state(ClubEvent $event, string $locale, string $status): JsonResponse
    {
        $known = $this->locales->has($locale);

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'status' => $status,
            'ready' => $status === 'ready' || $status === 'source',
            'name' => $known ? $this->locales->native($locale) : $locale,
            'dir' => $known ? $this->locales->dir($locale) : 'ltr',
            // Whether the buttons and labels will be in this language too, or
            // only the organiser's words. The picker says so before choosing.
            'interface' => $known && $this->locales->isInterfaceLocale($locale),
        ]);
    }

    /**
     * An event with no public page has no public anything. Same 404 as an
     * unknown uuid — never a different answer for "exists but private", which
     * would make this a way to confirm an event exists.
     */
    private function assertPublic(ClubEvent $event): void
    {
        abort_unless($this->publisher->isPublic($event), 404);
    }
}
