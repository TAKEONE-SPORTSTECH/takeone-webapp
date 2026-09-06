<?php

namespace App\EventLab\Controllers;

use App\Http\Controllers\Controller;

use App\Events\Contracts\EventType;
use App\Events\EventTypeRegistry;
use App\Events\Support\EntryClaim;
use App\Events\Support\EntryService;
use App\Events\Support\EventAccess;
use App\Events\Support\EventFee;
use App\Events\Support\RosterPeople;
use App\EventLab\Support\PublicEntry;
use App\Models\EventFeeOption;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Models\EventCategory;
use App\Models\EventEntryClaim;
use App\Models\EventChecklistItem;
use App\Models\EventExpense;
use App\Models\EventOfficial;
use App\Models\EventParticipantBan;
use App\Models\EventPublicEntry;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/*
 * COPY — this file is `app/Http/Controllers/PersonalEventController.php`, copied verbatim into the
 * sandbox on 2026-09-05 and then rewritten ONLY where it had to be:
 *
 *   - the namespace, and an explicit import of the base Controller it used to
 *     inherit by being a neighbour of it;
 *   - `view('personal.…')` / `view('entry.…')` now name the sandbox's own copies
 *     of those templates (`eventlab::…`);
 *   - `route('me.events.…')` / `route('events.public…')` now name the sandbox's
 *     own routes, so a copied screen links to other copied screens.
 *
 * Nothing else was touched, so `diff` against the original still reads clean.
 * The original is untouched and still serves every real event; this copy is
 * reachable only under /testcode and only for a SANDBOX twin.
 */
class PersonalEventController extends Controller
{
    use \App\Traits\StoresBase64Images;

    public function __construct(private EventTypeRegistry $registry) {}

    /**
     * The package that owns this event. All type-specific behaviour — schema,
     * enrolment gate, lifecycle, engine, results, financials, screens — is
     * asked of it. This controller must never branch on sport or event_type
     * itself (CLAUDE.md → "Events Are Self-Contained Packages").
     */
    private function typeFor(ClubEvent $event): EventType
    {
        return $this->registry->for($event);
    }

    /**
     * The package's own screen for a slot, falling back to the shared screen.
     *
     * A declared view is only used when it actually exists, so a package can
     * take over one screen (or one device) at a time during migration and can
     * never point the app at a view it hasn't shipped.
     */
    private function packageView(EventType $type, string $slot, string $device, string $fallback): string
    {
        $declared = $type->views()[$slot][$device] ?? null;

        return ($declared && view()->exists($declared)) ? $declared : $fallback;
    }

    /**
     * Clubs this member may create an event for: the ones they belong to, PLUS
     * the ones they own or administer.
     *
     * A club owner is not automatically a MEMBER of their own club — owning it
     * and training in it are different things — so a memberships-only list left
     * owners unable to create an event for the club they run.
     *
     * @return array<int, array{id: int, name: string, currency: string}>
     */
    private function clubsICanCreateFor(User $me, ?ClubEvent $event = null): array
    {
        // A super-admin runs the platform, so every club is theirs to hold an
        // event for. Without this they saw only the clubs they happen to own or
        // train at — a club somebody else owns was simply missing from the
        // picker, with nothing on screen to say why. `assertMayBroadcast()`
        // already treats them this way; this is the same rule, one screen
        // earlier.
        $query = \App\Clubs\Models\Tenant::query();

        if (! $me->isSuperAdmin()) {
            $ids = $me->memberClubs()->pluck('tenants.id')
                ->merge(app(EntryService::class)->administeredClubIds($me))
                ->unique();

            // On an EDIT, the event's own host is always in the list. It is
            // already the answer on file, and dropping it would show the
            // organiser a picker that cannot say where their event actually is.
            if ($event) {
                $ids = $ids->push($event->tenant_id)->unique();
            }

            $query->whereIn('id', $ids);
        }

        return $query
            ->orderBy('club_name')
            ->get(['id', 'club_name', 'currency'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->club_name, 'currency' => $c->currency ?: 'BHD'])
            ->values()->all();
    }

    /**
     * A scope wider than the host club addresses people who never opted into
     * this club — potentially a whole country. Only the club's owner or an admin
     * of it (or a super-admin) may do that; an ordinary member is limited to
     * `internal`, so no member can turn event creation into a mass-mail button.
     */
    private function assertMayBroadcast(User $me, int $tenantId, ?string $scope): void
    {
        if (! in_array($scope, config('event_notifications.broadcast_scopes', []), true)) {
            return;
        }

        $owns = \App\Clubs\Models\Tenant::whereKey($tenantId)->where('owner_user_id', $me->id)->exists();

        abort_unless($owns || $me->isClubAdmin($tenantId) || $me->isSuperAdmin(), 403);
    }

    /** Resolve the package for an event that doesn't exist yet, from its input. */
    private function typeForInput(array $data, ?ClubEvent $event = null): EventType
    {
        return $this->registry->for(new ClubEvent([
            'event_type' => $data['event_type'] ?? $event?->event_type,
            'sport' => $data['sport'] ?? $event?->sport,
        ]));
    }

    /* ---- Schema (config-driven: types + sports) ---- */
    private function types(): array
    {
        return config('event_schema.types', []);
    }

    private function sports(): array
    {
        return config('event_schema.sports', []);
    }

    private function scopes(): array
    {
        return config('event_schema.scopes', []);
    }

    private function scopeLabel(string $k): string
    {
        return $this->scopes()[$k]['label'] ?? 'This club only';
    }

    private function typeLabel(string $k): string
    {
        return $this->types()[$k]['label'] ?? 'Event';
    }

    private function typeIcon(string $k): string
    {
        return $this->types()[$k]['icon'] ?? 'bi-calendar-event';
    }

    private function typeColor(string $k): string
    {
        return $this->types()[$k]['color'] ?? '#7c3aed';
    }

    private function typeSections(string $k): array
    {
        return $this->types()[$k]['sections'] ?? [];
    }

    /* ===================== Pages ===================== */

    public function index(Request $request): View
    {
        $me = Auth::user();
        $clubIds = $me->memberClubs()->pluck('tenants.id');
        $myCountries = $me->memberClubs()->pluck('tenants.country')->filter()->unique()->values();
        $today = now()->startOfDay()->toDateString();

        // Visible = own-club events (any scope) + other clubs' events whose scope
        // reaches this member (open platform-wide, or country-matched).
        $base = fn () => ClubEvent::query()
            ->where('is_archived', false)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($clubIds, $myCountries) {
                $q->whereIn('tenant_id', $clubIds)
                    ->orWhereIn('scope', ['inter_club', 'worldwide'])
                    ->orWhere(fn ($w) => $w->whereIn('scope', ['nationwide', 'regional'])
                        ->whereHas('tenant', fn ($t) => $t->whereIn('country', $myCountries)));
            })
            ->withCount('participantRegistrations')
            ->with('tenant:id,club_name,country');

        // Upcoming / ongoing — end_date (or date when no end_date) is today or later. Soonest first.
        $upcoming = $base()->where(function ($q) use ($today) {
            $q->where(fn ($w) => $w->whereNotNull('end_date')->whereDate('end_date', '>=', $today))
                ->orWhere(fn ($w) => $w->whereNull('end_date')->whereDate('date', '>=', $today));
        })->orderBy('date')->get();

        // Finished — most recent first, capped.
        $past = $base()->where(function ($q) use ($today) {
            $q->where(fn ($w) => $w->whereNotNull('end_date')->whereDate('end_date', '<', $today))
                ->orWhere(fn ($w) => $w->whereNull('end_date')->whereDate('date', '<', $today));
        })->orderByDesc('date')->limit(40)->get();

        $all = $upcoming->concat($past);
        $myReg = $this->myRegistrations($me->id, $all->pluck('id'));
        $demo = $all->map(fn ($e) => $this->eventView($e, $me->id, $myReg))->values()->all();

/*
         * The MOBILE view, at every width and on every device.
         *
         * There is no desktop event surface on this platform: an event is read
         * in a hall, on a phone, one-handed. `PublicEventController::show()`
         * already says this about the public page — a second layout to keep in
         * step is exactly where the two drifted apart last time — and it is
         * true of the organiser's screens for the same reason.
         *
         * Forced here rather than by deleting the branches, so this file still
         * diffs cleanly against the original it was copied from.
         */
        $isMobile = true;

        return view($isMobile ? 'eventlab::personal.mobile.events' : 'eventlab::personal.desktop.events', compact('demo'));
    }

    public function show(ClubEvent $event, Request $request): View
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $type = $this->typeFor($event);

        $event->loadCount(['participantRegistrations']);
        $myReg = $this->myRegistrations($me->id, collect([$event->id]));
        $e = $this->eventView($event, $me->id, $myReg, full: true);
        $e['cancelled'] = $event->status === 'cancelled';
        // Just the number, for the Officials tile. Counted here rather than in
        // eventView() because that runs once per row on the events LIST, and one
        // more query per card there buys nothing.
        $e['officials_count'] = $event->officials()->count();
        // How many bouts were filmed, for the Gallery door. A count, not the
        // gallery — the event page should not pay for a page it only links to.
        $e['clips_count'] = app(\App\Media\VideoLibrary::class)->eventClipCount($event);

        $canManage = $this->canManage($event, $me);
        $banned = $this->isBanned($event, $me->id);
        $gate = $type->enrolmentGate($event, $me, $myReg->get($event->id));

$isMobile = true;   // events are mobile-only — see show()
        $device = $isMobile ? 'mobile' : 'desktop';

        $view = $this->packageView($type, 'show', $device, $isMobile ? 'eventlab::personal.mobile.event-show' : 'eventlab::personal.desktop.event-show');

        $entries = app(EntryService::class);
        $myClubs = $entries->representableClubs($me);
        $entriesState = $entries->entriesState($event);

        return view($view, [
            'e' => $e,
            'canManage' => $canManage,
            'banned' => $banned,
            'canCompete' => $banned ? false : $gate->allowed,
            'eligReason' => $banned ? __('events.banned_by_organiser') : $gate->message,
            'actions' => $canManage ? $type->availableActions($event) : [],
            // The one address to hand out for this event — the public page when
            // the organiser published one, the member page otherwise. The QR,
            // the printable poster and the share button all take THIS, so a
            // published event can never be shared as a login form.
            'shareUrl' => \App\Http\Controllers\QrController::eventUrl($event),
            'finance' => $canManage ? $type->finance($event) : null,
            // The verification desk only exists for the people who staff it.
            'canOfficiate' => $canOfficiate = app(EventAccess::class)->canOfficiate($event, $me),
            // The run-day checklist is run-day work: it goes to the people who
            // do it and nobody else. A competitor has no use for "mats laid"
            // and no business reading the organiser's preparation notes.
            'checklist' => $canOfficiate
                ? $event->checklistItems()->with('checker:id,full_name,name')->get()
                    ->map(fn ($i) => $this->checklistItemView($i))->all()
                : [],
            // How to pay, for the join sheet.
            'payment' => $this->paymentInstructions($event),
            // Entering a squad: offered only to someone who holds the grant for
            // a club, and only while the event is still taking entries.
            // Only while entries are actually open — a card that opens a sheet
            // where every row is refused is a door onto a wall.
            'canEnterAthletes' => $entriesState['open'] && $entries->administeredClubIds($me) !== [],
            // Whether anyone may still take a place, and the reason when not.
            'entriesOpen' => $entriesState['open'],
            'entriesNote' => $entriesState['note'],
            // Which club the viewer competes for. Only asked at events that
            // reach past one club — inside a club's own event there is nothing
            // to represent, and the question would be noise.
            'representing' => [
                'ask' => $myClubs !== [] && in_array($event->scope ?? 'internal', ['inter_club', 'nationwide', 'regional', 'worldwide'], true),
                'clubs' => $myClubs,
                'claim' => (int) ($myReg->get($event->id)?->representedTenantId() ?? 0),
                'disowned' => (bool) $myReg->get($event->id)?->isDisowned(),
                // Pre-selected when they have not chosen yet: where they last
                // practised this event's sport. See EntryService.
                'default' => $myClubs !== [] ? $entries->defaultRepresentingClub($me, $event) : null,
            ],
            // Attached documents — the viewer already passed assertVisible()
            // above, which is exactly the rule the download route re-checks.
            'documents' => $event->documents()
                ->get()
                ->map(fn ($d) => app(\App\Http\Controllers\EventDocumentController::class)->present($d, $event))
                ->all(),
        ] + $type->viewData($event, $me));
    }

    /**
     * The organiser's console — everything you DO to an event, off the page
     * everybody else reads.
     *
     * The public page had grown a finance button, a ⋮ menu, a winners editor,
     * an uploader and the run-day checklist threaded between the cover, the
     * agenda and the join button. Two audiences, one screen. This is the other
     * audience's screen; `show` keeps only what a visitor came for.
     *
     * Reached by the organiser and by anyone appointed to officiate — and each
     * section is gated on its own, because a weigh-in official has a job here
     * (the checklist, their verification desk) but no business in the money or
     * the danger zone. Every endpoint those sections call re-checks the same
     * rule server-side; what this screen renders is only what it offers.
     */
    public function manage(ClubEvent $event, Request $request): View
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $access = app(EventAccess::class);
        $canManage = $this->canManage($event, $me);
        $canOfficiate = $access->canOfficiate($event, $me);

        // Nobody without a job here gets so much as the shape of the page.
        abort_unless($canManage || $canOfficiate, 403);

        $type = $this->typeFor($event);

        $event->loadCount(['participantRegistrations']);
        $e = $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: true);
        $e['cancelled'] = $event->status === 'cancelled';

$isMobile = true;   // events are mobile-only — see show()

        // A package may bring its own console. A sparring session's run-day
        // screen has nothing in common with a championship's — no draw, no
        // weigh-in, no entries to verify — so it supplies its own rather than
        // hiding half of the shared one. Falls back to the shared console for
        // every type that declares nothing, exactly like `show` and `bracket`.
        return view($this->packageView($type, 'manage', $isMobile ? 'mobile' : 'desktop',
            $isMobile ? 'eventlab::personal.mobile.event-manage' : 'eventlab::personal.desktop.event-manage'), [
            'e' => $e,
            'canManage' => $canManage,
            'canOfficiate' => $canOfficiate,
            'canWeigh' => $access->canVerifyWeighIn($event, $me),
            'canPay' => $access->canVerifyPayments($event, $me),

            // The scoring table for this event's mats, or null when the sport
            // has none or this person may not score.
            //
            // The console had no door. It could be reached by pairing a tablet
            // to it, or by an Open Mat screen that built the URL itself — and
            // neither of those is available to an organiser sitting at a laptop
            // running a tournament, who is exactly the person who needs it. The
            // address comes from App\Scoreboard\Fleet so this view never learns
            // a sport's name, and the console re-checks canScore on the way in
            // regardless: this decides what to OFFER, not what is allowed.
            'scoringUrl' => $access->canScore($event, $me)
                ? \App\Scoreboard\Fleet::consoleUrl($event)
                : null,

            // Whether this event has a page anybody may open, and the address of
            // it. Organiser only — publishing is not an official's decision.
            'isPublic' => $canManage && ($event->entry_mode ?? 'members') === 'public',
            'publicUrl' => $canManage ? route('testcode.e', $event->uuid) : null,
            // The picture the public cover opens onto — images[0]. Organiser
            // only: changing the event's face is not an official's decision.
            'coverPhoto' => $canManage ? file_url(($event->images[0] ?? null)) : null,
            'autoAccept' => (bool) $event->public_entry_auto_accept,
            // When the draw becomes readable, and the day `start_day` would open
            // it on. Organiser only: withholding a draw is not an official's
            // decision, though an official always reads it (EventAccess::
            // drawVisible).
            'drawReveal' => $event->draw_reveal ?: \App\Models\ClubEvent::DRAW_ALWAYS,
            'drawRevealDate' => $event->date?->translatedFormat('D j M'),
            // Strangers who followed the public link and are waiting to be let
            // in. Rendered by the organiser's console only — accepting one is
            // the gate, and it is not an official's to open.
            'publicEntries' => $canManage ? app(PublicEntry::class)->pending($event, $me) : [],
            // The clubs standing behind this competition — invited, or written
            // down by the organiser. Everyone reading the console sees them;
            // only a manager may change them, which the panel and every
            // endpoint behind it check independently.
            'eventClubs' => \App\EventLab\Models\SandboxEventClub::with('tenant:id,club_name,logo,country,owner_user_id')
                ->where('event_id', $event->id)
                ->orderByRaw("case state when 'accepted' then 0 when 'listed' then 1 when 'invited' then 2 else 3 end")
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => $c->present())
                ->all(),
            'actions' => $canManage ? $type->availableActions($event) : [],
            // Money is the organiser's alone — an official never receives it.
            'finance' => $canManage ? $type->finance($event) : null,
            'checklist' => $event->checklistItems()->with('checker:id,full_name,name')->get()
                ->map(fn ($i) => $this->checklistItemView($i))->all(),
            'documents' => $event->documents()
                ->get()
                ->map(fn ($d) => app(\App\Http\Controllers\EventDocumentController::class)->present($d, $event))
                ->all(),
            // Hall screens, if this type drives any. The type answers; a type
            // with no wall boards returns null and the section is simply absent.
            'screens' => $canManage ? $type->hallScreens($event) : null,
            // The phones filming the mats. Sport-neutral and type-neutral, so
            // this is asked directly rather than of the package: pointing a
            // lens at a mat needs nothing from the sport, and an event that
            // drives no wall screens can still be filmed.
            'cameras' => $canManage ? \App\Events\Support\Cameras\CameraFleet::console($event) : null,
            // Which screen roles this event's package can actually serve. The
            // panel offers only these — a slot it cannot serve ends with a
            // screen in a hall showing an error and no way back.
            'screenSurfaces' => app(\App\Events\Support\HallScreenRouter::class)->surfaces($event),
            // The address to open ON a screen so it joins THIS event's fleet.
            'screenNewUrl' => app(\App\Events\Support\HallScreenRouter::class)->newScreenUrl($event),
            // What the screens PLAY: the introduction, the celebration and a
            // noise per scoring action. Only for an event whose type drives
            // screens at all — an event with no wall boards has nothing to play
            // it on, and the section is absent rather than empty.
            'screenAudio' => $canManage && $type->hallScreens($event)
                ? collect(\App\Events\Support\ScreenMedia::forEvent($event))->map(fn ($m) => [
                    'name' => $m->original_name,
                    'bytes' => $m->bytes,
                    'uploaded_at' => $m->updated_at?->toIso8601String(),
                ])->all()
                : null,
            // One audition URL per slot that actually has a file, so the panel
            // never offers Play for silence. Organiser-authorised, not the
            // token route the screens use.
            'screenAudioUrls' => $canManage && $type->hallScreens($event)
                ? collect(\App\Events\Support\ScreenMedia::forEvent($event))
                    ->mapWithKeys(fn ($m, $slot) => [$slot => route('testcode.me.events.screen-audio.show', [$event->uuid, $slot])])
                    ->all()
                : null,
            // Counts for the section cards, so each one says what is waiting
            // inside it before it is opened.
            'counts' => [
                'entrants' => (int) ($event->participant_registrations_count ?? 0),
                'officials' => $event->officials()->count(),
                'documents' => $event->documents()->count(),
                'outstanding' => $event->outstandingChecks(),
            ],
        ] + $type->viewData($event, $me));
    }

    /** True if the member is barred from this event (event block OR club-wide blacklist). */
    private function isBanned(ClubEvent $event, int $userId): bool
    {
        return EventParticipantBan::where('user_id', $userId)
            ->where(function ($q) use ($event) {
                $q->where(fn ($w) => $w->where('scope', 'event')->where('event_id', $event->id))
                    ->orWhere(fn ($w) => $w->where('scope', 'club')->where('tenant_id', $event->tenant_id));
            })->exists();
    }

    public function bracket(Request $request, ClubEvent $event): View
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $type = $this->typeFor($event);

        // Let the package bring its own derived state up to date (for a
        // championship: refresh the provisional draw, or lock the final one).
        $type->onEntrantsChanged($event);

        $categories = $type->runData($event, $me)['categories'] ?? [];

        /*
         * A draw the organiser has not let out yet.
         *
         * The board fetches itself and is veiled by the JSON door, but this page
         * ALSO prints the same bouts underneath in readable form — bout by bout,
         * with the entrant list beside them. Withholding one and not the other
         * would be withholding nothing, so the categories are emptied here and
         * both halves of the page go quiet together.
         *
         * The page still renders: a member who taps "Draw" is told when it
         * opens, which is the answer they came for.
         */
        $drawHidden = app(EventAccess::class)->drawVisible($event, $me)
            ? null
            : $this->drawHiddenMessage($event);

        if ($drawHidden !== null) {
            $categories = [];
        }

        /*
         * Which division to open on.
         *
         * The board used to open on the FIRST category whatever the link said, so
         * "View draw" from a bout in any other division showed the wrong bracket
         * and the wrong match. A caller can now name either the category or the
         * bout it came from.
         *
         * Resolved against THIS event's own categories, so an id belonging to
         * another event (or an invented one) falls back to the first rather than
         * naming a division the viewer never asked for.
         */
        $wantedCategory = (int) $request->query('category', 0);

        if ($wantedCategory === 0 && $request->filled('bout')) {
            $wantedCategory = (int) EventMatch::where('event_id', $event->id)
                ->where('match_no', (int) $request->query('bout'))
                ->value('category_id');
        }

        $initialCategory = collect($categories)->firstWhere('id', $wantedCategory)['key']
            ?? (collect($categories)->first()['key'] ?? '');

        $e = $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: true);
        $canManage = $this->canManage($event, $me);

$isMobile = true;   // events are mobile-only — see show()
        $device = $isMobile ? 'mobile' : 'desktop';

        return view(
            $this->packageView($type, 'bracket', $device, 'eventlab::personal.'.$device.'.event-bracket'),
            [
                'e' => $e,
                'categories' => $categories,
                // The division to open on.
                'initialCategory' => $initialCategory,
                // Set to the sentence that says when the draw opens, or null
                // when there is nothing being withheld.
                'drawHidden' => $drawHidden,
                'canManage' => $canManage,
                'canArrange' => $this->canArrangeDraw($event, $type, $canManage),
                // The viewer's own entries, so the board can mark their bouts.
                'myCompetitorIds' => ClubEventRegistration::where('event_id', $event->id)
                    ->where('user_id', $me->id)->pluck('id')->all(),
                'actions' => $canManage ? $type->availableActions($event) : [],
            ] + $type->viewData($event, $me)
        );
    }

    /* ---------------- The gallery ---------------- */

    /**
     * Everything filmed at this event, grouped by division.
     *
     * A competition is read by division, not by bout number — nobody looks for
     * bout 34, they look for the -61 kg final. So the shelves here are the same
     * `event_categories` the draw uses, in the organiser's own order, and a
     * division with nothing filmed is left out entirely.
     *
     * Shaped exactly like bracket(): same guard, same package-view resolution,
     * so a sport that wants its own gallery screen can take it over later
     * without a line changing here.
     */
    public function gallery(Request $request, ClubEvent $event): View
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $type = $this->typeFor($event);

        $e = $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: true);
        $canManage = $this->canManage($event, $me);

