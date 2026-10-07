<?php

namespace App\Federations\Controllers;

use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationDirectory;
use App\Federations\Services\FederationEvents;
use App\Federations\Services\FederationInvitation;
use App\Federations\Services\InvitePage;
use App\Members\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Controller;
use App\Translation\EventLocale;
use App\Translation\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * The club invitation in the EVENT PAGE's design (federations.invite_v2).
 *
 * The page is new; nothing else is. The flow inside it talks to the same
 * `federations.invite.*` doors through the same federation-store.platform.js
 * the prototype page uses, so who may verify, what a submit saves and what a
 * club may read are decided exactly where they always were
 * (FederationInvitation). This controller adds only what the event page's
 * design brings with it: a language the reader picks on the cover, the
 * machine translation behind it, and the organiser's gear page where the
 * offered languages are chosen.
 */
class InvitePageController extends Controller
{
    public function __construct(
        private InvitePage $page,
        private FederationInvitation $invitation,
        private FederationDirectory $directory,
        private FederationEvents $events,
    ) {}

    /**
     * Is the new page serving THIS request? Switched on for everyone
     * (federations.invite_v2), or previewed by a signed-in super-admin
     * (`?preview=v2` on the invitation, remembered in their session).
     */
    public static function enabled(Request $request): bool
    {
        if (! config('federations.enabled')) {
            return false;
        }

        return (bool) config('federations.invite_v2')
            || ($request->hasSession() && $request->session()->get('fedinv.preview') === true && $request->user()?->hasRole('super-admin'));
    }

    /** Called by FederationAppController::publicPage with the prototype's own boot. */
    public function show(Request $request, FederationEvent $event, array $boot): Response
    {
        // The SHARED club-entry page (events::entry.club.page) — the same file
        // the event's own /me/events/{uuid}/club addresses draw — with no step
        // addresses: this legacy door keeps its in-page steps.
        $response = response()->view('events::entry.club.page', $this->viewData($request, $event, $boot) + ['nav' => null]);

        return $response->withHeaders([
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
        ]);
    }

    /**
     * Everything the club-entry page draws with, for one federation event —
     * shared by this legacy door and App\Federations\Support\FederationClubEntryDesk.
     * Sets the reading locale for the request as a side effect.
     */
    public function viewData(Request $request, FederationEvent $event, array $boot): array
    {
        $locale = $this->readingLocale($request, $event);
        $this->useLocale($locale);

        $linked = $this->record($event);
        if ($linked instanceof \App\Events\Models\ClubEvent) {
            // The same poster payload the event page draws — one record, one
            // face. Addressed by the FEDERATION event's uuid on this page (its
            // doors and its remembered language are keyed on it); the
            // federation's own attachments ride along.
            $e = array_merge((array) app(\App\Events\Support\PublicEvent::class)->readerPayload($linked), [
                'key' => $event->uuid, 'uuid' => $event->uuid, 'manifest' => null,
                'documents' => $this->page->documentsOf($event),
            ]);
            $coverLanguages = \App\Events\Support\CoverLanguages::for($linked, $e);
            // The event's own doors — Draw, Officials, Gallery, Participants,
            // Results — the same buttons its public page shows, leading into the
            // platform event's sections (App\Events\Support\EventDoors).
            $doors = \App\Events\Support\EventDoors::public($e, new \App\Events\Support\EventAddress($linked));
            // The event's own rule book, so a club sees the category its
            // athlete will actually be placed in (CategorySystems).
            $system = app(\App\Events\Support\CategorySystems::class)->forEvent($linked);
            if ($system) {
                $boot['rulebook'] = ['key' => $system->key, 'label' => $system->label, 'age_groups' => $system->ageGroups, 'classes' => $system->table()];
            }
        } else {
            $e = $this->page->payload($event, $locale);
            $coverLanguages = $this->coverLanguages($event, $e);
        }

        $v = fn ($f) => substr(md5((string) @filemtime(app_path('Federations/resources/app/'.$f))), 0, 8);

        return [
            'e' => $e,
            'doors' => $doors ?? [],
            'event' => $event,
            'boot' => $boot,
            'coverLanguages' => $coverLanguages,
            'languageUrls' => $this->languageUrls($event),
            'console' => route('federations.invite.settings', $event->uuid),
            'strings' => (array) trans('federations::invite.ui'),
            'noindex' => true,
            // The store the steps talk to — the federation's own, one copy.
            'scripts' => [
                ['src' => route('federations.app.asset', 'takeone-bridge.js').'?v='.$v('takeone-bridge.js'), 'defer' => false],
                ['src' => route('federations.app.asset', 'federation-store.platform.js').'?v='.$v('federation-store.platform.js'), 'defer' => false],
                ['src' => route('federations.app.asset', 'takeone-test-mode.js').'?v='.$v('takeone-test-mode.js'), 'defer' => true],
            ],
        ];
    }

