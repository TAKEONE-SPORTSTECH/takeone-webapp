<?php

namespace App\Federations\Controllers;

use App\Federations\Models\Federation;
use App\Federations\Services\FederationApp;
use App\Federations\Services\FederationBilling;
use App\Federations\Services\FederationDesk;
use App\Federations\Services\FederationDirectory;
use App\Federations\Services\FederationDraws;
use App\Federations\Services\FederationMessages;
use App\Federations\Services\FederationStrings;
use App\Federations\Services\FederationEvents;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the federation dashboard prototype, as designed, from
 * app/Federations/resources/app — see FederationApp for what is rewritten and
 * why. Everything is behind config('federations.enabled').
 */
class FederationAppController extends Controller
{
    public const HOME = 'Events Dashboard.dc.html';

    /**
     * Every screen of the dashboard has its own address — /federation/events,
     * /federation/events/<event uuid>/participants, /federation/settings… —
     * all answered by home(); the bridge (takeone-bridge.js › routes) opens the
     * screen the address names and keeps the address in step as you move.
     * Also the only shape a sign-in may return to (FederationLoginController).
     */
    public const SECTIONS = 'statistics|events|clubs|calendar|athletes|officials|financials|reports|settings|sub-federations';

    public const DEEP_LINK = '#^/federation/('.self::SECTIONS.')(/[A-Za-z0-9-]{1,64}){0,2}/?$#';

    public function __construct(private FederationApp $app, private FederationDirectory $directory, private FederationBilling $billing, private FederationEvents $events, private FederationDesk $desk, private FederationDraws $draws, private FederationStrings $strings, private FederationMessages $messages) {}