$isMobile = true;   // events are mobile-only — see show()
        $device = $isMobile ? 'mobile' : 'desktop';

        $library = app(\App\Media\VideoLibrary::class);
        $divisions = $library->forEvent($event);

        return view(
            $this->packageView($type, 'gallery', $device, 'eventlab::personal.'.$device.'.event-gallery'),
            [
                'e' => $e,
                'divisions' => $divisions,
                // A tab per stage as well as per division — a viewer looks for
                // "the finals" as readily as for a weight class. Derived from the
                // filmed bouts, so a stage that was not fought offers no tab.
                'stages' => $library->stagesIn($divisions),
                'clipCount' => (int) collect($divisions)->sum('count'),
                'canManage' => $canManage,
            ] + $type->viewData($event, $me)
        );
    }

    /** The same shelves as JSON, for a live refresh after a bout is filmed. */
    public function galleryData(ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $library = app(\App\Media\VideoLibrary::class);
        $divisions = $library->forEvent($event);

        return response()->json([
            'divisions' => $divisions,
            'stages' => $library->stagesIn($divisions),
            'count' => (int) collect($divisions)->sum('count'),
        ]);
    }

    /* ---------------- Officials' console ---------------- */

    /*
     * ===== The verification desk is GONE =====
     *
     * `verify()` rendered a screen of its own — the roster with the weigh-in
     * and payment gates on it. Everything it did now happens on the ENTRY LIST:
     * tapping a person there opens the SAME sheet (personal/partials/event-people
     * included in `sheetOnly` mode), which is where an official is already
     * standing when they need it. Two doors to one job, and the second one made
     * the console a maze — removed 2026-09-03 at the user's request.
     *
     * What stayed: that partial, and the endpoints the sheet writes through —
     * verifyWeighIn(), verifyPayment() and verifyProof() below. Nothing about
     * WHO may verify changed, and people() now lets an official in explicitly
     * so deleting this screen could not strand one.
     */

    /** Record an official weight. Signing it is the point — hence weighed_in_by. */
    /**
     * A face for a competitor, uploaded by whoever is running the event.
     *
     * The screens already show a picture when the athlete has one on their
     * profile AND has made it public — but a competition is full of people who
     * were entered off a federation list or at a weigh-in desk and have no
     * account at all, and those bouts were being introduced with a silhouette.
     * This is the organiser's own photo, taken for this event, and it lives on
     * the ENTRY rather than on the person: it is a fact about this competition,
     * not a change to somebody's profile, and it goes away with the entry.
     *
     * Bytes are validated by the shared trait, which sniffs the real MIME and
     * assigns the extension itself. A client-supplied extension here would be an
     * upload endpoint that stores whatever it is told to.
     */
    public function competitorPhoto(Request $request, ClubEvent $event, ClubEventRegistration $registration): JsonResponse
    {
        $me = Auth::user();
        abort_unless(app(EventAccess::class)->canManage($event, $me), 403);
        // Scoped, always: an entry id from another event must not be writable
        // through this event's URL.
        abort_unless($registration->event_id === $event->id, 404);

        $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image/'],
        ]);

        $previous = $registration->photo;

        // Folder built by US from the event's public id, never from the request.
        $path = $this->storeBase64Image(
            $request->input('image'),
            'events/'.$event->uuid.'/competitors',
            'c'.$registration->id.'-'.Str::random(16),
        );

        if ($path === null) {
            return response()->json([
                'success' => false,
                'message' => __('personal.event_photo_rejected'),
            ], 422);
        }

        $registration->update(['photo' => $path]);

        // Only once the new one is safely stored, and only if it moved.
        //
        // Through EntryPhoto, because `photo` is not always a file this entry
        // owns: an entry settled through the public door points at the
        // athlete's own profile picture, and deleting that here took a member's
        // face off the disk while their profile carried on pointing at it.
        if ($previous && $previous !== $path) {
            \App\Events\Support\EntryPhoto::discard($previous);
        }

        return response()->json([
            'success' => true,
            'message' => __('personal.event_photo_saved'),
            'photo' => file_url($path),
            'registration' => $registration->id,
        ]);
    }

    /** Take the event photo off an entry, falling back to whatever their profile allows. */
    public function competitorPhotoDestroy(Request $request, ClubEvent $event, ClubEventRegistration $registration): JsonResponse
    {
        abort_unless(app(EventAccess::class)->canManage($event, Auth::user()), 403);
        abort_unless($registration->event_id === $event->id, 404);

        $path = $registration->photo;

        // File first, then the row's reference to it — but only if it IS this
        // entry's file. See EntryPhoto: a publicly-entered athlete's `photo` is
        // their profile picture, and removing the event photo must not remove
        // their face from the platform.
        if ($path) {
            \App\Events\Support\EntryPhoto::discard($path);
        }

        $registration->update(['photo' => null]);

        return response()->json([
            'success' => true,
            'message' => __('personal.event_photo_removed'),
            'registration' => $registration->id,
        ]);
    }

    public function verifyWeighIn(Request $request, ClubEvent $event, ClubEventRegistration $registration): JsonResponse
    {
        $me = Auth::user();
        abort_unless(app(EventAccess::class)->canVerifyWeighIn($event, $me), 403);
        abort_unless($registration->event_id === $event->id, 404);

        // The belt is optional and the weight is not, because a weigh-in is
        // valid without one: an official may simply not be recording rank at
        // this event. Both are free text — belt ladders differ per sport and per
        // federation, and this desk is not the place to enforce a vocabulary.
        $data = $request->validate([
            'weight' => ['required', 'numeric', 'min:10', 'max:250'],
            'belt_colour' => ['nullable', 'string', 'max:40'],
            'belt_grade' => ['nullable', 'string', 'max:40'],
        ]);

        // Absent keys must not wipe a belt recorded a moment ago, so only the
        // fields that were actually sent are written.
        $registration->update(array_filter([
            'weight' => $data['weight'],
            'belt_colour' => $data['belt_colour'] ?? null,
            'belt_grade' => $data['belt_grade'] ?? null,
            'weighed_in_at' => now(),
            'weighed_in_by' => $me->id,
        ], fn ($v) => $v !== null));

        // An athlete their club entered before anyone had a weight for them is
        // unclassified until exactly this moment. The scale is what places them,
        // so the package is asked where this weight puts them — and the draw is
        // told the entrant set moved.
        $registration->refresh();
        $division = $this->typeFor($event)->classifyEntry($event, $registration);

        if ($division) {
            $this->typeFor($event)->onEntrantsChanged($event, $division);
        }

        // Weighed, and it places them in nothing this event is running — a
        // cadet at a seniors-only championship, say. Deferring the weight is
        // what let them in; the desk has to be TOLD when that turns out badly,
        // because a silent "signed off" would leave an entrant nobody can draw.
        $unplaced = ! $division && ! $registration->fresh()->category_id;

        $belt = app(\App\Sports\Combat\BeltRank::class)->for($registration->user, $registration->fresh());

        return response()->json([
            'success' => true,
            'message' => $division
                ? __('personal.event_verify_weight_placed', ['division' => $division->name])
                : ($unplaced
                    ? __('personal.event_verify_weight_unplaced')
                    : __('personal.event_verify_weight_recorded')),
            'weight' => (float) $data['weight'],
            // The division they were just placed in, when the weigh-in is what
            // decided it — so the desk sees the result of what it did.
            'division' => $division?->name,
            'unplaced' => $unplaced,
            // Echoed back so the desk shows what the arena screen will announce
            // — including a rank that came from the profile rather than this
            // form, which is the official's cue that they need not type it.
            'belt' => $belt,
        ]);
    }

    /**
     * Approve or reject a payment after looking at the proof.
     *
     * Rejecting clears the verifier as well as the flag: a payment that was
     * approved by mistake must fall all the way back to unverified, or the
     * entry keeps its place in the final draw on a signature that was withdrawn.
     */
    public function verifyPayment(Request $request, ClubEvent $event, ClubEventRegistration $registration): JsonResponse
    {
        $me = Auth::user();
        abort_unless(app(EventAccess::class)->canVerifyPayments($event, $me), 403);
        abort_unless($registration->event_id === $event->id, 404);

        $approve = (bool) $request->validate(['approve' => ['required', 'boolean']])['approve'];

        $registration->update([
            'paid' => $approve,
            'paid_at' => $approve ? now() : null,
            'paid_by' => $approve ? $me->id : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $approve
                ? __('personal.event_verify_payment_approved')
                : __('personal.event_verify_payment_rejected'),
        ]);
    }

    /** Stream one entry's proof image to an official (private disk). */
    public function verifyProof(ClubEvent $event, ClubEventRegistration $registration)
    {
        $me = Auth::user();
        abort_unless(app(EventAccess::class)->canVerifyPayments($event, $me), 403);
        abort_unless($registration->event_id === $event->id, 404);
        abort_unless($registration->payment_proof && Storage::disk('local')->exists($registration->payment_proof), 404);

        return Storage::disk('local')->response($registration->payment_proof);
    }

    /**
     * How to pay this club, for the join sheet.
     *
     * Returns the club's primary bank account when it has one. Many clubs take
     * cash at the door and have never filled this in, so `bank` is null far more
     * often than not — the sheet falls back to "pay at the club" rather than
     * showing an empty transfer form.
     */
    private function paymentInstructions(ClubEvent $event): array
    {
        $bank = $event->tenant?->bankAccounts()
            ->orderByDesc('is_primary')->orderBy('id')->first();

        return [
            'club' => $event->tenant?->club_name,
            'bank' => $bank ? array_filter([
                'bank_name' => $bank->bank_name,
                'account_name' => $bank->account_name,
                'account_number' => $bank->account_number,
                'iban' => $bank->iban,
                'benefitpay' => $bank->benefitpay_account,
            ]) : null,
        ];
    }

    /**
     * Who's joined — the competitors, and the clubs behind them. Reading only.
     *
     * This screen answers one question for anyone entered in the event: who else
     * is here. It carries no controls of any kind, for anyone — an organiser
     * opening it sees exactly what a first-time competitor sees. That is the
     * point: it briefly did both jobs, and a screen that changes what it IS
     * depending on who opened it is a screen nobody can describe to anyone else.
     * The officials' gates moved to verify().
     *
     * Spectators are not listed. Ticket-holders did not enter a competition and
     * have no place on a page about who is competing.
     *
     * Every row links somewhere public and already reachable: an athlete to
     * their public profile (/people/{uuid}), a club to its club page. Nothing on
     * this screen discloses more than those destinations already do.
     */
    public function people(ClubEvent $event, Request $request): View
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $event->loadCount(['participantRegistrations']);
        $myReg = $this->myRegistrations($me->id, collect([$event->id]));
        // The whole roster: this page exists to list everyone, and its search
        // can only narrow names that are actually on the page.
        $e = $this->eventView($event, $me->id, $myReg, full: true, wholeRoster: true);
        $e['cancelled'] = $event->status === 'cancelled';

        // Two tabs, one roster: the competitors, and the clubs they came from.
        // Nothing actionable is assembled here — no registration ids, no
        // weights, no payment state, no moderation. An official who needs those
        // goes to verify(), which is a different screen with a different guard.
        $canManage = app(EventAccess::class)->canManage($event, $me);

        /*
         * The whole list, for whoever runs the event.
         *
         * A sport's own roster may hold names back — the shared tournament one
         * drops anybody with no weight on file, because the screens it feeds
         * cannot announce an unclassified competitor. That is right for a
         * reading surface and wrong for this one: an entrant with no weight is
         * PRECISELY who an organiser is looking for when they open the list, and
         * a name that is not on the page cannot be struck off it.
         *
         * So the missing entries are appended, and only for the organiser: what
         * everybody else reads here is unchanged.
         */
        if ($canManage) {
            $e['participants'] = $this->appendUnlistedEntrants($event, $e['participants']);
        }

        $people = app(RosterPeople::class)->build($e['participants'], $event);

        /*
         * ===== The two readiness badges on each card =====
         *
         * Asked for on 2026-09-04: an organiser reading down the list must see,
         * without tapping anything, whether an entry is paid for and whether a
         * weight has been taken.
         *
         * RosterPeople is a READING surface and deliberately drops payment and
         * weight, so the flags are put back here — positionally, because
         * build() maps the rows it was given one-for-one and in order.
         *
         * The scoping decision is NOT retaken: a row the event view already
         * withheld status from (`show_status` false — somebody else's child's
         * fee and body weight) gets no badges at all. A missing badge would
         * itself disclose something, so the pair is absent rather than grey.
         */
        $people['participants'] = $this->attachReadiness($event, $people['participants'], $e['participants']);

        /*
         * ===== The club each athlete is down as, on THIS event =====
         *
         * `RosterPeople` reads `representing_tenant_id`, which is the only club
         * the platform knows how to name — so an athlete put under a club that
         * was written down for this competition showed no club at all. The
         * squad picker said one thing and the roster said nothing.
         *
         * Overlaid rather than pushed into RosterPeople: which clubs stand
         * behind an event is this experiment's idea, and the shared roster
         * should not have to know about it. Positional, exactly like
         * attachReadiness above — build() maps its rows one-for-one and in
         * order.
         */
        $people['participants'] = $this->attachEventClubs($event, $people['participants'], $e['participants']);
        $people['clubs'] = $this->eventClubTab($event, $people['clubs'] ?? []);

        /*
         * ===== The flag beside a name is the PERSON'S =====
         *
         * Asked for on 2026-09-05, and it reverses the rule this platform has
         * followed until now: `ClubEventRegistration::countryCode()` answers
         * with the CLUB's country, so an Egyptian competing for a Bahraini club
         * flew Bahrain on every sheet.
         *
         * Both answers are defensible — a team competition flies the club, an
         * international flies the passport — which is exactly why this is done
         * HERE, on the sandbox's own roster, and not in the shared
         * `countryCode()` that the draw, the boards and the hall screens all
         * read. One screen changing its mind is a change; all of them changing
         * silently is a regression somebody finds on an event day.
         *
         * Falls back to what was there when a nationality is not on file — a
         * blank flag says less than the club's.
         */
        $people['participants'] = $this->attachNationality($people['participants'], $e['participants']);

        /*
         * ===== Verifying an entry WITHOUT leaving this list =====
         *
         * Asked for on 2026-09-03: tapping a person here and choosing the
         * verification desk must open that person's weigh-in and payment sheet
         * ON THIS PAGE, not navigate to another screen. So the same data
         * verify() assembles is assembled here, by the same method, behind the
         * same two questions — a weigh-in official may put an athlete on the
         * scale and must still not be able to approve money.
         *
         * Withheld entirely from anybody who may not officiate: the roster
         * itself is readable by everyone in the event, and a weight or a
         * receipt is not part of it.
         */
        $access = app(EventAccess::class);
        $canWeigh = $access->canVerifyWeighIn($event, $me);
        $canPay = $access->canVerifyPayments($event, $me);

        if ($canWeigh || $canPay) {
            $e['participants'] = $this->attachVerification($event, $e['participants'], $canWeigh, $canPay);
        }

        /*
         * ===== The money, at a glance =====
         *
         * Asked for on 2026-09-05: an organiser opening this list wants to know
         * how much of the field has paid without counting badges down a column
         * of thirty.
         *
         * Counted only over rows this viewer may see the status of — the same
         * `show_status` gate the per-card badges use — so the denominator never
         * silently includes an entry whose fee is somebody else's business. And
         * only when there IS a fee: a free competition has nothing to be a
         * fraction of, and a permanent 0% would read as a fault.
         */
        $money = null;

        /* `eventView()` does not carry `fee_is_paid` (that is PublicEvent's
           payload, a different shape) — so ask EventFee directly, which is the
           authority either way. */
        $hasFee = \App\Events\Support\EventFee::isPaid($event, 'participant');

        if (($canManage || $canPay) && $hasFee) {
            $visible = collect($people['participants'])->filter(fn ($p) => $p['show_status'] ?? false);
            $paid = $visible->filter(fn ($p) => $p['paid'] ?? false)->count();

            if ($visible->isNotEmpty()) {
                $money = [
                    'paid' => $paid,
                    'due' => $visible->count() - $paid,
                    'total' => $visible->count(),
                    'percent' => (int) round($paid / $visible->count() * 100),
                ];
            }
        }

        return view('eventlab::personal.event-people', [
            'e' => $e,
            'participants' => $people['participants'],
            'clubs' => $people['clubs'],
            'money' => $money,
            // Whether this viewer may put a face on an entry, and select names
            // to take off the list. The roster is readable by everyone in the
            // event; only whoever runs it may act on it.
            'canManage' => $canManage,
            // The verification sheet is rendered on this page for an official,
            // and not at all for anybody else.
            'canWeigh' => $canWeigh,
            'canPay' => $canPay,
            'payment' => ($canWeigh || $canPay) ? $this->paymentInstructions($event) : null,
        ]);
    }

    /**
     * Put back the entrants the event type's own roster left out.
     *
     * Organiser only, and only on the roster PAGE — see people(). Built here
     * rather than by loosening the type's roster, because the two lists answer
     * different questions: a package's roster feeds the hall screens and is
     * entitled to hold back a competitor it cannot yet announce, while this page
     * answers "who is on my list", where an incomplete entry is the interesting
     * one.
     *
     * Deliberately minimal. These rows carry a name, a face, a country and the
     * entry's id — no payment state, no weight, nothing the roster proper
     * scopes per viewer — so appending them can leak nothing that the guarded
     * rows would not already have shown to the organiser reading them.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function appendUnlistedEntrants(ClubEvent $event, array $rows): array
    {
        $listed = collect($rows)->pluck('registration')->filter()->all();

        $missing = $event->participantRegistrations()
            ->when($listed !== [], fn ($q) => $q->whereNotIn('id', $listed))
            ->with(['user:id,full_name,name,gender', 'category:id,name,weight_class'])
            ->latest('registered_at')
            ->get()
            ->map(fn (ClubEventRegistration $r) => [
                'id' => $r->user?->id,
                'name' => $r->user?->full_name ?? $r->user?->name ?? __('shared.unknown'),
                'gender' => $r->user?->gender ?: null,
                'category' => $r->category?->name,
                'weight_class' => $r->category?->weight_class,
                'meta' => null,
                'country' => $r->countryCode(),
                'enrolled' => $r->status === 'joined',
                'registration' => $r->id,
                'registration_photo' => $r->photo,
                // The organiser is staff, so the roster's own scoping would have
                // shown them these anyway.
                'show_status' => true,
                'paid' => (bool) $r->paid,
                'paid_verified' => $r->paid && $r->paid_by !== null,
                'weighed' => $r->weighed_in_at !== null,
                'weighed_verified' => $r->weighed_in_at !== null && $r->weighed_in_by !== null,
            ])
            ->all();

        return array_merge($rows, $missing);
    }

    /**
     * Put the payment / weigh-in state back on the people-page rows.
     *
     * Read from the registrations themselves rather than from whatever shape a
     * sport's roster happened to build, because the three weigh-in states the
     * cards draw need a fact no roster row carries consistently: a weight ON
     * FILE with nobody's name against it.
     *
     *   · payment  — green once paid, grey until then.
     *   · weigh-in — grey with no weight anywhere, amber for a weight the
     *                athlete themselves put on file, green once a weigh-in
     *                official signed for it (`weighed_in_by`).
     *
     * Gated by the roster row's own `show_status`, so this widens no
     * disclosure: exactly the rows that were already allowed to show their
     * status show these badges.
     *
     * @param  array<int, array<string, mixed>>  $people  RosterPeople participants
     * @param  array<int, array<string, mixed>>  $rows    the roster rows they were built from
     * @return array<int, array<string, mixed>>
     */
    private function attachReadiness(ClubEvent $event, array $people, array $rows): array
    {
        $regs = $event->participantRegistrations()
            ->get(['id', 'user_id', 'paid', 'paid_by', 'weight', 'weighed_in_at', 'weighed_in_by'])
            ->keyBy('user_id');

        $rows = array_values($rows);

        foreach (array_values($people) as $i => $person) {
            $row = $rows[$i] ?? [];

            if (! ($row['show_status'] ?? false)) {
                continue;
            }

            $reg = $regs->get($row['id'] ?? null);

            if (! $reg) {
                continue;
            }

            $people[$i]['show_status'] = true;
            /* The belt's degree is NOT set here: RosterPeople already carries
               the announced rank (BeltRank resolves weigh-in, then
               certification, then skill), and a second writer would let the
               card disagree with the hall screen. */
            $people[$i]['paid'] = (bool) $reg->paid;
            $people[$i]['paid_verified'] = (bool) $reg->paid && $reg->paid_by !== null;
            $people[$i]['weigh_state'] = match (true) {
                $reg->weighed_in_by !== null => 'official',
                $reg->weight !== null => 'self',
                default => 'none',
            };
            // The number itself, so the card can print it rather than leaving
            // the weight line blank (asked for on 2026-09-04). Same gate as the
            // badges — `show_status` — so it is the organiser, the officials
            // and the athlete's own row, never the rest of the draw. Trailing
            // zeros go: a scale reading of 62.00 is written 62.
            $people[$i]['weight'] = $reg->weight !== null
                ? rtrim(rtrim(number_format((float) $reg->weight, 2, '.', ''), '0'), '.')
                : null;
        }

        return $people;
    }

    /**
     * The officiating sheet — who is running this competition.
     *
     * A reading screen, like people(): no controls for anybody, the same page
     * for an organiser and for a first-time competitor. Appointing still happens
     * on the event's own edit screen, which is where the authority to appoint
     * lives.
     *
     * It says a name, the job, and the country beside it — the three things an
     * officiating sheet has always printed, and nothing else. The email, phone
     * and fee that officials() returns are appointment paperwork and stay behind
     * assertCanManage(); a photo appears only when the member published one
     * (`profile_picture_is_public`), because a face is their own choice.
     */
    public function officiating(ClubEvent $event, Request $request): View
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $labels = $this->officialRoleOptions($event);

        $rows = $event->officials()
            ->with('user:id,uuid,full_name,name,gender,nationality,profile_picture,profile_picture_is_public,updated_at')
            ->get()
            ->filter(fn (EventOfficial $o) => $o->user !== null)
            ->map(fn (EventOfficial $o) => [
                'role' => $o->role,
                'name' => $o->user->full_name ?: $o->user->name,
                'uuid' => $o->user->uuid,
                'gender' => $o->user->gender,
                'nationality' => $o->user->nationality ?: null,
                'country' => $o->user->nationality
                    ? ($this->countryNames()[strtoupper($o->user->nationality)] ?? null)
                    : null,
                'photo' => ($o->user->profile_picture && $o->user->profile_picture_is_public)
                    ? file_url($o->user->profile_picture).'?v='.($o->user->updated_at?->timestamp ?? 0)
                    : null,
            ]);

        $byRole = $rows->groupBy('role');
        // What the job actually is, in one line — the sport's own words for a mat
        // role, the access granted for a platform one. Printed on the card so a
        // competitor reading the sheet knows what the person beside their name
        // will be doing, and an organiser can check they appointed the right job.
        $hints = $this->officialRoleHints($event);

        // Grouped by job, in the SPORT's own order — a sheet reads Shushin
        // first, not whoever was appointed first.
        $groups = collect($labels)
            ->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'hint' => $hints[$key] ?? null,
                'people' => ($byRole[$key] ?? collect())->values()->all(),
            ])
            ->filter(fn ($g) => $g['people'] !== [])
            ->values()
            ->all();

        $myReg = $this->myRegistrations($me->id, collect([$event->id]));

        return view('eventlab::personal.event-officials', [
            'e' => $this->eventView($event, $me->id, $myReg),
            'groups' => $groups,
            'total' => $rows->count(),
            // Whether this viewer may APPOINT — narrower than whether they may
            // read the page. Same split as the main app.
            'canManage' => app(\App\Events\Support\EventAccess::class)->canManage($event, $me),
        ]);
    }


    /**
     * Merge the officiating payload onto roster rows — officials only.
     *
     * Roster rows are keyed by USER id (that is what moderation acts on), but
     * the weigh-in and payment endpoints act on a REGISTRATION. This is where
     * the two are joined, and it is the only place `reg_id`, the recorded
     * weight, or a proof-of-payment URL ever enters a roster payload.
     *
     * Each gate's DETAIL goes only to the role that guards it, because the two
     * jobs are held by different people:
     *   - the body weight — these are Kids divisions — only to weigh-in
     *   - whether a receipt exists, and the link to it, only to payments
     *
     * What both roles do get is the pair of signed/not-signed booleans, since
     * "is this entry cleared for the draw?" is the question the whole screen
     * answers and neither flag discloses a weight or a bank transfer.
     *
     * Callers must have already established that the viewer may officiate.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachVerification(ClubEvent $event, array $rows, bool $canWeigh, bool $canPay): array
    {
        $regs = $event->participantRegistrations()
            ->get(['id', 'user_id', 'weight', 'payment_proof'])
            ->keyBy('user_id');

        return array_map(function (array $row) use ($regs, $canWeigh, $canPay, $event) {
            $reg = $regs->get($row['id'] ?? null);

            if (! $reg) {
                return $row;
            }

            $row['reg_id'] = $reg->id;

            if ($canWeigh) {
                $row['weight'] = $reg->weight !== null ? (float) $reg->weight : null;
            }

            if ($canPay) {
                $row['has_proof'] = (bool) $reg->payment_proof;
                $row['proof_url'] = $reg->payment_proof
                    ? route('testcode.me.events.verify.proof', [$event->uuid, $reg->id])
                    : null;
            }

            return $row;
        }, $rows);
    }

    /**
     * Manage the draw — the board, full screen, and nothing else.
     *
     * Arranging a bracket is close work: you are reading names, spotting two
     * club-mates drawn together, dragging one of them somewhere better. On the
     * ordinary bracket page the board shares the screen with a header band and
     * the readable bout detail beneath it. Here it gets the whole viewport.
     *
     * Same board component, same endpoints — only the chrome is gone. Anyone
     * who may not arrange is sent back to the read-only bracket page rather
     * than shown a board they cannot touch.
     */
    public function manageBracket(Request $request, ClubEvent $event): View|RedirectResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $type = $this->typeFor($event);
        $type->onEntrantsChanged($event);

        $canManage = $this->canManage($event, $me);
        $canArrange = $this->canArrangeDraw($event, $type, $canManage);

        /*
         * This page is TWO jobs now: arranging a draw, and building the groups a
         * draw is cut from.
         *
         * It used to open only when arranging was on offer, which the packages
         * withhold until at least one division exists. That is right for
         * arranging — there is nothing to move — and it locked an organiser out
         * of the one screen where the FIRST group is made. An event with no
         * divisions could never get one by hand.
         *
         * So the door is "may you manage this event, or may you arrange its
         * draw", and arranging itself stays gated exactly as it was: the board
         * below is handed $canArrange, not a hardcoded true, so an organiser who
         * arrives before there is a draw gets the groups tools and a read-only
         * board rather than an arrange mode that would refuse every drop.
         */
        if (! $canManage && ! $canArrange) {
            return redirect()
                ->route('testcode.me.events.bracket', $event->uuid)
                ->with('error', __('events.draw_final'));
        }

        /*
         * The DRAW's own actions — build it, clear it — offered here rather
         * than on the console.
         *
         * They were console tiles, three screens away from the board they act
         * on: an organiser pressed "create the draw" and then had to go and
         * find it. The package still decides whether each one may run today
         * (availableActions is the only place that judges that); this only
         * decides WHERE it is offered. `arrange_draw` is dropped from the list,
         * because arranging is what this page IS.
         */
        $drawActions = $canManage
            ? array_values(array_filter(
                $type->availableActions($event),
                fn (array $a) => str_contains($a['action'] ?? '', 'draw') && ($a['action'] ?? '') !== 'arrange_draw',
            ))
            : [];

        return view('eventlab::personal.event-manage-draw', [
            'e' => $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: true),
            'canArrange' => $canArrange,
            'drawActions' => $drawActions,
            // Creating and deleting a GROUP is managing the event; filling one is
            // only arranging. An appointed official reaches this page and may
            // move people between groups, but does not get the New group button.
            'canManage' => $canManage,
            'myCompetitorIds' => ClubEventRegistration::where('event_id', $event->id)
                ->where('user_id', $me->id)->pluck('id')->all(),
            // Deep-link straight to the division the organiser came from.
            'division' => $request->query('division'),
        ]);
    }

    /**
     * The bracket screen's own data feed. The renderer re-fetches this on every
     * realtime nudge, so a draw someone else arranges — or a bout that just
     * landed — appears without anyone reloading.
     */
    /**
     * One bout, and where in the event it sat.
     *
     * This is the page a match video links back to (VIDEO-INTEGRATION.md §6.6):
     * from a clip on the video platform, "which bout was this?" — its division,
     * round, mat and day, who fought, and how it ended.
     *
     * Addressed by the EVENT's uuid plus the bout's match number rather than by
     * a bout id. `event_matches` has no public identifier of its own, and a
     * sequential id in a link shared between platforms is exactly what the
     * Unpredictable Resource Identifiers rule forbids. The unguessable part is
     * the event uuid, which a viewer holding this link already has; the match
     * number is only meaningful inside it.
     *
     * Authorisation is the event's own: assertVisible() — the same gate as the
     * event page and the bracket. Nothing about a bout is visible to someone who
     * could not see the event it belongs to.
     */
    public function bout(Request $request, ClubEvent $event, int $matchNo): View|RedirectResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        /*
         * A bout page is the draw, one pairing at a time — so a concealed draw
         * that still served bout pages would be concealed from nobody who could
         * count to sixteen. Sent back to the event, which says when the draw
         * opens; never a 404, which would say a bout number does not exist and
         * make the next one worth trying.
         */
        if (! app(EventAccess::class)->drawVisible($event, $me)) {
            return redirect()->route('testcode.me.events.show', $event->uuid)
                ->with('error', $this->drawHiddenMessage($event));
        }

        $match = EventMatch::where('event_id', $event->id)
            ->where('match_no', $matchNo)
            ->with([
                'category:id,name,weight_class',
                // Both corners, with what a sheet prints beside a name. The club
                // they compete FOR, not the one they train at — competingClub().
                'competitorA.user:id,uuid,full_name,name,birthdate,gender,profile_picture,profile_picture_is_public,is_discoverable,updated_at',
                'competitorA.representingTenant:id,club_name,slug,logo,country',
                'competitorA.user.memberClubs:id,club_name,slug,logo,country',
                'competitorB.user:id,uuid,full_name,name,birthdate,gender,profile_picture,profile_picture_is_public,is_discoverable,updated_at',
                'competitorB.representingTenant:id,club_name,slug,logo,country',
                'competitorB.user.memberClubs:id,club_name,slug,logo,country',
            ])
            ->first();

        // A bout number that does not exist in this event is a 404, not an empty
        // page — and it says nothing about which numbers do exist.
        abort_if($match === null, 404);

        $e = $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: false);