    /** The cover / language sheet's choice: remembered for THIS event, in this session only. */
    public function locale(Request $request, string $event): RedirectResponse
    {
        $ev = $this->event($event);
        $code = Translations::locales()->normalise((string) $request->input('locale'));
        if ($code !== null && Translations::offers($this->record($ev), $code)) {
            EventLocale::set($request, $ev->uuid, $code);
        }

        return redirect()->to($this->inviteUrl($ev));
    }

    /** Start (or find) the translation into one offered language. */
    public function prepare(Request $request, string $event): JsonResponse
    {
        $ev = $this->event($event);
        $code = Translations::locales()->normalise((string) $request->input('locale'));
        $rec = $this->record($ev);
        if ($code === null || ! Translations::offers($rec, $code)) {
            return $this->state((string) $request->input('locale'), 'unavailable');
        }

        return $this->state($code, Translations::ensure($rec, $code));
    }

    public function status(string $event, string $locale): JsonResponse
    {
        $ev = $this->event($event);
        $code = Translations::locales()->normalise($locale);
        $rec = $this->record($ev);
        if ($code === null || ! Translations::offers($rec, $code)) {
            return $this->state($locale, 'unavailable');
        }

        return $this->state($code, Translations::status($rec, $code));
    }

    // ─── The gear: the organiser's settings for this invitation ──────────────