    /**
     * /federation — the dashboard itself, at a clean address.
     *
     * Not behind `auth` in the route file: a signed-out visitor goes to the
     * federation's own sign-in (FederationLoginController), which the
     * platform-wide guest redirect cannot know about. The same gates the
     * staff routes carry — signed in, verified, 2FA passed — are applied here.
     */
    public function home(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->ensureEnabled();
        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('federations.login'));
        }
        if ($user->hasTwoFactorEnabled() && ! $request->session()->get('two_factor.verified')) {
            \Illuminate\Support\Facades\Auth::logout();

            return redirect()->route('federations.login');
        }
        if ($user->email && ! $user->hasVerifiedEmail()) {
            return app(\App\Federations\Federations::class)->sealedPage($request, __('federations::messages.sealed_verify_title'), __('federations::messages.sealed_verify_body'), false, false);
        }
        if (! $this->directory->access($user)) {
            // Rendered in place, never redirected: the surface is sealed, and a
            // redirect from here would come straight back.
            return app(\App\Federations\Federations::class)->sealedPage($request, __('federations::messages.sealed_no_access_title'),
                $user->isSuperAdmin() ? __('federations::messages.login_create_first') : __('federations::messages.login_no_access'), false, false);
        }

        return $this->staffPage($request, self::HOME, '/federation/app/'.rawurlencode(self::HOME));
    }

    /**
     * The Draw tab's board: <x-competition-brackets> in a same-origin frame.
     * Carries no draw data — the dashboard hands it the division — and saves
     * nothing; every winner goes back through federations.api.draws.
     */
    public function drawBoard(Request $request): Response
    {
        $this->ensureEnabled();
        abort_unless(config('federations.draw_board') && $this->directory->access($request->user()), 404);

        return response()->view('federations::draw.board', ['canRecord' => true])
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * The Participants tab's athlete editor, in a same-origin frame so it can
     * use the platform's own form controls. Carries no athlete data: the frame
     * reads and saves through federations.api.regs.athlete, which scope and
     * authorise every request (FederationAthleteEditor).
     */
    public function athleteEdit(Request $request): Response
    {
        $this->ensureEnabled();
        $access = $this->directory->access($request->user());
        abort_unless($access, 404);
        $q = $request->validate([
            'entry' => ['required', 'uuid'],
            'athlete' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);

        return response()->view('federations::athlete.edit', [
            'entry' => $q['entry'], 'athlete' => $q['athlete'],
            'canEdit' => (bool) ($access['manager'] ?? false),
            'urls' => ['show' => route('federations.api.regs.athlete'), 'update' => route('federations.api.regs.athlete.update'),
                // Taking them off the event: the dashboard's own withdrawal (FederationDesk::withdrawRegs).
                'withdraw' => route('federations.api.regs.withdraw')],
        ])->header('X-Frame-Options', 'SAMEORIGIN')->header('Cache-Control', 'no-store, private');
    }

    /**
     * The Officials tab's editor (appoint, change, remove; and the person's own
     * name, gender, nationality and photo), in a same-origin frame like the
     * athlete editor. Carries no person data; every read and write goes through
     * the event's officiating endpoints and federations.api.officials.*, which
     * scope and authorise each request.
     */
    public function officialEdit(Request $request, \App\Federations\Services\FederationOfficialEditor $editor): Response
    {
        $this->ensureEnabled();
        $access = $this->directory->access($request->user());
        abort_unless($access, 404);
        $q = $request->validate([
            'event' => ['required', 'uuid'],
            'official' => ['nullable', 'integer', 'min:1'],
        ]);
        $ev = $editor->eventInScope($access, $request->user(), $q['event']);
        abort_unless($ev, 404);

        return response()->view('federations::official.edit', [
            'event' => $ev->uuid, 'official' => isset($q['official']) ? (int) $q['official'] : null,
            'fedClubs' => $editor->clubs($access),
            'urls' => [
                'list' => route('me.events.officials', $ev->uuid),
                'store' => route('me.events.officials.store', $ev->uuid),
                'newPerson' => route('me.events.officials.person', $ev->uuid),
                // A designation added to the host's list, offered on its every event.
                'designation' => route('me.events.officials.designations.store', $ev->uuid),
                'one' => route('me.events.officials.update', [$ev->uuid, '__ID__']),
                'show' => route('federations.api.officials.one'),
                'person' => route('federations.api.officials.person'),
            ],
        ])->header('X-Frame-Options', 'SAMEORIGIN')->header('Cache-Control', 'no-store, private');
    }

    /**
     * The event Clubs tab's club editor (name, contact, address, logo), in a
     * same-origin frame like the athlete and official editors. Carries no club
     * data; it reads and saves through federations.api.clubs.*, where
     * FederationClubManager decides which clubs this federation may change.
     */
    public function clubEdit(Request $request, \App\Federations\Services\FederationClubManager $manager): Response
    {
        $this->ensureEnabled();
        $access = $this->directory->access($request->user());
        abort_unless($access && $manager->mayManage($access), 404);
        $q = $request->validate(['club' => ['required', 'string', 'regex:/^(fc|fa)-[0-9a-f-]{36}$/']]);

        return response()->view('federations::club.edit', [
            'club' => $q['club'],
            'urls' => ['show' => route('federations.api.clubs.detail'), 'update' => route('federations.api.clubs.update'),
                'logo' => route('federations.api.clubs.logo')],
        ])->header('X-Frame-Options', 'SAMEORIGIN')->header('Cache-Control', 'no-store, private');
    }

    /**
     * GET /federation/results-pdf/{event}?kind=results|bouts|both — the
     * event's Results tab as an A4 PDF (FederationResultsPdf). Served to the
     * same people the tab's data is (FederationEventBracket::payload: the
     * viewer's federation subtree and sports); anybody else, and an event not
     * on the platform, gets the 404 a missing event gets. A headless browser
     * per request, so on the draw PDF's hard limiter.
     */
    public function resultsPdf(Request $request, string $event, \App\Federations\Services\FederationResultsPdf $pdf): \Symfony\Component\HttpFoundation\Response
    {
        $this->ensureEnabled();
        abort_unless(\App\Federations\Services\FederationResultsPdf::available(), 404);
        $access = $this->staffAccess($request);
        $kind = $request->validate(['kind' => ['required', 'in:'.implode(',', \App\Federations\Services\FederationResultsPdf::KINDS)]])['kind'];

        $q = \App\Federations\Models\FederationEvent::where('uuid', $event)->whereNotNull('club_event_id')
            ->whereIn('federation_id', collect($this->directory->subtree($access['root']))->pluck('id'));
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }
        $fe = $q->first();
        $ev = $fe ? \App\Events\Models\ClubEvent::whereKey($fe->club_event_id)->where('is_archived', false)->first() : null;
        abort_unless($fe && $ev, 404);

        $refuse = fn (string $message, int $status) => response()->json(['success' => false, 'message' => $message], $status);

        try {
            $html = $pdf->html($fe, $ev, $request->user(), $kind);
            if ($html === null) {
                return $refuse(__('federations::messages.results_pdf_nothing'), 422);
            }
            $bytes = $pdf->render($html);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Federation results PDF failed', ['event' => $fe->uuid, 'kind' => $kind, 'error' => $e->getMessage()]);

            return $refuse(__('events::events.bracket_pdf_failed'), 503);
        }

        // A name that says what it is, built from the title — never from input.
        $slug = \Illuminate\Support\Str::slug(\Illuminate\Support\Str::ascii((string) ($ev->title ?: $fe->name))) ?: 'event';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$slug.'-'.['results' => 'results', 'bouts' => 'bouts', 'both' => 'results-and-bouts'][$kind].'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * GET /federation/competition-report/{event} — the whole competition as
     * one report PDF (FederationCompetitionReport): front matter, every
     * bracket, statistics & analytics. Same people as the Results PDF (the
     * viewer's federation subtree and sports); anybody else gets the 404 a
     * missing event gets. Three browser prints and a join — the heaviest
     * download there is — so the draw PDF's hard limiter, and room to finish.
     */
    public function competitionReport(Request $request, string $event, \App\Federations\Services\FederationCompetitionReport $report): \Symfony\Component\HttpFoundation\Response
    {
        $this->ensureEnabled();
        abort_unless(\App\Federations\Services\FederationCompetitionReport::available(), 404);
        [$fe, $ev] = $this->reportEvent($request, $event);

        @set_time_limit(240);
        @ini_set('memory_limit', '512M');

        try {
            $bytes = $report->build($fe, $ev, $request->user());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Federation competition report failed', ['event' => $fe->uuid, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => __('events::events.bracket_pdf_failed')], 503);
        }
        if ($bytes === null) {
            return response()->json(['success' => false, 'message' => 'There is nothing to report yet — this event has no draw.'], 422);
        }

        $slug = \Illuminate\Support\Str::slug(\Illuminate\Support\Str::ascii((string) ($ev->title ?: $fe->name))) ?: 'event';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$slug.'-competition-report.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * GET /federation/analytics/{event} — the event's statistics & analytics as
     * JSON, for its Analytics tab: the competition report's own figures
     * (FederationCompetitionReport::analytics), served to whoever may download
     * that report. Read-only; cached briefly per event (the figures do not
     * depend on who reads them), since every open tab would otherwise replay
     * the whole officiating log.
     */
    public function analytics(Request $request, string $event, \App\Federations\Services\FederationCompetitionReport $report): \Illuminate\Http\JsonResponse
    {
        $this->ensureEnabled();
        [$fe, $ev] = $this->reportEvent($request, $event);

        $data = \Illuminate\Support\Facades\Cache::remember('fed-analytics:'.$ev->id, 30,
            fn () => $report->analytics($fe, $ev, $request->user()) ?? []);

        return response()->json(['success' => true, 'analytics' => $data ?: null], 200, ['Cache-Control' => 'private, no-store']);
    }

    /**
     * The federation event a report door may serve: in the viewer's federation
     * tree and sport scope, on the platform and not archived. Anything else is
     * one 404, whether it exists or not.
     *
     * @return array{0: \App\Federations\Models\FederationEvent, 1: \App\Events\Models\ClubEvent}
     */
    private function reportEvent(Request $request, string $event): array
    {
        $access = $this->staffAccess($request);

        $q = \App\Federations\Models\FederationEvent::where('uuid', $event)->whereNotNull('club_event_id')
            ->whereIn('federation_id', collect($this->directory->subtree($access['root']))->pluck('id'));
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }
        $fe = $q->first();
        $ev = $fe ? \App\Events\Models\ClubEvent::whereKey($fe->club_event_id)->where('is_archived', false)->first() : null;
        abort_unless($fe && $ev, 404);

        return [$fe, $ev];
    }

    /** The dashboard's file address (old bookmarks) lands on the clean one. */
    public function dashboardFile(): RedirectResponse
    {
        $this->ensureEnabled();

        return redirect()->route('federations.home');
    }

    /** Old per-federation address: land on the same dashboard. */
    public function legacy(Request $request, Federation $federation): RedirectResponse
    {
        $this->ensureEnabled();
        $access = $this->staffAccess($request);
        $inScope = collect($this->directory->subtree($access['root']))->contains(fn ($f) => $f->is($federation));
        abort_unless($inScope, 404);

        return redirect()->route('federations.home');
    }

    /** A staff page — signed in, and running a federation. */
    public function staffPage(Request $request, string $file, ?string $base = null): Response
    {
        $this->ensureEnabled();
        abort_unless($this->app->isStaffPage($file), 404);

        $access = $this->staffAccess($request);
        $user = $request->user();
        $root = $access['root'];

        return $this->html($this->app->page($file, [
            'csrf' => csrf_token(),
            'mode' => 'staff',
            'favicon' => \App\Federations\Support\FederationBrand::favicon($root),
            'federation' => ['uuid' => $root->uuid, 'name' => $root->name],
            // The platform's one country list — the enroll dialog's nationality
            // picker is the invitation's, fed the same way (see inviteBoot()).
            'dial' => $this->countryList(),
            // What the page OFFERS; the endpoint decides (FederationEvents::delete).
            'canDeleteEvents' => (bool) ($access['manager'] ?? false),
            // Test mode (FederationTestMode): offered to managers; the endpoint decides.
            'canTestMode' => (bool) ($access['manager'] ?? false),
            'testEvents' => $this->events->testEventIds($access),
            // Paid featuring on the members' Events page (takeone-featuring.js): offered to managers.
            'canFeature' => (bool) ($access['manager'] ?? false),
            'featureStates' => ($access['manager'] ?? false) ? $this->events->featureStates($access) : [],
            // The rule book per event (takeone-rulebook.js): offered to managers.
            'canRuleBook' => (bool) ($access['manager'] ?? false),
            'ruleBooks' => ($access['manager'] ?? false) ? $this->events->ruleBooks($access) : [],
            'featureFee' => class_exists(\App\Events\Support\EventFeaturing::class) ? \App\Events\Support\EventFeaturing::fee() : null,
            'user' => [
                'name' => $user->full_name ?: $user->name,
                'email' => strtolower((string) $user->email),
                'twoFactor' => $user->hasTwoFactorEnabled(),
            ],
            ...$this->live($access, $user),
            'takeoneBank' => (function () {
                $b = (array) config('federations.takeone_bank');
                $iban = preg_replace('/\s+/', '', (string) ($b['iban'] ?? ''));

                return ['beneficiary' => (string) ($b['beneficiary'] ?? ''), 'bank' => $b['bank'] ?? null,
                    'iban' => $iban !== '' ? trim(chunk_split(strtoupper($iban), 4, ' ')) : null, 'swift' => $b['swift'] ?? null];
            })(),
            'urls' => [
                'state' => \Illuminate\Support\Facades\Route::has('federations.api.state') ? route('federations.api.state') : null,
                'logout' => route('federations.logout'),
                'security' => $this->securityUrl(),
                'orgs' => route('federations.api.orgs'),
                'users' => route('federations.api.users'),
                'region' => route('federations.api.region'),
                'billing' => route('federations.api.billing'),
                'events' => route('federations.api.events'),
                // One event editor (events.federation_hosts): "Plan new event" opens
                // the shared editor with this federation chosen. Null keeps the modal.
                'eventCreate' => config('events.federation_hosts') && config('federations.platform_events') && ($access['manager'] ?? false)
                    ? route('me.events.create', ['host' => 'federation:'.$access['root']->uuid]) : null,
                'testMode' => \Illuminate\Support\Facades\Route::has('federations.api.events.test-mode') ? route('federations.api.events.test-mode') : null,
                'ruleBook' => \Illuminate\Support\Facades\Route::has('federations.api.events.rulebook') ? route('federations.api.events.rulebook') : null,
                // The Analytics tab's ranking coefficient (App\Rankings) — managers only.
                'rankingFactor' => ($access['manager'] ?? false) && config('rankings.enabled') && \Illuminate\Support\Facades\Route::has('federations.api.events.ranking-factor')
                    ? route('federations.api.events.ranking-factor') : null,
                'feature' => \Illuminate\Support\Facades\Route::has('federations.api.events.feature') ? route('federations.api.events.feature') : null,
                'eventDelete' => \Illuminate\Support\Facades\Route::has('federations.api.events.delete') ? route('federations.api.events.delete') : null,
                'uploads' => route('federations.api.uploads'),
                'entryConfirm' => route('federations.api.entries.confirm'),
                'transferReply' => route('federations.api.transfers.reply'),
                'transferDecide' => route('federations.api.transfers.decide'),
                'regsWithdraw' => route('federations.api.regs.withdraw'),
                // Registrations → who withdrew athletes, when and why (managers only).
                'regsWithdrawals' => ($access['manager'] ?? false) ? route('federations.api.regs.withdrawals') : null,
                // Clubs tab → take a whole club off the event (managers only).
                'clubWithdraw' => ($access['manager'] ?? false) && \Illuminate\Support\Facades\Route::has('federations.api.regs.withdraw-club') ? route('federations.api.regs.withdraw-club') : null,
                'regsApprove' => route('federations.api.regs.approve'),
                // An athlete's edit dialog on the Athletes tab (FederationAthleteEditor).
                'regsAthlete' => ['show' => route('federations.api.regs.athlete'), 'update' => route('federations.api.regs.athlete.update'),
                    'frame' => \Illuminate\Support\Facades\Route::has('federations.athlete-edit') ? route('federations.athlete-edit') : null],
                // The Officials tab's editor frame (FederationOfficialEditor).
                'officialFrame' => \Illuminate\Support\Facades\Route::has('federations.official-edit') ? route('federations.official-edit') : null,
                // The event Clubs tab's club editor frame (managers, club management on).
                'clubFrame' => ($access['manager'] ?? false) && config('federations.club_management') && \Illuminate\Support\Facades\Route::has('federations.club-edit') ? route('federations.club-edit') : null,
                'regsEnrol' => config('federations.manual_enrolment') && \Illuminate\Support\Facades\Route::has('federations.api.regs.enrol') ? route('federations.api.regs.enrol') : null,
                'regsImport' => config('federations.manual_enrolment') && \Illuminate\Support\Facades\Route::has('federations.api.regs.import') ? [
                    'template' => route('federations.api.regs.template'),
                    'preview' => route('federations.api.regs.import.preview'),
                    'import' => route('federations.api.regs.import'),
                ] : null,
                'draws' => route('federations.api.draws'),
                // Guarded: a stale route cache must never take the dashboard down.
                'schedule' => \Illuminate\Support\Facades\Route::has('federations.api.schedule') ? route('federations.api.schedule') : null,
                'strings' => route('federations.api.strings'),
                'messages' => route('federations.api.messages'),
                'messagesRead' => route('federations.api.messages.read'),
                // Guarded: a stale route cache must never take the dashboard down.
                'statistics' => \Illuminate\Support\Facades\Route::has('federations.api.statistics') ? route('federations.api.statistics') : null,
                'mapLink' => \Illuminate\Support\Facades\Route::has('federations.api.map-link') ? route('federations.api.map-link') : null,
                'clubs' => \Illuminate\Support\Facades\Route::has('federations.api.clubs') ? route('federations.api.clubs') : null,
                'clubSearch' => \Illuminate\Support\Facades\Route::has('federations.api.clubs.search') ? route('federations.api.clubs.search') : null,
                'clubAdd' => \Illuminate\Support\Facades\Route::has('federations.api.clubs.add') ? route('federations.api.clubs.add') : null,
                'drawBoard' => config('federations.draw_board') && \Illuminate\Support\Facades\Route::has('federations.draw-board') ? route('federations.draw-board') : null,
                // Edit / unlink / decide: only for a manager, and only with the feature switched on.
                'clubManage' => ($access['manager'] ?? false) && config('federations.club_management') && \Illuminate\Support\Facades\Route::has('federations.api.clubs.decide') ? [
                    'detail' => route('federations.api.clubs.detail'),
                    'update' => route('federations.api.clubs.update'),
                    'unlink' => route('federations.api.clubs.unlink'),
                    'decide' => route('federations.api.clubs.decide'),
                    'delete' => \Illuminate\Support\Facades\Route::has('federations.api.clubs.delete') ? route('federations.api.clubs.delete') : null,
                    'mergePreview' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.merge-preview') ? route('federations.api.athletes.merge-preview') : null,
                    'merge' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.merge') ? route('federations.api.athletes.merge') : null,
                    'issueId' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.issue-id') ? route('federations.api.athletes.issue-id') : null,
                    'deleteAthletes' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.delete') ? route('federations.api.athletes.delete') : null,
                    'exportAthletes' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.export') ? route('federations.api.athletes.export') : null,
                    'importAthletes' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.import') ? route('federations.api.athletes.import') : null,
                ] : null,
            ],
        ], $base,
            // Live updates: the platform's own MQTT client, and nothing else of the shell.
            // ⚠️ The client starts only on a page carrying `rt-user` (realtime.js
            // boot()) — without it the script loads and never connects, which is
            // how every live signal here was silently lost.
            '<meta name="rt-user" content="'.(int) $user->id.'">'."\n"
            .app(\Illuminate\Foundation\Vite::class)(['resources/js/realtime-only.js'])->toHtml()));
    }

    /**
     * Everything on a staff dashboard that changes: the events, the desk's
     * lists, the draws, who may be messaged, and the prototype's own storage
     * keys. The page is handed it on load, and asks for it again (state())
     * whenever a FederationPulse says something in its federation changed.
     */
    private function live(array $access, $user): array
    {
        $root = $access['root'];
        $brackets = app(\App\Federations\Services\FederationEventBracket::class)->payload($access);

        return [
            'events' => $this->events->payload($access),
            'fedstore' => $this->desk->store($access, $user),
            'draws' => $this->draws->payload($access),
            // The platform event's real bracket and results, for the Statistics tab.
            'brackets' => $brackets,
            // The Results tab's PDF door per event (FederationResultsPdf) —
            // only where there is a bracket to print and the browser to print it.
            'resultsPdf' => (object) (\App\Federations\Services\FederationResultsPdf::available()
                ? collect(array_keys((array) $brackets))->mapWithKeys(fn ($u) => [$u => route('federations.results-pdf', ['event' => $u])])->all()
                : []),
            // The whole competition report per event (FederationCompetitionReport).
            'competitionReport' => (object) (\App\Federations\Services\FederationCompetitionReport::available()
                ? collect(array_keys((array) $brackets))->mapWithKeys(fn ($u) => [$u => route('federations.competition-report', ['event' => $u])])->all()
                : []),
            // The Analytics tab's door per event — the report's figures as JSON.
            'analytics' => (object) collect(array_keys((array) $brackets))->mapWithKeys(fn ($u) => [$u => route('federations.analytics', ['event' => $u])])->all(),
            // Its cover page (FederationReportCover): what is set, and — for a
            // manager only — where to upload or remove it.
            'resultsCover' => (object) (\App\Federations\Services\FederationResultsPdf::available() && ($access['manager'] ?? false)
                ? collect(array_keys((array) $brackets))->mapWithKeys(function ($u) use ($access) {
                    $svc = app(\App\Federations\Services\FederationReportCover::class);
                    $ev = $svc->event($access, $u);

                    return $ev ? [$u => ['kind' => $svc->kind($ev), 'upload' => route('federations.api.events.report-cover'),
                        'remove' => route('federations.api.events.report-cover.delete')]] : [];
                })->all()
                : []),
            // Each event's own officials, for the event's Officials tab.
            'eventOfficials' => app(\App\Federations\Services\FederationEventOfficials::class)->payload($access, $user),
            // Per event, the platform doors this viewer may use: the participants
            // PDFs (Athletes tab) and the full-screen draw board (Divisions tab).
            ...$this->platformDoors($access, $user),
            'schedules' => app(\App\Federations\Services\FederationSchedule::class)->payload($access),
            'messageClubs' => $this->messages->clubMap($access),
            // The prototype's own storage keys, filled from the server. The
            // bridge writes them before the page reads them.
            'store' => [
                'fed-orgs' => $this->directory->orgs($root),
                'fed-users' => $this->directory->users($root, $user, $access),
                'fed-region' => $this->directory->region($root),
                'takeone_club_regs' => $this->desk->regs($access),
                'takeone_strings' => $this->strings->table($access),
                'fed-messages' => $this->messages->threads($access),
            ] + $this->billing->payloads($root),
        ];
    }

    /** The live half of a staff page, re-read after a FederationPulse — the viewer's own scope only. */
    public function state(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->ensureEnabled();
        $access = $this->staffAccess($request);

        return response()->json(['success' => true] + $this->live($access, $request->user()), 200, ['Cache-Control' => 'no-store, private']);
    }

    /** The club invitation — public: a club opens it from a link. */
    public function publicPage(Request $request, string $file): \Symfony\Component\HttpFoundation\Response
    {
        $this->ensureEnabled();
        abort_unless($this->app->isPublicPage($file), 404);

        // Every invitation is to ONE event: /…/Club Invitation.dc.html?event=<uuid>.
        $invitation = app(\App\Federations\Services\FederationInvitation::class);
        $event = $invitation->event((string) $request->query('event', ''));
        abort_unless($event, 404);

        // A club's personal link proves the club; the session remembers it.
        if ($request->query('club')) {
            $invitation->bindToken($event, (string) $request->query('club'));
        }
        // A signed-in owner of exactly one of the federation's clubs needs no code.
        $invitation->bindOwner($event, $request->user());

        // The invitation in the event page's design, when switched on
        // (federations.invite_v2). It is handed the SAME boot the prototype
        // gets, so both pages run on one store and one set of doors.
        // A super-admin may PREVIEW it before it is switched on for everyone:
        // `?preview=v2` (or `?preview=off`) sets it for their session only.
        if ($request->user()?->hasRole('super-admin') && in_array($request->query('preview'), ['v2', 'off'], true)) {
            $request->session()->put('fedinv.preview', $request->query('preview') === 'v2');
        }
        // ONE address per event (owner, 2026-10-04): with the shared club-entry
        // screens on, the invitation forwards to the event's own page, carrying
        // an invitation pass (EventInvitePass) so a club with no account can read
        // it and register. The club's proof bound above rides along in the
        // session. A 302 adds nothing to the history, so Back is not trapped.
        if (config('events.club_entry') && InvitePageController::enabled($request)
            && ($platform = app(\App\Federations\Services\FederationPlatformEvent::class)->enabled()
                ? app(\App\Federations\Services\FederationPlatformEvent::class)->of($event) : null)
            && ! $platform->is_archived) {
            // A link reader (WhatsApp, Instagram…) keeps no session, so the pass
            // would not survive the forward and it would unfurl the login page.
            // It gets the event's preview tags here instead (ShareCard).
            if (\App\Events\Support\ShareCard::isLinkReader($request)) {
                return response()->view('events::entry.share-preview', [
                    'share' => app(\App\Events\Support\ShareCard::class)->meta($platform),
                    'url' => $request->fullUrl(),
                    'host' => $platform->host()->name,
                ])->header('Cache-Control', 'public, max-age=600');
            }
            \App\Events\Support\EventInvitePass::grant($request, $platform);

            // Leaving /federation on purpose — SealFederation would otherwise
            // re-aim it at the federation's sign-in.
            return redirect()->to(route('me.events.show', ['event' => $platform->uuid]).'#cover')
                ->header(\App\Federations\Middleware\SealFederation::LEAVE, '1');
        }

        if (InvitePageController::enabled($request)) {
            return app(InvitePageController::class)->show($request, $event, $this->inviteBoot($event));
        }

        return $this->html($this->app->page($file, $this->inviteBoot($event)));
    }

    /** What the club invitation boots with — its store, its doors, the country list. Public for FederationClubEntryDesk. */
    public function inviteBoot(\App\Federations\Models\FederationEvent $event): array
    {
        $invitation = app(\App\Federations\Services\FederationInvitation::class);

        return [
            'csrf' => csrf_token(),
            'mode' => 'public',
            'favicon' => \App\Federations\Support\FederationBrand::favicon($event->federation),
            'event' => $event->uuid,
            // Test mode: the page says so, so a real club never mistakes it for the real thing.
            'testMode' => $event->test_mode_at !== null,
            'store' => $invitation->store($event),
            // The platform's one country list (App\Support\Countries), for the phone picker.
            // Same list, with the alpha-3 code the page keys nationality by.
            'dial' => $this->countryList(),
            'urls' => [
                'code' => route('federations.invite.code', $event->uuid),
                'verify' => route('federations.invite.verify', $event->uuid),
                'choose' => \Illuminate\Support\Facades\Route::has('federations.invite.choose') ? route('federations.invite.choose', $event->uuid) : null,
                'lookup' => route('federations.invite.lookup', $event->uuid),
                'clubs' => route('federations.invite.clubs', $event->uuid),
                'checkClub' => route('federations.invite.check-club', $event->uuid),
                'submit' => route('federations.invite.submit', $event->uuid),
                'withdraw' => route('federations.invite.withdraw', $event->uuid),
            ],
        ];
    }

    /** App\Support\Countries as the prototype pages read it: alpha-2, alpha-3, name, dial code. */
    private function countryList(): array
    {
        return collect(\App\Support\Countries::dialCodes())
            ->map(fn ($c) => ['i2' => strtoupper($c['flag']), 'i3' => \App\Support\Countries::iso3($c['flag']), 'name' => $c['name'], 'code' => $c['code']])
            ->filter(fn ($c) => $c['i3'] !== 'UNK')->values()->all();
    }

    /** Code, fonts and images the pages load. Holds no data. */
    public function asset(string $file): Response
    {
        $this->ensureEnabled();
        $path = $this->app->assetPath($file);
        abort_unless($path, 404);

        return response($this->app->asset($path), 200, [
            'Content-Type' => $this->app->contentType($path),
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The prototype draws flags from flagcdn.com/w40/{iso2}.png. We hold the
     * same flags locally (public/images/flags) — Self-Host Everything.
     */
    public function flag(string $code): BinaryFileResponse
    {
        $this->ensureEnabled();
        $code = strtolower($code);
        $path = public_path('images/flags/'.(preg_match('/^[a-z]{2}(-[a-z]{2,3})?$/', $code) ? $code : 'xx').'.svg');
        if (! is_file($path)) {
            $path = public_path('images/flags/xx.svg');
        }

        return response()->file($path, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * What this user runs. Deny by default; a user who runs nothing gets the
     * same 404 as a missing page.
     *
     * @return array{root: Federation, manager: bool, sports: array, role: string}
     */
    private function staffAccess(Request $request): array
    {
        $access = $this->directory->access($request->user());
        abort_unless($access, 404);

        return $access;
    }

    /**
     * Each event's platform doors, keyed by the federation event's id — only
     * for an event already running as a platform event, and only when this
     * viewer is its organiser or an appointed official (the check the PDF
     * endpoint makes). Every endpoint re-authorises; this decides only whether
     * the tabs offer the link at all.
     *  · participantsPdf — the entry list / check-in PDFs (Athletes tab)
     *  · drawPage — the tablet's draw screen at a desk, opened on a division (Divisions tab)
     *  · divisions — the Divisions tab's editor doors; organisers only
     *  · runOrder — the Schedule tab's running order: what it may READ (the
     *    mats, for anybody who may score; the day, for an organiser) and
     *    what it may WRITE (organiser only). Every endpoint re-authorises.
     *
     * @return array{participantsPdf: object, drawPage: object, runOrder: object, divisions: object}
     */
    private function platformDoors(array $access, $user): array
    {
        $pdfOn = \App\Events\Support\ParticipantsPdf::available() && \Illuminate\Support\Facades\Route::has('me.events.participants.pdf');
        $drawOn = \Illuminate\Support\Facades\Route::has('me.events.draw-desk');
        $orderOn = \Illuminate\Support\Facades\Route::has('me.events.order')
            && \Illuminate\Support\Facades\Route::has('me.events.run-order.data');
        $divOn = \Illuminate\Support\Facades\Route::has('me.events.divisions.data');
        if (! $pdfOn && ! $drawOn && ! $orderOn && ! $divOn) {
            return ['participantsPdf' => (object) [], 'drawPage' => (object) [], 'runOrder' => (object) [], 'divisions' => (object) []];
        }

        $q = \App\Federations\Models\FederationEvent::whereIn('federation_id', collect($this->directory->subtree($access['root']))->pluck('id'))
            ->whereNotNull('club_event_id');
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }
        $fes = $q->get(['uuid', 'club_event_id']);
        $events = \App\Events\Models\ClubEvent::whereIn('id', $fes->pluck('club_event_id'))->where('is_archived', false)->get()->keyBy('id');
        $gate = app(\App\Events\Support\EventAccess::class);

        $pdf = $draw = $order = $divs = [];
        foreach ($fes as $fe) {
            $ev = $events[$fe->club_event_id] ?? null;
            if ($ev && $orderOn && $gate->canScore($ev, $user)) {
                $order[$fe->uuid] = $this->runOrderDoors($ev, $gate->canManage($ev, $user));
            }
            if ($ev && $divOn && $gate->canManage($ev, $user)) {
                $k = ['event' => $ev->uuid];
                // `base` is store, and update/destroy/duplicate/candidates/members
                // are base + '/{id}…' — the same arrangement <x-event-divisions> uses.
                $divs[$fe->uuid] = ['event' => $ev->uuid, 'data' => route('me.events.divisions.data', $k),
                    'base' => route('me.events.divisions.store', $k), 'order' => route('me.events.divisions.order', $k),
                    'preview' => route('me.events.divisions.preview', $k),
                    // Divisions from a spreadsheet (DivisionImport): template, preview, import.
                    'importTemplate' => \Illuminate\Support\Facades\Route::has('me.events.divisions.import') ? route('me.events.divisions.import.template', $k) : null,
                    'importPreview' => \Illuminate\Support\Facades\Route::has('me.events.divisions.import') ? route('me.events.divisions.import.preview', $k) : null,
                    'import' => \Illuminate\Support\Facades\Route::has('me.events.divisions.import') ? route('me.events.divisions.import', $k) : null,
                    // Start again: delete every division / auto-place by the rule book (DivisionReset).
                    'deleteAll' => \App\Events\Support\DivisionReset::enabled() && \Illuminate\Support\Facades\Route::has('me.events.divisions.delete-all') ? route('me.events.divisions.delete-all', $k) : null,
                    'autoPlace' => \App\Events\Support\DivisionReset::enabled() && \Illuminate\Support\Facades\Route::has('me.events.divisions.auto-place') ? route('me.events.divisions.auto-place', $k) : null,
                    // Who is in no division, and where each fits — placed through base/{id}/members.
                    'unplaced' => \Illuminate\Support\Facades\Route::has('me.events.divisions.unplaced') ? route('me.events.divisions.unplaced', $k) : null,
                    'draw' => \Illuminate\Support\Facades\Route::has('me.events.draw-desk') ? route('me.events.draw-desk', $k) : null,
                    // Every division's bracket as one PDF (BracketPdf) — the draw desk's own "whole draw".
                    'drawPdf' => \App\Events\Support\BracketPdf::available() && \Illuminate\Support\Facades\Route::has('me.events.bracket.pdf') ? route('me.events.bracket.pdf', $k) : null,
                    // One division per person — the event's switch (OneDivisionRule).
                    'oneDivision' => \Illuminate\Support\Facades\Route::has('me.events.bracket.one-division') ? route('me.events.bracket.one-division', $k) : null,
                    // Who already stands in two divisions (OneDivisionRule::conflicts) — the Overview's "Resolve".
                    'conflicts' => \Illuminate\Support\Facades\Route::has('me.events.bracket.one-division.conflicts') ? route('me.events.bracket.one-division.conflicts', $k) : null,
                    // Club country or nationality on the flags (FlagSource).
                    'flagSource' => \Illuminate\Support\Facades\Route::has('me.events.flag-source') ? route('me.events.flag-source', $k) : null,
                    // Merging small divisions — only while the feature is on, so
                    // the tab never draws a control that would answer 404.
                    'merge' => \App\Events\Support\DivisionMerge::enabled() && \Illuminate\Support\Facades\Route::has('me.events.divisions.merge')
                        ? route('me.events.divisions.merge', $k) : null,
                    // The registration & consent form (RegistrationFormPdf): blank, or ?fill=all
                    // for every participant's form already filled in — the Participants "Download".
                    'regForm' => \App\Events\Support\RegistrationFormPdf::available() && \Illuminate\Support\Facades\Route::has('me.events.registration-form.pdf')
                        ? route('me.events.registration-form.pdf', $k) : null,
                    // The officials' merging handbook PDF (MergeHandbookPdf).
                    'mergeHandbook' => \App\Events\Support\MergeHandbookPdf::available() && \Illuminate\Support\Facades\Route::has('me.events.divisions.merge.handbook')
                        ? route('me.events.divisions.merge.handbook', $k) : null];
            }
            if ($ev && ($gate->canManage($ev, $user) || $gate->canOfficiate($ev, $user))) {
                if ($pdfOn) {
                    $pdf[$fe->uuid] = route('me.events.participants.pdf', ['event' => $ev->uuid]);
                }
                if ($drawOn) {
                    $draw[$fe->uuid] = route('me.events.draw-desk', ['event' => $ev->uuid]);
                }
            }
        }

        return ['participantsPdf' => (object) $pdf, 'drawPage' => (object) $draw, 'runOrder' => (object) $order, 'divisions' => (object) $divs];
    }

    /**
     * The platform running order's own endpoints, for the Schedule tab — the
     * same doors /me/events/{uuid}/order uses. Write doors only for somebody
     * who manages the event.
     *
     * @return array<string, mixed>
     */
    private function runOrderDoors(\App\Events\Models\ClubEvent $ev, bool $manage): array
    {
        $k = ['event' => $ev->uuid];
        $has = fn (string $name) => \Illuminate\Support\Facades\Route::has($name);

        return [
            'event' => $ev->uuid,
            'manage' => $manage,
            'mats' => route('me.events.order', $k),
            'full' => $has('me.events.order.page') ? route('me.events.order.page', $k) : null,
        ] + ($manage ? [
            'day' => route('me.events.run-order.data', $k),
            'reorder' => route('me.events.run-order.reorder', $k),
            'move' => \App\Events\Support\RunSessions::enabled() && $has('me.events.run-order.move') ? route('me.events.run-order.move', $k) : null,
            'boutMove' => route('me.events.order.move', $k),
            'renumber' => $has('me.events.order.renumber') ? route('me.events.order.renumber', $k) : null,
            'auto' => $has('me.events.run-order.auto') ? route('me.events.run-order.auto', $k) : null,
            // When the day starts — the STARTS tile's editor (updateDayStart).
            'start' => $has('me.events.run-order.start') ? route('me.events.run-order.start', $k) : null,
            // A group box's "+ Add classes" (storeRunSlot with `group`) and its
            // method switch (updateRunSession) — the mobile board's own doors.
            'store' => \App\Events\Support\RunSessions::enabled() ? route('me.events.run-order.store', $k) : null,
            'session' => \App\Events\Support\RunSessions::enabled() && $has('me.events.run-order.session') ? route('me.events.run-order.session', $k) : null,
        ] : []);
    }

    private function securityUrl(): string
    {
        return route('federations.security');
    }

    private function html(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
        ]);
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('federations.enabled'), 404);
    }
}