$isMobile = true;   // events are mobile-only — see show()
        $device = $isMobile ? 'mobile' : 'desktop';

        return view('eventlab::personal.'.$device.'.event-bout', [
            'e' => $e,
            'bout' => $this->boutView($event, $match),
            'officials' => $this->eventOfficials($event),
            'canManage' => $this->canManage($event, $me),
        ]);
    }

    /**
     * Who officiated, for the panel under the bout.
     *
     * Appointments are recorded per EVENT, not per bout — event_officials has no
     * match column — so this is the officiating panel of the championship, and the
     * heading says so rather than implying these four stood on this one mat.
     *
     * Only what an official's own name and role disclose is returned. Compensation
     * and fee live on the same row and are deliberately NOT read here: what a
     * volunteer or a paid official is owed is the organiser's business, and this
     * page is visible to everyone the event reaches.
     *
     * Faces and profile links follow the same rules as the corners above: a face
     * only when the person made their picture public, a link only when they are
     * discoverable and not a minor. Withholding is silent.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventOfficials(ClubEvent $event): array
    {
        // Referee first, then the rest of the mat, then the administrative roles —
        // reading order on an officiating sheet, not insertion order.
        $rank = ['referee' => 0, 'judge' => 1, 'timekeeper' => 2, 'recorder' => 3, 'jury' => 4, 'organiser' => 5];

        return \App\Models\EventOfficial::where('event_id', $event->id)
            ->with('user:id,uuid,full_name,name,birthdate,nationality,profile_picture,profile_picture_is_public,is_discoverable,updated_at')
            ->get()
            ->sortBy(fn ($o) => [$rank[$o->role] ?? 99, mb_strtolower((string) ($o->user?->full_name ?? ''))])
            ->map(function (\App\Models\EventOfficial $o) {
                $user = $o->user;

                $isMinor = $user?->birthdate ? Carbon::parse($user->birthdate)->age < 18 : false;

                /*
                 * An official's flag is their NATIONALITY, not a club country.
                 * The club-country rule exists because a competitor represents the
                 * club that entered them; an official represents nobody, and is
                 * listed by country on every officiating sheet. Validated to two
                 * letters before it reaches a CSS class name — the field is member-
                 * editable, and an unexpected value must produce no flag rather
                 * than a junk selector.
                 */
                $country = strtoupper(trim((string) $user?->nationality));
                $country = preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;

                return [
                    'name' => $user?->full_name ?: ($user?->name ?: __('shared.unknown')),
                    'role_label' => $this->officialRoleLabel((string) $o->role),
                    'country' => $country,
                    'photo' => ($user?->profile_picture && $user->profile_picture_is_public)
                        ? file_url($user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0)
                        : null,
                    'profile_url' => ($user !== null && $user->uuid !== null && (bool) $user->is_discoverable && ! $isMinor)
                        ? route('people.show', $user->uuid)
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The label for an officiating role.
     *
     * Mat roles (referee, judge, timekeeper, recorder) come from the sport's own
     * federation vocabulary; the administrative appointments (jury, weigh-in,
     * payments, organiser) have their own. An unrecognised role is titled rather
     * than dropped, so a federation that invents one still reads sensibly.
     */
    private function officialRoleLabel(string $role): string
    {
        foreach (['events.official_'.$role, 'eventlab::personal.event_officials_role_'.$role] as $key) {
            if (\Illuminate\Support\Facades\Lang::has($key)) {
                return __($key);
            }
        }

        return \Illuminate\Support\Str::title(str_replace('_', ' ', $role));
    }

    /**
     * The athletes who may stand in this bout.
     *
     * Restricted to entrants of THIS event in THIS bout's own category, because a
     * bout belongs to a division: offering the whole entry list would let an
     * organiser put a -68 kg fighter into a -61 kg final by mistyping, which is
     * precisely what a free-text name field allowed.
     *
     * Returns registration ids, not user ids. The registration IS the entry — it
     * carries the club they compete for and the weight they made — so naming it
     * keeps the bout tied to the draw rather than to a person in the abstract.
     */
    public function boutCompetitors(Request $request, ClubEvent $event, int $matchNo): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $match = EventMatch::where('event_id', $event->id)->where('match_no', $matchNo)->first();
        abort_if($match === null, 404);

        $entries = ClubEventRegistration::where('event_id', $event->id)
            ->when($match->category_id !== null, fn ($q) => $q->where('category_id', $match->category_id))
            ->whereIn('role', ['participant', 'athlete'])
            ->with('user:id,full_name,name,profile_picture,profile_picture_is_public,updated_at')
            ->get(['id', 'user_id', 'category_id', 'meta', 'representing_tenant_id', 'club_disowned_at']);

        return response()->json([
            'success' => true,
            'competitors' => $entries->map(function (ClubEventRegistration $r) {
                $user = $r->user;

                return [
                    'id' => $r->id,
                    'name' => $user?->full_name ?: ($user?->name ?: __('shared.unknown')),
                    // Honours the athlete's own "show my picture" choice, like every
                    // other surface that draws a competitor's face.
                    'photo' => ($user?->profile_picture && $user->profile_picture_is_public)
                        ? file_url($user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0)
                        : null,
                    'club' => $r->competingClub()?->club_name,
                    'country' => $r->countryCode(),
                ];
            })->sortBy('name')->values(),
        ]);
    }

    /**
     * Organiser corrections to one bout.
     *
     * WHO WINS, stated plainly because two things can write a result:
     *
     *   During the bout the MAT is authoritative. The scoring console holds its
     *   own state and writes the result when the bout is committed, so anything
     *   typed here for a bout still to be fought is a pre-fill the console will
     *   legitimately overwrite — that is correct, not a bug.
     *
     *   After the bout, THIS is authoritative. A finished scoresheet being
     *   corrected by the officials' table is how the paper version has always
     *   worked, and refusing it would leave a wrong result permanent.
     *
     * Every correction is appended to the officiating log, so a hand-edited
     * result is never indistinguishable from what the mat recorded. Corrections
     * are recorded, not disguised.
     *
     * Competitor NAMES are editable here (a typo on a sheet); WHO is in the bout
     * is not — that is the draw's job, through the bracket's Arrange mode, which
     * keeps the registration link intact.
     */
    public function updateBout(Request $request, ClubEvent $event, int $matchNo): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'a_corner' => ['nullable', Rule::in(['red', 'blue'])],
            'b_corner' => ['nullable', Rule::in(['red', 'blue'])],
            'a_score'  => ['nullable', 'integer', 'min:0', 'max:999'],
            'b_score'  => ['nullable', 'integer', 'min:0', 'max:999'],
            'winner'   => ['nullable', Rule::in(['a', 'b'])],
            'a_name'   => ['nullable', 'string', 'max:120'],
            'b_name'   => ['nullable', 'string', 'max:120'],
            // A picked entrant, by registration id. Validated against this event
            // and this bout's category below — an id alone is not authority to
            // place someone in a division they never entered.
            'a_competitor_id' => ['nullable', 'integer'],
            'b_competitor_id' => ['nullable', 'integer'],
        ]);

        $match = EventMatch::where('event_id', $event->id)->where('match_no', $matchNo)->first();
        abort_if($match === null, 404);

        $a = $data['a_corner'] ?? null;
        $b = $data['b_corner'] ?? null;

        // Two fighters cannot share a corner. Rejected rather than silently
        // corrected, because guessing which one the organiser meant is how a
        // point ends up attributed to the wrong athlete.
        if ($a !== null && $a === $b) {
            return response()->json([
                'success' => false,
                'message' => __('events.bout_corners_conflict'),
            ], 422);
        }

        /*
         * Placing an entrant in a corner.
         *
         * Only an entry in THIS event and THIS bout's category qualifies, and the
         * same entry cannot hold both corners — a bout against oneself is not a
         * bout. Rejected rather than ignored, so a bad pick is visible instead of
         * silently discarded.
         */
        $picks = [];

        foreach (['a', 'b'] as $side) {
            $id = $data[$side.'_competitor_id'] ?? null;
            if ($id === null) {
                continue;
            }

            $entry = ClubEventRegistration::where('id', $id)
                ->where('event_id', $event->id)
                ->when($match->category_id !== null, fn ($q) => $q->where('category_id', $match->category_id))
                ->first();

            if ($entry === null) {
                return response()->json([
                    'success' => false,
                    'message' => __('events.bout_competitor_ineligible'),
                ], 422);
            }

            $picks[$side] = $entry;
        }

        if (isset($picks['a'], $picks['b']) && $picks['a']->id === $picks['b']->id) {
            return response()->json([
                'success' => false,
                'message' => __('events.bout_competitor_duplicate'),
            ], 422);
        }

        foreach ($picks as $side => $entry) {
            $match->{$side.'_competitor_id'} = $entry->id;
            $match->{$side.'_name'} = $entry->user?->full_name ?: ($entry->user?->name ?: $match->{$side.'_name'});
        }

        // What actually changed, for the log. Recording the diff rather than the
        // whole row keeps the audit readable and the payload small.
        $before = [
            'a_corner' => $match->getOriginal('a_corner'), 'b_corner' => $match->getOriginal('b_corner'),
            'a_score' => $match->getOriginal('a_score'), 'b_score' => $match->getOriginal('b_score'),
            'winner' => $match->getOriginal('winner'),
            'a_name' => $match->getOriginal('a_name'), 'b_name' => $match->getOriginal('b_name'),
            'a_competitor_id' => $match->getOriginal('a_competitor_id'),
            'b_competitor_id' => $match->getOriginal('b_competitor_id'),
        ];

        $match->a_corner = $a;
        $match->b_corner = $b;
        $match->a_score  = $data['a_score'] ?? null;
        $match->b_score  = $data['b_score'] ?? null;
        $match->winner   = $data['winner'] ?? null;

        // A cleared name falls back to what the draw holds rather than blanking
        // the sheet: an unnamed corner reads as a missing competitor.
        if (array_key_exists('a_name', $data) && trim((string) $data['a_name']) !== '') {
            $match->a_name = trim($data['a_name']);
        }
        if (array_key_exists('b_name', $data) && trim((string) $data['b_name']) !== '') {
            $match->b_name = trim($data['b_name']);
        }

        $match->save();

        // Compared as strings: the columns come back from the database as strings
        // while the request supplies integers, so a strict comparison reported an
        // unchanged score as changed and put noise in the audit trail.
        $changed = collect($before)
            ->reject(fn ($old, $field) => (string) $old === (string) $match->{$field})
            ->keys()
            ->all();

        if ($changed !== []) {
            // Cannot throw (MatchEventLog swallows everything) — an audit row must
            // never be the reason a correction fails to save.
            \App\Events\Support\MatchEventLog::record(
                event: $event,
                court: (string) ($match->court ?? ''),
                sport: (string) ($event->sport ?? ''),
                command: 'organiser_correction',
                payload: ['by' => $me->id, 'fields' => $changed],
                matchId: $match->id,
                scoreA: (int) $match->a_score,
                scoreB: (int) $match->b_score,
            );
        }

        // Other organisers, and anyone whose profile shows this bout, are nudged
        // to re-read rather than sent the change: what each viewer may see of a
        // bout differs, so the refresh signal is the safe shape here.
        $this->pushEventRefresh($event);

        return response()->json([
            'success' => true,
            'message' => __('events.bout_saved'),
            'bout' => $this->boutView($event, $match->refresh()),
        ]);
    }

    /**
     * Shape one bout for display, including the links out to profiles and club
     * pages.
     */
    private function boutView(ClubEvent $event, EventMatch $match): array
    {
        return [
            'match_no' => $match->match_no,
            'round' => $match->round,
            'phase' => $match->phase,
            'division' => $match->category?->weight_class ?: $match->category?->name,
            'court' => $match->court,
            'day' => $match->day,
            'scheduled_time' => $match->scheduled_time,
            'status' => $match->status,
            'winner' => $match->winner,
            'a' => $this->boutSide($match, 'a'),
            'b' => $this->boutSide($match, 'b'),
            // Scoped to this bout's own division. Unscoped, this opened whichever
            // division sorts first — a different bracket and a different match.
            'bracket_url' => route('testcode.me.events.bracket', array_filter([
                'event' => $event->uuid,
                'category' => $match->category_id,
                'bout' => $match->match_no,
            ])),
            /*
             * Where the bout can be watched, or null.
             *
             * Our own review page — the picture, the angles, and the highlights
             * bar derived from the officiating log. Only when the media is
             * actually watchable: a clip still transcoding offers no link rather
             * than a broken one, and a bout that was never filmed has no
             * recording row at all, which is the ordinary case. The button is
             * absent rather than disabled.
             *
             * `play_url` is the legacy fallback and nothing writes it any more.
             * The video-platform integration has been removed; a handful of
             * bouts still carry a URL published there before that, and those
             * links are left working rather than blanked. When those videos are
             * gone, this branch and the column can go with them.
             */
            'video_url' => (function () use ($event, $match) {
                $row = \App\Models\EventRecording::with('mediaFile')
                    ->where('match_id', $match->id)
                    ->where('status', \App\Models\EventRecording::STATUS_LINKED)
                    ->where(fn ($q) => $q->whereNotNull('play_url')->orWhereNotNull('media_file_id'))
                    ->latest('id')
                    ->first();

                if ($row === null) {
                    return null;
                }

                if ($row->mediaFile?->isPlayable()) {
                    return route('testcode.me.events.bout.video', [
                        'event' => $event->uuid,
                        'matchNo' => $match->match_no,
                    ]);
                }

                return filled($row->play_url) ? $row->play_url : null;
            })(),
        ];
    }

    /**
     * One corner of a bout.
     *
     * The two links here are disclosures, so each is withheld rather than
     * rendered when it should not exist:
     *
     *   profile — only for a member who is discoverable and not a minor. Opting
     *             out of discovery is a choice not to be found, and a link from
     *             a shared match page is precisely being found.
     *   club    — only when the entry actually has one. competingClub() returns
     *             null for an unattached or disowned entry, and that must stay
     *             null: re-badging an athlete with a club they merely train at
     *             is the thing that method exists to prevent.
     *
     * Withholding is silent. A greyed-out link or a "profile hidden" label would
     * disclose the very fact the guard protects.
     */
    private function boutSide(EventMatch $match, string $side): array
    {
        /** @var ClubEventRegistration|null $reg */
        $reg = $side === 'a' ? $match->competitorA : $match->competitorB;
        $user = $reg?->user;
        $club = $reg?->competingClub();

        $isMinor = $user?->birthdate
            ? Carbon::parse($user->birthdate)->age < 18
            : false;

        $showProfile = $user !== null
            && (bool) $user->is_discoverable
            && ! $isMinor
            && $user->uuid !== null;

        return [
            'name' => $match->{$side.'_name'},
            /*
             * Their face, or null. Honours the athlete's own "show my picture"
             * choice, exactly as BracketView does — a bout page reaches everyone
             * the event reaches, so it is not a place to override that. An athlete
             * who has not opted in gets the gendered silhouette instead.
             */
            'photo' => ($user?->profile_picture && $user->profile_picture_is_public)
                ? file_url($user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0)
                : null,
            'gender' => $user?->gender,
            /*
             * Which corner they actually fought in.
             *
             * Recorded per bout rather than inferred from the draw slot: seeding
             * decides the slot, the mat decides the corner, and they do not always
             * agree. Falls back to the old aka='a'/ao='b' assumption only when
             * nothing was recorded, so an unannotated bout looks exactly as before.
             */
            'corner' => $match->{$side.'_corner'} ?: ($side === 'a' ? 'red' : 'blue'),
            'country' => $reg?->countryCode() ?: $match->{$side.'_country'},
            'seed' => $match->{$side.'_seed'},
            'score' => $match->{$side.'_score'},
            'provisional' => (bool) $match->{$side.'_provisional'},
            'won' => $match->winner === $side,
            'belt' => $reg?->belt_colour,
            'profile_url' => $showProfile ? route('people.show', $user->uuid) : null,
            'club' => $club ? [
                'name' => $club->club_name,
                'logo' => $club->logo,
                // The public club page needs the ISO country prefix; without a
                // country there is no valid URL, so the link is dropped rather
                // than built broken.
                'url' => $club->country && $club->slug
                    ? route('clubs.show', ['country' => strtolower($club->country), 'slug' => $club->slug])
                    : null,
            ] : null,
        ];
    }

    /**
     * Fly the athlete's own nationality, where it is known.
     *
     * Positional, like the two overlays above it: `RosterPeople::build()` maps
     * the rows it was given one-for-one and in order.
     *
     * @param  array<int, array<string, mixed>>  $people
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachNationality(array $people, array $rows): array
    {
        $ids = collect($rows)->pluck('id')->filter()->unique()->all();

        if (! $ids) {
            return $people;
        }

        $nationality = User::whereIn('id', $ids)
            ->whereNotNull('nationality')
            ->pluck('nationality', 'id');

        if ($nationality->isEmpty()) {
            return $people;
        }

        $rows = array_values($rows);

        foreach (array_values($people) as $i => $person) {
            $own = $nationality->get($rows[$i]['id'] ?? null);

            if ($own) {
                $people[$i]['country'] = strtoupper($own);
            }
        }

        return $people;
    }

    /**
     * Name the club an athlete was put under on this event.
     *
     * Only where the roster has nothing to show — a real club already named
     * through `representing_tenant_id` is left exactly as it was, so this can
     * only ever fill a blank, never overwrite an answer.
     *
     * @param  array<int, array<string, mixed>>  $people
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachEventClubs(ClubEvent $event, array $people, array $rows): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sandbox_event_club_entrants')) {
            return $people;
        }

        // user id => the club they are down as here.
        $byUser = \Illuminate\Support\Facades\DB::table('sandbox_event_club_entrants as l')
            ->join('sandbox_event_clubs as c', 'c.id', '=', 'l.sandbox_event_club_id')
            ->join('club_event_registrations as r', 'r.id', '=', 'l.registration_id')
            ->where('c.event_id', $event->id)
            ->select('r.user_id', 'c.name', 'c.logo', 'c.country', 'c.tenant_id')
            ->get()
            ->keyBy('user_id');

        if ($byUser->isEmpty()) {
            return $people;
        }

        $rows = array_values($rows);

        foreach (array_values($people) as $i => $person) {
            if (! empty($person['club'])) {
                continue;
            }

            $row = $rows[$i] ?? [];
            $club = $byUser->get($row['id'] ?? null);

            if (! $club) {
                continue;
            }

            $people[$i]['club'] = [
                'name' => $club->name,
                'logo' => $club->logo ? file_url($club->logo) : null,
                'country' => $club->country,
                // A club written down for one event has no page to open.
                'href' => null,
            ];

            $people[$i]['country'] = $people[$i]['country'] ?: $club->country;
        }

        return $people;
    }

    /**
     * The Clubs tab, counting the written-down clubs too.
     *
     * Rebuilt from the same overlay so the two tabs cannot disagree: a club
     * showing on an athlete's card and missing from the club list would read as
     * a mistake in the draw.
     *
     * @param  array<int, array<string, mixed>>  $clubs
     * @return array<int, array<string, mixed>>
     */
    private function eventClubTab(ClubEvent $event, array $clubs): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sandbox_event_clubs')) {
            return $clubs;
        }

        $named = collect($clubs)->pluck('name')->map(fn ($n) => mb_strtolower((string) $n))->all();

        $extra = \App\EventLab\Models\SandboxEventClub::where('event_id', $event->id)
            ->whereNull('tenant_id')
            ->where('state', '!=', 'declined')
            ->withCount('entrants')
            ->get()
            ->reject(fn ($c) => in_array(mb_strtolower($c->name), $named, true))
            ->filter(fn ($c) => $c->entrants_count > 0)
            ->map(fn ($c) => [
                'name' => $c->name,
                'logo' => $c->logo ? file_url($c->logo) : null,
                'country' => $c->country,
                'athletes' => (int) $c->entrants_count,
                'href' => null,
            ])
            ->values()
            ->all();

        if (! $extra) {
            return $clubs;
        }

        // Biggest squad first, the order the roster already uses.
        return collect(array_merge($clubs, $extra))
            ->sortByDesc(fn ($c) => $c['athletes'] ?? 0)
            ->values()
            ->all();
    }

    public function bracketData(ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $type = $this->typeFor($event);
        $canManage = $this->canManage($event, $me);

        /*
         * A draw the organiser has not let out yet.
         *
         * Withheld HERE rather than in the view, because the board fetches its
         * own data and this endpoint is the only door to it — a veil painted in
         * Blade over a JSON feed that still answers is not a veil at all.
         *
         * The board is told WHY, so it can say when the draw opens instead of
         * drawing an empty bracket that reads as "nobody entered".
         */
        if (! app(EventAccess::class)->drawVisible($event, $me)) {
            return response()->json([
                'divisions' => [],
                'can_arrange' => false,
                'locked' => true,
                'hidden' => true,
                'hidden_message' => $this->drawHiddenMessage($event),
            ]);
        }

        return response()->json([
            'divisions' => $type->bracketView($event, $me),
            // The bench is an organiser's working area, not part of the public
            // draw — and only the client that may arrange is told it can.
            'can_arrange' => $this->canArrangeDraw($event, $type, $canManage),
            'locked' => $event->hasStarted() || $event->hasEnded(),
        ]);
    }

    /**
     * Move one competitor within a division's first round — the drag-and-drop
     * save. Thin by design: authorization and rate limiting here, every rule
     * about what a legal arrangement IS inside the owning package.
     */
    public function arrangeBracket(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        // Arranging, not managing: the appointed jury may do this without being
        // able to edit or delete the event.
        $this->assertCanArrange($event, $me);

        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'from.type' => ['required', Rule::in(['slot', 'bench'])],
            'from.match_id' => ['nullable', 'integer'],
            'from.side' => ['nullable', Rule::in(['a', 'b'])],
            'from.competitor_id' => ['nullable', 'integer'],
            'to.type' => ['required', Rule::in(['slot', 'bench'])],
            'to.match_id' => ['nullable', 'integer'],
            'to.side' => ['nullable', Rule::in(['a', 'b'])],
        ]);

        return $this->dispatchAction($event, 'arrange_draw', $data);
    }

    /** Empty a division's draw onto the bench, to rebuild it by hand. */
    public function clearBracket(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanArrange($event, $me);

        $data = $request->validate(['category_id' => ['required', 'integer']]);

        return $this->dispatchAction($event, 'clear_draw', $data);
    }

    /**
     * Run a package action, refusing anything the package is not currently
     * offering. Same deny-by-default gate performAction() uses, so a bracket
     * route can never reach an action the type has withdrawn (a draw that has
     * locked, a type with no brackets at all).
     */
    private function dispatchAction(ClubEvent $event, string $action, array $payload): JsonResponse
    {
        $type = $this->typeFor($event);

        $offered = collect($type->availableActions($event))->pluck('action')->all();
        if (! in_array($action, $offered, true)) {
            return response()->json(['success' => false, 'message' => __('events.action_unavailable')], 422);
        }

        $result = $type->performAction($event, $action, $payload);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Plain-language reasons for the date/time rules people actually trip over.
     *
     * The form shows the FIRST error it gets back, so these are the sentences a
     * user reads when a save is refused. Laravel's defaults ("The enrollment
     * ends at field must be a date before or equal to date.") name columns and
     * describe a rule; these name the thing on screen and say what to change.
     *
     * @return array<string, string>
     */
    private function eventMessages(): array
    {
        return [
            'enrollment_ends_at.before_or_equal' => __('events.validate_enrolment_ends_after_event'),
            'enrollment_ends_at.after_or_equal' => __('events.validate_enrolment_ends_before_start'),
            'end_date.after_or_equal' => __('events.validate_end_date_before_start'),
            'start_time.date_format' => __('events.validate_start_time_format'),
            'end_time.date_format' => __('events.validate_end_time_format'),
            'break_start.after_or_equal' => __('events.validate_break_before_start'),
            'break_end.after' => __('events.validate_break_end_before_break_start'),
            'break_end.before_or_equal' => __('events.validate_break_after_end'),
        ];
    }

    /** May this viewer rearrange the draw right now? */
    /**
     * WHO may arrange (organiser, appointed jury, platform staff) AND whether
     * arranging is on offer at all — the package withdraws `arrange_draw` the
     * moment the event starts, so a started draw is final for everyone.
     */
    private function canArrangeDraw(ClubEvent $event, EventType $type, bool $canManage): bool
    {
        $who = $canManage || app(EventAccess::class)->isOfficial($event, Auth::user());

        return $who
            && collect($type->availableActions($event))->pluck('action')->contains('arrange_draw');
    }

    /**
     * 403 unless this user is allowed to arrange draws for this event.
     *
     * WHO only — deliberately not whether arranging is on offer right now. An
     * organiser asking to move a competitor after the event has started is not
     * forbidden, they are asking for something that can no longer happen:
     * dispatchAction() refuses the withdrawn action with 422, and Arrangement
     * refuses it again underneath. Answering 403 here would tell an organiser
     * they lack a permission they actually hold.
     */
    private function assertCanArrange(ClubEvent $event, User $me): void
    {
        abort_unless(app(EventAccess::class)->canArrange($event, $me), 403);
    }

    /* ---------------- Run-day checklist, and starting ---------------- */

    /**
     * Add one thing that must be true before the day can begin.
     *
     * The organiser writes the list; officials clear it. Deliberately free
     * text — "mats laid", "first-aid on site", "scoreboard tested" are not
     * facts this system can enumerate, and a fixed vocabulary would just push
     * organisers into an "Other" box.
     */
    public function storeChecklistItem(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:160'],
        ]);

        $item = $event->checklistItems()->create([
            'label' => $data['label'],
            // Append. The organiser's order is the order they wrote them in.
            'sort_order' => (int) $event->checklistItems()->max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('personal.event_check_added'),
            'item' => $this->checklistItemView($item),
            'outstanding' => $event->outstandingChecks(),
        ]);
    }

    /**
     * Clear an item, or put it back.
     *
     * Any appointed official may do this, not only the organiser: the list is
     * shared run-day work and the person who laid the mats is the person who
     * knows they are laid. Who cleared it is recorded either way.
     *
     * Refused once the event has started — the checklist is a gate on starting,
     * and a gate you can still edit afterwards is decoration.
     */
    public function toggleChecklistItem(Request $request, ClubEvent $event, EventChecklistItem $checklistItem): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_unless(app(EventAccess::class)->canOfficiate($event, $me), 403);
        // The route parameter is named for the checklistItems() relation, so
        // Laravel already scopes the lookup to THIS event. Re-checked anyway:
        // the day someone renames the parameter, this is what still refuses an
        // item belonging to another organiser's event.
        abort_unless($checklistItem->event_id === $event->id, 404);

        if ($event->hasStarted()) {
            return response()->json([
                'success' => false,
                'message' => __('personal.event_check_locked'),
            ], 422);
        }

        $checked = (bool) $request->validate(['checked' => ['required', 'boolean']])['checked'];

        $checklistItem->update([
            'checked_at' => $checked ? now() : null,
            'checked_by' => $checked ? $me->id : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $checked ? __('personal.event_check_done') : __('personal.event_check_undone'),
            'item' => $this->checklistItemView($checklistItem->fresh()),
            'outstanding' => $event->outstandingChecks(),
        ]);
    }

    /** Drop an item from the list. The organiser's list, the organiser's call. */
    public function destroyChecklistItem(ClubEvent $event, EventChecklistItem $checklistItem): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);
        abort_unless($checklistItem->event_id === $event->id, 404);

        $checklistItem->delete();

        return response()->json([
            'success' => true,
            'message' => __('personal.event_check_removed'),
            'outstanding' => $event->outstandingChecks(),
        ]);
    }

    /**
     * Begin the competition.
     *
     * This is the moment the draw locks and the event becomes read-only work —
     * so it is the organiser's call alone, never an official's, and never the
     * clock's.
     *
     * With items outstanding the request is refused unless the organiser
     * explicitly overrides. The override is not a way around the checklist; it
     * is the organiser saying "I know, start anyway", and it is recorded on the
     * event so the decision has a name against it afterwards.
     */
    public function startEvent(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        if ($event->hasStarted()) {
            return response()->json([
                'success' => false,
                'message' => __('personal.event_start_already'),
            ], 422);
        }

        if ($event->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => __('personal.event_start_cancelled'),
            ], 422);
        }

        $override = (bool) ($request->validate([
            'override' => ['nullable', 'boolean'],
        ])['override'] ?? false);

        $outstanding = $event->outstandingChecks();

        if ($outstanding > 0 && ! $override) {
            return response()->json([
                'success' => false,
                'code' => 'checklist_incomplete',
                'outstanding' => $outstanding,
                'message' => trans_choice('personal.event_start_blocked', $outstanding, ['count' => $outstanding]),
            ], 422);
        }

        // Not mass-assigned: starting is a state transition, not a form field.
        $event->started_at = now();
        $event->started_by = $me->id;
        $event->start_overridden = $outstanding > 0;
        $event->save();

        // Whoever is looking at this event is looking at a screen whose answer
        // just changed — the draw locked, the actions closed.
        $this->pushEventRefresh($event);

        return response()->json([
            'success' => true,
            'message' => $outstanding > 0
                ? trans_choice('personal.event_start_overridden', $outstanding, ['count' => $outstanding])
                : __('personal.event_started'),
            'started_at' => $event->started_at->toIso8601String(),
            'overridden' => $event->start_overridden,
        ]);
    }

    /**
     * Tell everyone attached to this event that its state changed.
     *
     * A refresh signal rather than a payload, per the realtime rule: the event
     * screen renders differently for the organiser, an official, a competitor
     * and a spectator, so there is no single patch that is correct for all of
     * them — each client re-reads the page it is entitled to. It also means
     * this carries nothing an unintended recipient could read.
     */
    private function pushEventRefresh(ClubEvent $event): void
    {
        $userIds = $event->registrations()->pluck('user_id')
            ->merge($event->officials()->pluck('user_id'))
            ->push($event->created_by)
            ->filter()->unique()->values()->all();

        if (! $userIds) {
            return;
        }

        rescue(function () use ($userIds, $event) {
            if (! \Realtime()->enabled()) {
                return;
            }

            // publishMany takes pre-built topics so the whole fan-out goes over
            // one broker connection.
            \Realtime()->publishMany(array_map(fn (int $uid) => [
                'topic' => \Realtime()->userTopic($uid, 'events'),
                'payload' => ['action' => 'refresh', 'event' => $event->uuid],
            ], $userIds));
        }, null, false);
    }

    /** One checklist row, shaped for the UI. */
    private function checklistItemView(EventChecklistItem $item): array
    {
        return [
            'uuid' => $item->uuid,
            'label' => $item->label,
            'checked' => $item->isChecked(),
            // Who signed it off — the reason the list is worth keeping.
            'by' => $item->checked_by ? ($item->checker?->full_name ?? $item->checker?->name) : null,
            'at' => $item->checked_at?->format('M j, g:i A'),
        ];
    }

    /* ---------------- Officials (the jury) ---------------- */

    /**
     * The event's appointed officials, plus who else could be appointed.
     *
     * Candidates are members of the HOST club: a jury is drawn from the club
     * running the championship, and bounding the pool that way keeps this from
     * becoming a search across every user on the platform.
     *
     * Appointing is the organiser's call (canManage), never the jury's own —
     * otherwise an official could appoint their friends onto the panel.
     */
    /**
     * Who may hold a role that GRANTS ACCESS to this event — organiser, jury,
     * weigh-in, payments: somebody connected to the club HOSTING it.
     *
     * "Connected" is not just a `memberships` row, and reading only that row is
     * the bug this replaces. A club's OWNER is not automatically a member of
     * their own club — owning it and training in it are different things — and
     * neither is somebody holding `club-admin` on it. So the membership-only
     * test left the very people who RUN the host club unable to be appointed to
     * their own club's event: they were missing from the picker with nothing on
     * screen to say why, and the endpoint would have refused them anyway.
     *
     * One list, asked by both the picker and the write endpoint, so an offered
     * candidate is never one the server then refuses.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function hostClubPeople(ClubEvent $event): \Illuminate\Support\Collection
    {
        $ids = DB::table('memberships')
            ->where('tenant_id', $event->tenant_id)
            ->distinct()->pluck('user_id');

        if ($owner = Tenant::whereKey($event->tenant_id)->value('owner_user_id')) {
            $ids->push($owner);
        }

        $ids = $ids->merge(
            DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.tenant_id', $event->tenant_id)
                ->whereIn('roles.slug', ['club-admin', 'instructor'])
                ->pluck('user_roles.user_id')
        );

        return $ids->map('intval')->unique()->values();
    }

    /**
     * May this person be appointed to an access-granting role here?
     *
     * A super-admin may appoint anybody: they can already add that person to
     * the host club or hand them a role there, so the host-club rule only makes
     * them do it in two steps. It grants nothing new — the rule stays exactly
     * as it was for every organiser who is not a super-admin.
     */
    private function mayHoldAccessRole(ClubEvent $event, int $userId, User $actor): bool
    {
        return $actor->isSuperAdmin() || $this->hostClubPeople($event)->contains($userId);
    }

    public function officials(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $appointed = $event->officials()->with('user:id,full_name,name,email,mobile,nationality,profile_picture')->get();

        // One person may hold two jobs — the club treasurer often runs the
        // weigh-in as well — so the candidate list no longer drops someone the
        // moment they are appointed to anything. Each row instead carries the
        // roles they already hold, and storeOfficial() refuses the duplicate.
        $heldRoles = $appointed->groupBy('user_id')->map->pluck('role');

        $q = trim((string) $request->query('q', ''));

        // Treat it as a phone search ONLY when the whole query looks like a
        // number. Pulling the digits out of any query made "user.3@mail" search
        // for "%3%", which matches nearly every phone on the books and returned
        // the entire club.
        $phone = '';
        if (preg_match('/^[\d\s+()\-\.]{4,}$/', $q)) {
            $phone = preg_replace('/\D+/', '', $q);
            // Match on the tail so a typed country code still finds a number
            // stored without one ("+973 3340 0036" vs "33400036").
            if (strlen($phone) > 8) {
                $phone = substr($phone, -8);
            }
        }

        /*
         * Which pool to search. Filling a mat role searches the platform, because
         * that is where referees are; filling a permission role searches the host
         * club only, matching what storeOfficial() will actually accept — an
         * offered candidate the server would refuse is a worse experience than a
         * shorter list.
         *
         * The wider pool is still limited to DISCOVERABLE members: being findable
         * is the member's own choice, and an organiser browsing for a referee is
         * exactly the kind of finding it governs.
         */
        $hints = $this->officialRoleHints($event);

        $forRole = (string) $request->query('role', '');
        $wide = $forRole !== '' && $this->isMatRole($event, $forRole);

        $candidates = User::query()
            ->distinct()
            ->when($wide,
                // Discoverability is the member's own opt-in to being found, so
                // an organiser browsing the platform for a referee obeys it. A
                // super-admin does not: this is an admin tool, and they can
                // already open any member's record from /admin — hiding people
                // here only stopped them appointing somebody they were looking
                // straight at.
                fn ($q) => $me->isSuperAdmin() ? $q : $q->where('is_discoverable', true),
                fn ($q) => $me->isSuperAdmin() ? $q : $q->whereIn('id', $this->hostClubPeople($event)))
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q, $phone) {
                $w->where('full_name', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");

                if ($phone !== '') {
                    $w->orWhere('mobile', 'like', "%{$phone}%");
                }
            }))
            /*
             * An empty query used to answer NOTHING for a mat role, so an organiser
             * whose host club has one membership row opened the picker and saw one
             * name and concluded there was nobody to appoint. It now lists the
             * pool it is about to search — the first 20 discoverable members by
             * name — and typing narrows it by name, email or phone.
             *
             * Still not a browsable directory: organiser-only, throttled, capped
             * at 20, and DISCOVERABLE members only (being findable is the
             * member's own choice).
             */
            ->orderBy('full_name')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'full_name', 'name', 'email', 'mobile', 'nationality', 'profile_picture']);

        // Names repeat — clubs have two Ahmeds — so every row carries the email
        // and phone that tell them apart. A picker that shows four identical rows
        // is a picker you cannot choose from.
        $shape = fn (User $u) => [
            'id' => $u->id,
            'name' => $u->full_name ?: $u->name,
            'email' => $u->email,
            'phone' => is_array($u->mobile) && ! empty($u->mobile['number'])
                ? trim(($u->mobile['code'] ?? '').' '.$u->mobile['number'])
                : null,
            'avatar' => $u->profile_picture ? file_url($u->profile_picture) : null,
            // An official is listed by country on every officiating sheet, and it
            // is the one fact about them the bout page cannot derive from anything
            // else. Sent so the form can show what is on file and ask when it is
            // blank — which is how a referee ended up with no flag at all.
            'nationality' => $u->nationality ?: null,
        ];

        return response()->json([
            'success' => true,
            // `id` is the APPOINTMENT, not the person: the same member can appear
            // twice with two roles, and removing one must not remove the other.
            'officials' => $appointed
                /*
                 * array_merge, NOT `+`.
                 *
                 * `+` keeps the LEFT operand for a duplicate key, so
                 * $shape($o->user) + ['id' => $o->id] silently kept the USER's id
                 * and threw the appointment's away — which made every delete and
                 * every role change address a row that does not exist, answer
                 * success, and change nothing.
                 */
                ->map(fn (EventOfficial $o) => array_merge($shape($o->user), [
                    'id' => $o->id,
                    'user_id' => $o->user_id,
                    'role' => $o->role,
                    // Volunteer or paid, and how much — a paid appointment is a
                    // line in the event's P&L, kept in step automatically.
                    'compensation' => $o->compensation,
                    'fee' => $o->fee !== null ? (float) $o->fee : null,
                ]))
                ->values(),
            'candidates' => $candidates
                ->map(fn (User $u) => $shape($u) + ['roles' => ($heldRoles[$u->id] ?? collect())->values()])
                ->values(),
            'roles' => collect($this->officialRoleOptions($event))
                ->map(function ($label, $key) use ($event, $hints) {
                    return [
                        'value' => $key,
                        'label' => $label,
                        // Every role explains itself now: a mat role says what the job
                        // is, a platform role says what access it grants.
                        'hint' => $hints[$key] ?? null,
                        'group' => in_array($key, EventOfficial::roles(), true) ? 'platform' : 'mat',
                    ];
                })->values(),
            'compensations' => collect(EventOfficial::compensations())->map(fn ($c) => [
                'value' => $c,
                'label' => __('personal.event_officials_'.$c),
                'hint' => __('personal.event_officials_'.$c.'_hint'),
            ])->values(),
            'currency' => $event->tenant?->currency ?: 'BHD',
        ]);
    }

    /**
     * The ISO-2 codes the app itself offers, read from the one list the country
     * pickers already use — so validation can never drift from the options a
     * user was given. Memoised: it is a 29 KB file and this runs on a write path.
     *
     * @return array<int, string>
     */
    /**
     * ISO-2 → the country's full name, from the same list every country picker
     * in the app reads. Officiating sheets print "Bahrain", not "BH": two
     * letters is a form value, not something a reader should have to decode.
     *
     * @return array<string, string>
     */
    private function countryNames(): array
    {
        static $names = null;

        if ($names !== null) {
            return $names;
        }

        $path = public_path('data/countries.json');
        $rows = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;

        $names = is_array($rows)
            ? collect($rows)->filter(fn ($r) => ! empty($r['iso2']) && ! empty($r['name']))
                ->mapWithKeys(fn ($r) => [strtoupper((string) $r['iso2']) => (string) $r['name']])
                ->all()
            : [];

        return $names;
    }

    private function countryCodes(): array
    {
        static $codes = null;

        if ($codes !== null) {
            return $codes;
        }

        $path = public_path('data/countries.json');
        $rows = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;

        $codes = is_array($rows)
            ? collect($rows)->pluck('iso2')->filter()->map(fn ($c) => strtoupper((string) $c))->values()->all()
            : [];

        return $codes;
    }

    /**
     * Record an official's nationality, but only when we do not already have one.
     *
     * The country belongs on the PERSON, not on the appointment: duplicating it
     * per event would give the same referee two countries the first time someone
     * typed it differently. Nationality is also the member's own data, so this
     * form fills a blank and never overwrites — an organiser appointing someone
     * to a job is not the authority to correct their passport.
     */
    private function recordOfficialNationality(?User $user, ?string $nationality): void
    {
        if ($user === null || $nationality === null || $nationality === '') {
            return;
        }

        // Already on file: leave it alone. See the docblock.
        if (trim((string) $user->nationality) !== '') {
            return;
        }

        $user->nationality = strtoupper($nationality);
        $user->save();
    }

    /**
     * Every officiating role this event can appoint, as key => label.
     *
     * Two vocabularies, because they answer different questions. The SPORT names
     * the panel that runs a mat — referee, judges, tatami manager — and each
     * federation words them its own way. The PLATFORM names the jobs that carry
     * permissions: jury arranges the draw, weigh-in signs weights, payments
     * approves proof.
     *
     * Merging them here is what makes a referee appointable at all: the form
     * accepted only the platform's four, so a karate event could not record the
     * person who actually refereed the bout, which is exactly what an officiating
     * sheet exists to state.
     *
     * @return array<string, string>
     */
    private function officialRoleOptions(ClubEvent $event): array
    {
        // The public event page prints the same sheet, so the vocabulary moved
        // to App\Events\Support\OfficialRoles rather than being copied into a
        // second place to drift from this one.
        return app(\App\Events\Support\OfficialRoles::class)->labels($event);
    }

    /**
     * What each role actually does, for the picker to print under its name.
     *
     * A mat role's description comes from the SPORT (only Karate knows what a
     * Kansa does); a platform role's explains the access it grants, which is the
     * more important sentence of the two.
     *
     * @return array<string, string|null>
     */
    private function officialRoleHints(ClubEvent $event): array
    {
        $out = [];

        $sport = app(\App\Sports\Combat\SportRegistry::class)->get($event->sport);

        if ($sport !== null) {
            foreach ($sport->officialRoles() as $role) {
                if (! empty($role['key'])) {
                    $out[$role['key']] = $role['hint'] ?? null;
                }
            }
        }

        foreach (EventOfficial::roles() as $role) {
            $out[$role] = __('personal.personal_event_officials_role_'.$role.'_hint');
        }

        return $out;
    }

    /**
     * Is this a role that runs the mat, rather than one that grants access?
     *
     * The distinction decides who may be appointed. A referee, judge or
     * timekeeper is usually a federation official who has never been a member of
     * the host club — which is why appointing one failed until now, and why both
     * referees on the National Team Selection Trials had to be inserted directly.
     * These roles grant NOTHING: EventAccess derives every permission from the
     * platform roles below, so widening the pool for them opens no door.
     *
     * jury / weigh_in / payments / organiser DO carry access to a club's event
     * data, so those stay members-only.
     */
    private function isMatRole(ClubEvent $event, string $role): bool
    {
        return array_key_exists($role, $this->officialRoleOptions($event))
            && ! in_array($role, EventOfficial::roles(), true);
    }

    /** Appoint someone to one officiating job on this event. */
    public function storeOfficial(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', Rule::in(array_keys($this->officialRoleOptions($event)))],
            // Officiating is volunteered or paid; if paid, the amount is not
            // optional — it becomes a line in the event's P&L.
            'compensation' => ['required', Rule::in(EventOfficial::compensations())],
            'fee' => ['nullable', 'numeric', 'min:0.001', 'max:999999', 'required_if:compensation,'.EventOfficial::COMP_PAID],
            // Optional: the form only asks when the member has no country on file.
            'nationality' => ['nullable', 'string', 'size:2', 'alpha', Rule::in($this->countryCodes())],
        ]);

        /*
         * A role that grants access must come from the host club; a role that
         * runs the mat may come from anywhere, because a referee is a federation
         * official and has usually never joined the club hosting the event.
         */
        if (! $this->isMatRole($event, $data['role'])) {
            if (! $this->mayHoldAccessRole($event, (int) $data['user_id'], $me)) {
                return response()->json([
                    'success' => false,
                    'message' => __('personal.personal_event_officials_not_a_member'),
                ], 422);
            }
        }

        $official = EventOfficial::firstOrNew([
            'event_id' => $event->id,
            'user_id' => $data['user_id'],
            'role' => $data['role'],
        ]);

        if ($official->exists) {
            return response()->json([
                'success' => false,
                'message' => __('personal.personal_event_officials_already'),
            ], 422);
        }

        $official->assigned_by = $me->id;
        $official->compensation = $data['compensation'];
        // Never carry a fee on a volunteer — it would sit in the row unused and
        // reappear as a cost the moment someone flipped the type.
        $official->fee = $data['compensation'] === EventOfficial::COMP_PAID ? $data['fee'] : null;
        // Saving syncs the matching expense (EventOfficial::booted).
        $official->save();

        $this->recordOfficialNationality(User::find($data['user_id']), $data['nationality'] ?? null);


        return response()->json([
            'success' => true,
            // Says which role, because there are now thirteen of them rather than
            // the one the copy used to assume.
            'message' => __('personal.personal_event_officials_added', [
                'role' => $this->officialRoleOptions($event)[$data['role']] ?? $data['role'],
            ]),
        ]);
    }

    /**
     * Change what an official is being paid (or move them to volunteer).
     *
     * The linked expense follows automatically, so the ledger can never drift
     * from who is actually being paid.
     */
    public function updateOfficial(Request $request, ClubEvent $event, EventOfficial $official): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);
        abort_unless($official->event_id === $event->id, 404);

        $data = $request->validate([
            'compensation' => ['required', Rule::in(EventOfficial::compensations())],
            'fee' => ['nullable', 'numeric', 'min:0.001', 'max:999999', 'required_if:compensation,'.EventOfficial::COMP_PAID],
            // Lets a blank country be filled without re-appointing the official.
            'nationality' => ['nullable', 'string', 'size:2', 'alpha', Rule::in($this->countryCodes())],
            // Changing the position in place, rather than removing and re-adding —
            // which would lose the appointment's history and its linked expense.
            'role' => ['nullable', Rule::in(array_keys($this->officialRoleOptions($event)))],
        ]);

        $official->compensation = $data['compensation'];
        $official->fee = $data['compensation'] === EventOfficial::COMP_PAID ? $data['fee'] : null;

        if (! empty($data['role']) && $data['role'] !== $official->role) {
            // Moving to a role that grants access requires what that role requires.
            if (! $this->isMatRole($event, $data['role'])
                && ! $this->mayHoldAccessRole($event, (int) $official->user_id, $me)) {
                return response()->json([
                    'success' => false,
                    'message' => __('personal.personal_event_officials_not_a_member'),
                ], 422);
            }

            $official->role = $data['role'];
        }

        $official->save();

        $this->recordOfficialNationality($official->user, $data['nationality'] ?? null);


        return response()->json([
            'success' => true,
            'message' => __('personal.event_officials_pay_updated'),
            'official' => [
                'id' => $official->id,
                'compensation' => $official->compensation,
                'fee' => $official->fee !== null ? (float) $official->fee : null,
                'nationality' => $official->user?->nationality ?: null,
                'role' => $official->role,
            ],
            // The finance modal reads this to refresh without a reload.
            'finance' => $this->typeFor($event)->finance($event),
        ]);
    }

    /** Withdraw ONE appointment — not every job the person holds. */
    public function destroyOfficial(ClubEvent $event, int $official): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $event->officials()->whereKey($official)->delete();


        return response()->json([
            'success' => true,
            'message' => __('personal.personal_event_officials_removed'),
        ]);
    }

    public function create(): View
    {
        $me = Auth::user();
        $clubs = $this->clubsICanCreateFor($me);

        return view('eventlab::personal.event-create', ['clubs' => $clubs] + $this->schemaPayload());
    }

    /** Schema + (in edit mode) the event's existing divisions for the form. */
    private function schemaPayload(?ClubEvent $event = null): array
    {
        $divisions = $event
            ? $event->categories()->orderBy('sort_order')->get(['id', 'name', 'capacity', 'schedule'])
                ->map(fn ($c) => [
                    'name' => $c->name,
                    'capacity' => $c->capacity,
                    'schedule' => $c->schedule ?: ['preliminary' => 1, 'quarterfinals' => 1, 'finals' => 1],
                ])->all()
            : [];

        return [
            'schema' => config('event_schema'),
            'divisions' => $divisions,
            // Each package contributes its own reference data (weight tables,
            // belt ladders …) keyed by package. The controller never names a
            // type to fetch a catalogue.
            'catalogs' => collect($this->registry->all())
                ->map(fn (EventType $t) => $t->formCatalog())
                ->filter()->all(),
        ];
    }

    /* ===================== Write actions ===================== */

    public function store(Request $request): JsonResponse
    {
        $me = Auth::user();

        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:140'],
            'event_type' => ['required', Rule::in(array_keys($this->types()))],
            'scope' => ['nullable', Rule::in(array_keys($this->scopes()))],
            'date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'weigh_in_at' => ['nullable', 'date'],
            'enrollment_starts_at' => ['nullable', 'date'],
            'enrollment_ends_at' => ['nullable', 'date', 'after_or_equal:enrollment_starts_at', 'before_or_equal:date'],
            'location' => ['nullable', 'string', 'max:160'],
            'level' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'participant_free' => ['required', 'boolean'],
            'participant_fee' => ['nullable', 'string', 'max:40'],
            // The real price. The string above is the line the page shows.
            'participant_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'spectator_enabled' => ['required', 'boolean'],
            'spectator_fee' => ['nullable', 'string', 'max:40'],
            'spectator_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            /*
             * Multi-pricing (2026-09-06). The two amounts above stay the BASE
             * price; these are the named extras an entrant ticks on top, and
             * the penalty for entering late.
             *
             * Capped at 30 options because this is a list an organiser types by
             * hand — a request carrying five hundred of them is not a very
             * detailed competition, it is somebody probing the endpoint.
             */
            'fee_options' => ['nullable', 'array', 'max:30'],
            // Which roles the form is speaking for, so retiring an option is
            // never inferred from a role the request simply did not mention.
            'fee_option_roles' => ['nullable', 'array', 'max:2'],
            'fee_option_roles.*' => [Rule::in(['participant', 'spectator'])],
            'fee_options.*.uuid' => ['nullable', 'string', 'max:64'],
            'fee_options.*.role' => ['nullable', Rule::in(['participant', 'spectator'])],
            'fee_options.*.label' => ['required_with:fee_options', 'string', 'max:80'],
            'fee_options.*.amount' => ['required_with:fee_options', 'numeric', 'min:0', 'max:1000000'],
            'late_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'late_fee_from' => ['nullable', 'date'],
            'max_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'prize' => ['nullable', 'string', 'max:120'],
            'sport' => ['nullable', Rule::in(array_keys($this->sports()))],
        ], $this->eventMessages());

        // Must belong to — or run — the club you're creating the event for.
        abort_unless(
            collect($this->clubsICanCreateFor($me))->contains('id', (int) $data['tenant_id']),
            403,
        );

        $this->assertMayBroadcast($me, (int) $data['tenant_id'], $data['scope'] ?? 'internal');

        // The owning package validates and normalises its own half of the payload.
        $type = $this->typeForInput($data);
        $data += $request->validate($type->validationRules(), $this->eventMessages());

        $event = ClubEvent::create($type->columnsFromInput($data) + [
            'tenant_id' => $data['tenant_id'],
            'created_by' => $me->id,
            'title' => $data['title'],
            'event_type' => $data['event_type'],
            'scope' => $data['scope'] ?? 'internal',
            'icon' => $this->typeIcon($data['event_type']),
            'date' => $data['date'],
            'end_date' => $data['end_date'] ?? null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'] ?? null,
            'weigh_in_at' => $data['weigh_in_at'] ?? null,
            'enrollment_starts_at' => $data['enrollment_starts_at'] ?? null,
            'enrollment_ends_at' => $data['enrollment_ends_at'] ?? null,
            'location' => $data['location'] ?? null,
            'gps_lat' => $data['gps_lat'] ?? null,
            'gps_long' => $data['gps_long'] ?? null,
            'location_url' => $data['location_url'] ?? null,
            'break_start' => $data['break_start'] ?? null,
            'break_end' => $data['break_end'] ?? null,
            'level' => $data['level'] ?? null,
            'description' => $data['description'] ?? null,
            'prize' => $data['prize'] ?? null,
            'max_capacity' => $data['max_capacity'] ?? null,
            'color' => $this->typeColor($data['event_type']),
            'status' => 'active',
            'is_archived' => false,
            // Priced in the host club's currency, from the amount that was
            // typed — never from a sentence assembled in the browser.
        ] + $this->feeColumns($data, Tenant::whereKey($data['tenant_id'])->value('currency') ?: 'BHD'));

        $this->syncFeeOptions($event, $data);
        $this->refreshFeeDisplay($event);

        $type->saveRelatedData($event, $data);

        // Announce it to everyone the event's scope reaches. Queued and capped —
        // a nationwide announcement never runs inline in this request.
        app(\App\Events\Support\EventNotifier::class)->fireOnce($event->fresh(), 'created');

        return response()->json([
            'success' => true,
            'message' => 'Event created 🎉',
            'redirect' => route('testcode.me.events.show', $event->uuid),
        ]);
    }

    public function edit(ClubEvent $event): View
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $clubs = $this->clubsICanCreateFor($me, $event);

        return view('eventlab::personal.event-create', ['clubs' => $clubs, 'mode' => 'edit', 'event' => $event] + $this->schemaPayload($event));
    }

    public function update(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'event_type' => ['required', Rule::in(array_keys($this->types()))],
            'scope' => ['nullable', Rule::in(array_keys($this->scopes()))],
            'date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'weigh_in_at' => ['nullable', 'date'],
            'enrollment_starts_at' => ['nullable', 'date'],
            'enrollment_ends_at' => ['nullable', 'date', 'after_or_equal:enrollment_starts_at', 'before_or_equal:date'],
            'location' => ['nullable', 'string', 'max:160'],
            'level' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'participant_free' => ['required', 'boolean'],
            'participant_fee' => ['nullable', 'string', 'max:40'],
            // The real price. The string above is the line the page shows.
            'participant_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'spectator_enabled' => ['required', 'boolean'],
            'spectator_fee' => ['nullable', 'string', 'max:40'],
            'spectator_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            /*
             * Multi-pricing (2026-09-06). The two amounts above stay the BASE
             * price; these are the named extras an entrant ticks on top, and
             * the penalty for entering late.
             *
             * Capped at 30 options because this is a list an organiser types by
             * hand — a request carrying five hundred of them is not a very
             * detailed competition, it is somebody probing the endpoint.
             */
            'fee_options' => ['nullable', 'array', 'max:30'],
            // Which roles the form is speaking for, so retiring an option is
            // never inferred from a role the request simply did not mention.
            'fee_option_roles' => ['nullable', 'array', 'max:2'],
            'fee_option_roles.*' => [Rule::in(['participant', 'spectator'])],
            'fee_options.*.uuid' => ['nullable', 'string', 'max:64'],
            'fee_options.*.role' => ['nullable', Rule::in(['participant', 'spectator'])],
            'fee_options.*.label' => ['required_with:fee_options', 'string', 'max:80'],
            'fee_options.*.amount' => ['required_with:fee_options', 'numeric', 'min:0', 'max:1000000'],
            'late_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'late_fee_from' => ['nullable', 'date'],
            'max_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'prize' => ['nullable', 'string', 'max:120'],
            'sport' => ['nullable', Rule::in(array_keys($this->sports()))],
            'tenant_id' => ['nullable', 'integer'],
        ]);

        /*
         * The HOST CLUB, which an edit may now change.
         *
         * The form has always shown the picker and the endpoint has always
         * ignored it, so choosing a different club looked like it worked and
         * silently did nothing. Accepting it is guarded exactly as creating an
         * event for that club is — the actor must be able to create for the
         * TARGET club, on top of already being able to manage this event.
         *
         * The event's fee CURRENCY is deliberately left alone: the fees on file
         * were quoted in it, and re-labelling an amount because the event moved
         * clubs would change what an entrant was asked to pay.
         */
        $host = isset($data['tenant_id']) ? (int) $data['tenant_id'] : (int) $event->tenant_id;

        if ($host !== (int) $event->tenant_id) {
            abort_unless(
                collect($this->clubsICanCreateFor($me, $event))->contains('id', $host),
                403,
            );
        }

        // Widening the scope on an EDIT is the same broadcast power as setting
        // it at creation — guard it identically, and against the club the event
        // is being moved TO.
        $this->assertMayBroadcast($me, $host, $data['scope'] ?? $event->scope);

        $type = $this->typeForInput($data, $event);
        $data += $request->validate($type->validationRules($event));

        /*
         * ⚠️ ABSENT IS NOT THE SAME AS BLANK — CLAUDE.md says it, and this
         * method was the counter-example.
         *
         * Every optional column was written as `$data['x'] ?? null`, so a
         * request that simply did not MENTION a field erased it. That is how
         * this event lost its end date (audit log, 2026-09-04 13:16): one
         * partial payload, and a stored value was gone with nothing on screen
         * to say so. Anything posting less than the whole form — an older
         * client, an integration, a screen rendering only some sections — was
         * quietly destroying data.
         *
         * Now a key the request SENT is written (including an explicit null or
         * an empty string, which is a deliberate clear), and a key it did not
         * send is left exactly as it is on the row.
         */
        $optional = [
            'end_date', 'end_time', 'weigh_in_at', 'enrollment_starts_at', 'enrollment_ends_at',
            'location', 'gps_lat', 'gps_long', 'location_url', 'break_start', 'break_end',
            'level', 'description', 'prize', 'max_capacity',
        ];

        $columns = [];

        foreach ($optional as $key) {
            if (array_key_exists($key, $data)) {
                $columns[$key] = $data[$key];
            }
        }

        $event->update($type->columnsFromInput($data, $event) + $columns + [
            'tenant_id' => $host,
            'title' => $data['title'],
            'event_type' => $data['event_type'],
            'scope' => $data['scope'] ?? $event->scope ?? 'internal',
            'icon' => $event->icon ?: ($this->typeIcon($data['event_type'])),
            'date' => $data['date'],
            'start_time' => $data['start_time'],
        ] + $this->feeColumns($data, EventFee::currency($event)));

        $this->syncFeeOptions($event, $data);
        $this->refreshFeeDisplay($event);

        // Divisions, fixtures, re-scheduling — whatever this type keeps outside
        // the event row.
        $type->saveRelatedData($event, $data);

        return response()->json([
            'success' => true,
            'message' => 'Event updated',
            'redirect' => route('testcode.me.events.show', $event->uuid),
        ]);
    }

    /**
     * The three fee columns, from what the form submitted.
     *
     * The form has always had the two things money needs — an amount typed into
     * a box, and the club's currency beside it — and then threw them away by
     * concatenating them into a sentence for the server to un-parse later. When
     * an amount comes through, it is authoritative and the display line is
     * composed FROM it, so the two can never disagree.
     *
     * When no amount comes through (an older form, an integration), the string
     * is kept exactly as sent — some of them are legitimately prose, like
     * "Qualified finalists" — and the model's saving hook derives what number it
     * can from it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function feeColumns(array $data, string $currency): array
    {
        $free = (bool) ($data['participant_free'] ?? false);
        $spectators = (bool) ($data['spectator_enabled'] ?? false);

        $pAmount = isset($data['participant_fee_amount']) ? (float) $data['participant_fee_amount'] : null;
        $sAmount = isset($data['spectator_fee_amount']) ? (float) $data['spectator_fee_amount'] : null;

        $columns = ['fee_currency' => $currency, 'spectator_enabled' => $spectators];

        /*
         * The late-entry penalty. Both halves are required to mean anything —
         * an amount with no date has no moment to start from, and a date with
         * no amount charges nothing — so a form that sends one without the
         * other clears both rather than leaving a half-armed rule on the event.
         */
        if (array_key_exists('late_fee_amount', $data) || array_key_exists('late_fee_from', $data)) {
            $lateAmount = ($data['late_fee_amount'] ?? null) !== null ? (float) $data['late_fee_amount'] : null;
            $lateFrom = $data['late_fee_from'] ?? null;

            $armed = $lateAmount !== null && $lateAmount > 0 && $lateFrom;

            $columns['late_fee_amount'] = $armed ? $lateAmount : null;
            $columns['late_fee_from'] = $armed ? $lateFrom : null;
        }

        // Participants.
        if ($free) {
            $columns['participant_fee'] = null;
            $columns['participant_fee_amount'] = null;
        } elseif ($pAmount !== null) {
            $columns['participant_fee'] = EventFee::display($pAmount, $currency);
            $columns['participant_fee_amount'] = $pAmount;
        } else {
            // String only — leave the amount alone so the model derives it.
            $columns['participant_fee'] = ($data['participant_fee'] ?? null) ?: __('events.fee_free');
        }

        // Spectators.
        if (! $spectators) {
            $columns['spectator_fee'] = null;
            $columns['spectator_fee_amount'] = null;
        } elseif ($sAmount !== null) {
            $columns['spectator_fee'] = EventFee::display($sAmount, $currency);
            $columns['spectator_fee_amount'] = $sAmount;
        } else {
            $columns['spectator_fee'] = ($data['spectator_fee'] ?? null) ?: __('events.fee_free');
        }

        return $columns;
    }

    /**
     * Put the event's priced extras in step with what the organiser just sent.
     *
     * Three rules, and the third is the one that matters:
     *
     *  1. A row with a uuid the event already owns is UPDATED. Matching on the
     *     public key rather than on the label means renaming "T-shirt" to
     *     "Event T-shirt" edits that option instead of retiring it and creating
     *     a stranger with the same price.
     *  2. A row with no uuid, or one this event does not own, is CREATED. The
     *     ownership check is not paranoia for its own sake: a uuid arrives from
     *     a browser, and without it an organiser could edit another event's
     *     prices by pasting an id.
     *  3. An option the form no longer lists is DEACTIVATED, never deleted. A
     *     competitor's frozen fee line points back at it for provenance, and a
     *     deleted row would orphan that — the same reason `club_product_variants`
     *     deactivates. It stops being offered either way; the difference is only
     *     visible to somebody asking what an old charge was for.
     *
     * A request that never mentions `fee_options` leaves them all alone, exactly
     * as the `$optional` block above leaves an unsent column alone. An organiser
     * editing an event from a client that has not been taught about pricing must
     * not silently retire every extra they sell.
     *
     * ⚠️ Retirement is scoped BY ROLE, and to the roles the request actually
     * spoke for. Without that, a form sending only participant rows retired
     * every spectator ticket type — so switching spectators off destroyed the
     * ticket list, and switching them back on showed an empty editor (it seeds
     * from ACTIVE rows), leaving the organiser to retype prices that were still
     * sitting in the table, dead. Roles are taken from `fee_option_roles` when
     * the form states them, and otherwise inferred from the rows themselves,
     * which keeps an older client from reaching past what it knows about.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncFeeOptions(ClubEvent $event, array $data): void
    {
        if (! array_key_exists('fee_options', $data)) {
            return;
        }

        $rows = is_array($data['fee_options'] ?? null) ? $data['fee_options'] : [];

        $existing = EventFeeOption::where('event_id', $event->id)->get()->keyBy('uuid');
        $kept = [];
        $sort = 0;

        // Which roles this request is authoritative for. An explicit list wins;
        // otherwise only the roles it actually sent rows for.
        $roles = array_values(array_intersect(
            EventFeeOption::ROLES,
            is_array($data['fee_option_roles'] ?? null)
                ? $data['fee_option_roles']
                : array_map(
                    fn ($r) => ($r['role'] ?? 'participant') === 'spectator' ? 'spectator' : 'participant',
                    $rows,
                ),
        ));

        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $role = ($row['role'] ?? 'participant') === 'spectator' ? 'spectator' : 'participant';
            $amount = round((float) ($row['amount'] ?? 0), 3);
            $uuid = (string) ($row['uuid'] ?? '');

            $option = $uuid !== '' ? $existing->get($uuid) : null;

            if ($option) {
                $option->update([
                    'label' => $label,
                    'amount' => $amount,
                    'role' => $role,
                    'is_active' => true,
                    'sort' => $sort++,
                ]);
            } else {
                $option = EventFeeOption::create([
                    'event_id' => $event->id,
                    'role' => $role,
                    'label' => $label,
                    'amount' => $amount,
                    'is_active' => true,
                    'sort' => $sort++,
                ]);
            }

            $kept[] = $option->id;
        }

        if ($roles === []) {
            return;
        }

        EventFeeOption::where('event_id', $event->id)
            ->whereIn('role', $roles)
            ->whereNotIn('id', $kept ?: [0])
            ->update(['is_active' => false]);
    }

    /**
     * Rewrite the display line now that the fee list is the price.
     *
     * `feeColumns()` composes `participant_fee` from the amount column, and that
     * column is deliberately zero for an event priced through its fee list — so
     * left alone, every such event would advertise itself as "Free" while
     * charging fifteen. The line has to be composed AFTER the options are
     * synced, because until then there is nothing to compose it from.
     *
     * `headline()` gives "From BHD 10" once a list can take the total higher,
     * and the plain price when there is only one row. A row-less paid event
     * legitimately says Free — that is the warning the form shows the organiser,
     * not something to paper over here.
     */
    private function refreshFeeDisplay(ClubEvent $event): void
    {
        $columns = [];

        foreach (['participant' => 'participant_fee', 'spectator' => 'spectator_fee'] as $role => $column) {
            // An event still priced the old way keeps its own line untouched.
            if (! EventFee::hasOptions($event, $role)) {
                continue;
            }

            if ($role === 'spectator' && ! $event->spectator_enabled) {
                continue;
            }

            $columns[$column] = EventFee::headline($event, $role);

            /*
             * The amount column is written in the same breath, and set to ZERO.
             *
             * The price of an event like this lives in `event_fee_options`; a
             * number left in the base column would be charged ON TOP of every
             * row somebody ticks. Zero rather than null, because null means
             * "nobody ever stated a price" and this event has stated one — it is
             * simply stated in the list.
             *
             * (ClubEvent's saving hook, which re-derives the amount by scraping
             * the display line, skips any event that has options — otherwise
             * "From BHD 5" put a phantom 5 back into the base.)
             */
            $columns[$role === 'spectator' ? 'spectator_fee_amount' : 'participant_fee_amount'] = 0;
        }

        if ($columns !== []) {
            $event->forceFill($columns)->save();
        }
    }

    /** Set / update the event's winners (podium). Manager only. */
    public function setResults(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        // Types that derive their podium from their own engine refuse hand-entry
        // outright — a typed-in result must never contradict the recorded play.
        if (! $this->typeFor($event)->allowsManualResults()) {
            return response()->json([
                'success' => false,
                'message' => __('events.results_derived_from_engine'),
            ], 422);
        }

        $data = $request->validate([
            'results' => ['present', 'array', 'max:20'],
            'results.*.place' => ['nullable', 'integer', 'min:1', 'max:50'],
            'results.*.name' => ['nullable', 'string', 'max:120'],
            'results.*.prize' => ['nullable', 'string', 'max:120'],
        ]);

        // Normalise: drop blank names, default missing places by order, sort.
        $results = collect($data['results'])
            ->map(fn ($r, $i) => [
                'place' => (int) ($r['place'] ?? 0) ?: ($i + 1),
                'name' => trim((string) ($r['name'] ?? '')),
                'prize' => trim((string) ($r['prize'] ?? '')) ?: null,
            ])
            ->filter(fn ($r) => $r['name'] !== '')
            ->sortBy('place')->values()->all();

        $event->update(['results' => $results ?: null]);

        return response()->json([
            'success' => true,
            'message' => $results ? 'Winners saved 🏆' : 'Winners cleared',
            'results' => $results,
        ]);
    }

    /** Mark an event cancelled (kept visible, flagged). */
    public function cancelEvent(ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $event->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Event cancelled',
            'redirect' => route('testcode.me.events'),
        ]);
    }

    /** Permanently delete an event (cascades registrations, categories, matches). */
    public function destroy(ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted',
            'redirect' => route('testcode.me.events'),
        ]);
    }

    public function register(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertEligible($event, $me);

        // Finished events are view-only.
        if ($event->hasEnded()) {
            return response()->json(['success' => false, 'message' => 'This event has ended.'], 422);
        }

        // Moderation: blocked/blacklisted members can't take part.
        if ($this->isBanned($event, $me->id)) {
            return response()->json(['success' => false, 'code' => 'banned', 'message' => 'You can’t register for this event.'], 403);
        }

        // Once it has started, the entry list IS the competition being run — the
        // draw is cut from it and the mats are working through it. Nobody new
        // joins, by either door.
        //
        // Someone already entered is NOT refused here: this same endpoint is how
        // they upload a receipt, and paying at the venue on the day is the most
        // ordinary thing there is.
        $alreadyIn = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $me->id)->where('role', 'participant')->exists();

        if (($event->hasStarted() || $event->isOverdueToStart()) && ! $alreadyIn) {
            return response()->json([
                'success' => false,
                'code' => 'started',
                'message' => __('events.entry_event_started'),
            ], 422);
        }

        $type = $this->typeFor($event);

        $data = $request->validate([
            'category_id' => ['nullable', 'integer', Rule::exists('event_categories', 'id')->where('event_id', $event->id)],
            // Optional manual proof-of-payment (base64 data-URI). No gateway — the
            // club admin approves it elsewhere; here we only RECORD it.
            'payment_proof' => ['nullable', 'string', 'starts_with:data:image'],
            // The club they compete FOR. Claimed, never approved — but only from
            // clubs they actually belong to, checked below against the server's
            // own list rather than the one the form was rendered with.
            'representing_tenant_id' => ['nullable', 'integer'],
            /*
             * The priced extras they ticked, as option UUIDS ONLY.
             *
             * The form never sends a price and this never reads one: what an
             * option costs is the event's business, looked up server-side by
             * EventFee::quote() against the event's own active rows. A key that
             * names nothing, an inactive option, or one belonging to another
             * event is dropped there rather than refused here — a stale form
             * somebody left open overnight must not become an error page
             * between them and entering.
             *
             * Capped at 30, matching what the organiser's form can create.
             */
            'fee_options' => ['nullable', 'array', 'max:30'],
            'fee_options.*' => ['string', 'max:64'],
        ]);

        // Participation-by-qualification events can't be self-joined.
        if (str_contains(strtolower((string) $event->participant_fee), 'qualified')) {
            return response()->json(['success' => false, 'message' => 'Entry to this event is by qualification only.'], 422);
        }

        // Enrollment window.
        $today = now()->startOfDay();
        if ($event->enrollment_starts_at && $today->lt($event->enrollment_starts_at)) {
            return response()->json(['success' => false, 'message' => 'Registration opens '.$event->enrollment_starts_at->format('M j').'.'], 422);
        }
        if ($event->enrollment_ends_at && $today->gt($event->enrollment_ends_at)) {
            return response()->json(['success' => false, 'message' => 'Registration closed on '.$event->enrollment_ends_at->format('M j').'.'], 422);
        }

        // Capacity guard (participants only).
        if ($event->max_capacity && $event->participantRegistrations()->count() >= $event->max_capacity
            && ! $event->registrations()->where('user_id', $me->id)->where('role', 'participant')->exists()) {
            return response()->json(['success' => false, 'message' => 'This event is full.'], 422);
        }

        // The owning package decides whether this member may compete and, when
        // the type is divisioned, which division they belong in. It classifies
        // them from their own profile — a member never picks their own class.
        $existing = ClubEventRegistration::where('event_id', $event->id)->where('user_id', $me->id)->first();
        $decision = $type->enrolmentGate($event, $me, $existing);

        if (! $decision->allowed) {
            return response()->json([
                'success' => false,
                'code' => $decision->code,
                'spectator' => $decision->offerSpectator,
                'message' => $decision->message,
            ], 422);
        }

        $categoryId = $decision->category?->id ?? ($data['category_id'] ?? null);
        $division = $decision->category?->name;
        $weight = $decision->weight;

        /*
         * What THIS entry costs — the quote, priced before the row is written
         * so the `paid` flag below can be derived from it.
         *
         * Three things depend on getting the moment and the selection right:
         *
         *  · `$at` is the EXISTING entry's registration time when there is one.
         *    This endpoint is also how somebody uploads a receipt or changes the
         *    club they represent, and re-pricing at `now()` would hand a
         *    competitor who entered on time a late penalty they never incurred
         *    the first time they came back to it.
         *  · The selection is only taken from the request when the request
         *    actually CARRIED one. The join sheet posts an empty list when it
         *    is re-opened for a receipt upload, and committing that would
         *    silently delete the extras they had already agreed to pay for —
         *    the same guard `EntryService::enter()` makes.
         *  · `paid` comes from the TOTAL, not from `isPaid()`, which only knows
         *    about the base fee. An event priced entirely as options ("Gi 15 /
         *    No-Gi 15", no base) marked every entrant settled on the way in.
         */
        $keepExistingOptions = $existing && ! array_key_exists('fee_options', $data);

        $quote = $keepExistingOptions
            ? ['total' => EventFee::charged($existing, $event), 'currency' => EventFee::currency($event), 'lines' => []]
            : EventFee::quote(
                $event,
                'participant',
                $data['fee_options'] ?? [],
                $existing?->registered_at,
            );

        $paidFee = $quote['total'] > 0;

        // Optional proof-of-payment (paid participant events only). Manual flow —
        // we record the member's proof on the PRIVATE disk and leave paid=false so
        // the club admin still has to approve it. Registration succeeds either way
        // (the member may pay at the venue instead).
        $proofPath = $existing?->payment_proof;
        $storedNewProof = false;
        if ($paidFee && ! empty($data['payment_proof'])) {
            $stored = $this->storeBase64Image(
                $data['payment_proof'],
                'event-payment-proofs/'.$event->tenant_id.'/'.$event->id,
                'reg_'.$me->id.'_'.time(),
                'local',
            );
            if (! $stored) {
                return response()->json(['success' => false, 'message' => 'Please upload a valid image (JPG or PNG).'], 422);
            }
            // Replace any previous proof file so we don't orphan it in storage.
            if ($proofPath && $proofPath !== $stored) {
                rescue(fn () => \Illuminate\Support\Facades\Storage::disk('local')->delete($proofPath), null, false);
            }
            $proofPath = $stored;
            $storedNewProof = true;
        }

        // Which club they compete for. A member may only claim a club they are
        // an ACTIVE member of; anything else is silently dropped rather than
        // trusted, and no claim at all means competing unattached.
        $entryService = app(EntryService::class);
        $claimable = collect($entryService->representableClubs($me))->pluck('id')->all();
        $representing = $data['representing_tenant_id'] ?? null;

        if ($representing !== null && ! in_array((int) $representing, $claimable, true)) {
            return response()->json([
                'success' => false,
                'message' => __('events.claim_not_your_club'),
            ], 422);
        }

        // Nothing chosen and only one club to choose from — there was no
        // decision to make, so do not leave the sheet unattached by accident.
        if ($representing === null && ! $existing?->representing_tenant_id && count($claimable) === 1) {
            $representing = $claimable[0];
        }

        $representing = $representing !== null ? (int) $representing : $existing?->representing_tenant_id;
        $claimChanged = $representing && (int) ($existing?->representing_tenant_id ?? 0) !== $representing;

        $registration = ClubEventRegistration::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $me->id],
            [
                'role' => 'participant',
                'status' => 'joined',
                'paid' => ! $paidFee,        // free → instantly "settled"; paid → awaiting approval
                'category_id' => $categoryId,
                'weight' => $weight,
                'payment_proof' => $paidFee ? $proofPath : null,
                'registered_at' => now(),
                'entry_channel' => 'individual',
                'representing_tenant_id' => $representing,
                // A fresh claim starts unrejected — the club gets to look at it
                // again rather than inherit its verdict on an older one.
                'club_disowned_at' => $claimChanged ? null : $existing?->club_disowned_at,
            ]
        );

        /*
         * Freeze what this entry costs, as its own lines.
         *
         * AFTER the row exists, because a line belongs to a registration; and
         * on every pass through here rather than only on the first, because
         * this endpoint is also how somebody changes their mind about what they
         * are entering. `commit()` replaces rather than appends, so re-running
         * it leaves one correct set of lines instead of two overlapping ones.
         *
         * What is recorded is what they were CHARGED. Whether they have PAID is
         * the separate question `paid` above answers, and neither one is derived
         * from the other.
         */
        if (! $keepExistingOptions) {
            EventFee::commit($registration, $quote);
        }

        // The club finds out it is being represented. It cannot pre-approve
        // this; it can only disown it afterwards, so being told is the point.
        if ($claimChanged) {
            $entryService->notifyClaim($event, $me, $representing);
        }

        // The entrant set changed — let the package re-derive whatever depends
        // on it (a provisional bracket, a fixture list).
        $type->onEntrantsChanged($event, $decision->category);

        $note = $division ? ' · '.$division : '';

        return response()->json([
            'success' => true,
            'message' => ($storedNewProof
                ? __('events.reg_proof_sent')
                : ($paidFee
                    ? __('events.reg_pay_at_club', ['fee' => $event->participant_fee])
                    : __('events.reg_confirmed'))).$note,
            'role' => 'participant',
            'division' => $division,
            'pending_payment' => $paidFee && (bool) $proofPath,   // proof recorded, awaiting approval
            'going' => $event->participantRegistrations()->count(),
        ]);
    }

    /**
     * Run a manager action the owning package defines (generating a draw,
     * closing weigh-in, publishing a table). The controller neither knows nor
     * validates what the action does — the package owns it.
     */
    public function performAction(Request $request, ClubEvent $event, string $action): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $type = $this->typeFor($event);

        // Deny by default: only an action the package currently offers may run.
        $offered = collect($type->availableActions($event))->pluck('action')->all();
        if (! in_array($action, $offered, true)) {
            return response()->json(['success' => false, 'message' => __('events.action_unavailable')], 422);
        }

        $result = $type->performAction($event, $action, $request->all());

        return response()->json($result + [
            'redirect' => route('testcode.me.events.bracket', $event->uuid),
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Record the outcome of one unit of competition — a bout, a fixture, a test.
     * The package propagates it (advancing a winner, updating a table) and
     * returns what changed so the UI patches in place.
     */
    public function recordOutcome(Request $request, ClubEvent $event, int $unit): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $payload = $request->validate([
            'winner' => ['nullable', Rule::in(['a', 'b', ''])],
            'a_score' => ['nullable', 'string', 'max:16'],
            'b_score' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', Rule::in(['upcoming', 'live', 'done'])],
        ]);

        $changed = $this->typeFor($event)->recordOutcome($event, $unit, $payload);

        return response()->json(['success' => true, 'message' => __('events.outcome_saved')] + $changed);
    }

    public function ticket(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertEligible($event, $me);

        if ($event->hasEnded()) {
            return response()->json(['success' => false, 'message' => 'This event has ended.'], 422);
        }

        // Moderation: a ban bars spectating too.
        if ($this->isBanned($event, $me->id)) {
            return response()->json(['success' => false, 'code' => 'banned', 'message' => 'You can’t attend this event.'], 403);
        }

        abort_unless($event->spectator_enabled, 422, 'This event has no spectator tickets.');

        // Which ticket they chose, when the event sells more than one kind.
        // UUIDs only — the price is read from the event's own rows.
        $ticketData = $request->validate([
            'fee_options' => ['nullable', 'array', 'max:30'],
            'fee_options.*' => ['string', 'max:64'],
        ]);

        $existingTicket = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $me->id)->first();

        // A re-open that names no ticket keeps the one already agreed, rather
        // than replacing it with nothing — same guard as the entry door.
        $keepTicket = $existingTicket && ! array_key_exists('fee_options', $ticketData);

        // No late penalty on this door: turning up to watch on the day is the
        // ordinary way anybody watches sport, not a late entry.
        $quote = $keepTicket
            ? ['total' => EventFee::charged($existingTicket, $event), 'currency' => EventFee::currency($event), 'lines' => []]
            : EventFee::quote($event, 'spectator', $ticketData['fee_options'] ?? []);

        // Settled only when there is genuinely nothing to pay for THIS ticket —
        // `isPaid()` reads the base fare alone and would wave through an event
        // whose only price is a ringside option.
        $paidFee = $quote['total'] > 0;

        $registration = ClubEventRegistration::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $me->id],
            ['role' => 'spectator', 'status' => 'joined', 'paid' => ! $paidFee, 'registered_at' => now()],
        );

        if (! $keepTicket) {
            EventFee::commit($registration, $quote);
        }

        // A ticket with options priced into it must quote the TOTAL, not the
        // event's base line — otherwise the door is told one number and the
        // spectator paid another.
        $ticketLine = $quote['total'] > 0
            ? EventFee::display($quote['total'], $quote['currency'])
            : $event->spectator_fee;

        return response()->json([
            'success' => true,
            'message' => $paidFee || $quote['total'] > 0
                ? 'Ticket booked · '.$ticketLine.' — show this in the app at the door'
                : "You're on the guest list 🎟️",
            'role' => 'spectator',
            'spectators' => $event->registrations()->where('role', 'spectator')->count(),
        ]);
    }

    public function cancel(ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $reg = ClubEventRegistration::where('event_id', $event->id)->where('user_id', $me->id)->first();

        if ($reg) {
            // Registration is final — no self-cancel once you've joined.
            $fee = $reg->role === 'spectator' ? $event->spectator_fee : $event->participant_fee;
            $hasFee = EventFee::isPaid($event, $reg->role === 'spectator' ? 'spectator' : 'participant');

            return response()->json([
                'success' => false,
                'message' => $hasFee
                    ? "Your spot is confirmed and final — the {$fee} fee is still due at the club."
                    : 'Your spot is confirmed — registrations are final and can’t be cancelled.',
            ], 422);
        }

        return response()->json(['success' => true, 'message' => 'Nothing to cancel', 'role' => null]);
    }

    /* ===================== Run day — my next bout ===================== */

    /**
     * The athlete's countdown screen: which mat, which bout, how many bouts
     * still ahead, roughly how long. The bout count leads — a mat that runs slow
     * makes any fixed clock time a lie within the first hour.
     */
    public function nextUp(ClubEvent $event, Request $request, EntryService $entries): View|JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $type = $this->typeFor($event);
        $mine = $type->nextUp($event, $me);

        // A coach sees the same data for their whole squad, soonest first.
        $squad = [];
        if (method_exists($type, 'squadNextUp') && ($clubIds = $entries->administeredClubIds($me))) {
            $squad = $type->squadNextUp($event, $this->athletesOfClubs($clubIds));
        }

        $payload = [
            'e' => ['key' => $event->uuid, 'title' => $event->title, 'color' => $event->color ?: '#7c3aed'],
            'mine' => $mine,
            'squad' => $squad,
        ];

        if ($request->expectsJson()) {
            return response()->json(['success' => true] + $payload);
        }