    public function settings(Request $request, string $event): Response
    {
        $ev = $this->event($event);
        $manager = $this->canManage($request, $ev);

        // Once the event runs on the platform, whoever runs it manages it in
        // the SAME console as any event (/me/events/{uuid}/manage).
        $linked = $this->record($ev);
        if ($manager && $linked instanceof \App\Events\Models\ClubEvent) {
            // Marked, so SealFederation lets this one redirect leave /federation.
            return redirect()->route('me.events.manage', $linked->uuid)->header(\App\Federations\Middleware\SealFederation::LEAVE, '1');
        }

        $this->useLocale($this->readingLocale($request, $ev));
        $e = $this->page->payload($ev, app()->getLocale());

        $locales = Translations::locales();
        $rows = collect($locales->all())->map(fn ($meta, $code) => [
            'code' => $code,
            'name' => $meta['name'],
            'native' => $meta['native'],
            'dir' => $meta['dir'] ?? 'ltr',
            'flag' => $locales->flag($code),
            'ui' => $locales->isInterfaceLocale($code),
        ])->values()->sortBy([['ui', 'desc'], ['name', 'asc']])->values()->all();

        return response()->view('federations::invite.settings', [
            'e' => $e,
            'event' => $ev,
            'manager' => $manager,
            'signedIn' => (bool) $request->user(),
            'languageRows' => $manager ? $rows : [],
            'offered' => $ev->offeredLocales(),
            'source' => $ev->sourceLocale(),
            'backUrl' => $this->inviteUrl($ev),
            'noindex' => true,
        ])->withHeaders(['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** The gear's sign-in, in the invitation's own skin. Lands back on the gear. */
    public function signIn(Request $request, string $event, AuthenticatedSessionController $auth): Response
    {
        $ev = $this->event($event);
        $request->session()->put('url.intended', route('federations.invite.settings', $ev->uuid));

        $response = $auth->store($request);
        if (Auth::user()) {
            $request->session()->forget('url.intended');

            return redirect()->route('federations.invite.settings', $ev->uuid);
        }
        if ($response instanceof RedirectResponse && $response->getTargetUrl() === route('two-factor.challenge')) {
            return redirect()->route('federations.login.verify');
        }

        return $response;
    }

    public function updateSettings(Request $request, string $event): JsonResponse
    {
        $ev = $this->event($event);
        abort_unless($this->canManage($request, $ev), 403);

        $known = array_keys(Translations::locales()->all());
        $data = $request->validate([
            'offered' => ['required', 'array', 'min:1', 'max:'.count($known)],
            'offered.*' => ['string', 'in:'.implode(',', $known)],
            'source' => ['required', 'string', 'in:'.implode(',', $known)],
        ]);

        $offered = array_values(array_unique($data['offered']));
        if (! in_array($data['source'], $offered, true)) {
            $offered[] = $data['source'];
        }
        $ev->forceFill(['offered_locales' => $offered, 'source_locale' => $data['source']])->save();
        Cache::forget($this->coverKey($ev));
        activity('federation')->causedBy($request->user())->performedOn($ev)
            ->withProperties(['offered' => $offered, 'source' => $data['source']])->log('federation.invite_languages');

        return response()->json([
            'success' => true,
            'message' => __('federations::invite.settings_saved'),
            'offered' => Translations::offered($ev->fresh()),
            'source' => $ev->sourceLocale(),
        ]);
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    /**
     * What the invitation's words are read from: the platform event once the
     * federation event runs as one (federations.platform_events) — the same
     * record, translations and languages the console manages — else the
     * federation event itself.
     */
    private function record(FederationEvent $ev): FederationEvent|\App\Events\Models\ClubEvent
    {
        return app(\App\Federations\Services\FederationPlatformEvent::class)->enabled()
            ? (app(\App\Federations\Services\FederationPlatformEvent::class)->of($ev) ?? $ev)
            : $ev;
    }

    /** The invitation's own gate: approved, unsuspended federation, event not completed. */
    private function event(string $uuid): FederationEvent
    {
        abort_unless(self::enabled(request()), 404);
        $ev = $this->invitation->event($uuid);
        abort_unless($ev, 404);

        return $ev;
    }

    /** A manager of the federation that runs this event (the same gate as test mode). */
    private function canManage(Request $request, FederationEvent $ev): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        $access = $this->directory->access($user);

        return $access && ($access['manager'] ?? false) && $this->events->find($access, $ev->uuid) !== null;
    }

    /**
     * The language this reader chose on the cover, if the organiser still
     * offers it — else English, never the browser's guess (the cover exists to
     * ask). The organiser's own language is offered whatever the list says.
     */
    private function readingLocale(Request $request, FederationEvent $ev): string
    {
        $rec = $this->record($ev);
        $chosen = EventLocale::get($request, $ev->uuid);
        if ($chosen && Translations::offers($rec, $chosen)) {
            return $chosen;
        }

        return Translations::offers($rec, 'en') ? 'en' : $rec->sourceLocale();
    }

    private function useLocale(string $locale): void
    {
        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale);
    }

    private function inviteUrl(FederationEvent $ev): string
    {
        return route('federations.app.invitation').'?event='.rawurlencode($ev->uuid);
    }

    private function languageUrls(FederationEvent $ev): array
    {
        return [
            'set' => route('federations.invite.locale', $ev->uuid),
            'method' => 'PUT',
            'back' => $this->inviteUrl($ev),
            'prepare' => route('federations.invite.language.prepare', $ev->uuid),
            'status' => route('federations.invite.language.status', ['event' => $ev->uuid, 'locale' => '__L__']),
        ];
    }

    private function coverKey(FederationEvent $ev): string
    {
        // The stored translations are part of the key: a language finishing
        // after the cards were built must show on the next render, not in an hour.
        return 'fedinv-cover:'.$ev->id.':'.optional($ev->updated_at)->timestamp
            .':'.substr(md5(implode(',', Translations::offered($ev)).'|'.json_encode(Translations::stored($ev))), 0, 12);
    }

    private function coverLanguages(FederationEvent $ev, array $e): array
    {
        try {
            return Cache::remember($this->coverKey($ev), now()->addHour(), fn () => $this->page->coverLanguages($ev, $e));
        } catch (\Throwable) {
            return $this->page->coverLanguages($ev, $e);
        }
    }

    private function state(string $locale, string $status): JsonResponse
    {
        $locales = Translations::locales();
        $known = $locales->has($locale);

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'status' => $status,
            'ready' => $status === 'ready' || $status === 'source',
            'name' => $known ? $locales->native($locale) : $locale,
            'dir' => $known ? $locales->dir($locale) : 'ltr',
        ]);
    }
}