$isMobile = true;   // events are mobile-only — see show()

        return view($isMobile ? 'eventlab::personal.mobile.event-next-up' : 'eventlab::personal.desktop.event-next-up', $payload);
    }

    /** @return array<int, int> */
    private function athletesOfClubs(array $clubIds): array
    {
        return \App\Members\Models\User::whereHas('memberClubs', fn ($q) => $q
            ->whereIn('tenants.id', $clubIds)->where('memberships.status', 'active'))
            ->pluck('id')->map('intval')->all();
    }

    /**
     * The venue board — a hall screen, not a personal page.
     *
     * Deliberately impersonal: mats, bout numbers and the two names on each
     * bout, nothing tied to a viewer. It refreshes itself on the realtime
     * channel AND polls on a slow timer, because arena wifi drops and a board
     * that silently freezes is worse than one that is a few seconds stale.
     */
    public function board(ClubEvent $event, Request $request): View|JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $type = $this->typeFor($event);
        $mats = method_exists($type, 'board')
            ? $type->board($event, $request->query('mat'))
            : [];

        $payload = [
            // The board wears the event's own colour now, the same one the
            // poster uses — so the payload has to carry it.
            'e' => ['key' => $event->uuid, 'title' => $event->title, 'color' => $event->color ?: '#7c3aed'],
            'mats' => $mats,
        ];

        return $request->expectsJson()
            ? response()->json(['success' => true] + $payload)
            : view('eventlab::personal.event-board', $payload);
    }

    /* ===================== Club / coach entry ===================== */

    /**
     * The athletes this coach may enter, each with its verdict already worked
     * out — who is enterable, who is already in, and why anyone is not.
     */
    public function entryRoster(Request $request, ClubEvent $event, EntryService $entries): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        abort_if($entries->administeredClubIds($me) === [] && ! $me->isSuperAdmin(), 403);

        // Name, email or phone. The search runs against the actor's OWN club
        // members only, so it can narrow a squad list without ever becoming a
        // lookup for the platform's user table.
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);

        $result = $entries->roster($event, $me, $data['q'] ?? null);

        return response()->json(['success' => true] + $result);
    }

    /**
     * Enter a squad in one go.
     *
     * Every athlete is checked exactly as self-entry checks them — this is a
     * convenience for coaches, never a way around the rules. Partial success is
     * normal, so the response reports each athlete individually.
     */
    public function storeEntries(Request $request, ClubEvent $event, EntryService $entries): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:200'],
            'user_ids.*' => ['integer'],
            /*
             * What each athlete is entering, keyed BY ATHLETE ID.
             *
             * Per athlete rather than per squad because a real squad is mixed —
             * ten adults and four juniors, some in Gi and some in No-Gi — and a
             * single selection for everyone could not say that. The coach's
             * sheet offers an "apply to all" shortcut, which fills this map
             * rather than replacing it.
             *
             * UUIDs only; the prices are the event's, read server-side.
             */
            'fee_options' => ['nullable', 'array', 'max:200'],
            'fee_options.*' => ['array', 'max:30'],
            'fee_options.*.*' => ['string', 'max:64'],
        ]);

        abort_if($entries->administeredClubIds($me) === [] && ! $me->isSuperAdmin(), 403);

        $result = $entries->enterMany($event, $me, $data['user_ids'], $data['fee_options'] ?? []);

        // A squad landing at once changes the draw everyone else is looking at.
        if ($result['entered']) {
            $this->pushEventRefresh($event);
        }

        return response()->json([
            'success' => true,
            'message' => __('events.entry_result', [
                'entered' => count($result['entered']),
                'rejected' => count($result['rejected']),
            ]),
        ] + $result);
    }

    /**
     * Self-entries claiming a club this coach runs, and the state of each.
     *
     * Read-only company for the entry roster: the same sheet answers "who can I
     * enter" and "who has put my club's name on themselves".
     */
    public function entryClaims(ClubEvent $event, EntryService $entries): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        abort_if($entries->administeredClubIds($me) === [], 403);

        return response()->json([
            'success' => true,
            'claims' => $entries->claims($event, $me),
        ]);
    }

    /**
     * Turn this event's public page on or off.
     *
     * The feature flag, held per event and defaulted OFF: nothing is published
     * because a feature shipped, only because an organiser decided to publish
     * THIS event. Only whoever may manage the event may decide that — a coach
     * with entry authority cannot publish somebody else's competition.
     */
    public function setEntryMode(Request $request, ClubEvent $event, EventAccess $access): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        abort_if(! $access->canManage($event, $me), 403);

        $data = $request->validate([
            'entry_mode' => ['required', 'in:members,public'],
        ]);

        $event->update(['entry_mode' => $data['entry_mode']]);

        $public = $data['entry_mode'] === 'public';

        return response()->json([
            'success' => true,
            'message' => $public ? __('events.public_turned_on') : __('events.public_turned_off'),
            'entry_mode' => $data['entry_mode'],
            'url' => $public ? route('testcode.e', $event->uuid) : null,
        ]);
    }

    /**
     * The picture the public cover opens onto.
     *
     * The cover page (`entry/public/partials/cover`) lays `images[0]` in
     * full-bleed behind the event's name, so "the cover picture" IS the first
     * image — this endpoint replaces that one entry and leaves any others the
     * organiser has attached alone.
     *
     * Three things make it safe rather than "an upload endpoint":
     *
     *  - `StoresBase64Images` sniffs the REAL bytes and assigns the extension
     *    from a whitelist, so a file claiming to be a PNG in its data-URI
     *    header cannot land as anything executable. SVG is refused (it can
     *    carry script).
     *  - The folder is built by `StoragePath::eventBranding()` from the event's
     *    uuid, never from client input, and the filename is generated. That
     *    path is also the ONLY thing `FileAccess::event()` will serve to a
     *    stranger, and only while the organiser has the event public.
     *  - Only somebody who may MANAGE the event may change its face.
     */
    public function setCover(Request $request, ClubEvent $event, EventAccess $access): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        abort_if(! $access->canManage($event, $me), 403);

        $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image/'],
        ]);

        $path = $this->storeBase64Image(
            $request->input('image'),
            \App\Support\StoragePath::eventBranding($event),
            'cover-'.Str::uuid()->toString(),
            'local',
        );

        if ($path === null) {
            return response()->json([
                'success' => false,
                'message' => __('events.cover_rejected'),
            ], 422);
        }

        $images = is_array($event->images) ? array_values($event->images) : [];
        $old    = $images[0] ?? null;
        $images[0] = $path;

        $event->update(['images' => $images]);

        /* Delete the replaced file only if it was one of OURS — a picture under
         * this event's own branding folder. The seeded events point at images
         * living in the club's gallery, and those rows are still using them:
         * unlinking one here would blank a picture somewhere else. */
        $this->forgetOldCover($event, $old);

        $this->pushCoverChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('events.cover_saved'),
            'photo'   => file_url($path),
            // The shared cropper widget patches the page from `res.url`; the
            // same value under both names so either consumer works.
            'url'     => file_url($path),
        ]);
    }

    /** Take the picture off, back to the ground drawn from the event's colour. */
    public function clearCover(ClubEvent $event, EventAccess $access): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        abort_if(! $access->canManage($event, $me), 403);

        $images = is_array($event->images) ? array_values($event->images) : [];
        $old    = array_shift($images);

        $event->update(['images' => $images]);

        $this->forgetOldCover($event, $old);

        $this->pushCoverChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('events.cover_cleared'),
            'photo'   => null,
        ]);
    }

    /**
     * Files first, then the record — but only files this event owns.
     *
     * *Delete Files Before Records* applied narrowly: the path has to sit under
     * this event's branding folder before anything is unlinked, because an
     * event may legitimately point at a picture that belongs to the club's
     * gallery and is shared with other rows.
     */
    private function forgetOldCover(ClubEvent $event, ?string $old): void
    {
        if (! $old || $old === '') {
            return;
        }

        $mine = rtrim(\App\Support\StoragePath::eventBranding($event), '/').'/';

        if (! str_starts_with($old, $mine)) {
            return;
        }

        try {
            Storage::disk('local')->delete($old);
        } catch (\Throwable) {
            // Best effort: a missing file must never block the change.
        }
    }

    /**
     * Nudge the other organisers looking at this event.
     *
     * A refresh signal, not the picture: who may see this event's console
     * differs per person, so each one re-fetches what THEY may see rather than
     * being handed a payload built for somebody else (Realtime rule §4).
     */
    private function pushCoverChanged(ClubEvent $event): void
    {
        rescue(function () use ($event) {
            if (! \Realtime()->enabled()) {
                return;
            }

            $ids = app(\App\Events\Support\AudienceResolver::class)->forEvent($event);

            if (! $ids) {
                return;
            }

            // publishMany takes pre-built topics so the whole fan-out goes over
            // one broker connection.
            \Realtime()->publishMany(array_map(fn (int $uid) => [
                'topic' => \Realtime()->userTopic($uid, 'events'),
                'payload' => ['action' => 'cover', 'event' => $event->uuid],
            ], $ids));
        }, null, false);
    }

    /* ---------------- Groups: building a bracket by hand ------------------
     *
     * A division is a GROUP of entrants that a bracket is then cut from. Until
     * now one could only be created on the event form, named by hand, and filled
     * by whatever the package's own enrolment decided. That works while the
     * package cuts the divisions; it is useless when an organiser wants to build
     * one on the day — two brackets merged because four people did not show, a
     * strong junior moved up, a group invented for a category nobody planned.
     *
     * These four endpoints are that. They are SHARED, not part of any sport's
     * package: a group of people and a range to sort them by means the same
     * thing in jiu-jitsu, karate and taekwondo. What stays private to a package
     * is what the range is USUALLY set to — its weight tables and belt ladder.
     *
     * Every one of them scopes the division to the event in the URL, so a
     * division id belonging to another event resolves to nothing. The event's
     * own uuid is the unguessable part of the address; a division id is only
     * meaningful inside it, which is the same argument bout() already makes for
     * match numbers.
     */

    /** Create a group by hand. */
    /**
     * The weight-classes page — same screen as the main app's, sandbox twin.
     */
    public function divisions(ClubEvent $event, Request $request): View
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $myReg = $this->myRegistrations($me->id, collect([$event->id]));

        return view('eventlab::personal.event-divisions', [
            'e' => $this->eventView($event, $me->id, $myReg, true),
        ]);
    }

    public function storeDivision(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $this->validateDivision($request, $event);

        $division = EventCategory::create($data + [
            'event_id' => $event->id,
            'status' => 'enrolling',
            'sort_order' => (int) $event->categories()->max('sort_order') + 1,
        ]);

        $this->pushDivisionsChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('events.division_created'),
            'division' => $this->divisionPayload($division),
        ]);
    }

    /** Rename a group, or change what it is for. */
    public function updateDivision(Request $request, ClubEvent $event, int $division): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $category = $this->divisionOf($event, $division);
        $category->update($this->validateDivision($request, $event, $category));

        $this->pushDivisionsChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('events.division_saved'),
            'division' => $this->divisionPayload($category->fresh()),
        ]);
    }

    /**
     * Delete a group.
     *
     * Only an EMPTY one, and only while it has no bouts — the same rule
     * SyncsDivisions applies when the event form drops a division. A group with
     * people in it is somebody's work, and a draw that has been cut is somebody's
     * competition; neither disappears behind one tap.
     */
    public function destroyDivision(Request $request, ClubEvent $event, int $division): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $category = $this->divisionOf($event, $division);

        if ($category->registrations()->exists() || $category->matches()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('events.division_not_empty'),
            ], 422);
        }

        $category->delete();
        $this->pushDivisionsChanged($event);

        return response()->json(['success' => true, 'message' => __('events.division_deleted')]);
    }

    /**
     * Who could go in this group, and how each one sits against its range.
     *
     * EVERY participant in the event, never a filtered subset: the filtering is
     * the client's job precisely because the organiser must be able to reach
     * past it. Someone already in another division is listed too — moving them
     * is the common case — and says so, so nobody is taken out of a bracket by
     * accident.
     */
    public function divisionCandidates(Request $request, ClubEvent $event, int $division): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanArrange($event, $me);

        $category = $this->divisionOf($event, $division);
        $range = $category->range();

        $names = $event->categories()->pluck('name', 'id');

        $people = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->with(['user:id,full_name,name,gender,birthdate,profile_picture,profile_picture_is_public,updated_at'])
            ->get()
            ->map(function (ClubEventRegistration $r) use ($range, $category, $names) {
                $judged = $range->judge($r);

                return [
                    'competitor_id' => $r->id,
                    'name' => $r->user?->full_name ?: $r->user?->name ?: __('events.athlete'),
                    'gender' => $r->user?->gender ?: null,
                    'age' => $range->ageOf($r),
                    'weight' => $r->weight === null ? null : (float) $r->weight,
                    'belt' => $r->belt_colour ?: null,
                    // The face taken at the desk for THIS event first, then the
                    // member's own picture — and only when they made it public
                    // (CLAUDE.md → Profile Pictures Are Portrait 3:4). No photo
                    // is not a fault; the client draws a gender avatar.
                    'photo' => $r->photo
                        ? file_url($r->photo)
                        : (($r->user?->profile_picture && $r->user->profile_picture_is_public)
                            ? file_url($r->user->profile_picture).'?v='.($r->user->updated_at?->timestamp ?? 0)
                            : null),
                    'country' => $r->countryCode(),
                    // Where they are now: this group, another one (named), or nowhere.
                    'division_id' => $r->category_id,
                    'division_name' => $r->category_id && $r->category_id !== $category->id
                        ? ($names[$r->category_id] ?? null) : null,
                    'here' => $r->category_id === $category->id,
                    'fit' => $judged['fit'],          // in | out | unknown
                    'misses' => $judged['misses'],
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()->all();

        return response()->json([
            'success' => true,
            'division' => $this->divisionPayload($category),
            'people' => $people,
        ]);
    }

    /**
     * Put people in this group, or take them out of it.
     *
     * Deliberately accepts an entrant the range would reject. An organiser
     * moving a fourteen-year-old up into the adults, or a lighter athlete into a
     * heavier bracket to give them a fight at all, is doing their job — the
     * range narrows the picker and marks the exception, it does not forbid it.
     * See App\Events\Support\DivisionRange.
     *
     * Moving someone between groups takes them out of the bracket they were in:
     * a bout cannot keep a competitor the division no longer holds. The draw is
     * re-cut from what is left, by the package.
     */
    public function updateDivisionMembers(Request $request, ClubEvent $event, int $division): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanArrange($event, $me);

        if ($event->hasStarted() || $event->hasEnded()) {
            return response()->json(['success' => false, 'message' => __('events.draw_final')], 422);
        }

        $category = $this->divisionOf($event, $division);

        $data = $request->validate([
            'add' => ['nullable', 'array', 'max:256'],
            'add.*' => ['integer'],
            'remove' => ['nullable', 'array', 'max:256'],
            'remove.*' => ['integer'],
        ]);

        // Scoped to THIS event's participants — an id from another event, or a
        // spectator's registration, resolves to nothing rather than being moved.
        $scope = fn (array $ids) => ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->whereIn('id', $ids)->pluck('id')->all();

        $add = $scope($data['add'] ?? []);
        $remove = $scope($data['remove'] ?? []);

        $touched = collect();

        DB::transaction(function () use ($add, $remove, $category, $event, &$touched) {
            if ($add) {
                // The divisions they are leaving, so their old brackets are
                // re-cut too — not just the one they arrive in.
                $touched = ClubEventRegistration::whereIn('id', $add)
                    ->pluck('category_id')->filter()->unique();

                ClubEventRegistration::whereIn('id', $add)->update(['category_id' => $category->id]);
            }

            if ($remove) {
                ClubEventRegistration::whereIn('id', $remove)
                    ->where('category_id', $category->id)
                    ->update(['category_id' => null]);
            }

            // A competitor who has left a division cannot stay in its draw.
            $moved = array_merge($add, $remove);

            if ($moved) {
                EventMatch::where('event_id', $event->id)
                    ->whereIn('a_competitor_id', $moved)
                    ->where('category_id', '!=', $category->id)
                    ->update(['a_competitor_id' => null, 'a_name' => null]);

                EventMatch::where('event_id', $event->id)
                    ->whereIn('b_competitor_id', $moved)
                    ->where('category_id', '!=', $category->id)
                    ->update(['b_competitor_id' => null, 'b_name' => null]);
            }
        });

        // Let the package re-derive every division that changed shape.
        $type = $this->typeFor($event);

        foreach ($touched->push($category->id)->unique() as $id) {
            $type->onEntrantsChanged($event, $event->categories()->find($id));
        }

        $this->pushDivisionsChanged($event);

        return $this->divisionCandidates($request, $event, $division);
    }

    /* ---------------- Group plumbing ---------------- */

    /** This event's division, or 404 — never another event's. */
    private function divisionOf(ClubEvent $event, int $division): EventCategory
    {
        $category = $event->categories()->find($division);

        abort_unless($category, 404);

        $category->setRelation('event', $event);

        return $category;
    }

    /**
     * The rules a group's own fields are held to.
     *
     * The range is a guide, so the only things enforced are that it makes SENSE:
     * a name, a gender from the canonical vocabulary, and bounds that are the
     * right way round. Nothing here says who may be put in it.
     */
    private function validateDivision(Request $request, ClubEvent $event, ?EventCategory $existing = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'weight_class' => ['nullable', 'string', 'max:40'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'min_age' => ['nullable', 'integer', 'min:2', 'max:100'],
            'max_age' => ['nullable', 'integer', 'min:2', 'max:100', 'gte:min_age'],
            'min_weight' => ['nullable', 'numeric', 'min:10', 'max:300'],
            'max_weight' => ['nullable', 'numeric', 'min:10', 'max:300', 'gte:min_weight'],
        ], [
            'max_age.gte' => __('events.division_age_backwards'),
            'max_weight.gte' => __('events.division_weight_backwards'),
        ]);
    }

    /** One group, as the board and the picker read it. */
    private function divisionPayload(EventCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'weight_class' => $category->weight_class ?: null,
            'status' => $category->status,
            'entrants' => $category->registrations()->where('role', 'participant')->count(),
            'matches' => $category->matches()->count(),
            'range' => $category->range()->toArray(),
        ];
    }

    /**
     * The divisions moved. A refresh signal rather than the picture: a bracket
     * renders differently for every viewer (their own bouts are marked, only an
     * organiser sees the bench), so each one re-fetches what THEY may see.
     */
    private function pushDivisionsChanged(ClubEvent $event): void
    {
        rescue(function () use ($event) {
            if (! \Realtime()->enabled()) {
                return;
            }

            $ids = app(\App\Events\Support\AudienceResolver::class)->forEvent($event);

            if (! $ids) {
                return;
            }

            \Realtime()->publishMany(array_map(fn (int $uid) => [
                'topic' => \Realtime()->userTopic($uid, 'events'),
                'payload' => ['action' => 'draw', 'event' => $event->uuid],
            ], $ids));
        }, null, false);
    }

    /* ---------------- Entering someone not listed (Door B) ----------------
     *
     * Documentation/EVENTS-PUBLIC-ENTRY.md. A coach types a NAME and nothing
     * else; the athlete supplies the rest through a single-use claim link. The
     * service owns every rule — these methods only carry the request.
     */

    /** Commit an entry from a name alone, and hand back the link that completes it. */
    public function storeUnnamedEntry(Request $request, ClubEvent $event, EntryClaim $claims): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $data = $request->validate([
            // A name and nothing else is the whole point. The optional contact
            // is only so the platform can deliver the link for the coach.
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:32'],

            // What the person at the desk happens to know. Every one of these is
            // OPTIONAL and stays optional — a walk-in handed over on a paper
            // sheet often comes with a name and nothing else, and an invented
            // birthdate is worse than a blank because it drives age groups and
            // the minor safeguards (CLAUDE.md → "Who Fills The Form Decides What
            // It Demands"). But when the desk DOES know, recording it is what
            // lets the athlete be sorted into a division immediately instead of
            // being chased a second time.
            //
            // The FORMAT is still enforced. Only the demand that a value be
            // present is lifted.
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'weight' => ['nullable', 'numeric', 'min:10', 'max:300'],
            'belt_colour' => ['nullable', 'string', 'max:20'],
            'representing_tenant_id' => ['nullable', 'integer'],
        ]);

        $result = $claims->issue(
            $event, $me, $data['full_name'],
            $data['contact_email'] ?? null,
            $data['contact_phone'] ?? null,
            array_filter([
                'gender' => $data['gender'] ?? null,
                'birthdate' => $data['birthdate'] ?? null,
                'weight' => $data['weight'] ?? null,
                'belt_colour' => $data['belt_colour'] ?? null,
                'representing_tenant_id' => $data['representing_tenant_id'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
        );

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        // The entrant count moved for everyone looking at this event.
        $this->pushEventRefresh($event);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'claim' => $result['claim'],
            'going' => $event->participantRegistrations()->count(),
        ]);
    }

    /** The entries this coach committed that are still waiting on somebody. */
    public function entryClaimLinks(ClubEvent $event, EntryClaim $claims): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        abort_if(app(EntryService::class)->administeredClubIds($me) === [] && ! $me->isSuperAdmin(), 403);

        return response()->json(['success' => true, 'pending' => $claims->pending($event, $me)]);
    }

    /** Take back a name typed by mistake, before anybody claimed it. */
    public function revokeEntryClaim(ClubEvent $event, string $claim, EntryClaim $claims): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $row = EventEntryClaim::where('event_id', $event->id)->where('uuid', $claim)->first();
        abort_if(! $row, 404);

        $result = $claims->revoke($row, $me);
        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        $this->pushEventRefresh($event);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'going' => $event->participantRegistrations()->count(),
        ]);
    }

    /** A fresh link for the same entry — the old one stops working immediately. */
    public function regenerateEntryClaim(ClubEvent $event, string $claim, EntryClaim $claims): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $row = EventEntryClaim::where('event_id', $event->id)->where('uuid', $claim)->first();
        abort_if(! $row, 404);

        $result = $claims->regenerate($row, $me);
        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        return response()->json(['success' => true, 'message' => $result['message'], 'claim' => $result['claim']]);
    }

    /* ---------------- Reviewing public entries (Door C) ----------------
     *
     * Documentation/EVENTS-PUBLIC-ENTRY.md, Phase C. A stranger followed the
     * shared link and asked to compete. The request is worth nothing until the
     * organiser accepts it — it counts toward no entrant total, no capacity and
     * no money — so this queue IS the gate, not paperwork after one.
     *
     * Only whoever may MANAGE the event decides; a coach with entry authority
     * at some club has no say over somebody else's competition. The service
     * re-checks that on every call, and these methods carry the request.
     */

    /** Everybody waiting on this organiser. */
    public function publicEntries(ClubEvent $event, PublicEntry $entries, EventAccess $access): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        abort_if(! $access->canManage($event, $me), 403);

        return response()->json(['success' => true, 'pending' => $entries->pending($event, $me)]);
    }

    /** Admit them. This is the moment the request becomes an entry. */
    public function acceptPublicEntry(ClubEvent $event, string $entry, PublicEntry $entries): JsonResponse
    {
        return $this->decidePublicEntry($event, $entry, fn ($row, $me) => $entries->accept($row, $me));
    }

    /** Turn it down. The account they made stays theirs; this competition does not. */
    public function declinePublicEntry(ClubEvent $event, string $entry, PublicEntry $entries): JsonResponse
    {
        return $this->decidePublicEntry($event, $entry, fn ($row, $me) => $entries->decline($row, $me));
    }

    /**
     * Accept and decline differ by one call, so they share everything else —
     * including the scoping, which is what stops a uuid from one event being
     * decided through another event's endpoint.
     */
    private function decidePublicEntry(ClubEvent $event, string $entry, callable $decide): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $row = EventPublicEntry::where('event_id', $event->id)->where('uuid', $entry)->first();
        abort_if(! $row, 404);

        $result = $decide($row, $me);

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        // The entrant count moved for everyone looking at this event.
        $this->pushEventRefresh($event);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'division' => $result['division'] ?? null,
            'going' => $event->participantRegistrations()->count(),
        ]);
    }

    /**
     * Take people OUT of the event — the organiser's multi-select removal.
     *
     * The mirror of storeEntries(), and deliberately narrower on who may call
     * it: entering an athlete is a CLUB's act (a coach submits their squad, from
     * anywhere), but striking a name off the list is the COMPETITION's, so this
     * is the organiser and platform staff and nobody else. A coach who wants
     * their own athlete out asks the organiser, exactly as they would at a desk.
     *
     * Thin by design. Every rule about whether a particular entry may go — the
     * event is underway, the athlete has already fought — lives in
     * EntryService::remove(), beside the rules for getting in.
     *
     * Ids are REGISTRATIONS and are scoped to this event inside the service, so
     * an id belonging to another competition finds nothing rather than reaching
     * across events.
     */
    public function removeEntries(Request $request, ClubEvent $event, EntryService $entries, EventAccess $access): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        abort_if(! $access->canManage($event, $me), 403);

        $data = $request->validate([
            'registration_ids' => ['required', 'array', 'min:1', 'max:200'],
            'registration_ids.*' => ['integer'],
        ]);

        $result = $entries->removeMany($event, $me, $data['registration_ids']);

        // The entry list — and very possibly the draw cut from it — just moved
        // for everyone looking at this event.
        if ($result['removed']) {
            $this->pushEventRefresh($event);
        }

        return response()->json([
            'success' => true,
            'message' => __('events.entry_remove_result', [
                'removed' => count($result['removed']),
                'rejected' => count($result['rejected']),
            ]),
            // The head count as a SENTENCE, rendered here: "12 athletes"
            // pluralises differently in the two languages this platform speaks
            // (Arabic has five forms), and a client that rebuilt it from the
            // number would get one of them wrong.
            'going_label' => trans_choice('personal.event_people_athletes', $result['going'], ['count' => $result['going']]),
        ] + $result);
    }

    /**
     * Whether a public entry needs the organiser to say yes.
     *
     * Separate from the publish switch on purpose: publishing a page and
     * opening an unreviewed door are two different decisions, and the second
     * one should never happen as a side effect of the first.
     */
    public function setPublicAutoAccept(Request $request, ClubEvent $event, EventAccess $access): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        abort_if(! $access->canManage($event, $me), 403);

        $data = $request->validate(['auto_accept' => ['required', 'boolean']]);

        $event->update(['public_entry_auto_accept' => $data['auto_accept']]);

        return response()->json([
            'success' => true,
            'auto_accept' => (bool) $data['auto_accept'],
            'message' => $data['auto_accept']
                ? __('events.public_auto_on_done')
                : __('events.public_auto_off_done'),
        ]);
    }

    /**
     * When the draw opens, said in one sentence.
     *
     * One place, because three surfaces say it — the board's veil, the bout
     * redirect and the console row — and a message that exists three times will
     * eventually say three different things.
     *
     * `hidden` gets no date because there is none: it opens when the organiser
     * says so, and inventing "soon" would be a promise the platform cannot keep.
     */
    private function drawHiddenMessage(ClubEvent $event): string
    {
        if (($event->draw_reveal ?? ClubEvent::DRAW_ALWAYS) === ClubEvent::DRAW_START_DAY && $event->date) {
            return __('events.draw_hidden_until', ['date' => $event->date->translatedFormat('D j M')]);
        }

        return __('events.draw_hidden_msg');
    }

    /**
     * When this event's draw becomes readable.
     *
     * Three settings, one switch, and deliberately NOT folded into the event
     * edit form: an organiser reaches for this in the hour before the doors
     * open, which is not a moment to walk through a form of thirty fields.
     *
     * Organiser only. An official reads a concealed draw (EventAccess::
     * drawVisible) because they are arranging it — that is not the same as
     * deciding when a hall full of people gets to read it.
     */
    public function setDrawReveal(Request $request, ClubEvent $event, EventAccess $access): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        abort_if(! $access->canManage($event, $me), 403);

        $data = $request->validate([
            'draw_reveal' => ['required', Rule::in(ClubEvent::DRAW_REVEALS)],
        ]);

        $event->update(['draw_reveal' => $data['draw_reveal']]);

        /*
         * Everyone looking at this event is looking at a board that may have
         * just opened or closed. A refresh signal rather than a payload: what
         * the draw looks like differs per reader (their own bouts are marked,
         * an organiser sees the bench), so each client re-fetches what it may
         * see rather than being handed somebody else's view.
         */
        $this->pushEventRefresh($event);

        return response()->json([
            'success' => true,
            'draw_reveal' => $data['draw_reveal'],
            'revealed' => $event->fresh()->drawRevealed(),
            'message' => __('events.draw_reveal_saved'),
        ]);
    }

    /**
     * Reject a claim on the club's name.
     *
     * The athlete stays in the event and competes unattached — this is the
     * club's say over its own name, never a veto on someone competing.
     */
    public function disownClaim(ClubEvent $event, User $user, EntryService $entries): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        if ($event->enrollment_ends_at && now()->startOfDay()->gt($event->enrollment_ends_at)) {
            return response()->json([
                'success' => false,
                'message' => __('events.claim_disown_closed'),
            ], 422);
        }

        $entries->disown($event, $me, $user);

        rescue(fn () => \Realtime()->publishToUser($user->id, 'events', [
            'action' => 'refresh', 'event' => $event->uuid,
        ]), null, false);

        return response()->json([
            'success' => true,
            'message' => __('events.claim_disowned_done'),
        ]);
    }

    /* ===================== Owner moderation ===================== */

    /**
     * Manager removes / blocks / blacklists a registrant.
     *  - remove    → delete their registration (they may rejoin)
     *  - block     → delete + bar from THIS event
     *  - blacklist → delete + bar from ALL the club/chain's events
     */
    public function moderateParticipant(Request $request, ClubEvent $event, User $user): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'action' => ['required', Rule::in(['remove', 'block', 'blacklist'])],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if ($user->id === $me->id) {
            return response()->json(['success' => false, 'message' => 'You can’t moderate yourself.'], 422);
        }

        $reg = ClubEventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first();
        $catId = $reg?->category_id;
        $reg?->delete();

        if ($data['action'] === 'block') {
            EventParticipantBan::updateOrCreate(
                ['scope' => 'event', 'event_id' => $event->id, 'user_id' => $user->id],
                ['tenant_id' => $event->tenant_id, 'reason' => $data['reason'] ?? null, 'created_by' => $me->id],
            );
        } elseif ($data['action'] === 'blacklist') {
            EventParticipantBan::updateOrCreate(
                ['scope' => 'club', 'tenant_id' => $event->tenant_id, 'user_id' => $user->id, 'event_id' => null],
                ['reason' => $data['reason'] ?? null, 'created_by' => $me->id],
            );
        }

        // The entrant set changed — the owning package re-derives whatever
        // depended on it (for a championship, the affected division's draw).
        $this->typeFor($event)->onEntrantsChanged($event, $catId ? EventCategory::find($catId) : null);

        // Best-effort realtime nudge to the affected member (DB stays source of truth).
        rescue(fn () => \Realtime()->publishToUser($user->id, 'events', [
            'action' => 'moderated',
            'event' => $event->uuid,
            'kind' => $data['action'],
        ]), null, false);

        $banned = $data['action'] !== 'remove';
        $messages = [
            'remove' => $user->name.' was removed from the event.',
            'block' => $user->name.' was blocked from this event.',
            'blacklist' => $user->name.' was blacklisted from all your club’s events.',
        ];

        return response()->json([
            'success' => true,
            'message' => $messages[$data['action']],
            'user' => ['id' => $user->id, 'name' => $user->name, 'scope' => $banned ? ($data['action'] === 'block' ? 'event' : 'club') : null],
            'banned' => $banned,
            'going' => $event->participantRegistrations()->count(),
            'spectators' => $event->registrations()->where('role', 'spectator')->count(),
        ]);
    }

    /** Manager lifts any ban (event block and/or club blacklist) on a member for this event. */
    public function liftBan(ClubEvent $event, User $user): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        EventParticipantBan::where('user_id', $user->id)
            ->where(function ($q) use ($event) {
                $q->where(fn ($w) => $w->where('scope', 'event')->where('event_id', $event->id))
                    ->orWhere(fn ($w) => $w->where('scope', 'club')->where('tenant_id', $event->tenant_id));
            })->delete();

        rescue(fn () => \Realtime()->publishToUser($user->id, 'events', [
            'action' => 'unbanned', 'event' => $event->uuid,
        ]), null, false);

        return response()->json([
            'success' => true,
            'message' => $user->name.' can join again.',
            'user' => ['id' => $user->id, 'name' => $user->name],
        ]);
    }

    /* ===================== Mappers ===================== */

    private function myRegistrations(int $meId, $eventIds)
    {
        return ClubEventRegistration::where('user_id', $meId)
            ->whereIn('event_id', $eventIds)
            ->get()->keyBy('event_id');
    }

    /**
     * @param  bool  $full  detail page: the classified roster rather than a teaser
     * @param  bool  $wholeRoster  don't cap the roster at 12. Only the dedicated
     *                             people page asks for this — the event screens
     *                             show a teaser and link to it.
     */
    private function eventView(ClubEvent $e, int $meId, $myReg, bool $full = false, bool $wholeRoster = false): array
    {
        $type = $this->typeFor($e);

        // ── Who is looking ────────────────────────────────────────────────────
        // Staff-only facts are stripped HERE, not in the templates, so a view
        // that forgets a guard cannot leak them. Two levels:
        //   organiser — moderation data (who is blocked/blacklisted)
        //   staff     — organiser OR appointed official: other people's payment
        //               and weigh-in status, which they are the ones who sign off
        // Everyone else sees names, division and country, plus their OWN status.
        $viewer = Auth::user();
        $viewer = ($viewer && $viewer->id === $meId) ? $viewer : null;
        $access = app(EventAccess::class);
        $isOrganiser = $viewer !== null && $access->canManage($e, $viewer);
        $isStaff = $isOrganiser || ($viewer !== null && $access->canOfficiate($e, $viewer));
        $date = $e->date ? Carbon::parse($e->date) : now();
        $start = $e->start_time ? Carbon::parse($e->start_time) : null;
        $end = $e->end_time ? Carbon::parse($e->end_time) : null;

        $going = $e->participant_registrations_count ?? $e->participantRegistrations()->count();
        $spectators = $e->spectator_enabled ? $e->registrations()->where('role', 'spectator')->count() : 0;
        $reg = $myReg->get($e->id);

        // Detail page: full classified, weighed-only roster. List cards: a light teaser.
        $spectatorRows = [];
        $spectatorsTotal = $spectators;
        if ($full) {
            $prows = $this->scopeRosterStatus($type->rosterRows($e), $isStaff, $meId);
            $participants = $wholeRoster ? $prows : array_slice($prows, 0, 12);
            $participantsTotal = count($prows);
            if ($e->spectator_enabled) {
                // Ticket-holders' payment status is staff-only too, for the same
                // reason as the competitor roster.
                $srows = $this->scopeRosterStatus($this->spectatorRows($e), $isStaff, $meId);
                $spectatorRows = $wholeRoster ? $srows : array_slice($srows, 0, 12);
                $spectatorsTotal = count($srows);
            }
        } else {
            $participants = $e->participantRegistrations()->with('user:id,full_name,name')
                ->latest('registered_at')->limit(6)->get()
                ->map(fn ($r) => ['name' => $r->user?->full_name ?? $r->user?->name ?? 'Member'])->all();
            $participantsTotal = $going;
        }

        $view = [
            'id' => $e->id,
            'key' => $e->uuid,   // unpredictable public id for URLs
            // ⚠️ translatedFormat, never format — `format()` is locale-blind
            // and printed an English "Fri 18 Sep" on an Arabic page. Same fix,
            // same reason, as App\Events\Support\PublicEvent::payload(); the
            // two views of one event must not disagree about its date. `day` is
            // a bare number with nothing to translate.
            'day' => $date->format('d'),
            'mon' => $date->translatedFormat('M'),
            'wday' => $date->translatedFormat('D'),
            // Comparable form of the same day. The run-of-show timeline uses it
            // to find which of its phases IS the start of the event, so the
            // date chip can jump straight to that row rather than the section.
            'date_iso' => $date->toDateString(),
            'title' => $e->title,
            'club' => $e->tenant?->club_name ?? 'TAKEONE',
            'location' => $e->location ?? 'TBA',
            'address' => $e->location ?? '',
            'location_url' => $e->location_url,
            'lat' => $e->gps_lat ? (float) $e->gps_lat : ($e->tenant?->gps_lat ? (float) $e->tenant->gps_lat : null),
            'lng' => $e->gps_long ? (float) $e->gps_long : ($e->tenant?->gps_long ? (float) $e->tenant->gps_long : null),
            'time' => $start ? $start->translatedFormat('g:i A') : __('events.tba'),
            'end' => $end ? $end->translatedFormat('g:i A') : '',
            'duration' => $this->duration($start, $end),
            'level' => $e->level ?? 'All',
            'tag' => $this->typeLabel($e->event_type),
            'type' => $this->typeLabel($e->event_type),
            'scope' => $e->scope ?? 'internal',
            'scope_label' => $this->scopeLabel($e->scope ?? 'internal'),
            'host_club' => $e->tenant?->club_name,
            'sections' => $this->typeSections($e->event_type),
            'sport' => $e->sport,
            'sport_label' => $e->sport ? ($this->sports()[$e->sport]['label'] ?? null) : null,
            'sport_icon' => $e->sport ? ($this->sports()[$e->sport]['icon'] ?? null) : null,
            'division_label' => $e->sport ? ($this->sports()[$e->sport]['division_label'] ?? 'Category') : 'Category',

            'icon' => $e->icon ?: $this->typeIcon($e->event_type),
            'color' => $e->color ?: $this->typeColor($e->event_type),
            'going' => $going,
            // `cap` falls back to the head count so the progress maths never
            // divides by zero — which means an uncapped event reads as exactly
            // full, "0 spots left". That is a lie invented by the fallback, so
            // `capped` says whether a limit was ever set and the capacity bars
            // render only when it was.
            'cap' => $e->max_capacity ?: max($going, 1),
            'capped' => (int) $e->max_capacity > 0,
            /*
             * The fee LINE, priced now rather than read back.
             *
             * `participant_fee` is a stored display string, written in whatever
             * language the organiser's browser was in when they saved — so an
             * Arabic reader was shown "BHD 10" for ever after (reported
             * 2026-09-04). Where there is a real AMOUNT on the row, the line is
             * built from it through EventFee::display(), which translates the
             * currency — exactly what the public poster already does.
             *
             * The stored string is still the fallback, and has to be: a fee
             * column may hold free text an organiser typed ("by qualification"),
             * which is not a number and must not be replaced by one.
             */
            'participant_fee' => EventFee::amount($e, 'participant')
                ? EventFee::display(EventFee::amount($e, 'participant'), EventFee::currency($e))
                : ($e->participant_fee ?: __('events.fee_free')),
            'spectator' => $e->spectator_enabled ? [
                'fee' => EventFee::amount($e, 'spectator')
                    ? EventFee::display(EventFee::amount($e, 'spectator'), EventFee::currency($e))
                    : ($e->spectator_fee ?: __('events.fee_free')),
                'count' => $spectators,
            ] : null,
            /*
             * Multi-pricing: what this event sells beyond the base fee, and the
             * penalty for entering late.
             *
             * The UUID is what goes back to the server when somebody ticks a
             * box; the amount here is for DISPLAY, so the sheet can show a
             * running total without a round trip. It is never what anybody is
             * charged — the server re-prices from its own rows on entry, so a
             * reader editing these numbers in their console changes what their
             * screen says and nothing else.
             */
            'fees' => [
                'currency' => EventFee::currencyLabel(EventFee::currency($e)),
                'base' => (float) (EventFee::amount($e, 'participant') ?? 0),
                'options' => EventFee::options($e, 'participant')
                    ->map(fn ($o) => [
                        'key' => $o->uuid,
                        'label' => $o->label,
                        'amount' => (float) $o->amount,
                        'display' => EventFee::display((float) $o->amount, EventFee::currency($e)),
                    ])->values()->all(),
                'spectator_base' => (float) (EventFee::amount($e, 'spectator') ?? 0),
                'spectator_options' => $e->spectator_enabled
                    ? EventFee::options($e, 'spectator')
                        ->map(fn ($o) => [
                            'key' => $o->uuid,
                            'label' => $o->label,
                            'amount' => (float) $o->amount,
                            'display' => EventFee::display((float) $o->amount, EventFee::currency($e)),
                        ])->values()->all()
                    : [],
                // Whether the penalty is live RIGHT NOW, decided on the server:
                // a browser with a wrong clock must not be able to talk itself
                // out of a late fee, and the number it shows should match the
                // number it will be charged.
                'late_active' => EventFee::lateFeeApplies($e, 'participant'),
                'late_amount' => (float) ($e->late_fee_amount ?? 0),
                'late_from' => $e->late_fee_from?->toIso8601String(),
            ],
            'prize' => $e->prize,
            'results' => array_values($e->results ?? []),
            'about' => $e->description ?? '',
            'tags' => $e->tags ?: [],
            'requirements' => $e->requirements ?: [],
            // Timeline, run-of-show and final standings all come from the owning
            // package — a bracketed championship derives them from its draw, a
            // simple event just replays what the organiser typed.
            'phases' => $type->timeline($e),
            'agenda' => $e->agenda ?: [],
            'bracket_results' => ($full && ! $type->allowsManualResults()) ? $type->results($e) : [],
            'divisions' => $e->categories()->orderBy('sort_order')->pluck('name')->all(),
            'participants' => $participants,
            'participants_total' => $participantsTotal,
            'spectators_list' => $spectatorRows,
            'spectators_total' => $spectatorsTotal,
            // Moderation data. Organiser only — the blocked TAB was gated, but the
            // names were still serialised into the page for every viewer.
            'bans_list' => $full && $isOrganiser ? $this->bansList($e) : [],
            'joined' => $reg && $reg->role === 'participant',
            // The member's OWN proof-of-payment is awaiting the club's approval.
            'payment_pending' => $reg && $reg->role === 'participant' && ! $reg->paid && (bool) $reg->payment_proof,
            // Holding a place with the fee still outstanding — "pay later", or a
            // proof that no official has approved yet. Distinct from being IN:
            // a screen that says "Booked" over an unpaid entry is lying to them.
            'fee_due' => $reg && ! $reg->paid,
            'watching' => $reg && $reg->role === 'spectator',
            'started' => $e->hasStarted(),
            // Its scheduled time came and went and nobody started it — the
            // difference between "running" and "late", which the old clock-based
            // rule could not express.
            'overdue' => $e->isOverdueToStart(),
            'start_overridden' => (bool) $e->start_overridden,
            'ended' => $e->hasEnded(),
            'categories' => $e->categories()->exists() ? ['_' => true] : [],
        ];

        return $view;
    }

    /** Active bans affecting this event (event blocks + club-wide blacklist), for the manager tab. */
    /**
     * Strip other people's payment / weigh-in status from roster rows.
     *
     * Whether a competitor has paid their fee and whether they made weight are
     * facts for the organiser and the officials who sign them off — not for
     * everyone else in the draw. These events run Kids divisions, so this is
     * another family's child's fee and body weight.
     *
     * Each row keeps its OWN status: you must be able to see that your fee is
     * outstanding. `show_status` tells the template whether to draw the chips at
     * all, so a hidden row never renders as a grey "not paid" — which would
     * still be disclosing something.
     */
    private function scopeRosterStatus(array $rows, bool $isStaff, int $meId): array
    {
        return array_map(function (array $row) use ($isStaff, $meId) {
            $mine = ($row['id'] ?? null) === $meId;

            if ($isStaff || $mine) {
                return $row + ['show_status' => true];
            }

            return array_diff_key($row, array_flip([
                'paid', 'paid_verified', 'weighed', 'weighed_verified', 'weighed_in', 'has_weight',
            ])) + ['show_status' => false];
        }, $rows);
    }

    private function bansList(ClubEvent $e): array
    {
        return EventParticipantBan::with('user:id,full_name,name')
            ->where(function ($q) use ($e) {
                $q->where(fn ($w) => $w->where('scope', 'event')->where('event_id', $e->id))
                    ->orWhere(fn ($w) => $w->where('scope', 'club')->where('tenant_id', $e->tenant_id));
            })
            ->latest()->get()
            ->unique('user_id')
            ->map(fn ($b) => [
                'id' => $b->user_id,
                'name' => $b->user?->full_name ?? $b->user?->name ?? 'Member',
                'scope' => $b->scope,   // 'event' | 'club'
            ])->values()->all();
    }

    /** Spectators (ticket holders) for the detail-page roster tab. */
    private function spectatorRows(ClubEvent $e): array
    {
        return $e->registrations()->where('role', 'spectator')
            ->with('user:id,full_name,name')
            ->latest('registered_at')->get()
            ->map(fn ($r) => [
                'id' => $r->user?->id,
                'name' => $r->user?->full_name ?? $r->user?->name ?? 'Member',
                'paid' => (bool) $r->paid,
            ])->values()->all();
    }

    public function addExpense(Request $request, ClubEvent $event): JsonResponse
    {
        $this->assertCanManage($event, Auth::user());
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);
        $exp = $event->expenses()->create([
            'label' => $data['label'], 'amount' => $data['amount'], 'created_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'expense' => ['id' => $exp->id, 'label' => $exp->label, 'amount' => (float) $exp->amount]]);
    }

    public function deleteExpense(ClubEvent $event, EventExpense $expense): JsonResponse
    {
        $this->assertCanManage($event, Auth::user());
        abort_unless($expense->event_id === $event->id, 404);

        // An officials' fee is owned by the appointment. Deleting the line here
        // would drop a real cost out of the P&L while the person is still
        // recorded as being paid — change the appointment instead.
        if ($expense->isSystemManaged()) {
            return response()->json([
                'success' => false,
                'message' => __('personal.event_expense_locked_to_official'),
            ], 422);
        }

        $expense->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Manager: save a division's draw — status, podium and the full match list
     * (bulk-replaced). This is how the bracket/draw is set.
     */
    public function saveCategory(Request $request, ClubEvent $event, EventCategory $category): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);
        abort_unless($category->event_id === $event->id, 404);

        // The bracket is final once the event is over.
        if ($event->hasEnded()) {
            return response()->json(['success' => false, 'message' => 'This event has ended — the bracket is final.'], 422);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['enrolling', 'live', 'completed'])],
            'note' => ['nullable', 'string', 'max:120'],
            'matches' => ['present', 'array', 'max:64'],
            'matches.*.round' => ['nullable', 'string', 'max:40'],
            'matches.*.a_name' => ['nullable', 'string', 'max:80'],
            'matches.*.a_seed' => ['nullable', 'integer', 'min:1', 'max:128'],
            'matches.*.a_score' => ['nullable', 'string', 'max:16'],
            'matches.*.b_name' => ['nullable', 'string', 'max:80'],
            'matches.*.b_seed' => ['nullable', 'integer', 'min:1', 'max:128'],
            'matches.*.b_score' => ['nullable', 'string', 'max:16'],
            'matches.*.winner' => ['nullable', Rule::in(['a', 'b', ''])],
            'matches.*.court' => ['nullable', 'string', 'max:40'],
            'matches.*.time' => ['nullable', 'string', 'max:40'],
            'matches.*.status' => ['nullable', Rule::in(['upcoming', 'live', 'done'])],
            'podium' => ['nullable', 'array', 'max:8'],
            'podium.*.place' => ['nullable', 'integer', 'min:1', 'max:8'],
            'podium.*.name' => ['nullable', 'string', 'max:80'],
            'podium.*.country' => ['nullable', 'string', 'max:8'],
            'podium.*.prize' => ['nullable', 'string', 'max:80'],
        ]);

        $podium = collect($data['podium'] ?? [])
            ->map(fn ($p, $i) => [
                'place' => (int) ($p['place'] ?? 0) ?: ($i + 1),
                'name' => trim((string) ($p['name'] ?? '')),
                'country' => trim((string) ($p['country'] ?? '')),
                'prize' => trim((string) ($p['prize'] ?? '')),
            ])
            ->filter(fn ($p) => $p['name'] !== '')->sortBy('place')->values()->all();

        $category->update([
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'podium' => $podium ?: null,
        ]);

        // The editor sends names only, so re-link each corner to the entry it
        // belongs to. An unmatched name is kept as free text — that is how an
        // invited athlete with no platform account still appears on the board.
        $entrants = $this->entrantsByName($category);

        // Bulk-replace the matches.
        $category->matches()->delete();
        $slot = 0;
        foreach ($data['matches'] as $m) {
            if (trim((string) ($m['a_name'] ?? '')) === '' && trim((string) ($m['b_name'] ?? '')) === '') {
                continue;
            }
            $category->matches()->create([
                'event_id' => $event->id,
                'round' => trim((string) ($m['round'] ?? '')) ?: 'Round',
                'slot' => $slot++,
                'a_name' => trim((string) ($m['a_name'] ?? '')) ?: null,
                'a_competitor_id' => $entrants[mb_strtolower(trim((string) ($m['a_name'] ?? '')))] ?? null,
                'a_seed' => $m['a_seed'] ?? null,
                'a_score' => trim((string) ($m['a_score'] ?? '')) ?: null,
                'b_name' => trim((string) ($m['b_name'] ?? '')) ?: null,
                'b_competitor_id' => $entrants[mb_strtolower(trim((string) ($m['b_name'] ?? '')))] ?? null,
                'b_seed' => $m['b_seed'] ?? null,
                'b_score' => trim((string) ($m['b_score'] ?? '')) ?: null,
                'winner' => in_array($m['winner'] ?? '', ['a', 'b'], true) ? $m['winner'] : null,
                'court' => trim((string) ($m['court'] ?? '')) ?: null,
                'scheduled_time' => trim((string) ($m['time'] ?? '')) ?: null,
                'status' => in_array($m['status'] ?? '', ['upcoming', 'live', 'done'], true) ? $m['status'] : 'upcoming',
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Draw saved 🥋', 'redirect' => route('testcode.me.events.bracket', $event->uuid)]);
    }

    /**
     * This division's entrants, keyed by lower-cased display name.
     *
     * A name shared by two entrants maps to null — an ambiguous link is worse
     * than none, because it would put a result on the wrong athlete.
     *
     * @return array<string, int|null>
     */
    private function entrantsByName(EventCategory $category): array
    {
        $map = [];

        foreach ($category->registrations()->with('user:id,full_name,name')->get() as $reg) {
            // One entrant may answer to the same string twice (full_name and
            // name are often identical) — dedupe per entrant first, so nobody is
            // mistaken for a duplicate of themselves.
            $labels = collect([$reg->user?->full_name, $reg->user?->name])
                ->filter()->map(fn ($l) => mb_strtolower(trim($l)))->unique();

            foreach ($labels as $key) {
                $map[$key] = array_key_exists($key, $map) ? null : $reg->id;
            }
        }

        return $map;
    }

    /* ===================== Helpers ===================== */

    private function duration(?Carbon $start, ?Carbon $end): string
    {
        if (! $start || ! $end) {
            return '';
        }
        $mins = $start->diffInMinutes($end);
        if ($mins <= 0) {
            return '';
        }
        $h = intdiv($mins, 60);
        $m = $mins % 60;

        return trim(($h ? "{$h}h " : '').($m ? "{$m}m" : '')) ?: "{$mins}m";
    }

    /**
     * Can this member see / self-register for the event, given its scope?
     * Host-club members always qualify; wider scopes admit other clubs' members.
     */
    /*
     * Who may see an event and who may run it lives in App\Events\Support\EventAccess
     * — the MCP server enforces the same rule, and an authorization rule kept in
     * two places eventually disagrees with itself.
     */
    private function isEligible(ClubEvent $event, User $me): bool
    {
        return app(EventAccess::class)->eligible($event, $me);
    }

    private function assertEligible(ClubEvent $event, User $me): void
    {
        abort_unless($this->isEligible($event, $me) || $this->canManage($event, $me), 403);
    }

    private function canManage(ClubEvent $event, User $me): bool
    {
        return app(EventAccess::class)->canManage($event, $me);
    }

    private function assertCanManage(ClubEvent $event, User $me): void
    {
        abort_unless($this->canManage($event, $me), 403);
    }

    private function assertVisible(ClubEvent $event, User $me): void
    {
        abort_if($event->is_archived, 404);
        $this->assertEligible($event, $me);
    }
}
