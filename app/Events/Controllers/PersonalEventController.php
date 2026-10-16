<?php

namespace App\Events\Controllers;

use App\Http\Controllers\Controller;

use App\Support\Cldr;
use App\Events\Contracts\EventType;
use App\Events\EventTypeRegistry;
use App\Events\Support\EntryClaim;
use App\Events\Support\EntryEditor;
use App\Events\Support\EntryService;
use App\Events\Support\EventAccess;
use App\Events\Support\EventFee;
use App\Events\Support\EventStreams;
use App\Events\Support\RosterPeople;
use App\Events\Support\PublicEntry;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventFeeOption;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventMatch;
use App\Events\Models\EventCategory;
use App\Events\Models\EventEntryClaim;
use App\Events\Models\EventChecklistItem;
use App\Events\Models\EventExpense;
use App\Events\Models\EventOfficial;
use App\Events\Models\EventParticipantBan;
use App\Events\Models\EventPublicEntry;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Events\Support\DestructiveChallenge;
use App\Events\Support\Rehearsal;
use App\Events\Support\Tournament\OrderBoard;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
            ->get(['id', 'club_name', 'currency', 'timezone'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->club_name, 'currency' => $c->currency ?: 'BHD',
                // The default clock for an event this club hosts (ClubEvent::zone()).
                'timezone' => ClubEvent::firstValidZone($c->timezone)])
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

    /**
     * The division presets as THIS event's rule book has them: the chosen
     * category system's table in place of the sport's own. An event that
     * chose none gets its package's presets untouched.
     */
    private function rulebookPresets(ClubEvent $event, array $presets): array
    {
        if (! $event->category_system) {
            return $presets;
        }
        $system = app(\App\Events\Support\CategorySystems::class)->forEvent($event);
        if ($system) {
            $presets['weight_divisions'] = $system->table();
        }

        return $presets;
    }

    /** Resolve the package for an event that doesn't exist yet, from its input. */
    private function typeForInput(array $data, ?ClubEvent $event = null): EventType
    {
        /* An EDIT asks with a copy of the real event (so `exists` and its own
           settings come along) rather than a blank one: a type that claims only
           the events it created (App\Events\Championship) must be able to tell
           an existing event from a new one. The copy is never saved. Every
           other type reads only the kind and the sport, as before. */
        if ($event) {
            return $this->registry->for((clone $event)->forceFill([
                'event_type' => $data['event_type'] ?? $event->event_type,
                'sport' => $data['sport'] ?? $event->sport,
            ]));
        }

        return $this->registry->for(new ClubEvent([
            'event_type' => $data['event_type'] ?? null,
            'sport' => $data['sport'] ?? null,
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
            // A HIDDEN event: only in the lists of those who run it or are entered.
            ->listedFor($me)
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
        // The LIST's idea of "ended" (EventOver): a same-day event stays open
        // until the day is out or every result is in — not the moment its
        // planned end time passes. The event page itself keeps hasEnded().
        $demo = $all->map(fn ($e) => ['ended' => \App\Events\Support\EventOver::isOver($e)] + $this->eventView($e, $me->id, $myReg))->values()->all();

        // Paid featuring (App\Events\Support\EventFeaturing): only an APPROVED
        // event may take the spotlight card, and only while it has not ended.
        $featured = \App\Events\Support\EventFeaturing::featuredClubEventIds($upcoming->pluck('id'))->flip();
        $demo = array_map(fn (array $v) => $v + ['featured' => ! $v['ended'] && isset($featured[$v['id']])], $demo);

        // Federation events the member's clubs are part of — listed until they
        // end, then gone. Merged into the upcoming run, soonest first; the
        // finished club events stay after them.
        if (class_exists(\App\Federations\Support\MemberFederationEvents::class)) {
            $fed = \App\Federations\Support\MemberFederationEvents::for($me);
            if ($fed !== []) {
                $when = fn (array $v) => $v['sort'] ?? (isset($v['date_iso']) ? strtotime($v['date_iso']) : PHP_INT_MAX);
                $live = collect($demo)->where('ended', false)->concat($fed)->sortBy($when)->values();
                $demo = $live->concat(collect($demo)->where('ended', true))->values()->all();
            }
        }

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'events::personal.mobile.events' : 'events::personal.desktop.events', compact('demo'));
    }

    public function show(ClubEvent $event, Request $request): View
    {
        $me = Auth::user();

        /*
         * One event, one page (config `events.unified_page`, OFF by default).
         *
         * ON, this address renders the PUBLIC poster's own page file for every
         * reader. A visitor — decided by EventPageReader, which only ever says
         * so for a published event — gets exactly what /e/{uuid} serves. A
         * member falls through the checks below as always and gets the same
         * page with their own flow switched on inside it (unifiedPoster()).
         */
        $unified = $this->unifiedPage($event);

        // A visitor reaches here only for a published event (EventPageReader),
        // and gets the poster whatever the type or the member flag: since /e/
        // went, this IS the public page.
        if ($request->attributes->get('event_reader') === 'visitor') {
            $publisher = app(\App\Events\Support\PublicEvent::class);
            $poster = $publisher->payload($event);
            $passOnly = false;
            // Not published, but the reader holds this event's invitation pass
            // (EventPageReader let them in for that reason alone): the same
            // narrow stranger's poster, with no doors into sections the pass
            // does not open.
            if ($poster === null && \App\Events\Support\EventInvitePass::has($request, $event)) {
                $poster = $publisher->readerPayload($event);
                if ($poster !== null) {
                    $poster['manifest'] = null;
                }
                $passOnly = true;
            }
            abort_if($poster === null, 404);

            return app(PublicEventController::class)->poster($event, $poster)->with('passOnly', $passOnly);
        }

        // READ-only widening for federation staff (FederationEventView). Every
        // write here keeps assertVisible(), which this does not touch.
        abort_if($event->is_archived, 404);
        abort_unless($this->isEligible($event, $me) || $this->canManage($event, $me)
            || \App\Federations\Support\FederationEventView::sees($event, $me)
            // A club that enters athletes in a federation's event reads it (EventAccess::visible).
            || (! $event->tenant_id && ($event->host()->entryDoor($event, $me)['enters'] ?? false))
            // A published event is readable by anybody at all — a stranger
            // may open it, so a signed-in member who is not eligible to ENTER
            // may certainly read it. Only on the unified page.
            || ($unified && app(\App\Events\Support\PublicEvent::class)->isPublic($event))
            // An invitation pass (EventInvitePass) — reading this page only.
            || ($passOnly = $unified && \App\Events\Support\EventInvitePass::has($request, $event)), 403);
        $passOnly = ($passOnly ?? false) === true;

        $type = $this->typeFor($event);

        $event->loadCount(['participantRegistrations']);
        $myReg = $this->myRegistrations($me->id, collect([$event->id]));
        $e = $this->eventView($event, $me->id, $myReg, full: true);
        $e['cancelled'] = $event->status === 'cancelled';
        /*
         * What the POSTER COVER needs, and nothing else does.
         *
         * The member event page opens on the same tap-away cover the shared
         * link does (`entry.public.partials.cover`), and that partial reads the
         * event's artwork and its long-form dates. Added HERE rather than in
         * eventView() on purpose: that runs once per row on the events LIST,
         * and three more values per card there buy nothing.
         *
         * `photo` comes from PublicEvent, so the cover shows the same picture on
         * both surfaces by construction rather than by two people remembering
         * to. Computed for EVERY event, not only a published one — this is the
         * member's own page, and an event with the public switch off still has
         * a cover worth opening on.
         */
        /*
         * The PACKAGE's own name for this kind of event — "Karate
         * Championship", not the generic schema's "Tournament".
         *
         * The two payloads disagreed about `type`: the poster prints the
         * EventType's `label()` while eventView() prints
         * `config('event_schema')`'s, so one event's classification line read
         * "Karate Championship" on the poster and "Karate Tournament" on the
         * member page — the same two-faces problem the shared band was built to
         * end. Added as its OWN key rather than by changing `type`, because
         * `type` is printed on every card of the events LIST and re-wording all
         * of it is not this change's business (RULE #1).
         *
         * The band prefers this and falls back to `type`, so a type with no
         * package keeps exactly the words it has today.
         */
        $e['type_package'] = $type->label();
        $e['photo'] = app(\App\Events\Support\PublicEvent::class)->photo($event);
        $e['date'] = $event->date ? Cldr::fullDate($event->date) : null;
        $e['end_date'] = $event->end_date ? Cldr::fullDate($event->end_date) : null;
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

        /*
         * Taken off at the weigh-in deadline.
         *
         * Their entry is gone, so without this the page reads as though they
         * never entered at all — the "enter this competition" screen, on the
         * morning they were expecting to fight. The exclusion row is the one
         * sentence the removal left behind, and it says WHICH thing was
         * missing: the fee, the scale, or both.
         */
        $excluded = app(\App\Events\Support\EntryDeadline::class)->exclusionFor($event, $me->id);

        $isMobile = (bool) $request->attributes->get('is_mobile');
        $device = $isMobile ? 'mobile' : 'desktop';

        // An event whose host takes its athletes from CLUBS (a federation's
        // competition): nobody enters themselves, their club does — through
        // the host's own door (EventHost::entryDoor). Null at a club's event.
        $hostEntry = $event->host()->entryDoor($event, $me);
        if ($hostEntry) {
            $hostEntry['host'] = $event->host()->name;
        }

        // The organiser's channels (EntryChannels): with individual entry off,
        // a member's club enters them — the join card explains instead of
        // offering a door that refuses. With club entry off, no squad card.
        $channels = app(\App\Events\Support\EntryChannels::class);
        $individualClosed = ! $hostEntry && ! $channels->allows($event, \App\Events\Support\EntryChannels::INDIVIDUAL);
        $clubClosed = ! $channels->allows($event, \App\Events\Support\EntryChannels::CLUB);

        $view = $this->packageView($type, 'show', $device, $isMobile ? 'events::personal.mobile.event-show' : 'events::personal.desktop.event-show');

        $entries = app(EntryService::class);
        $myClubs = $entries->representableClubs($me);
        $entriesState = $entries->entriesState($event);

        $data = [
            'e' => $e,
            // Where the band's back control goes (EventReturn; My Events while its flag is off).
            'eventReturn' => \App\Events\Support\EventReturn::for($request, $event),
            // In on an invitation pass alone: no doors into sections it does not open.
            'passOnly' => $passOnly,
            'canManage' => $canManage,
            'banned' => $banned,
            /*
             * What this event's own package publishes — the pool tables of the
             * classes that are not bracketed, a league's whole table. The
             * PUBLIC poster has carried this since the doors were built; the
             * member page now reads the same key, so the two surfaces offer the
             * same doors with the same words on them (partials/event-doors).
             */
            'typePublic' => $type->publicView($event),
            'canCompete' => ($banned || $excluded || $hostEntry || $individualClosed) ? false : $gate->allowed,
            'hostEntry' => $hostEntry,
            'eligReason' => match (true) {
                $banned => __('events::events.banned_by_organiser'),
                $excluded !== null => $excluded->sentence(),
                $hostEntry !== null => __('events::events.host_entry_body', ['host' => $hostEntry['host']]),
                $individualClosed => __('events::events.entry_individual_closed'),
                default => $gate->message,
            },
            // Removed by the deadline rather than by a person — a different
            // thing from a ban, and the page says so in its own words.
            'excludedAt' => $excluded?->excluded_at,
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
            /*
             * The public page's own address — null unless the organiser has
             * actually put one up.
             *
             * The band's "open the public page" control hangs off this, and it
             * has to be NULL-or-real rather than a URL that might 404: while
             * `entry_mode` is `members` there IS no poster, and a round control
             * in the header that lands on a refusal is a dead end (Navigation
             * Integrity). `$shareUrl` cannot answer this — it falls back to the
             * member page precisely so a share never breaks — so the question is
             * asked of the one class that decides it.
             *
             * Shown to EVERY viewer, not just organisers: a page anyone in the
             * world may open is not a thing to hide from a competitor, and the
             * QR and share controls beside it already point at this same
             * address once it exists.
             */
            'publicUrl' => app(\App\Events\Support\PublicEvent::class)->isPublic($event)
                ? route('me.events.show', ['event' => $event->uuid])
                : null,
            // How to pay, for the join sheet.
            'payment' => $this->paymentInstructions($event),
            // Entering a squad: offered only to someone who holds the grant for
            // a club, and only while the event is still taking entries.
            // Only while entries are actually open — a card that opens a sheet
            // where every row is refused is a door onto a wall.
            'canEnterAthletes' => ! $hostEntry && ! $clubClosed && $entriesState['open'] && $entries->administeredClubIds($me) !== [],
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
                ->map(fn ($d) => app(\App\Events\Controllers\EventDocumentController::class)->present($d, $event))
                ->all(),
        ] + $type->viewData($event, $me);

        if ($unified) {
            return $this->unifiedPoster($event, $data);
        }

        return view($view, $data);
    }

    /**
     * The sections only the public door had — the entry list, the result
     * sheet, a package's own table — at the event's one address. They exist
     * only on the unified page; with the flag off there is nothing to serve.
     */
    public function participantsPage(Request $request, ClubEvent $event): View
    {
        abort_unless($this->servesPoster($event, $request), 404);

        return $this->unifiedSection($request, $event, 'participants');
    }

    public function resultsPage(Request $request, ClubEvent $event): View
    {
        abort_unless($this->servesPoster($event, $request), 404);

        return $this->unifiedSection($request, $event, 'results');
    }

    public function tablePage(Request $request, ClubEvent $event): View
    {
        abort_unless($this->servesPoster($event, $request), 404);

        return $this->unifiedSection($request, $event, 'table');
    }

    /**
     * One section of the unified event — the PUBLIC section page, for every
     * reader (config `events.unified_page`).
     *
     * A visitor (EventPageReader says so only for a published event) gets
     * exactly what /e/{uuid}/{section} serves. A member is authorized as the
     * event page authorizes them, and gets the same page with their own powers
     * on it — `$extra` supplies what a section needs beyond the poster payload
     * (the draw's permissions). `__openDraw` in it lets the people who build
     * a draw open the section before it has any divisions.
     */
    private function unifiedSection(Request $request, ClubEvent $event, string $section, ?\Closure $extra = null): View
    {
        $publisher = app(\App\Events\Support\PublicEvent::class);
        $public = app(PublicEventController::class);

        if ($request->attributes->get('event_reader') === 'visitor') {
            $poster = $publisher->payload($event);
            abort_if($poster === null, 404);

            return $public->sectionPage($request, $event, $section, $poster);
        }

        $me = Auth::user();
        abort_if($event->is_archived, 404);

        $isPublic = $publisher->isPublic($event);

        // The same rule as show(): who may read the event may read its parts.
        abort_unless($this->isEligible($event, $me) || $this->canManage($event, $me)
            || \App\Federations\Support\FederationEventView::sees($event, $me)
            || $isPublic, 403);

        $poster = $isPublic ? $publisher->payload($event) : $publisher->readerPayload($event);
        abort_if($poster === null, 404);

        if (! $isPublic) {
            $poster['manifest'] = null;
        }

        $data = $extra ? $extra($me) : [];
        $openDraw = (bool) ($data['__openDraw'] ?? false);
        unset($data['__openDraw']);

        $access = app(EventAccess::class);
        $mayRun = $access->canManage($event, $me) || $access->canOfficiate($event, $me);

        return $public->sectionPage($request, $event, $section, $poster, $openDraw)->with($data + [
            'asMember' => true,
            'posterPublic' => $isPublic,
            'console' => $mayRun
                ? route('me.events.manage', ['event' => $event->uuid])
                : ($isPublic ? null : false),
            'noindex' => ! $isPublic,
        ]);
    }

    /**
     * Does this request get the public page files? A visitor always does —
     * they are the only public pages there are — and a member does wherever
     * the unified page is on for the event's type.
     */
    private function servesPoster(ClubEvent $event, Request $request): bool
    {
        return $request->attributes->get('event_reader') === 'visitor' || $this->unifiedPage($event);
    }

    /** Does this event render the one shared page? See show(). */
    private function unifiedPage(ClubEvent $event): bool
    {
        return (bool) config('events.unified_page', false)
            && $this->typeFor($event)->brandedSurface();
    }

    /**
     * The poster, for a MEMBER — the public page's own file, with the member's
     * data beside it.
     *
     * `$e` stays the poster payload (PublicEvent decides what the page may say,
     * exactly as on /e/{uuid}); the member's own payload travels as
     * `$memberEvent` and switches on the parts only a member has — joining,
     * paying, the reason they may not, their doors into the platform. Every
     * other key of the member page rides along under its usual name, because
     * the member partials the page includes read them.
     */
    private function unifiedPoster(ClubEvent $event, array $data): View
    {
        $publisher = app(\App\Events\Support\PublicEvent::class);
        $isPublic = $publisher->isPublic($event);

        $poster = $isPublic ? $publisher->payload($event) : $publisher->readerPayload($event);
        abort_if($poster === null, 404);

        if (! $isPublic) {
            // Its addresses are the public doors', which 404 with the switch off.
            $poster['manifest'] = null;
        }

        $memberEvent = $data['e'];
        unset($data['e']);

        $mayRun = ($data['canManage'] ?? false) || ($data['canOfficiate'] ?? false);

        return app(PublicEventController::class)->poster($event, $poster)->with($data + [
            'memberEvent' => $memberEvent,
            'posterPublic' => $isPublic,
            // The gear: the console for whoever runs it; the event's own
            // sign-in for anybody else while there is a public page to sign in
            // ON; and no gear at all otherwise (false), because that sign-in
            // 404s with the public switch off.
            'console' => $mayRun
                ? route('me.events.manage', ['event' => $event->uuid])
                : ($isPublic ? null : false),
            // Nobody's search result while the organiser has not published it.
            'noindex' => ! $isPublic,
        ]);
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

        $isMobile = (bool) $request->attributes->get('is_mobile');

        // A package may bring its own console. A sparring session's run-day
        // screen has nothing in common with a championship's — no draw, no
        // weigh-in, no entries to verify — so it supplies its own rather than
        // hiding half of the shared one. Falls back to the shared console for
        // every type that declares nothing, exactly like `show` and `bracket`.
        return view($this->packageView($type, 'manage', $isMobile ? 'mobile' : 'desktop',
            $isMobile ? 'events::personal.mobile.event-manage' : 'events::personal.desktop.event-manage'), [
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
            'publicUrl' => $canManage ? route('me.events.show', $event->uuid) : null,
            // The picture the public cover opens onto — images[0]. Organiser
            // only: changing the event's face is not an official's decision.
            'coverPhoto' => $canManage ? file_url(($event->images[0] ?? null)) : null,
            'autoAccept' => (bool) $event->public_entry_auto_accept,

            /*
             * Is a dress rehearsal running, and may this person start one?
             *
             * Organiser only, and only before the day: a rehearsal snapshots
             * the competition and hands it back, which is not something an
             * official should be able to do to somebody else's event, and not
             * something anybody should be able to do to a competition that has
             * begun (App\Events\Support\Rehearsal refuses both again).
             */
            'rehearsing' => app(Rehearsal::class)->active($event),
            'canRehearse' => $canManage && ! $event->hasStarted(),
            // When the draw becomes readable, and the day `start_day` would open
            // it on. Organiser only: withholding a draw is not an official's
            // decision, though an official always reads it (EventAccess::
            // drawVisible).
            'drawReveal' => $event->draw_reveal ?: \App\Events\Models\ClubEvent::DRAW_ALWAYS,
            'visibility' => $event->visibility ?: \App\Events\Models\ClubEvent::LISTED,
            // Club payments (EventEntryInvoice): shown where clubs pay through the platform desk.
            'clubPayments' => ($cp = \App\Events\Models\EventEntryInvoice::where('event_id', $event->id))->exists()
                || \App\Events\Support\ClubEntry\ClubEntryDesks::for($event) instanceof \App\Events\Support\ClubEntry\PlatformClubEntryDesk,
            'clubPaymentsWaiting' => (clone $cp)->where('state', \App\Events\Models\EventEntryInvoice::SUBMITTED)->count(),
            // How athletes enter (EntryChannels). Not offered where the host
            // takes its athletes from clubs through its own invitation.
            'entryChannels' => app(\App\Events\Support\EntryChannels::class)->allowed($event),
            'entryChannelsEditable' => ! $event->host_type,
            'drawRevealDate' => $event->date ? Cldr::skeleton($event->date, 'EEEdMMM', 'D j M') : null,
            // Strangers who followed the public link and are waiting to be let
            // in. Rendered by the organiser's console only — accepting one is
            // the gate, and it is not an official's to open.
            'publicEntries' => $canManage ? app(PublicEntry::class)->pending($event, $me) : [],
            'actions' => $canManage ? $type->availableActions($event) : [],
            /* The clubs the "add a participant" sheet may enter somebody FOR —
               the same list, from the same authority, the roster's own club
               picker offers (EntryEditor::clubOptions). Resolved here rather
               than in the view so the console does no querying of its own. */
            'entryClubs' => $canManage ? app(EntryEditor::class)->clubOptions($event) : [],
            // Money is the organiser's alone — an official never receives it.
            'finance' => $canManage ? $type->finance($event) : null,
            'checklist' => $event->checklistItems()->with('checker:id,full_name,name')->get()
                ->map(fn ($i) => $this->checklistItemView($i))->all(),
            'documents' => $event->documents()
                ->get()
                ->map(fn ($d) => app(\App\Events\Controllers\EventDocumentController::class)->present($d, $event))
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
                    ->mapWithKeys(fn ($m, $slot) => [$slot => route('me.events.screen-audio.show', [$event->uuid, $slot])])
                    ->all()
                : null,
            // Counts for the section cards, so each one says what is waiting
            // inside it before it is opened.
            'counts' => [
                /* PEOPLE, not entries.
                 *
                 * `participant_registrations_count` counts ROWS, and an athlete
                 * who entered both Gi and No-Gi has two of them — so this tile
                 * said "90 entrants" while the list it opens drew 62 cards, and
                 * so did the public page. The gap widens as more people enter
                 * more than one part of the event.
                 *
                 * One card per person at an event (CLAUDE.md); a count under
                 * the tile that opens that list has to mean the same thing the
                 * list does. Entry-level figures — fees, weigh-ins, bracket
                 * slots — are still counted per ENTRY where they are read.
                 */
                'entrants' => (int) $event->participantRegistrations()->distinct()->count('user_id'),
                'officials' => $event->officials()->count(),
                'documents' => $event->documents()->count(),
                'outstanding' => $event->outstandingChecks(),
            ],
        ] + $type->viewData($event, $me));
    }

    /**
     * Each non-heading division and how many people are entered in it.
     *
     * @return array<int, array{id: int, joined: int}>
     */
    private function divisionCounts(ClubEvent $event): array
    {
        $divisions = $event->categories()->get(['id', 'is_heading']);

        if ($divisions->isEmpty()) {
            return [];
        }

        $counts = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as n')
            ->groupBy('category_id')
            ->pluck('n', 'category_id');

        return $divisions
            ->reject(fn ($c) => $c->isHeading())
            ->map(fn ($c) => ['id' => $c->id, 'joined' => (int) ($counts[$c->id] ?? 0)])
            ->values()->all();
    }

    /**
     * The people this event's SPORT already knows.
     *
     * Three ways in, and all three are earned rather than claimed — nobody ends
     * up here by editing a profile field:
     *   · they have officiated an event of this sport,
     *   · they have competed in one,
     *   · they belong to a club that runs them.
     *
     * Returns a query, not a list, so it stays a subquery — a platform-wide
     * `pluck()` here would be tens of thousands of ids in memory on every
     * keystroke of a search box.
     */
    private function sportPeople(ClubEvent $event): \Illuminate\Database\Query\Builder
    {
        $sport = (string) $event->sport;

        $events = DB::table('club_events')->where('sport', $sport)->select('id');
        $clubs = DB::table('club_events')->where('sport', $sport)->select('tenant_id');

        return DB::table('event_officials')
            ->whereIn('event_id', clone $events)
            ->select('user_id')
            ->union(
                DB::table('club_event_registrations')
                    ->whereIn('event_id', clone $events)
                    ->select('user_id')
            )
            ->union(
                DB::table('memberships')
                    ->whereIn('tenant_id', $clubs)
                    ->select('user_id')
            );
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

    /**
     * The old bracket page — RETIRED, and kept as a redirect.
     *
     * This route used to render a whole second draw surface: a bracket, an
     * entry list per division, a waiting room and a bout list, on its own
     * mobile and desktop views. The board moved to `brackets/manage`, which is
     * now the one draw screen for everybody — an organiser, an appointed
     * official and a reader all get the same full-screen board, with only the
     * controls differing — so this page had become a second answer to a
     * question that already had one.
     *
     * It stays as a redirect rather than a 404 because the address is OUT
     * THERE: in notifications with a deep link, in the bout page's own
     * `bracket_url`, and in whatever anybody bookmarked. Its two deep-link
     * parameters are carried across:
     *
     *   · `?category=` → `?division=`, the same division on the board.
     *   · `?bout=` → the division that bout belongs to, looked up here exactly
     *     as this page used to look it up, so "which bout was this?" still
     *     lands on the right draw.
     *
     * ⚠️ `manageBracket` must never redirect BACK here — it draws the withheld
     * draw itself now (`personal.event-draw-hidden`). Two pages pointing at
     * each other is an endless bounce; see the note there.
     *
     * The two views this used to render are registered for deletion in
     * Documentation/HOUSE-CLEANING.md rather than deleted in passing.
     */
    public function bracket(Request $request, ClubEvent $event): RedirectResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $division = (int) $request->query('category', 0);

        if ($division === 0 && $request->filled('bout')) {
            $division = (int) EventMatch::where('event_id', $event->id)
                ->where('match_no', (int) $request->query('bout'))
                ->value('category_id');
        }

        return redirect()->route(
            'me.events.bracket.manage',
            array_filter(['event' => $event->uuid, 'division' => $division ?: null]),
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
        if ($this->servesPoster($event, $request)) {
            return $this->unifiedSection($request, $event, 'gallery');
        }

        $me = Auth::user();
        $this->assertVisible($event, $me);

        $type = $this->typeFor($event);

        $e = $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: true);
        $canManage = $this->canManage($event, $me);

        $isMobile = (bool) $request->attributes->get('is_mobile');
        $device = $isMobile ? 'mobile' : 'desktop';

        $library = app(\App\Media\VideoLibrary::class);
        $divisions = $library->forEvent($event);

        return view(
            $this->packageView($type, 'gallery', $device, 'events::personal.'.$device.'.event-gallery'),
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

        // ── ONE place for a person's photograph ────────────────────────────
        //
        // A face belongs to the PERSON, so it is stored under their own subtree
        // and the entry holds a reference to it (CLAUDE.md → A Person's Photo
        // Has One Home). Written into the event's folder, it existed for the
        // bracket and the hall screen and nowhere else — the same athlete was a
        // blank silhouette on their profile and in the members list.
        //
        // An entry with no account behind it — a name off a paper list, no
        // person record yet — has nowhere to put it but the event's folder, so
        // that path stays for exactly that case.
        $path = \App\Members\Support\ProfilePhoto::capture(
            $registration->user_id ? \App\Members\Models\User::find($registration->user_id) : null,
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

        // Every entry this person holds in this event, not just the one the
        // caller happened to name: an athlete is entered once per division and
        // the draw places whichever row it likes, so writing one row left the
        // mat showing the old face. See EntryPhoto::assign.
        \App\Events\Support\EntryPhoto::assign($event, $registration, $path);

        // Only once the new one is safely stored, and only if it moved.
        //
        // Through EntryPhoto, because `photo` is not always a file this entry
        // owns: an entry settled through the public door points at the
        // athlete's own profile picture, and deleting that here took a member's
        // face off the disk while their profile carried on pointing at it.
        if ($previous && $previous !== $path) {
            \App\Events\Support\EntryPhoto::discard($previous);
        }

        // The mats, which do not re-read anything on their own: a board is
        // assembled when the bout is called and then kept. Silent and
        // best-effort — see App\Scoreboard\Fleet::entryChanged.
        rescue(fn () => app(\App\Scoreboard\Fleet::class)
            ->entryChanged($event, (int) $registration->id), null, false);

        return response()->json([
            'success' => true,
            'message' => __('personal.event_photo_saved'),
            'photo' => file_url($path),
            'registration' => $registration->id,
        ]);
    }

    /** Take the event photo off an entry, falling back to whatever their profile allows. */
    /**
     * The face on the officiating sheet.
     *
     * The same procedure a competitor's photo goes through — the shared photo
     * flow takes it (camera or gallery), the one cropper frames it 3:4, and
     * this stores the bytes. Validated by REAL BYTES, never by what the data
     * URI claims, and written to a folder this server builds from the event's
     * own uuid (CLAUDE.md → Image Uploads Must Validate Real Bytes).
     */
    public function officialPhoto(Request $request, ClubEvent $event, EventOfficial $official): JsonResponse
    {
        $me = Auth::user();
        abort_unless(app(EventAccess::class)->canManage($event, $me), 403);
        // Scoped, always: an appointment id from another event must not be
        // writable through this event's URL.
        abort_unless($official->event_id === $event->id, 404);

        $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image/'],
        ]);

        $previous = $official->photo;

        // An official is a person, so their photograph goes where every other
        // photograph of a person goes (CLAUDE.md → A Person's Photo Has One
        // Home). The appointment keeps a reference to it.
        $path = \App\Members\Support\ProfilePhoto::capture(
            $official->user,
            $request->input('image'),
            'events/'.$event->uuid.'/officials',
            'o'.$official->id.'-'.Str::random(16),
        );

        if ($path === null) {
            return response()->json([
                'success' => false,
                'message' => __('personal.event_photo_rejected'),
            ], 422);
        }

        $official->update(['photo' => $path]);

        // Only once the new one is safely stored, and only if it moved —
        // through EntryPhoto, never a raw delete: now that this path can be the
        // person's own picture, a plain Storage::delete() here would take their
        // face off the disk while their profile carried on naming it.
        if ($previous && $previous !== $path) {
            \App\Events\Support\EntryPhoto::discard($previous);
        }

        return response()->json([
            'success' => true,
            'photo' => file_url($path),
        ]);
    }

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

        // Cleared everywhere this person is entered, for the same reason it is
        // set everywhere — see EntryPhoto::assign.
        \App\Events\Support\EntryPhoto::assign($event, $registration, null);

        // As above: a board showing a face that has just been removed is the
        // same fault in the other direction.
        rescue(fn () => app(\App\Scoreboard\Fleet::class)
            ->entryChanged($event, (int) $registration->id), null, false);

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

        // One body, one weight: the scale's reading goes onto this person's
        // other entries here too, so no placement reads a declared weight the
        // scale already replaced (App\Events\Support\BodyWeight).
        \App\Events\Support\BodyWeight::spread($event->id, $registration->user_id);

        // An athlete their club entered before anyone had a weight for them is
        // unclassified until exactly this moment. The scale is what places them,
        // so the package is asked where this weight puts them — and the draw is
        // told the entrant set moved.
        $registration->refresh();
        $type = $this->typeFor($event);

        /*
         * A package that can say WHAT it did says so; every other type keeps
         * the plain classify. The distinction matters at the desk: an athlete
         * MOVED between divisions by the scale is news, and reporting it as
         * "weight recorded" is how somebody ends up in the wrong bracket with
         * nobody the wiser.
         */
        $placement = method_exists($type, 'weighInPlacement')
            ? $type->weighInPlacement($event, $registration)
            : ['division' => $type->classifyEntry($event, $registration), 'action' => null, 'from' => null];

        $division = $placement['division'];

        if ($division) {
            $type->onEntrantsChanged($event, $division);
        }

        // The division they came OUT of has lost an entrant, so its draw is
        // stale too — and it is a different bracket from the one they went into.
        if ($placement['from']) {
            $type->onEntrantsChanged($event, $placement['from']);
        }

        // Weighed, and it places them in nothing this event is running — a
        // cadet at a seniors-only championship, say. Deferring the weight is
        // what let them in; the desk has to be TOLD when that turns out badly,
        // because a silent "signed off" would leave an entrant nobody can draw.
        $unplaced = ! $division && ! $registration->fresh()->category_id;

        $belt = app(\App\Sports\Combat\BeltRank::class)->for($registration->user, $registration->fresh());

        return response()->json([
            'success' => true,
            'message' => match (true) {
                $placement['action'] === 'moved' => __('personal.event_verify_weight_moved', [
                    'from' => $placement['from']?->name ?? '',
                    'division' => $division->name,
                ]),
                // The organiser put them here on purpose and that stands. Said
                // out loud so a real mistake is caught at the desk, where it is
                // still cheap, rather than at the mat.
                $placement['action'] === 'mismatch' => __('personal.event_verify_weight_mismatch', [
                    'division' => $division?->name ?? '',
                ]),
                $division !== null => __('personal.event_verify_weight_placed', ['division' => $division->name]),
                $unplaced => __('personal.event_verify_weight_unplaced'),
                default => __('personal.event_verify_weight_recorded'),
            },
            'weight' => (float) $data['weight'],
            // The division they were just placed in, when the weigh-in is what
            // decided it — so the desk sees the result of what it did.
            'division' => $division?->name,
            // What the scale actually did: 'placed' | 'moved' | null.
            'placement' => $placement['action'],
            'moved_from' => $placement['from']?->name,
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

        $data = $request->validate([
            'approve' => ['required', 'boolean'],
            // The desk may say what the money was FOR in the same breath as
            // approving it — see recordFees().
            'fee_options' => ['nullable', 'array', 'max:30'],
            'fee_options.*' => ['string', 'max:64'],
        ]);

        $approve = (bool) $data['approve'];

        $registration->update([
            'paid' => $approve,
            'paid_at' => $approve ? now() : null,
            'paid_by' => $approve ? $me->id : null,
        ]);

        /*
         * Approving a payment WRITES THE MONEY RECORD.
         *
         * Until now ticking "paid" set a boolean and nothing else, so the
         * event knew that money had changed hands and not how much — revenue
         * fell back to an estimate at the list price, and the "where it comes
         * from" table could attribute none of it. A payment that is recorded
         * as an amount at the moment it is taken needs no estimating later.
         *
         * Nothing is written on a REJECTION: what an entry was charged is not
         * undone by refusing the receipt, and the lines are the record of the
         * charge, not of the payment.
         */
        $lines = null;

        if ($approve) {
            $lines = $this->recordFees($event, $registration, $data['fee_options'] ?? null);
        }

        return response()->json([
            'success' => true,
            'message' => $approve
                ? __('personal.event_verify_payment_approved')
                : __('personal.event_verify_payment_rejected'),
        ] + ($lines ?? []));
    }

    /**
     * Mark many entries paid, or sign off many weigh-ins, in one go — the
     * entry list's select mode (asked for on 2026-10-10: a desk with a long
     * list cannot do it one sheet at a time).
     *
     * A loop over EXACTLY what the one-entry endpoints do, behind the same
     * gates (a weigh-in official still cannot approve money), scoped to this
     * event's competitors. Nothing is invented in bulk:
     *
     *  - **paid** approves each unpaid entry and records its fee the way an
     *    untouched approval does (recordFees with no choice) — an entry whose
     *    price depends on a type nobody ticked stays UNRECORDED and is counted
     *    back, so the desk knows to set it on that person's sheet.
     *  - **weighed** signs off the weight already ON FILE (the entrant's
     *    declared weight). An entry with no weight anywhere is skipped and said
     *    out loud — a scale reading cannot be made up for somebody. Already
     *    signed weigh-ins are left alone, so a second press changes nothing.
     */
    public function verifyBulk(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();

        $data = $request->validate([
            'action' => ['required', Rule::in(['paid', 'weighed'])],
            'registration_ids' => ['required', 'array', 'min:1', 'max:500'],
            'registration_ids.*' => ['integer'],
        ]);

        $access = app(EventAccess::class);
        abort_unless($data['action'] === 'paid'
            ? $access->canVerifyPayments($event, $me)
            : $access->canVerifyWeighIn($event, $me), 403);

        // Only this event's competitors; an id from anywhere else simply matches nothing.
        $regs = $event->participantRegistrations()
            ->whereIn('id', array_unique(array_map('intval', $data['registration_ids'])))
            ->get();

        $done = [];
        $skipped = 0;
        $unrecorded = 0;
        $noWeight = [];

        if ($data['action'] === 'paid') {
            foreach ($regs as $reg) {
                if ($reg->paid && $reg->paid_by !== null) {
                    $skipped++;
                    continue;
                }

                $reg->update(['paid' => true, 'paid_at' => now(), 'paid_by' => $me->id]);

                $lines = $this->recordFees($event, $reg, null);
                $recorded = $lines !== null
                    || \App\Events\Models\EventRegistrationFeeLine::where('registration_id', $reg->id)->exists();

                if (! $recorded && EventFee::chargesAnything($event, 'participant')) {
                    $unrecorded++;
                }

                $done[] = $reg->id;
            }

            \Illuminate\Support\Facades\Log::info('event.bulk_paid', [
                'event' => $event->uuid, 'by' => $me->id, 'count' => count($done),
            ]);

            return response()->json([
                'success' => true,
                'message' => trans_choice('personal.event_people_bulk_paid_done', count($done), ['count' => count($done)])
                    .($skipped ? ' '.trans_choice('personal.event_people_bulk_already', $skipped, ['count' => $skipped]) : ''),
                'warning' => $unrecorded
                    ? trans_choice('personal.event_people_bulk_fee_unrecorded', $unrecorded, ['count' => $unrecorded])
                    : null,
                'updated' => $done,
            ]);
        }

        /* One body, one weight: a person entered in two divisions declared it
           once, on whichever entry. Siblings in the batch lend it to each other,
           and so do their other entries in this event. */
        $declared = $event->participantRegistrations()
            ->whereIn('user_id', $regs->pluck('user_id')->filter()->unique()->all())
            ->whereNotNull('weight')
            ->orderByRaw('weighed_in_by IS NULL')
            ->get(['user_id', 'weight'])
            ->unique('user_id')
            ->pluck('weight', 'user_id');

        $type = $this->typeFor($event);
        $touched = [];

        foreach ($regs as $reg) {
            if ($reg->weighed_in_by !== null) {
                $skipped++;
                continue;
            }

            $weight = $reg->weight ?? ($reg->user_id ? ($declared[$reg->user_id] ?? null) : null);

            if ($weight === null) {
                $noWeight[] = $reg->id;
                continue;
            }

            $reg->update([
                'weight' => $weight,
                'weighed_in_at' => now(),
                'weighed_in_by' => $me->id,
            ]);
            $reg->refresh();

            // The same placement the scale makes in verifyWeighIn().
            $placement = method_exists($type, 'weighInPlacement')
                ? $type->weighInPlacement($event, $reg)
                : ['division' => $type->classifyEntry($event, $reg), 'action' => null, 'from' => null];

            foreach ([$placement['division'] ?? null, $placement['from'] ?? null] as $div) {
                if ($div) {
                    $touched[$div->id] = $div;
                }
            }

            $done[] = $reg->id;
        }

        // Each stale draw told once, not once per athlete in it.
        foreach ($touched as $div) {
            $type->onEntrantsChanged($event, $div);
        }

        \Illuminate\Support\Facades\Log::info('event.bulk_weighed', [
            'event' => $event->uuid, 'by' => $me->id, 'count' => count($done), 'no_weight' => count($noWeight),
        ]);

        return response()->json([
            'success' => true,
            'message' => trans_choice('personal.event_people_bulk_weighed_done', count($done), ['count' => count($done)])
                .($skipped ? ' '.trans_choice('personal.event_people_bulk_already', $skipped, ['count' => $skipped]) : ''),
            'warning' => $noWeight
                ? trans_choice('personal.event_people_bulk_no_weight', count($noWeight), ['count' => count($noWeight)])
                : null,
            'updated' => $done,
            'no_weight' => $noWeight,
        ]);
    }

    /**
     * Write what an entry was charged, at the moment its payment is approved.
     *
     * Three cases, and the third is the reason this is careful rather than
     * clever:
     *
     *  1. **The desk said what it was for** (`fee_options` came with the
     *     approval, from the sheet's picker) → price exactly that. An explicit
     *     answer always wins, including the answer "nothing".
     *  2. **Nothing was said, and the price is not in doubt** — the event
     *     charges a base fee, or offers exactly ONE type, or is genuinely free
     *     for this role → record it. One option is not a choice.
     *  3. **Nothing was said and the price DEPENDS on a choice** (two or more
     *     types, nobody ticked one) → write NOTHING and leave the entry
     *     unrecorded.
     *
     * Case 3 matters: `commit()` writes a zero line for an empty quote, and a
     * zero line means "recorded free". Saying an entry was free because nobody
     * told us which of three prices it paid would turn a known unknown into a
     * confident wrong answer — and the finance sheet would report LESS than the
     * estimate it replaced. Unrecorded is the honest state, the sheet says so,
     * and the picker beside this button is how it gets answered.
     *
     * An entry that already has lines is never overwritten by an approval;
     * only the picker (verifyFees) rewrites a record that exists.
     *
     * @return array<string, mixed>|null  what to echo back, or null when nothing was written
     */
    private function recordFees(ClubEvent $event, ClubEventRegistration $registration, ?array $options): ?array
    {
        $role = $registration->role === 'spectator' ? 'spectator' : 'participant';

        $explicit = $options !== null;
        $chosen = array_values(array_filter((array) $options, 'is_string'));

        $recorded = \App\Events\Models\EventRegistrationFeeLine::where('registration_id', $registration->id)->exists();

        if (! $explicit) {
            if ($recorded) {
                return null;
            }

            $types = EventFee::options($event, $role);

            if ($types->count() === 1) {
                $chosen = [$types->first()->uuid];
            } elseif ($types->count() > 1) {
                return null;   // case 3 — ambiguous, so nothing is invented
            }
        }

        $quote = EventFee::quote($event, $role, $chosen, $registration->registered_at);

        // An implicit quote of nothing is only recorded when the event really
        // does charge nothing for this role. Otherwise it is case 3 again.
        if (! $explicit && $quote['total'] <= 0 && EventFee::chargesAnything($event, $role)) {
            return null;
        }

        $total = EventFee::commit($registration, $quote);

        return [
            'charged' => $total,
            'charged_label' => EventFee::display($total, $quote['currency']),
            'fee_options' => collect($quote['lines'])->pluck('fee_option_id')->filter()
                ->map(fn ($id) => \App\Events\Models\EventFeeOption::where('id', $id)->value('uuid'))
                ->filter()->values()->all(),
        ];
    }

    /**
     * What this entry is being charged for — set at the desk.
     *
     * The gap this closes: an entry taken at the door is ticked "paid" and
     * nothing records WHAT for, because no price was ever quoted. The event
     * then reports revenue it cannot attribute — eighteen paid entrants showing
     * one recorded amount and seventeen estimates (2026-09-06).
     *
     * The official ticks the types; the server prices them. Deliberately NOT
     * from the amounts the browser sends: `EventFee::quote` reads the event's
     * own rows, so a hand-made request cannot invent a price, and the late
     * penalty is judged by when they ENTERED rather than by when the desk got
     * round to them.
     *
     * `commit()` replaces the entry's lines wholesale, which is what makes this
     * a correction rather than an addition — and it writes a zero line for a
     * free entry, so "recorded free" stays distinguishable from "no record".
     */
    public function verifyFees(Request $request, ClubEvent $event, ClubEventRegistration $registration): JsonResponse
    {
        $me = Auth::user();
        abort_unless(app(EventAccess::class)->canVerifyPayments($event, $me), 403);
        abort_unless($registration->event_id === $event->id, 404);

        $data = $request->validate([
            'fee_options' => ['nullable', 'array', 'max:30'],
            'fee_options.*' => ['string', 'max:64'],
        ]);

        $role = $registration->role === 'spectator' ? 'spectator' : 'participant';

        $quote = EventFee::quote(
            $event,
            $role,
            $data['fee_options'] ?? [],
            $registration->registered_at,
        );

        $total = EventFee::commit($registration, $quote);

        return response()->json([
            'success' => true,
            'message' => __('personal.event_verify_fees_saved'),
            'charged' => $total,
            'charged_label' => EventFee::display($total, $quote['currency']),
            // Echo what was actually stored, so the sheet shows the server's
            // answer rather than what it hoped for.
            'fee_options' => collect($quote['lines'])->pluck('fee_option_id')->filter()
                ->map(fn ($id) => \App\Events\Models\EventFeeOption::where('id', $id)->value('uuid'))
                ->filter()->values()->all(),
            'lines' => collect($quote['lines'])->map(fn ($l) => [
                'label' => $l['label'],
                'amount' => (float) $l['amount'],
                'kind' => $l['kind'],
            ])->values()->all(),
        ]);
    }

    /**
     * The event's price list in the shape a picker reads.
     *
     * @return array<int, array<string, mixed>>
     */
    private function feeTypesFor(ClubEvent $event): array
    {
        return app(\App\Events\Support\EntryGates::class)->feeTypes($event);
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
        return app(\App\Events\Support\EntryGates::class)->paymentInstructions($event);
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

        /*
         * ===== One card per PERSON, never one per entry =====
         *
         * A sport's roster is a list of ENTRIES — it feeds the draw, where the
         * same athlete entered in a Gi group and a No-Gi group is genuinely two
         * competitors. This page is a list of PEOPLE, and there the same rows
         * read as the list having the same man on it twice (reported
         * 2026-09-11: "same person but two cards ? why ?").
         *
         * So the entries are folded into one row per person here, carrying
         * every division they are in. The entries themselves are not lost and
         * not merged: each keeps its own id on the row, because a fee, a
         * weigh-in signature and a receipt all belong to ONE entry, and the
         * verification sheet opens them one at a time. Multiplying the fees is
         * right; multiplying the person is not.
         */
        $e['participants'] = app(RosterPeople::class)->byPerson($e['participants']);

        /* The head count above this list counts what the list DRAWS: one card
           per person, after the fold and after an organiser's unlisted entrants
           have been put back. Everywhere else `participants_people` is the
           event's people count; here the two can differ by an entrant the
           sport's roster holds back, and the number on a list must be the
           length of that list. */
        $e['participants_people'] = count($e['participants']);

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
        /* Asked here rather than further down because the readiness pass needs
           to know whether this viewer may hold a body weight — the two gates
           were already answered below for the verification sheet, and one
           answer for both is what keeps them from drifting apart. */
        $access = app(EventAccess::class);
        $canWeigh = $access->canVerifyWeighIn($event, $me);
        $canPay = $access->canVerifyPayments($event, $me);

        $people['participants'] = $this->attachReadiness(
            $event, $people['participants'], $e['participants'], $canWeigh, (int) $me->id,
        );

        /*
         * ===== The club an athlete was written down under =====
         *
         * `event_club_entrants` records which club standing behind THIS event
         * brought which entrant, and that club may not be a tenant at all — it
         * is a name and a crest an organiser typed at the desk. RosterPeople
         * only knows about tenant-backed clubs, so an athlete brought by a
         * written-down one showed NO club on this screen, even though the join
         * row was sitting right there.
         *
         * Overlaid here rather than pushed into RosterPeople: the shared roster
         * feeds the draw, the boards and the hall screens, and none of them
         * asks this question. Positional, exactly like attachReadiness above —
         * build() maps its rows one-for-one and in order.
         */
        $people['participants'] = $this->attachEventClubs($event, $people['participants'], $e['participants']);
        $people['clubs'] = $this->eventClubTab($event, $people['clubs'] ?? []);

        /*
         * ===== The flag beside a name is the PERSON'S =====
         *
         * ⚠️ This REVERSES the platform's standing rule, deliberately and at
         * the owner's request. `ClubEventRegistration::countryCode()` answers
         * with the CLUB's country — so an Egyptian competing for a Bahraini
         * club flew Bahrain on every sheet — and CLAUDE.md records that as the
         * decided behaviour.
         *
         * Both answers are defensible: a team competition flies the club, an
         * international flies the passport. Which is exactly why it is done
         * HERE, on this one roster, and NOT in the shared `countryCode()` that
         * the draw, the boards and the hall screens all read. One screen
         * changing its mind is a change anybody can see; all of them changing
         * silently is a regression somebody finds on an event day.
         *
         * Falls back to whatever was there when no nationality is on file — a
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

        /* Counted over ENTRIES, not cards. A fee is charged per entry, so an
           athlete in two divisions owes twice — and now that the two entries
           share one card, counting cards would quietly under-count the money
           the desk is waiting for. */
        if (($canManage || $canPay) && $hasFee) {
            $visibleIds = collect($e['participants'])
                ->filter(fn ($p) => $p['show_status'] ?? false)
                ->flatMap(fn ($p) => collect($p['entries'] ?? [])->pluck('registration'))
                ->filter()->unique()->values();

            if ($visibleIds->isNotEmpty()) {
                $total = $visibleIds->count();
                $paid = ClubEventRegistration::whereIn('id', $visibleIds->all())
                    ->where('paid', true)->count();

                $money = [
                    'paid' => $paid,
                    'due' => $total - $paid,
                    'total' => $total,
                    'percent' => (int) round($paid / $total * 100),
                ];
            }
        }

        return view('events::personal.event-people', [
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
            // The event's own price list, for the desk's fee picker. Only to an
            // official who may take money — nobody else has a use for it here.
            'feeTypes' => $canPay ? $this->feeTypesFor($event) : [],
            // Radio or checkbox — the same answer the entrant's own form got,
            // so the desk cannot tick a pair the server would refuse to price.
            'feeSingle' => EventFee::selection($event) === 'single',
            'feeCurrency' => EventFee::currency($event),
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
     * status show these badges. The WEIGHT READING is gated more tightly still
     * — see the note on it below.
     *
     * @param  array<int, array<string, mixed>>  $people  RosterPeople participants
     * @param  array<int, array<string, mixed>>  $rows    the roster rows they were built from
     * @return array<int, array<string, mixed>>
     */
    private function attachReadiness(ClubEvent $event, array $people, array $rows, bool $canWeigh, int $meId): array
    {
        /*
         * Keyed by REGISTRATION, not by user: a person holding two entries used
         * to lose one of them to keyBy('user_id') — the badges on their card
         * described whichever entry the collection happened to keep last. The
         * rows are one per person now (RosterPeople::byPerson) and carry every
         * entry they hold, so the pair of badges is answered over ALL of them.
         */
        $regs = $event->participantRegistrations()
            ->get(['id', 'user_id', 'paid', 'paid_by', 'weight', 'weighed_in_at', 'weighed_in_by'])
            ->keyBy('id');

        $rows = array_values($rows);

        foreach (array_values($people) as $i => $person) {
            $row = $rows[$i] ?? [];

            if (! ($row['show_status'] ?? false)) {
                continue;
            }

            $ids = collect($row['entries'] ?? [])->pluck('registration')->filter();

            if ($ids->isEmpty() && ($row['registration'] ?? null)) {
                $ids = collect([$row['registration']]);
            }

            $entries = $ids->map(fn ($id) => $regs->get($id))->filter()->values();

            if ($entries->isEmpty()) {
                continue;
            }

            /* Two entries, two fees: the card says PAID only when every one of
               them is. A green badge over one outstanding fee is the kind of
               half-truth a desk acts on. */
            $reg = $entries->first();
            $allPaid = $entries->every(fn ($r) => (bool) $r->paid);
            $allSigned = $allPaid && $entries->every(fn ($r) => $r->paid_by !== null);

            /* One body, one weight — whichever entry it was taken against. An
               official's signature on any of them is the strongest answer. */
            $weighed = $entries->first(fn ($r) => $r->weighed_in_by !== null);
            $declared = $entries->first(fn ($r) => $r->weight !== null);

            $people[$i]['show_status'] = true;
            /* The belt's degree is NOT set here: RosterPeople already carries
               the announced rank (BeltRank resolves weigh-in, then
               certification, then skill), and a second writer would let the
               card disagree with the hall screen. */
            $people[$i]['paid'] = $allPaid;
            $people[$i]['paid_verified'] = $allSigned;
            $people[$i]['weigh_state'] = match (true) {
                $weighed !== null => 'official',
                $declared !== null => 'self',
                default => 'none',
            };
            $reg = $weighed ?? $declared ?? $reg;
            /*
             * The number itself, so the card can print it rather than leaving
             * the weight line blank (asked for on 2026-09-04). Trailing zeros
             * go: a scale reading of 62.00 is written 62.
             *
             * ⚠️ NARROWER than the badges beside it. `show_status` is "staff or
             * your own row", and staff includes the official who checks bank
             * transfers — who has no business holding another family's child's
             * body weight. attachVerification() has always split the two jobs
             * that way (the weight only to canVerifyWeighIn, the receipt only to
             * canVerifyPayments); this line shipped on the wider gate and handed
             * the cashier the number on the card instead. The badges may stay on
             * `show_status`: signed / not-signed is what the page is FOR, and it
             * discloses neither a weight nor a bank transfer.
             */
            if ($canWeigh || ($row['id'] ?? null) === $meId) {
                $people[$i]['weight'] = $reg->weight !== null
                    ? rtrim(rtrim(number_format((float) $reg->weight, 2, '.', ''), '0'), '.')
                    : null;
            }
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
        if ($this->servesPoster($event, $request)) {
            return $this->unifiedSection($request, $event, 'officials');
        }

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
                /*
                 * The APPOINTMENT's own photo first — taken at the desk, for
                 * this competition. Only then a profile picture, and only if
                 * the member published it: `profile_picture_is_public` is their
                 * choice about their own face and an officiating sheet does not
                 * overrule it (CLAUDE.md → Profile Pictures Are Portrait 3:4).
                 */
                'photo' => $o->photo
                    ? file_url($o->photo)
                    : (($o->user->profile_picture && $o->user->profile_picture_is_public)
                        ? file_url($o->user->profile_picture).'?v='.($o->user->updated_at?->timestamp ?? 0)
                        : null),
                /*
                 * Whose referee this is. The hall asks it about every official
                 * on the sheet, and a competitor has a real interest in the
                 * answer — the man refereeing their bout may wear the same
                 * badge as the person opposite. Null is a real answer, printed
                 * as independent rather than left blank.
                 */
                'club' => $o->club(),
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

        return view('events::personal.event-officials', [
            'e' => $this->eventView($event, $me->id, $myReg),
            'groups' => $groups,
            'total' => $rows->count(),
            /*
             * Whether this viewer may APPOINT, which is a narrower question than
             * whether they may read the page. The sheet of who is officiating is
             * visible to anyone who can see the event; the appointment card
             * moved here from the create form on 2026-09-06 and is a management
             * action, so it is gated separately rather than by where it sits.
             */
            'canManage' => app(\App\Events\Support\EventAccess::class)->canManage($event, $me),
        ]);
    }


    /**
     * Merge the officiating payload onto roster rows — officials only.
     *
     * MOVED to App\Events\Support\EntryGates on 2026-09-16, unchanged, because
     * a second screen opens the same desk: the participant card on the public
     * event page. This name stays so every caller here reads as it did, and so
     * the docblock explaining WHAT the desk discloses to WHICH role lives in one
     * place — read it there.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachVerification(ClubEvent $event, array $rows, bool $canWeigh, bool $canPay): array
    {
        return app(\App\Events\Support\EntryGates::class)->attach($event, $rows, $canWeigh, $canPay);
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
        /* A type that brings its own organiser workspace (ProvidesWorkspace —
           App\Events\Championship) shows it to the people who run the event;
           everybody else keeps the shared draw page below. */
        $workspaceType = $this->typeFor($event);
        $workspaceViewer = Auth::user();

        if ($workspaceType instanceof \App\Events\Contracts\ProvidesWorkspace
            && $workspaceViewer && $this->canManage($event, $workspaceViewer)) {
            return view($workspaceType->workspaceView(), [
                'e' => ['key' => $event->uuid, 'title' => $event->title, 'color' => app(\App\Events\Support\PublicEvent::class)->color($event)],
            ] + $workspaceType->workspaceData($event, $workspaceViewer));
        }

        if ($this->servesPoster($event, $request)) {
            return $this->unifiedSection($request, $event, 'draw', function (User $me) use ($event, $request) {
                $type = $this->typeFor($event);
                $type->onEntrantsChanged($event);

                $canManage = $this->canManage($event, $me);
                $canArrange = $this->canArrangeDraw($event, $type, $canManage);
                $sees = $canManage || $canArrange || app(EventAccess::class)->drawVisible($event, $me);

                return [
                    // The board, with this reader's powers — exactly what the
                    // member draw page handed the same component.
                    'memberDraw' => [
                        'canManage' => $canManage,
                        'canArrange' => $canArrange,
                        'drawActions' => $canManage
                            ? array_values(array_filter(
                                $type->availableActions($event),
                                fn (array $a) => str_contains($a['action'] ?? '', 'draw') && ($a['action'] ?? '') !== 'arrange_draw',
                            ))
                            : [],
                        'myCompetitorIds' => ClubEventRegistration::where('event_id', $event->id)
                            ->where('user_id', $me->id)->pluck('id')->all(),
                        'dataUrl' => route('me.events.bracket.data', ['event' => $event->uuid]),
                        'division' => $request->query('division'),
                    ],
                    // The stage opens for whoever may see the draw, and for the
                    // people who build it before there is anything to see.
                    'drawStage' => $sees && ($canManage || $canArrange || $event->categories()->exists()),
                    '__openDraw' => $canManage || $canArrange,
                ];
            });
        }

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
         *
         * And it is a THIRD job as of 2026-09-17: reading. Anybody who may see
         * the draw may open this stage, with neither permission and therefore
         * with no controls — see the block below.
         */
        /*
         * ===== A READER IS NOT AN ERROR =====
         *
         * This used to bounce anybody who could not arrange back to the
         * ordinary bracket page with "the draw is final" — which is not why
         * they were turned away, and a competitor looking for their own bout
         * was told off for asking. The stage is a component now
         * (`x-draw-stage`) and read-only is one of the two things it draws, so
         * they get the SAME full-screen board with no controls on it.
         *
         * The one case that still redirects is a draw this reader may not see
         * at all — the organiser is holding it back (`draw_reveal`). The
         * bracket page is where that is answered properly: it says WHEN the
         * draw opens, which is what they came to find out, and a full-screen
         * stage that renders an empty board says nothing at all.
         *
         * Withheld is asked of EventAccess, the one place that rule lives —
         * the same call the board's JSON door, the bout page and the MCP tool
         * make, so a reader cannot be let past here and refused there.
         */
        if (! $canManage && ! $canArrange && ! app(EventAccess::class)->drawVisible($event, $me)) {
            /* ⚠️ DRAWN HERE, NOT REDIRECTED. This used to send the reader to
               `me.events.bracket`, which is now itself a redirect back to this
               method — two pages pointing at each other is an endless bounce,
               and on a hall screen nobody can stop it. The chain ends on a
               page that says WHEN the draw opens. */
            return view('events::personal.event-draw-hidden', [
                'e' => $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: true),
                // The event's own colour, read the way every other event page
                // reads it — off the view array, not off the column.
                'color' => null,
                'drawHidden' => $this->drawHiddenMessage($event),
            ]);
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

        return view('events::personal.event-manage-draw', [
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
            return redirect()->route('me.events.show', $event->uuid)
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

        $isMobile = (bool) $request->attributes->get('is_mobile');
        $device = $isMobile ? 'mobile' : 'desktop';

        return view('events::personal.'.$device.'.event-bout', [
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

        return \App\Events\Models\EventOfficial::where('event_id', $event->id)
            ->with('user:id,uuid,full_name,name,birthdate,nationality,profile_picture,profile_picture_is_public,is_discoverable,updated_at')
            ->get()
            ->sortBy(fn ($o) => [$rank[$o->role] ?? 99, mb_strtolower((string) ($o->user?->full_name ?? ''))])
            ->map(function (\App\Events\Models\EventOfficial $o) {
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
        foreach (['events::events.official_'.$role, 'personal.event_officials_role_'.$role] as $key) {
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
                'message' => __('events::events.bout_corners_conflict'),
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
                    'message' => __('events::events.bout_competitor_ineligible'),
                ], 422);
            }

            $picks[$side] = $entry;
        }

        if (isset($picks['a'], $picks['b']) && $picks['a']->id === $picks['b']->id) {
            return response()->json([
                'success' => false,
                'message' => __('events::events.bout_competitor_duplicate'),
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
            'message' => __('events::events.bout_saved'),
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
            'bracket_url' => route('me.events.bracket.manage', array_filter([
                'event' => $event->uuid,
                'division' => $match->category_id,
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
                $row = \App\Events\Models\EventRecording::with('mediaFile')
                    ->where('match_id', $match->id)
                    ->where('status', \App\Events\Models\EventRecording::STATUS_LINKED)
                    ->where(fn ($q) => $q->whereNotNull('play_url')->orWhereNotNull('media_file_id'))
                    ->latest('id')
                    ->first();

                if ($row === null) {
                    return null;
                }

                if ($row->mediaFile?->isPlayable()) {
                    return route('me.events.bout.video', [
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
            /* Whether this championship plays third place off — the board's own
               menu shows it, and only an organiser is offered the switch. */
            'third_place_match' => \App\Events\Support\BronzeRule::playsOff($event),
            // The bench is an organiser's working area, not part of the public
            // draw — and only the client that may arrange is told it can.
            'can_arrange' => $canArrangeDraw = $this->canArrangeDraw($event, $type, $canManage),
            /*
             * The padlock banner, and it is now asked of the READER rather than
             * of the clock (2026-09-18). Arranging outlives the start — see
             * Arrangement::move — so telling the person who may still move
             * people that the draw is final is simply untrue, and it was the
             * first thing an organiser saw when they opened the board to fix
             * it. For everybody else nothing changes: a reader of a running
             * event still gets the banner, because for them the draw IS final.
             */
            'locked' => ! $canArrangeDraw && ($event->hasStarted() || $event->hasEnded()),
            /*
             * ⚠️ OFFERED, never TRUSTED.
             *
             * The board draws a winner on a tap and a score in a box, and both
             * go to me.events.outcome — which asks canManage for itself, every
             * call. This tells the board what to DRAW; it decides nothing.
             * Same argument as `can_arrange` above.
             */
            'can_manage' => $canManage,
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
            // The draw desk's board (owner, 2026-10-16): a board left with half
            // its people is laid again at the smaller size (BracketFit).
            'fit' => ['nullable', 'boolean'],
        ]);
        $fit = (bool) ($data['fit'] ?? false);
        unset($data['fit']);

        // One division per person: a basket → corner move puts somebody on a board.
        if ($data['from']['type'] === 'bench' && $data['to']['type'] === 'slot') {
            $entry = $event->registrations()->where('id', (int) ($data['from']['competitor_id'] ?? 0))->first();
            if ($refused = \App\Events\Support\OneDivisionRule::refusal($event, $entry, (int) $data['category_id'])) {
                return response()->json(['success' => false, 'message' => $refused], 422);
            }
        }

        $response = $this->dispatchAction($event, 'arrange_draw', $data);

        if ($fit && $data['to']['type'] === 'bench' && $response->getStatusCode() === 200) {
            $response = $this->fitted($event, (int) $data['category_id'], $response);
        }

        return $response;
    }

    /**
     * After a desk move: lay the board again smaller when its people fit half
     * of it (App\Events\Support\Tournament\BracketFit). The answer then
     * carries no division, so the board re-reads the whole draw.
     */
    private function fitted(ClubEvent $event, int $categoryId, JsonResponse $response): JsonResponse
    {
        $category = $event->categories()->find($categoryId);

        if (! $category || ! app(\App\Events\Support\Tournament\BracketFit::class)->shrink($event, $category)) {
            return $response;
        }

        $this->redrawDivision($event, $category->fresh());

        $payload = $response->getData(true);
        $payload['data']['division'] = null;
        $payload['resized'] = true;

        return response()->json($payload);
    }

    /**
     * Drag a competitor from one division's draw into ANOTHER's — the split
     * view's drop (App\Events\Support\Tournament\Transfer).
     *
     * MOVE is arranging, so it takes the arranging gate (the appointed jury
     * included). COPY makes a second entry and may put a fee on it, which is
     * money and therefore the ORGANISER's — the same line the outcome tools
     * already draw between arranging a draw and managing an event.
     */
    public function transferBracket(Request $request, ClubEvent $event): JsonResponse
    {
        abort_unless(config('events.draw_transfer'), 404);

        $me = Auth::user();
        $this->assertCanArrange($event, $me);

        $data = $request->validate([
            'from_category_id' => ['required', 'integer'],
            'competitor_id' => ['required', 'integer'],
            'to_category_id' => ['required', 'integer'],
            'to.type' => ['required', Rule::in(['slot', 'bench'])],
            'to.match_id' => ['nullable', 'integer'],
            'to.side' => ['nullable', Rule::in(['a', 'b'])],
            'mode' => ['required', Rule::in(['move', 'copy'])],
            'charge' => ['nullable', 'boolean'],
        ]);

        if ($data['mode'] === 'copy' && ! app(\App\Events\Support\EventAccess::class)->canManage($event, $me)) {
            return response()->json(['success' => false, 'message' => __('events::events.transfer_copy_organiser')], 403);
        }

        $data['charge'] = (bool) ($data['charge'] ?? false);

        // One division per person: a COPY onto another board is a second bracket.
        if ($data['to']['type'] === 'slot') {
            $entry = $event->registrations()->where('id', (int) $data['competitor_id'])->first();
            if ($refused = \App\Events\Support\OneDivisionRule::refusal($event, $entry, (int) $data['to_category_id'], moving: $data['mode'] === 'move')) {
                return response()->json(['success' => false, 'message' => $refused], 422);
            }
        }

        return $this->dispatchAction($event, 'transfer_draw', $data);
    }

    /**
     * Who could still be admitted to a division — the picker's list.
     *
     * Everybody entered in the championship who is NOT already standing in this
     * division's draw, the ones sitting in no division at all first, because
     * those are the people this whole surface exists for: entered, eligible,
     * and on no board anywhere. Each row says where they are now, so an
     * organiser can see they are about to be moved rather than copied.
     *
     * Read-only, authorised the same way arranging is, capped and searchable
     * rather than unbounded: an event can hold hundreds of entries and this is
     * a lookup on a competition morning, not an export.
     */
    public function lateEntryCandidates(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanArrange($event, $me);

        $data = $request->validate([
            // Optional for the desk's division editor, which lists who FITS a
            // range before the division exists (owner, 2026-10-16).
            'category_id' => [$request->boolean('basket') ? 'nullable' : 'required', 'integer'],
            // `basket`: the holding basket's list — longer, and every row says
            // which bracket the person already stands in (OneDivisionRule).
            'basket' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:60'],
            // The desk's filter (owner, 2026-10-15): narrow by who the person
            // IS, not only by name. Each bound is optional; set, it excludes
            // anybody whose value is unknown.
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'min_weight' => ['nullable', 'numeric', 'min:0', 'max:400'],
            'max_weight' => ['nullable', 'numeric', 'min:0', 'max:400'],
        ]);

        $basket = (bool) ($data['basket'] ?? false);
        $category = isset($data['category_id']) ? $event->categories()->find($data['category_id']) : null;

        if (! $category && ! ($basket && ! isset($data['category_id']))) {
            return response()->json(['success' => false, 'message' => __('events::events.division_not_found')], 422);
        }

        // A division not made yet: the range alone decides, against the event's dates.
        $category ??= new \App\Events\Models\EventCategory(['event_id' => $event->id]);

        // Everybody already on this division's board is not a candidate for it.
        $drawn = ! $category->exists ? [] : $category->matches()->get()
            ->flatMap(fn ($m) => $m->competitorIds())->unique()->all();

        /*
         * Excluded by PERSON, not by entry row.
         *
         * An athlete standing on this board may ALSO hold a second entry in the
         * championship — one with no division on it, say — and filtering only
         * the drawn row lets that second one back into the list. The desk then
         * sees a name they are already looking at on the bracket, picks it, and
         * is told they are already in the draw. Which is true, and is not a
         * list they should have been shown.
         */
        $drawnUsers = $drawn === [] ? [] : $event->registrations()
            ->whereIn('id', $drawn)->pluck('user_id')->unique()->all();

        $term = trim((string) ($data['q'] ?? ''));
        $category->setRelation('event', $event);
        $ages = new \App\Events\Support\DivisionRange($category);

        $rows = $event->registrations()
            ->where('role', 'participant')
            ->when($drawn !== [], fn ($q) => $q->whereNotIn('id', $drawn))
            ->when($drawnUsers !== [], fn ($q) => $q->whereNotIn('user_id', $drawnUsers))
            ->when($term !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('full_name', 'like', '%'.$term.'%')
                ->orWhere('name', 'like', '%'.$term.'%')))
            ->when(isset($data['gender']), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('gender', $data['gender'])))
            ->when(isset($data['min_weight']), fn ($q) => $q->whereNotNull('weight')->where('weight', '>=', $data['min_weight']))
            ->when(isset($data['max_weight']), fn ($q) => $q->whereNotNull('weight')->where('weight', '<=', $data['max_weight']))
            /* ⚠️ `nationality`, `representingTenant` and `memberClubs` are NOT
               optional in this load: countryCode() and competingClub() read
               all three, and a constrained eager load that does not name a
               column reads it back as NULL rather than raising. Same trap
               BracketView records against its own select. `birthdate` is read
               by the age filter and the age shown on each row. */
            ->with([
                'user:id,full_name,name,nationality,profile_picture,profile_picture_is_public,gender,birthdate,updated_at',
                'user.memberClubs:id,club_name,country',
                'representingTenant:id,club_name,country',
                'category:id,name',
            ])
            ->get()
            /* Age is worked out the way a division works it out (on the
               event's own date, or the age the desk wrote down), so a filter
               of 5–6 finds exactly who a 5–6 division would. Before the cap
               below, so nobody in range is cut off by it. */
            ->filter(function ($r) use ($data, $ages) {
                if (! isset($data['min_age']) && ! isset($data['max_age'])) {
                    return true;
                }
                $age = $ages->ageOf($r);

                return $age !== null
                    && (! isset($data['min_age']) || $age >= $data['min_age'])
                    && (! isset($data['max_age']) || $age <= $data['max_age']);
            })
            /*
             * ⚠️ ONE ROW PER PERSON, not per entry.
             *
             * A person legitimately holds one entry per division — Gi and No-Gi
             * in the same championship is ordinary — so an athlete can appear
             * here twice and the desk would be choosing between two rows with
             * the same name on them. The entry that ALREADY belongs to this
             * division is the one that can be admitted (moving the other one on
             * top of it breaks the unique key), so it wins; otherwise the one
             * standing in no division does, because that is the repair.
             */
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows
                ->sortBy(fn ($r) => match (true) {
                    $r->category_id === $category->id => 0,
                    $r->category_id === null => 1,
                    default => 2,
                })->first())
            ->values()
            // Nobody-yet first, then alphabetically — the order the desk reads in.
            ->sortBy(fn ($r) => [$r->category_id === null ? 0 : 1, mb_strtolower((string) ($r->user?->full_name ?: $r->user?->name))])
            ->values()
            ->take($basket ? 150 : 40);

        // The written-down clubs for everybody on the list, in one query rather
        // than one per row (App\Events\Support\EntryClub).
        $written = \App\Events\Support\EntryClub::forMany($event->id, $rows->pluck('id'));

        // Which bracket each person already stands in — the basket greys them
        // out when the event allows one division per person.
        $placed = $basket ? \App\Events\Support\OneDivisionRule::placements($event) : [];
        $oneOnly = \App\Events\Support\OneDivisionRule::on($event);

        return response()->json([
            'success' => true,
            'candidates' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->user?->full_name ?: $r->user?->name ?: __('events::events.athlete'),
                // Where they stand today. Null is the case worth seeing.
                'division' => $r->category?->name,
                'in_this_division' => $category->exists && $r->category_id === $category->id,
                'placed_in' => $basket ? collect($placed[$r->user_id] ?? [])
                    ->reject(fn ($d) => $category->exists && $d['id'] === $category->id)->pluck('name')->values()->all() : [],
                'blocked' => $basket && $oneOnly && collect($placed[$r->user_id] ?? [])
                    ->contains(fn ($d) => ! $category->exists || $d['id'] !== $category->id),
                'belt' => $r->belt_colour,
                'weight' => $r->weight,
                'age' => $ages->ageOf($r),
                'gender' => $r->user?->gender,
                /*
                 * The face, the flag and the club — what the desk actually
                 * recognises somebody by. Same three answers, from the same
                 * three places, as every other surface that draws a
                 * competitor: the entry's own photo before the profile
                 * picture (and only a published one), the club they compete
                 * FOR, and its country.
                 */
                'photo' => app(\App\Events\Support\CompetitorPhoto::class)
                    ->url($r, $r->user, $event->id),
                'country' => $r->countryCode() ?: ($written[$r->id]['country'] ?? null),
                'club' => $r->competingClub()?->club_name
                    ?: ($written[$r->id]['name'] ?? null),
                'club_ab' => \App\Events\Support\BracketView::mark(
                    $r->competingClub()?->club_name ?: ($written[$r->id]['name'] ?? null),
                ),
            ])->values(),
        ]);
    }

    /**
     * Admit an athlete to a division that is already being fought.
     *
     * Same gate as arranging — the appointed jury may do this without being
     * able to edit the event — because it is the same job: getting the board to
     * match who is actually in the hall. The package decides whether it is
     * possible; nothing here knows what a bracket is.
     */
    public function admitLateEntry(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanArrange($event, $me);

        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'competitor_id' => ['required', 'integer'],
        ]);

        // One division per person. The picked entry is MOVED here unless the
        // person already holds one in this division, so it never counts itself.
        $entry = $event->registrations()->where('id', (int) $data['competitor_id'])->first();
        $holds = $entry && $entry->user_id && $event->registrations()
            ->where('user_id', $entry->user_id)->where('category_id', (int) $data['category_id'])->exists();
        if ($refused = \App\Events\Support\OneDivisionRule::refusal($event, $entry, (int) $data['category_id'], moving: ! $holds)) {
            return response()->json(['success' => false, 'message' => $refused], 422);
        }

        return $this->dispatchAction($event, 'admit_late_entry', $data);
    }

    /**
     * Pull a candidate from the draw desk's basket onto a division's board
     * (owner, 2026-10-16: "as I drag them out to the brackets screen, create
     * brackets for them if it is not enough").
     *
     * Three steps, one transaction, each through a door that already exists:
     *  1. the person gets an entry IN this division — their own if they hold
     *     one; the picked one moved here if it is in no division (or the
     *     event allows one division per person); otherwise a second entry,
     *     recorded as free (the package's own transfer_draw COPY);
     *  2. onto the board: an empty board lays its final around them
     *     (LoneFinal), a named corner is an ordinary arrange, and anywhere
     *     else is a late entry — an open corner, or the ladder grows a round;
     *  3. a refusal at any step undoes the lot.
     *
     * Organiser only: a second entry is money (the same line transferBracket
     * draws for a copy). One division per person is checked first.
     */
    public function pullIntoBracket(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'competitor_id' => ['required', 'integer'],
            'to.type' => ['required', Rule::in(['slot', 'board'])],
            'to.match_id' => ['nullable', 'integer'],
            'to.side' => ['nullable', Rule::in(['a', 'b'])],
        ]);

        $category = $event->categories()->find($data['category_id']);
        if (! $category || $category->isHeading()) {
            return response()->json(['success' => false, 'message' => __('events::events.division_not_found')], 422);
        }

        $entry = $event->registrations()->where('role', 'participant')
            ->where('id', (int) $data['competitor_id'])->with('user:id,full_name,name')->first();
        if (! $entry) {
            return response()->json(['success' => false, 'message' => __('events::events.late_entry_not_an_entrant')], 422);
        }

        $own = $entry->category_id === $category->id ? $entry : ($entry->user_id ? $event->registrations()
            ->where('role', 'participant')->where('user_id', $entry->user_id)
            ->where('category_id', $category->id)->first() : null);

        if ($own && $category->matches()->get()->flatMap(fn ($m) => $m->competitorIds())->contains($own->id)) {
            return response()->json(['success' => false, 'message' => __('events::events.late_entry_already_drawn')], 422);
        }

        // Standing in ANOTHER bracket already — never pulled out of it silently.
        if ($refused = \App\Events\Support\OneDivisionRule::refusal($event, $own ?? $entry, $category->id)) {
            return response()->json(['success' => false, 'message' => $refused], 422);
        }

        try {
            return DB::transaction(function () use ($event, $category, $entry, $own, $data) {
                $target = $own;

                if (! $target && ($entry->category_id === null || \App\Events\Support\OneDivisionRule::on($event))) {
                    $from = $entry->category;
                    $entry->update([
                        'category_id' => $category->id,
                        'category_source' => \App\Events\Models\ClubEventRegistration::PLACED_MANUAL,
                    ]);
                    if ($from) {
                        $this->redrawDivision($event, $from->fresh());
                    }
                    $target = $entry->fresh();
                } elseif (! $target) {
                    $copied = $this->dispatchAction($event, 'transfer_draw', [
                        'from_category_id' => $entry->category_id, 'competitor_id' => $entry->id,
                        'to_category_id' => $category->id, 'to' => ['type' => 'bench'],
                        'mode' => 'copy', 'charge' => false,
                    ]);
                    if ($copied->getStatusCode() !== 200) {
                        throw new \App\Events\Support\Tournament\TransferRefused($copied->getData(true)['message'] ?? __('events::events.bracket_move_failed'));
                    }
                    $target = $event->registrations()->where('role', 'participant')
                        ->where('category_id', $category->id)
                        ->when($entry->user_id, fn ($q) => $q->where('user_id', $entry->user_id), fn ($q) => $q->latest('id'))
                        ->firstOrFail();
                }

                $to = $data['to'];
                $placed = match (true) {
                    ! $category->matches()->exists() => $this->dispatchAction($event, 'arrange_draw', [
                        'category_id' => $category->id,
                        'from' => ['type' => 'bench', 'competitor_id' => $target->id],
                        'to' => ['type' => 'slot', 'match_id' => null, 'side' => 'a'],
                    ]),
                    $to['type'] === 'slot' && ! empty($to['match_id']) => $this->dispatchAction($event, 'arrange_draw', [
                        'category_id' => $category->id,
                        'from' => ['type' => 'bench', 'competitor_id' => $target->id],
                        'to' => ['type' => 'slot', 'match_id' => (int) $to['match_id'], 'side' => $to['side'] ?? 'a'],
                    ]),
                    default => $this->dispatchAction($event, 'admit_late_entry', [
                        'category_id' => $category->id, 'competitor_id' => $target->id,
                    ]),
                };

                if ($placed->getStatusCode() !== 200) {
                    throw new \App\Events\Support\Tournament\TransferRefused($placed->getData(true)['message'] ?? __('events::events.bracket_move_failed'));
                }

                return $placed;
            });
        } catch (\App\Events\Support\Tournament\TransferRefused $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * One division per person — on or off (App\Events\Support\OneDivisionRule).
     * Organiser only; the federation Draw tab and the draw desk both call it.
     */
    public function setOneDivision(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate(['on' => ['required', 'boolean']]);
        $event->update(['one_division_per_person' => (bool) $data['on']]);

        \Illuminate\Support\Facades\Log::info('One division per person set', ['event' => $event->uuid, 'on' => (bool) $data['on'], 'by' => $me->id]);
        $this->pushDivisionsChanged($event);

        return response()->json([
            'success' => true,
            'one_division' => (bool) $data['on'],
            'message' => __($data['on'] ? 'events::events.one_division_on' : 'events::events.one_division_off'),
        ]);
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
     * Does THIS championship play third place off?
     *
     * The board's Actions menu used to offer this and write nothing — an
     * organiser picked "3rd place match", watched it revert on the next load,
     * and had no way to say what their own competition does. It is a real
     * setting now: stored on the event, obeyed by the draw engine, the podium
     * and the results sheet through App\Events\Support\BronzeRule, and the
     * draw is reconciled in the same request so the play-off bouts appear (or
     * go) immediately rather than at the next re-cut.
     *
     * Organiser only — the same gate every other draw verb uses — and the
     * answer says what it actually did, because "on" on a drawn championship
     * means bouts were created.
     */
    public function setThirdPlace(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate(['play_off' => ['required', 'boolean']]);
        $playOff = (bool) $data['play_off'];

        $event->update(['third_place_match' => $playOff]);

        $counts = app(\App\Events\Support\ThirdPlaceSwitch::class)
            ->apply($event, $playOff, $this->typeFor($event));

        /* Every other board, console and wall reading this championship — the
           draw just changed shape for all of them. Published directly rather
           than through the type, whose broadcast() is its own (protected):
           this is the same channel and the same discriminator every draw
           change already uses. */
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

        return response()->json([
            'success' => true,
            'play_off' => $playOff,
            'added' => $counts['added'],
            'removed' => $counts['removed'],
            'kept' => $counts['kept'],
            'message' => $playOff
                ? __('events::events.third_place_on', ['count' => $counts['added']])
                : __('events::events.third_place_off', ['count' => $counts['removed'], 'kept' => $counts['kept']]),
        ]);
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
            return response()->json(['success' => false, 'message' => __('events::events.action_unavailable')], 422);
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
            'enrollment_ends_at.before_or_equal' => __('events::events.validate_enrolment_ends_after_event'),
            'enrollment_ends_at.after_or_equal' => __('events::events.validate_enrolment_ends_before_start'),
            'end_date.after_or_equal' => __('events::events.validate_end_date_before_start'),
            'start_time.date_format' => __('events::events.validate_start_time_format'),
            'end_time.date_format' => __('events::events.validate_end_time_format'),
            'break_start.after_or_equal' => __('events::events.validate_break_before_start'),
            'break_end.after' => __('events::events.validate_break_end_before_break_start'),
            'break_end.before_or_equal' => __('events::events.validate_break_after_end'),
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

        /*
         * ⚠️ A REHEARSAL IS NEVER CARRIED INTO THE DAY.
         *
         * Ending it puts the draw, the divisions and the event row back the way
         * they were before anybody practised on them. Doing that HERE, rather
         * than trusting the organiser to press End Rehearsal first, is the
         * difference between a competition that starts clean and one that
         * starts on top of last night's practice results with its bracket half
         * advanced. Costs nothing when no rehearsal is running.
         */
        app(Rehearsal::class)->end($event);
        $event->refresh();

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

        /*
         * ⚠️ LOCK THE DRAW NOW, not whenever something next happens to ask.
         *
         * The comment below has always said "the draw locked" — but starting
         * only wrote `started_at`. The divisions stayed `provisional` until the
         * next call to DrawEngine::ensure(), which is triggered by a page view.
         * Bouts fought in that window were laid on the provisional bracket, and
         * the first bracket view afterwards re-cut the division as the final
         * paid-only draw — deleting every result on it. A ten-bout simulated
         * championship lost all ten this way (2026-09-13).
         *
         * The package's own hook builds the final draw once and marks it final,
         * so the window closes here, before anybody can load a bout on a mat.
         */
        $this->typeFor($event)->onEntrantsChanged($event->fresh());

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
            'at' => $item->checked_at?->format('j M, g:i A'),
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

        /*
         * ⚠️ THE SPORT, unless the query is a PHONE NUMBER.
         *
         * A mat role searches the whole platform, which is right — a referee is
         * a federation official, not a club member — but "the whole platform"
         * is thousands of people, and an organiser looking for a jiu-jitsu
         * referee has no use for a swimming coach with a similar name. So the
         * wide pool is narrowed to people this sport already knows: anybody who
         * has officiated it, competed in it, or belongs to a club that runs it.
         *
         * A PHONE NUMBER drops that narrowing on purpose (asked for
         * 2026-09-14). Somebody searching a number has the person in front of
         * them or on the phone; they know exactly who they mean, and the last
         * thing that should happen is a list of nobody because the platform has
         * not yet seen that person at a jiu-jitsu event.
         */
        $sportPool = ($wide && $phone === '') ? $this->sportPeople($event) : null;

        $candidates = User::query()
            ->distinct()
            ->when($sportPool !== null, fn ($q) => $q->whereIn('users.id', $sportPool))
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
                    // The face the desk took for THIS event, ahead of whatever
                    // profile picture the person may have — see officiating().
                    'avatar' => $o->photo ? file_url($o->photo) : ($shape($o->user)['avatar'] ?? null),
                    // Volunteer or paid, and how much — a paid appointment is a
                    // line in the event's P&L, kept in step automatically.
                    'compensation' => $o->compensation,
                    'fee' => $o->fee !== null ? (float) $o->fee : null,
                    // Whose referee this is. `club` is the key the picker
                    // re-selects on an edit; the name and logo are what the
                    // row prints. Null throughout means independent.
                    'club' => $o->club()['slug'] ?? null,
                    'club_name' => $o->club()['name'] ?? null,
                    'club_logo' => $o->club()['logo'] ?? null,
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
            'currency' => $event->host()->currency ?: 'BHD',
            /*
             * The clubs an official can be named with: the ones already at this
             * event plus the host, in the same two key shapes an ENTRY uses. An
             * empty list is a fine answer — the picker then offers only
             * "independent", which is the truth about an event nobody has
             * entered yet.
             */
            'clubs' => \App\Events\Support\EventClubChoice::options($event),
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
    /**
     * Appoint somebody the platform has never met.
     *
     * The other half of the officials desk, and the same pair of doors the
     * ENTRY desk has: find a person who is already here, or write down the one
     * standing in front of you. A federation sends three referees and two of
     * them have never opened this app — before this, an organiser could only
     * search for people who were already on it, and those two could not be put
     * on the sheet at all.
     *
     * The record it creates is a PERSON, not an account (UnclaimedPerson):
     * no password, no email, not discoverable, claimable later. The name is the
     * only thing demanded — an officiating sheet prints a name and a country,
     * and a guessed anything else is worse than a blank.
     */
    public function storeOfficialPerson(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'role' => ['required', Rule::in(array_keys($this->officialRoleOptions($event)))],
            'compensation' => ['required', Rule::in(EventOfficial::compensations())],
            'fee' => ['nullable', 'numeric', 'min:0.001', 'max:999999', 'required_if:compensation,'.EventOfficial::COMP_PAID],
            'nationality' => ['nullable', 'string', 'size:2', 'alpha', Rule::in($this->countryCodes())],
            'phone' => ['nullable', 'string', 'max:32'],
            'phone_code' => ['nullable', 'string', 'max:8'],
            /*
             * Asked for, never demanded. Staff writing somebody else down
             * rarely have these, and an invented birthdate is worse than a
             * blank — it looks authoritative and nobody revisits it (CLAUDE.md
             * → Who Fills The Form Decides What It Demands). The FORMAT is
             * still enforced when a value is given.
             */
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'birthdate' => ['nullable', 'date', 'before:today'],
            // Taken at the desk by the shared photo flow, already cropped 3:4.
            'photo' => ['nullable', 'string', 'starts_with:data:image/'],
            /*
             * Whose referee this is — a tenant slug, or an event club's uuid
             * for a visiting club with no account. Optional, because plenty of
             * officials come with nobody, and an unrecognised key resolves to
             * independent rather than failing (EventClubChoice::resolve).
             */
            'club' => ['nullable', 'string', 'max:190'],
        ]);

        /*
         * A role that GRANTS ACCESS is never created this way.
         *
         * A jury or payments role is permission over this club's event, and it
         * belongs to somebody the club knows — `mayHoldAccessRole()` enforces
         * exactly that for an existing member. A brand-new record cannot
         * satisfy it and must not be able to sidestep it by being new.
         */
        if (! $this->isMatRole($event, $data['role'])) {
            return response()->json([
                'success' => false,
                'message' => __('personal.personal_event_officials_new_mat_only'),
            ], 422);
        }

        $person = \App\Events\Support\UnclaimedPerson::make($data['full_name'], [
            'gender' => $data['gender'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'phone' => $data['phone'] ?? null,
            'phone_code' => $data['phone_code'] ?? null,
        ]);

        $official = new EventOfficial([
            'event_id' => $event->id,
            'user_id' => $person->id,
            'role' => $data['role'],
        ]);

        $official->assigned_by = $me->id;
        $official->compensation = $data['compensation'];
        $official->fee = $data['compensation'] === EventOfficial::COMP_PAID ? $data['fee'] : null;
        $official->forceFill(\App\Events\Support\EventClubChoice::resolve($event, $data['club'] ?? null));
        $official->save();

        /*
         * The photograph, if one was taken — and a failure here never costs the
         * APPOINTMENT. A referee on the sheet without a face is a small problem;
         * a referee who is not on the sheet at all because their photo would not
         * store is a much bigger one.
         */
        if (! empty($data['photo'])) {
            // The person's own store — see officialPhoto() above.
            $path = \App\Members\Support\ProfilePhoto::capture(
                $official->user,
                $data['photo'],
                'events/'.$event->uuid.'/officials',
                'o'.$official->id.'-'.Str::random(16),
            );

            if ($path !== null) {
                $official->update(['photo' => $path]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('personal.personal_event_officials_added', [
                'role' => $this->officialRoleOptions($event)[$data['role']] ?? $data['role'],
            ]),
            'official' => [
                'id' => $official->id,
                'user_id' => $person->id,
                'name' => $person->full_name,
                'email' => null,
                'phone' => is_array($person->mobile) && ! empty($person->mobile['number'])
                    ? trim(($person->mobile['code'] ?? '').' '.$person->mobile['number'])
                    : null,
                'avatar' => $official->photo ? file_url($official->photo) : null,
                'nationality' => $person->nationality,
                'role' => $official->role,
                'compensation' => $official->compensation,
                'fee' => $official->fee,
                'club' => $official->club()['slug'] ?? null,
                'club_name' => $official->club()['name'] ?? null,
                'club_logo' => $official->club()['logo'] ?? null,
            ],
        ]);
    }

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
            /*
             * Whose referee this is — a tenant slug, or an event club's uuid
             * for a visiting club with no account. Optional, because plenty of
             * officials come with nobody, and an unrecognised key resolves to
             * independent rather than failing (EventClubChoice::resolve).
             */
            'club' => ['nullable', 'string', 'max:190'],
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
        $official->forceFill(\App\Events\Support\EventClubChoice::resolve($event, $data['club'] ?? null));
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
            // Whose referee this is — see storeOfficial(). An empty string is a
            // deliberate "independent"; the key being ABSENT leaves whatever is
            // recorded alone (CLAUDE.md → Absent is not the same as blank).
            'club' => ['nullable', 'string', 'max:190'],
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

        if ($request->has('club')) {
            $official->forceFill(\App\Events\Support\EventClubChoice::resolve($event, $data['club'] ?? null));
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
                'club' => $official->club()['slug'] ?? null,
                'club_name' => $official->club()['name'] ?? null,
                'club_logo' => $official->club()['logo'] ?? null,
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

        /*
         * ⚠️ One at a time, NOT `->delete()` on the query.
         *
         * A mass delete does not fire Eloquent's model events, so the trait that
         * purges an appointment's photograph never ran and the file was left on
         * disk with nothing pointing at it (CLAUDE.md → Delete Files Before
         * Records, and its caveat about bulk deletes). There is at most one row
         * here, so iterating costs nothing.
         */
        $event->officials()->whereKey($official)->get()
            ->each(fn (EventOfficial $row) => $row->delete());


        return response()->json([
            'success' => true,
            'message' => __('personal.personal_event_officials_removed'),
        ]);
    }

    public function create(Request $request): View
    {
        $me = Auth::user();
        $clubs = $this->clubsICanCreateFor($me);

        // Arriving from a club's admin panel (?club={slug}): that club is
        // chosen — but only if it is one this person may create for.
        $slug = (string) $request->query('club', '');
        $preselect = $slug !== '' ? \App\Clubs\Models\Tenant::where('slug', $slug)->value('id') : null;
        $preselect = collect($clubs)->contains(fn ($c) => (int) $c['id'] === (int) $preselect) ? (int) $preselect : null;

        // Other organisers this person may act for (a federation they manage),
        // and arriving from one (?host=federation:{uuid}) chooses it.
        $clubs = array_merge($this->withMyself($clubs), $this->hostChoices($me));
        $host = (string) $request->query('host', '');
        if ($host !== '' && collect($clubs)->contains('id', $host)) {
            $preselect = $host;
        }

        // The fee hint under Fee types: the organiser's last planned budget, as a yardstick.
        $budgetYardstick = \App\Events\Support\Budget\BudgetPlan::yardstick(Auth::user());

        return view('events::personal.event-create', ['clubs' => $clubs, 'preselectClub' => $preselect, 'budgetYardstick' => $budgetYardstick] + $this->schemaPayload());
    }

    /** Schema + (in edit mode) the event's existing divisions for the form. */
    private function schemaPayload(?ClubEvent $event = null): array
    {
        $divisions = $event
            ? $event->categories()->orderBy('sort_order')->get(['id', 'name', 'is_heading', 'capacity', 'schedule'])
                ->map(fn ($c) => [
                    'name' => $c->name,
                    /*
                     * ⚠️ Load-bearing. Without it the form received an event's
                     * headings as ordinary divisions — rendered as division
                     * cards, offered a weight range, and posted back through
                     * SyncsDivisions as though they were things people compete
                     * in. A heading has to survive a round trip through the
                     * edit form as a heading.
                     */
                    'is_heading' => (bool) $c->is_heading,
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

        // "Myself" in the editor: a self-organised event, no club (events.person_hosts).
        $asPerson = config('events.person_hosts') && $request->input('tenant_id') === 'me';
        // Another organiser this person acts for — a federation (CreatesHostedEvents).
        $asHost = ! $asPerson && is_string($request->input('tenant_id'))
            && collect($this->hostChoices($me))->contains('id', $request->input('tenant_id'));

        $data = $request->validate([
            'tenant_id' => $asPerson ? ['required', 'in:me'] : ($asHost ? ['required', 'string'] : ['required', 'integer']),
            'title' => ['required', 'string', 'max:140'],
            'event_type' => ['required', Rule::in(array_keys($this->types()))],
            'scope' => ['nullable', Rule::in(array_keys($this->scopes()))],
            'date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            // The competition's clock (ClubEvent::zone()). A real identifier or nothing.
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'weigh_in_at' => ['nullable', 'date'],
            // Let the deadline take unpaid / un-weighed entries off by itself.
            // Opt-in, because it deletes entries (App\Events\Support\EntryDeadline).
            'entry_deadline_enforced' => ['nullable', 'boolean'],
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
            'fee_selection' => ['nullable', Rule::in(['single', 'multiple'])],
            'late_fee_from' => ['nullable', 'date'],
            'max_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'prize' => ['nullable', 'string', 'max:120'],
            'sport' => ['nullable', Rule::in(array_keys($this->sports()))],
            // The event's theme colour — it lands in style attributes, so a
            // 6-digit hex and nothing else. Absent: the type's own colour.
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], $this->eventMessages());

        if ($asHost) {
            // Created through that organiser's OWN storage (a federation's
            // dashboard row, invitation, draws), which then makes the platform
            // event. Whatever it refuses is refused here.
            $type = $this->typeForInput($data);
            $data += $request->validate($type->validationRules(), $this->eventMessages());
            $event = $this->hostModule((string) $data['tenant_id'])?->createHostedEvent(
                (string) $data['tenant_id'], $me,
                $data + ['fee_options' => (array) $request->input('fee_options', [])],
            );
            if (! $event) {
                return response()->json(['success' => false, 'message' => __('events::events.host_create_refused')], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Event created 🎉',
                'redirect' => route('me.events.show', $event->uuid),
            ]);
        }

        if ($asPerson) {
            $data += $this->personHostInput($request);
            // No club → no "this club only"; and it is announced to nobody
            // (AudienceResolver needs a host club), so no broadcast power to check.
            if (($data['scope'] ?? 'internal') === 'internal') {
                $data['scope'] = 'inter_club';
            }
        } else {
            // Must belong to — or run — the club you're creating the event for.
            abort_unless(
                collect($this->clubsICanCreateFor($me))->contains('id', (int) $data['tenant_id']),
                403,
            );

            $this->assertMayBroadcast($me, (int) $data['tenant_id'], $data['scope'] ?? 'internal');
        }

        // The owning package validates and normalises its own half of the payload.
        $type = $this->typeForInput($data);
        $data += $request->validate($type->validationRules(), $this->eventMessages());

        // The host is set explicitly (forceFill below), never mass-assigned.
        $event = ClubEvent::create($type->columnsFromInput($data) + [
            'tenant_id' => $asPerson ? null : $data['tenant_id'],
            'created_by' => $me->id,
            'title' => $data['title'],
            'event_type' => $data['event_type'],
            'scope' => $data['scope'] ?? 'internal',
            'icon' => $this->typeIcon($data['event_type']),
            'timezone' => $data['timezone'] ?? null,
            'date' => $data['date'],
            'end_date' => $data['end_date'] ?? null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'] ?? null,
            'weigh_in_at' => $data['weigh_in_at'] ?? null,
            'entry_deadline_enforced' => (bool) ($data['entry_deadline_enforced'] ?? false),
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
            'color' => $data['color'] ?? $this->typeColor($data['event_type']),
            'status' => 'active',
            'is_archived' => false,
            // Priced in the host club's currency, from the amount that was
            // typed — never from a sentence assembled in the browser.
        ] + $this->feeColumns($data, $asPerson
            ? $data['fee_currency']
            : (Tenant::whereKey($data['tenant_id'])->value('currency') ?: 'BHD')));

        if ($asPerson) {
            // The person IS the host; where entrants send the money is their own
            // details (EntryGates::bankFor).
            $event->forceFill(['host_type' => 'person', 'host_id' => $me->id, 'host_payment' => $data['host_payment']])->save();
        }

        $this->syncFeeOptions($event, $data);
        $this->refreshFeeDisplay($event);

        // The rule book the organiser picked — only one this host may use.
        $systems = app(\App\Events\Support\CategorySystems::class);
        $ruleBook = $request->validate([
            'category_system' => ['nullable', 'string', 'max:64'],
            'belt_system' => ['nullable', 'string', 'max:64'],
        ]);
        $pick = fn (?string $k, bool $ok) => $k && $ok ? $k : null;
        $event->forceFill([
            'category_system' => $pick($ruleBook['category_system'] ?? null, $systems->validWeights($event, $ruleBook['category_system'] ?? null)),
            'belt_system' => $pick($ruleBook['belt_system'] ?? null, $systems->validBelts($event, $ruleBook['belt_system'] ?? null)),
        ])->save();

        $type->saveRelatedData($event, $data);

        // Announce it to everyone the event's scope reaches. Queued and capped —
        // a nationwide announcement never runs inline in this request.
        app(\App\Events\Support\EventNotifier::class)->fireOnce($event->fresh(), 'created');

        return response()->json([
            'success' => true,
            'message' => 'Event created 🎉',
            'redirect' => route('me.events.show', $event->uuid),
        ]);
    }

    public function edit(ClubEvent $event): View
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $clubs = $this->clubsICanCreateFor($me, $event);
        // A self-organised event shows "Myself" as its organiser (and keeps it).
        if ($event->host_type === 'person' && ! $event->tenant_id) {
            $clubs = $this->withMyself([], true);
        }

        return view('events::personal.event-create', ['clubs' => $clubs, 'mode' => 'edit', 'event' => $event] + $this->schemaPayload($event));
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
            // The competition's clock (ClubEvent::zone()). A real identifier or nothing.
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'weigh_in_at' => ['nullable', 'date'],
            // Let the deadline take unpaid / un-weighed entries off by itself.
            // Opt-in, because it deletes entries (App\Events\Support\EntryDeadline).
            'entry_deadline_enforced' => ['nullable', 'boolean'],
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
            'fee_selection' => ['nullable', Rule::in(['single', 'multiple'])],
            'late_fee_from' => ['nullable', 'date'],
            'max_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'prize' => ['nullable', 'string', 'max:120'],
            'sport' => ['nullable', Rule::in(array_keys($this->sports()))],
            // A hosted event (a federation's, a person's) has no club to move to:
            // whatever its picker posted ("me" for "Myself") is ignored below.
            'tenant_id' => (! $event->tenant_id && $event->host_type) ? ['nullable'] : ['nullable', 'integer'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
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
        // An event whose host is NOT a club (a federation's competition) has
        // no club to move to: its host stays, whatever a club picker posted,
        // and the power to broadcast it is the host's own managers'. Without
        // this the empty club id was written back as 0, detaching the event.
        $hosted = ! $event->tenant_id && $event->host_type;
        $host = $hosted ? null : (isset($data['tenant_id']) ? (int) $data['tenant_id'] : (int) $event->tenant_id);

        if (! $hosted && $host !== (int) $event->tenant_id) {
            abort_unless(
                collect($this->clubsICanCreateFor($me, $event))->contains('id', $host),
                403,
            );
        }

        // Widening the scope on an EDIT is the same broadcast power as setting
        // it at creation — guard it identically, and against the club the event
        // is being moved TO.
        if ($hosted) {
            if (in_array($data['scope'] ?? $event->scope, config('event_notifications.broadcast_scopes', []), true)) {
                abort_unless($me->isSuperAdmin() || $event->host()->manages($event, $me), 403);
            }
        } else {
            $this->assertMayBroadcast($me, $host, $data['scope'] ?? $event->scope);
        }

        // The rule book: a change re-sorts everybody entered, and is refused —
        // before anything else is saved — once a draw has been cut.
        $ruleBook = $request->validate([
            'category_system' => ['nullable', 'string', 'max:64'],
            'belt_system' => ['nullable', 'string', 'max:64'],
        ]);
        $ruleBookSent = $request->has('category_system') || $request->has('belt_system');
        if ($ruleBookSent) {
            $systems = app(\App\Events\Support\CategorySystems::class);
            $cat = $request->has('category_system') ? ($ruleBook['category_system'] ?: null) : $event->category_system;
            $belt = $request->has('belt_system') ? ($ruleBook['belt_system'] ?: null) : $event->belt_system;
            if (! $systems->validWeights($event, $cat) || ! $systems->validBelts($event, $belt)) {
                return response()->json(['success' => false, 'message' => __('events::events.rulebook_invalid')], 422);
            }
            if ($cat !== $event->category_system && \App\Events\Models\EventMatch::where('event_id', $event->id)->exists()) {
                return response()->json(['success' => false, 'message' => __('events::events.rulebook_locked')], 422);
            }
        }

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
            'timezone', 'end_date', 'end_time', 'weigh_in_at', 'entry_deadline_enforced', 'enrollment_starts_at', 'enrollment_ends_at',
            'location', 'gps_lat', 'gps_long', 'location_url', 'break_start', 'break_end',
            'level', 'description', 'prize', 'max_capacity',
        ];

        $columns = [];

        foreach ($optional as $key) {
            if (array_key_exists($key, $data)) {
                $columns[$key] = $data[$key];
            }
        }

        // The theme colour: only when one was sent. Never cleared to nothing —
        // every header on the event is drawn from it.
        if (! empty($data['color'])) {
            $columns['color'] = $data['color'];
        }

        $event->update($type->columnsFromInput($data, $event) + $columns + ($hosted ? [] : ['tenant_id' => $host]) + [
            'title' => $data['title'],
            'event_type' => $data['event_type'],
            'scope' => $data['scope'] ?? $event->scope ?? 'internal',
            'icon' => $event->icon ?: ($this->typeIcon($data['event_type'])),
            'date' => $data['date'],
            'start_time' => $data['start_time'],
        ] + $this->feeColumns($data, $event->host_type === 'person' && ! $event->tenant_id && $request->filled('fee_currency')
            ? $this->personHostInput($request)['fee_currency']
            : EventFee::currency($event)));

        if ($event->host_type === 'person' && ! $event->tenant_id && $request->has('host_payment')) {
            $event->forceFill(['host_payment' => $this->personHostInput($request)['host_payment']])->save();
        }

        $this->syncFeeOptions($event, $data);
        $this->refreshFeeDisplay($event);

        // Divisions, fixtures, re-scheduling — whatever this type keeps outside
        // the event row.
        $type->saveRelatedData($event, $data);

        if ($ruleBookSent) {
            app(\App\Events\Support\CategorySystems::class)->change($event->fresh(), $cat, $belt);
        }

        // An event another kind of organiser hosts (a federation's) keeps its
        // own record there too: bring it into step with what was just saved.
        \App\Events\Support\EventHost::changed($event->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Event updated',
            'redirect' => route('me.events.show', $event->uuid),
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
    /**
     * Organisers other than a club or "Myself" that this person may act for in
     * the editor — a federation they manage (App\Support\Modules\Surfaces\
     * CreatesHostedEvents). Each module decides its own list.
     *
     * @return list<array{id: string, name: string, currency: ?string, timezone: ?string}>
     */
    private function hostChoices(User $me): array
    {
        return app(\App\Support\Modules\ModuleRegistry::class)
            ->contributingTo(\App\Support\Modules\Surfaces\CreatesHostedEvents::class)
            ->flatMap(fn ($m) => rescue(fn () => $m->eventHostChoices($me), [], report: true))
            ->values()->all();
    }

    /** The module that owns a host choice id ("federation:…"). */
    private function hostModule(string $choice): ?\App\Support\Modules\Surfaces\CreatesHostedEvents
    {
        $me = Auth::user();

        return app(\App\Support\Modules\ModuleRegistry::class)
            ->contributingTo(\App\Support\Modules\Surfaces\CreatesHostedEvents::class)
            ->first(fn ($m) => collect(rescue(fn () => $m->eventHostChoices($me), [], report: true))->contains('id', $choice));
    }

    /**
     * The organiser picker's "Myself" choice (events.person_hosts): an event
     * with no club, its host the person creating it. Last in the list, so a
     * member of a club still lands on their club by default.
     */
    private function withMyself(array $clubs, bool $force = false): array
    {
        if (! $force && ! config('events.person_hosts')) {
            return $clubs;
        }
        $clubs[] = ['id' => 'me', 'name' => __('personal.personal_event_create_host_myself'), 'currency' => null, 'timezone' => null];

        return $clubs;
    }

    /**
     * A self-organised event's own money details: the currency it charges in
     * and where to send it — the same fields a club's bank account has.
     *
     * @return array{fee_currency: string, host_payment: array<string, string>|null}
     */
    private function personHostInput(Request $request): array
    {
        $v = $request->validate([
            'fee_currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'host_payment' => ['nullable', 'array'],
            'host_payment.bank_name' => ['nullable', 'string', 'max:120'],
            'host_payment.account_name' => ['nullable', 'string', 'max:120'],
            'host_payment.account_number' => ['nullable', 'string', 'max:60'],
            'host_payment.iban' => ['nullable', 'string', 'max:60'],
            'host_payment.benefitpay' => ['nullable', 'string', 'max:60'],
        ]);
        $pay = array_filter(array_map(fn ($x) => is_string($x) ? trim($x) : null,
            array_intersect_key((array) ($v['host_payment'] ?? []), array_flip(['bank_name', 'account_name', 'account_number', 'iban', 'benefitpay']))));

        return ['fee_currency' => strtoupper($v['fee_currency'] ?? '') ?: 'BHD', 'host_payment' => $pay ?: null];
    }

    private function feeColumns(array $data, string $currency): array
    {
        $free = (bool) ($data['participant_free'] ?? false);
        $spectators = (bool) ($data['spectator_enabled'] ?? false);

        $pAmount = isset($data['participant_fee_amount']) ? (float) $data['participant_fee_amount'] : null;
        $sAmount = isset($data['spectator_fee_amount']) ? (float) $data['spectator_fee_amount'] : null;

        $columns = ['fee_currency' => $currency, 'spectator_enabled' => $spectators];

        // Radio or checkboxes on the entrant's side. Absent leaves it alone.
        if (in_array($data['fee_selection'] ?? null, ['single', 'multiple'], true)) {
            $columns['fee_selection'] = $data['fee_selection'];
        }

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
            $columns['participant_fee'] = ($data['participant_fee'] ?? null) ?: __('events::events.fee_free');
        }

        // Spectators.
        if (! $spectators) {
            $columns['spectator_fee'] = null;
            $columns['spectator_fee_amount'] = null;
        } elseif ($sAmount !== null) {
            $columns['spectator_fee'] = EventFee::display($sAmount, $currency);
            $columns['spectator_fee_amount'] = $sAmount;
        } else {
            $columns['spectator_fee'] = ($data['spectator_fee'] ?? null) ?: __('events::events.fee_free');
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
        // Moved verbatim to EventFee so the budget's "apply this fee" writes
        // the line the same way (2026-10-13).
        EventFee::refreshDisplay($event);
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
                'message' => __('events::events.results_derived_from_engine'),
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

    /**
     * The running order — ONE page, two views of the same day.
     *
     * There were two boards here, and the console carried two rows both reading
     * "Running order", which is the sort of thing that costs somebody ten
     * minutes on an event morning. They were never rivals: THE DAY is the
     * order of the day's PARTS — weigh-in, the opening, each division, the
     * prayer break — with every clock time worked out from it (RunSheet), and
     * THE MATS is the order of the BOUTS inside those divisions, one column per
     * mat (OrderBoard). One says when a division happens, the other says what
     * happens inside it.
     *
     * So they became two tabs of one page rather than one merged engine: the
     * two write different tables for different jobs, and welding them together
     * would be a rewrite of two working boards to solve a naming problem.
     *
     * ── Who sees which tab ────────────────────────────────────────────────
     * Reading THE MATS is for whoever may score — the table runs the day off
     * that board — while THE DAY is the organiser's timetable and stays behind
     * canManage, exactly as it was on its own page. An official who may score
     * but not manage therefore opens this page on the mats and is shown no tab
     * strip at all, rather than a tab that would 403. Every endpoint behind
     * both boards re-checks for itself; this only decides what to OFFER.
     */
    public function runningOrderPage(Request $request, ClubEvent $event)
    {
        $me = Auth::user();
        $access = app(EventAccess::class);

        abort_unless($access->canScore($event, $me), 403);

        $canManage = $this->canManage($event, $me);

        $e = $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: true);
        $isMobile = (bool) $request->attributes->get('is_mobile');
        $day = $this->runOrderDay($request);

        /*
         * Which tab opens. The organiser's day is the default because it is the
         * outer question — where does this division sit in the afternoon —
         * and the mats are the drill-down from it. `?view=mats` is what the day
         * board's own division rows link to, and what the old day-order address
         * redirects with.
         */
        $view = $request->query('view') === 'mats' || ! $canManage ? 'mats' : 'day';

        return view($isMobile ? 'events::personal.mobile.event-order' : 'events::personal.desktop.event-order', [
            'e' => $e,
            'canArrange' => $canManage,
            'canManage' => $canManage,
            'view' => $view,
            'day' => $day,
            // The board's FIRST PAINT, so the page never opens empty waiting on
            // a request — see the component's `plan` prop.
            'plan' => app(OrderBoard::class)->plan($event, (int) $request->query('day') ?: null),
            // The day tab's first paint, the same way. Only built for somebody
            // who may actually see it.
            'sheet' => $canManage ? $this->runOrderPayload($event, $day) : null,
        ]);
    }

    /**
     * The running order, as a board an organiser can rearrange.
     *
     * Read is open to anyone who may SCORE the event — the table needs to see
     * the day it is about to run — while the two writes below are the
     * organiser's, because a timetable is theirs.
     */
    public function runningOrder(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        abort_unless(app(EventAccess::class)->canScore($event, $me), 403);

        return response()->json([
            'success' => true,
        ] + app(OrderBoard::class)->plan($event, (int) $request->query('day') ?: null));
    }

    /** Drag one bout to a mat and a position. */
    public function moveInRunningOrder(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'match' => ['required', 'integer'],
            // The mat's own label, checked against the event's real mats by the
            // board rather than trusted: `court` is free text on the row.
            'court' => ['required', 'string', 'max:40'],
            'position' => ['required', 'integer', 'min:0', 'max:999'],
            'day' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $board = app(OrderBoard::class);
        $result = $board->move($event, (int) $data['match'], $data['court'], (int) $data['position'], $data['day'] ?? null);

        if (! ($result['ok'] ?? false)) {
            return response()->json(['success' => false] + $result, 422);
        }

        // Every other console and every wall board on this event re-reads what
        // it may see — the running order is what they all draw (CLAUDE.md →
        // Realtime, the refresh shape).
        rescue(fn () => app(\App\Events\EventTypeRegistry::class)->for($event)
            ->onEntrantsChanged($event, null), null, false);

        // …and the hall screens, whose queue IS the running order.
        \App\Events\Support\HallWalls::refresh($event);

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? null,
        ] + $board->plan($event, $data['day'] ?? null));
    }

    /**
     * "Renumber bouts" — number every bout from the day's running order now,
     * and refresh every bracket, wall and mats board (App\Events\Support\BoutNumbering).
     */
    public function renumberBouts(ClubEvent $event): JsonResponse
    {
        $this->assertCanManage($event, Auth::user());

        $result = app(\App\Events\Support\BoutNumbering::class)->renumber($event);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'numbered' => $result['numbered'] ?? null,
            'kept' => $result['kept'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    /** Lay the whole day out again from scratch — every hand placement goes. */
    public function autoRunningOrder(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $board = app(OrderBoard::class);
        $board->auto($event);
        \App\Events\Support\HallWalls::refresh($event);

        return response()->json([
            'success' => true,
            'message' => __('events::events.order_auto_done'),
        ] + $board->plan($event, (int) $request->input('day') ?: null));
    }

    /**
     * Begin a dress rehearsal — run the whole competition, then take it back.
     *
     * See App\Events\Support\Rehearsal for what is snapshotted, what is held
     * back while it runs, and why it cannot begin on an event that has started.
     */
    public function beginRehearsal(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        try {
            $rehearsal = app(Rehearsal::class)->begin($event, $me);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('events::events.rehearsal_started_msg'),
            'rehearsal' => [
                'running' => true,
                'started_at' => $rehearsal->started_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * End it, and put the event back exactly as it was.
     *
     * Idempotent: ending a rehearsal that is not running is a success with
     * nothing to do, because the alternative is an organiser stuck looking at a
     * button that answers with an error.
     */
    public function endRehearsal(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        app(Rehearsal::class)->end($event);

        return response()->json([
            'success' => true,
            'message' => __('events::events.rehearsal_ended_msg'),
            'rehearsal' => ['running' => false],
        ]);
    }

    /**
     * Mint the code an organiser must type to call off or erase this event.
     *
     * Behind the same authorisation as the act itself: somebody who may not
     * cancel an event may not find out that asking is even possible.
     */
    public function dangerCode(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(DestructiveChallenge::ACTIONS)],
        ]);

        $issued = app(DestructiveChallenge::class)->issue($event, (int) $me->id, $data['action']);

        return response()->json([
            'success' => true,
            'code' => $issued['code'],
            'expires_in' => $issued['expires_in'],
        ]);
    }

    /**
     * Mark an event cancelled (kept visible, flagged).
     *
     * Gated by a typed code (DestructiveChallenge). Calling a competition off
     * is not undone by pressing the button again — the entrants have been told
     * by then — so it is asked for in full, exactly like the delete below.
     */
    public function cancelEvent(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        if (! $this->passedDangerGate($request, $event, (int) $me->id, 'cancel')) {
            return $this->dangerRefusal();
        }

        $event->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Event cancelled',
            'redirect' => route('me.events'),
        ]);
    }

    /**
     * Permanently delete an event (cascades registrations, categories, matches).
     *
     * The most destructive button in the product, and until 2026-09-16 it was
     * one tap and one reflex dialog away. It now needs a code the server issued
     * and the organiser typed back — see DestructiveChallenge.
     */
    public function destroy(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        if (! $this->passedDangerGate($request, $event, (int) $me->id, 'delete')) {
            return $this->dangerRefusal();
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted',
            'redirect' => route('me.events'),
        ]);
    }

    /** The typed code, checked against the one this server issued. */
    private function passedDangerGate(Request $request, ClubEvent $event, int $actorId, string $action): bool
    {
        return app(DestructiveChallenge::class)
            ->verify($event, $actorId, $action, (string) $request->input('code', ''));
    }

    /**
     * One answer for every way of not having the code.
     *
     * 422 rather than 403: the person is allowed to do this, they have simply
     * not confirmed it yet, and the console re-opens the sheet on `code` rather
     * than bouncing them off the page.
     */
    private function dangerRefusal(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'field' => 'code',
            'message' => __('personal.event_danger_code_wrong'),
        ], 422);
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

        // A host that takes its athletes from clubs (a federation): the club
        // enters them, through the host's door — never the member here.
        if (! $alreadyIn && ($door = $event->host()->entryDoor($event, $me))) {
            return response()->json([
                'success' => false,
                'code' => 'club_entry',
                'message' => __('events::events.host_entry_body', ['host' => $event->host()->name]),
                'url' => $door['url'],
            ], 422);
        }

        // The organiser takes this event's entries through clubs only
        // (EntryChannels): a member's club enters them.
        if (! $alreadyIn && ! app(\App\Events\Support\EntryChannels::class)->allows($event, \App\Events\Support\EntryChannels::INDIVIDUAL)) {
            return response()->json([
                'success' => false,
                'code' => 'individual_closed',
                'message' => __('events::events.entry_individual_closed'),
            ], 422);
        }

        if (($event->hasStarted() || $event->isOverdueToStart()) && ! $alreadyIn) {
            return response()->json([
                'success' => false,
                'code' => 'started',
                'message' => __('events::events.entry_event_started'),
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

        // Enrollment window — through EntryWindow, so this door and the entry
        // service cannot disagree about whether a place can still be taken.
        // The named day runs to midnight where the COMPETITION is.
        if (\App\Events\Support\EntryWindow::hasNotOpened($event)) {
            return response()->json(['success' => false, 'message' => 'Registration opens '.$event->enrollment_starts_at->format('j M').'.'], 422);
        }
        if (\App\Events\Support\EntryWindow::hasClosed($event)) {
            return response()->json(['success' => false, 'message' => 'Registration closed on '.$event->enrollment_ends_at->format('j M').'.'], 422);
        }

        // Capacity guard (participants only).
        if ($event->max_capacity && $event->participantRegistrations()->count() >= $event->max_capacity
            && ! $event->registrations()->where('user_id', $me->id)->where('role', 'participant')->exists()) {
            return response()->json(['success' => false, 'message' => 'This event is full.'], 422);
        }

        // The owning package decides whether this member may compete and, when
        // the type is divisioned, which division they belong in. It classifies
        // them from their own profile — a member never picks their own class.
        /*
         * WHICH of their entries this door is talking about.
         *
         * A member may hold an entry in each activity the event runs — Gi and
         * No-Gi at one championship — and this endpoint is also how somebody
         * comes back to upload a receipt or change the club they represent. It
         * used to read `->first()` and write through `updateOrCreate` on
         * (event, user), so a member with two entries had whichever row came
         * first re-classified into the division the gate picked today, silently
         * (found 2026-09-11). So the row is chosen: the one in the activity
         * they named, else their earliest.
         *
         * One entry — every member before multi-activity entry — resolves to
         * exactly what `->first()` returned.
         */
        $held = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $me->id)
            ->orderBy('id')
            ->get();

        $existing = ($held->count() > 1 && ! empty($data['category_id'])
            ? $held->firstWhere('category_id', (int) $data['category_id'])
            : null) ?: $held->first();

        $decision = $type->enrolmentGate($event, $me, $existing);

        if (! $decision->allowed) {
            return response()->json([
                'success' => false,
                'code' => $decision->code,
                'spectator' => $decision->offerSpectator,
                'message' => $decision->message,
            ], 422);
        }

        /*
         * An entry that is already PLACED keeps its division. The gate's
         * classification is for an entry being made or one nobody has placed
         * yet; re-applying it to a member who came back for a receipt is how
         * one of two entries got moved out of the activity they entered.
         */
        $categoryId = $existing?->category_id
            ?: ($decision->category?->id ?? ($data['category_id'] ?? null));
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
                'message' => __('events::events.claim_not_your_club'),
            ], 422);
        }

        // Nothing chosen and only one club to choose from — there was no
        // decision to make, so do not leave the sheet unattached by accident.
        if ($representing === null && ! $existing?->representing_tenant_id && count($claimable) === 1) {
            $representing = $claimable[0];
        }

        $representing = $representing !== null ? (int) $representing : $existing?->representing_tenant_id;
        $claimChanged = $representing && (int) ($existing?->representing_tenant_id ?? 0) !== $representing;

        /* Keyed on the entry that was RESOLVED above, so a member holding
           several is never matched by (event, user) alone. */
        $registration = ClubEventRegistration::updateOrCreate(
            $existing
                ? ['id' => $existing->id]
                : ['event_id' => $event->id, 'user_id' => $me->id],
            [
                'event_id' => $event->id,
                'user_id' => $me->id,
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
                ? __('events::events.reg_proof_sent')
                : ($paidFee
                    ? __('events::events.reg_pay_at_club', ['fee' => $event->participant_fee])
                    : __('events::events.reg_confirmed'))).$note,
            'role' => 'participant',
            'division' => $division,
            'pending_payment' => $paidFee && (bool) $proofPath,   // proof recorded, awaiting approval
            'going' => $event->participantRegistrations()->count(),
            'going_people' => $event->participantRegistrations()->distinct()->count('user_id'),
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
            return response()->json(['success' => false, 'message' => __('events::events.action_unavailable')], 422);
        }

        $result = $type->performAction($event, $action, $request->all());

        return response()->json($result + [
            'redirect' => route('me.events.bracket.manage', $event->uuid),
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

        /*
         * ⚠️ THE PACKAGE'S OWN ANSWER WINS.
         *
         * This used to return `['success' => true, …] + $changed`, and `+`
         * keeps the LEFT operand — so a package that had refused, with its own
         * `success => false` and its own reason, was reported to the caller as
         * "Result recorded". A league fixture that this door could not write at
         * all answered 200 while the result was being wiped, and an id that
         * matched nothing answered 200 while nothing happened. A write endpoint
         * that cannot say "no" is worse than one that does not exist.
         */
        $ok = ($changed['ok'] ?? $changed['success'] ?? true) !== false;

        return response()->json($changed + [
            'success' => $ok,
            'message' => $changed['message'] ?? __('events::events.outcome_saved'),
        ], $ok ? 200 : 422);
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

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'events::personal.mobile.event-next-up' : 'events::personal.desktop.event-next-up', $payload);
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
            /*
             * Whether this reader may open the CONSOLE — which decides where the
             * board's one control goes, and nothing else.
             *
             * ⚠️ The board is `assertVisible` only, so ANY member who can see
             * the event can open it (it is exactly the sort of "here is the live
             * running order" link a coach forwards). The blade used to send its
             * back control to the console unconditionally, on the stated
             * assumption that "everybody who reaches THIS one is a signed-in
             * organiser". They are not: a competitor tapped the only control on
             * a chrome-less page and was thrown to the platform home by the 403
             * handler — a dead end with no tab bar and no drawer to escape
             * through (CLAUDE.md → *Unattended Devices Must Always Recover*, and
             * Navigation Integrity). Found by a navigation audit, 2026-09-08.
             *
             * The organiser's behaviour is unchanged; everybody else now gets a
             * door that opens.
             */
            'canManage' => $me !== null && (
                $this->canManage($event, $me)
                || app(EventAccess::class)->canOfficiate($event, $me)
            ),
        ];

        return $request->expectsJson()
            ? response()->json(['success' => true] + $payload)
            : view('events::personal.event-board', $payload);
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
     * Everyone on the platform this ORGANISER may add to their own event.
     *
     * Companion to entryRoster(), and deliberately a separate door: the roster
     * is the coach's — their own club, no query needed — while this one is the
     * organiser's, needs a query of at least two characters, and is gated on
     * running THIS event rather than on administering any club. The service
     * owns who is reachable; see EntryService::searchPeople().
     */
    public function entrySearch(Request $request, ClubEvent $event, EntryService $entries): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        // Only whoever runs this competition. A coach with entry authority is
        // not offered a platform-wide lookup — they have their roster.
        abort_unless(app(EventAccess::class)->canManage($event, $me), 403);

        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);

        return response()->json(['success' => true] + $entries->searchPeople($event, $me, $data['q'] ?? null));
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
            /*
             * WHICH ACTIVITIES each athlete is entering, keyed by athlete id.
             *
             * An event may run several activities — Gi and No-Gi at one
             * jiu-jitsu championship — and an athlete enters as many as they
             * are paying for, each becoming its own entry (owner's ruling,
             * 2026-09-11). Ids only, and each one is checked against THIS
             * event inside EntryService::enter(), which refuses a division
             * from anywhere else rather than trusting the number.
             *
             * Absent means what it always meant: one entry, the package's gate
             * choosing the division.
             */
            'divisions' => ['nullable', 'array', 'max:200'],
            'divisions.*' => ['array', 'max:'.EntryService::MAX_ACTIVITIES],
            'divisions.*.*' => ['integer'],
        ]);

        // Either authority opens this door: running a club whose athletes these
        // are, or running THIS event. The second was missing, so an organiser
        // who administers no club could not add anybody by account at all.
        // EntryService checks each athlete again regardless.
        abort_if($entries->administeredClubIds($me) === []
            && ! $me->isSuperAdmin()
            && ! app(EventAccess::class)->canManage($event, $me), 403);

        // A club enters its athletes in a federation's event through the
        // federation's own invitation; only the people running the event add
        // anybody here.
        if ($event->host()->entryDoor($event, $me) && ! app(EventAccess::class)->canManage($event, $me)) {
            return response()->json(['success' => false, 'code' => 'club_entry',
                'message' => __('events::events.host_entry_refused')], 403);
        }

        $result = $entries->enterMany(
            $event,
            $me,
            $data['user_ids'],
            $data['fee_options'] ?? [],
            $data['divisions'] ?? [],
        );

        // A squad landing at once changes the draw everyone else is looking at.
        if ($result['entered']) {
            $this->pushEventRefresh($event);
        }

        return response()->json([
            'success' => true,
            'message' => __('events::events.entry_result', [
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
            'message' => $public ? __('events::events.public_turned_on') : __('events::events.public_turned_off'),
            'entry_mode' => $data['entry_mode'],
            'url' => $public ? route('me.events.show', $event->uuid) : null,
        ]);
    }

    /**
     * Listed or hidden (ClubEvent::isHidden). Only whoever runs the event.
     */
    public function setVisibility(Request $request, ClubEvent $event, EventAccess $access): JsonResponse
    {
        $me = Auth::user();
        abort_if($event->is_archived || ! $access->canManage($event, $me), 403);

        $data = $request->validate([
            'visibility' => ['required', 'in:'.ClubEvent::LISTED.','.ClubEvent::HIDDEN],
        ]);
        $event->update(['visibility' => $data['visibility']]);

        return response()->json([
            'success' => true,
            'message' => $data['visibility'] === ClubEvent::HIDDEN ? __('events::events.visibility_now_hidden') : __('events::events.visibility_now_listed'),
            'visibility' => $data['visibility'],
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
                'message' => __('events::events.cover_rejected'),
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
            'message' => __('events::events.cover_saved'),
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
            'message' => __('events::events.cover_cleared'),
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

    /** Create a group by hand. */    /**
     * The weight-classes page.
     *
     * Its own screen rather than a slab on the console, for the same reason the
     * clubs and the officials got one: a division is not a console tile, it is a
     * subject with a list, an editor and consequences — entrants land in it and
     * the draw is cut from it. The console links to it.
     *
     * Organiser only. Everything on this page edits.
     */
    public function divisions(ClubEvent $event, Request $request): View
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $myReg = $this->myRegistrations($me->id, collect([$event->id]));

        return view('events::personal.event-divisions', [
            'e' => $this->eventView($event, $me->id, $myReg, true),
            /*
             * The sport's own tables, so this page can offer the federation's
             * weight classes instead of asking an organiser to type nine of
             * them. Comes from the event's TYPE (formCatalog) — the controller
             * never names a sport to fetch a table, and a type with no table
             * simply offers no preset.
             */
            'presets' => $this->rulebookPresets($event, rescue(fn () => $this->typeFor($event)->formCatalog(), [], false) ?: []),
            // The parts of the event entered separately — Gi and No-Gi here.
            // Empty on an event that runs one thing, and the sheet then asks
            // nothing about it.
            'streams' => EventStreams::of($event),
        ]);
    }


    /**
     * The divisions editor's data, as JSON — everything the weight-classes page
     * renders <x-event-divisions> with, for an editor that is not a Blade page:
     * the federation dashboard's Divisions tab (owner, 2026-10-14). Same
     * organiser-only rule as the page; every write still goes through the
     * endpoints below.
     */
    public function divisionsData(ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $cats = $event->categories()->orderBy('sort_order')->orderBy('id')->get();
        $faces = $this->facesFor($cats->pluck('id')->all());
        $presets = $this->rulebookPresets($event, rescue(fn () => $this->typeFor($event)->formCatalog(), [], false) ?: []);
        $criteria = \App\Events\Support\DivisionEditor::criteriaSports();
        $sport = $event->sport ?: null;

        return response()->json([
            'success' => true,
            'rows' => $cats->map(fn ($c) => $this->divisionPayload($c, $faces[$c->id] ?? []))->values()->all(),
            'phases' => $this->phaseDefs(),
            'days' => $this->dayCount($event),
            'presets' => \App\Events\Support\DivisionEditor::presetTable($presets),
            'belts' => \App\Events\Support\DivisionEditor::beltLadder($event, $sport),
            // Asked only where the sport divides by it — the same rule the
            // component applies (no sport at all asks both).
            'asks' => [
                'weight' => ! $sport || in_array($sport, $criteria['weight'], true),
                'rank' => ! $sport || in_array($sport, $criteria['rank'], true),
            ],
            'streams' => array_values(array_filter(EventStreams::of($event), fn ($r) => is_array($r) && ($r['key'] ?? '') !== '')),
            // One bracket per person (OneDivisionRule) — the tab's switch.
            'one_division' => (bool) $event->one_division_per_person,
        ]);
    }

    /** The phases a division can be scheduled across. */
    private function phaseDefs(): array
    {
        return [
            ['key' => 'preliminary', 'label' => __('personal.event_phase_prelim')],
            ['key' => 'quarterfinals', 'label' => __('personal.event_phase_quarters')],
            ['key' => 'finals', 'label' => __('personal.event_phase_finals')],
        ];
    }

    /** How many days the event runs over. Inclusive: a one-day event is 1, an end date the day after is 2. */
    private function dayCount(ClubEvent $e): int
    {
        return $e->end_date && $e->date ? max(1, $e->date->diffInDays($e->end_date) + 1) : 1;
    }

    public function storeDivision(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        // No name typed, and the caller asks for one (the draw desk's "Add
        // division", 2026-10-16): the next group letter and its own ages and
        // weights — App\Events\Support\GroupName, as the editors name it.
        if ($request->boolean('auto_name') && trim((string) $request->input('name')) === ''
            && ($letter = $this->nextGroupLetter($event)) !== null) {
            $request->merge(['name' => \App\Events\Support\GroupName::make($letter,
                $request->input('min_age'), $request->input('max_age'), $request->input('min_weight'), $request->input('max_weight'))]);
        }
        // Past Z there is no letter left: a plain numbered name the organiser
        // can rename, rather than refusing to add the division at all.
        if ($request->boolean('auto_name') && trim((string) $request->input('name')) === '') {
            $request->merge(['name' => __('events::events.tablet_div_new_title').' '.($event->categories()->count() + 1)]);
        }

        $data = $this->headingSafe($this->validateDivision($request, $event));

        $division = EventCategory::create($data + [
            'event_id' => $event->id,
            'status' => 'enrolling',
            'sort_order' => (int) $event->categories()->max('sort_order') + 1,
        ]);

        $this->redrawDivision($event, $division);
        $this->pushDivisionsChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('events::events.division_created'),
            'division' => $this->divisionPayload($division),
        ]);
    }

    /** The letter the next division at the end of the list takes (A, B… after the last heading), or null past Z. */
    private function nextGroupLetter(ClubEvent $event): ?string
    {
        $n = 0;
        foreach ($event->categories()->orderBy('sort_order')->orderBy('id')->get() as $c) {
            $n = $c->isHeading() ? 0 : $n + 1;
        }

        return $n < 26 ? chr(65 + $n) : null;
    }

    /**
     * Divisions from a spreadsheet (App\Events\Support\DivisionImport) — the
     * template, a preview, and the import. Organiser only, like every other
     * division write.
     *
     * Each row is judged by validateDivision(), the same rules a division typed
     * into the editor meets, so a spreadsheet can never make a division the
     * form would refuse. A row with no name is called by its group letter
     * ("Group A") and nothing else — never an age word or a weight (owner,
     * 2026-10-15).
     */
    public function divisionsImportTemplate(ClubEvent $event): \Symfony\Component\HttpFoundation\Response
    {
        $this->assertCanManage($event, Auth::user());

        return response(app(\App\Events\Support\DivisionImport::class)->template(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="divisions-template.xlsx"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Read the file and judge every row. Writes nothing. */
    public function divisionsImportPreview(Request $request, ClubEvent $event): JsonResponse
    {
        $this->assertCanManage($event, Auth::user());

        $request->validate(['file' => ['required', 'file', 'max:'.\App\Events\Support\DivisionImport::MAX_KB]],
            ['file.max' => 'That file is too large. Keep it under 1 MB.']);

        $read = app(\App\Events\Support\DivisionImport::class)->read($request->file('file')->getRealPath());
        if (! $read['ok']) {
            return response()->json(['success' => false, 'message' => $read['message']], 422);
        }

        $rows = $this->judgeImportRows($event, $read['rows']);

        return response()->json([
            'success' => true,
            'rows' => $rows,
            'ignored' => $read['ignored'],
            'ready' => count(array_filter($rows, fn ($r) => $r['status'] === 'ready')),
        ]);
    }

    /**
     * Create the rows the preview showed. Sent back as data rather than as the
     * file again, and judged again here — nothing the page says is trusted.
     * All or nothing: if any row is refused, none is made.
     */
    public function divisionsImport(Request $request, ClubEvent $event): JsonResponse
    {
        $this->assertCanManage($event, Auth::user());

        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:'.\App\Events\Support\DivisionImport::MAX_ROWS],
            'rows.*' => ['array'],
        ]);

        $fields = ['name', 'gender', 'min_age', 'max_age', 'min_weight', 'max_weight', 'capacity'];
        $rows = array_map(fn ($r, $i) => ['line' => (int) ($r['line'] ?? $i + 1), 'errors' => []]
            + array_intersect_key($r, array_flip($fields)), $data['rows'], array_keys($data['rows']));

        $judged = $this->judgeImportRows($event, $rows);
        $bad = array_values(array_filter($judged, fn ($r) => $r['status'] === 'error'));
        if ($bad !== []) {
            return response()->json(['success' => false, 'rows' => $judged,
                'message' => 'Line '.$bad[0]['line'].': '.$bad[0]['errors'][0]], 422);
        }

        $make = array_values(array_filter($judged, fn ($r) => $r['status'] === 'ready'));
        $created = DB::transaction(function () use ($event, $make) {
            $order = (int) $event->categories()->max('sort_order');

            return array_map(fn ($r) => EventCategory::create($r['data'] + [
                'event_id' => $event->id,
                'status' => 'enrolling',
                'sort_order' => ++$order,
            ]), $make);
        });

        foreach ($created as $division) {
            $this->redrawDivision($event, $division);
        }
        if ($created !== []) {
            $this->pushDivisionsChanged($event);
        }

        $skipped = count($judged) - count($created);

        return response()->json([
            'success' => true,
            'message' => count($created).' '.(count($created) === 1 ? 'division' : 'divisions').' created'
                .($skipped ? ' · '.$skipped.' already in the event, skipped' : '').'.',
            'divisions' => array_map(fn ($d) => $this->divisionPayload($d), $created),
        ]);
    }

    /**
     * Judge each imported row: 'ready', 'duplicate' (the event already has a
     * division with the same gender, ages and weights — or an earlier row of
     * the same file does), or 'error' with the reasons. A ready row carries
     * the validated data, named.
     */
    private function judgeImportRows(ClubEvent $event, array $rows): array
    {
        $existing = $event->categories()->orderBy('sort_order')->orderBy('id')->get();
        $sig = fn ($g, $a1, $a2, $w1, $w2) => implode('|', [
            strtolower((string) $g), $a1 === null ? '' : (int) $a1, $a2 === null ? '' : (int) $a2,
            $w1 === null ? '' : round((float) $w1, 2), $w2 === null ? '' : round((float) $w2, 2)]);
        $seen = [];
        foreach ($existing as $c) {
            if (! $c->is_heading) {
                $seen[$sig($c->gender, $c->min_age, $c->max_age, $c->min_weight, $c->max_weight)] = true;
            }
        }

        // The group letter carries on from the last division in the list,
        // restarting after a heading — the editor's own lettering.
        $letter = 0;
        foreach ($existing as $c) {
            $letter = $c->is_heading ? 0 : $letter + 1;
        }

        $out = [];
        foreach ($rows as $r) {
            $r['errors'] = array_values($r['errors'] ?? []);
            $key = $sig($r['gender'] ?? null, $r['min_age'] ?? null, $r['max_age'] ?? null, $r['min_weight'] ?? null, $r['max_weight'] ?? null);

            if ($r['errors'] === [] && isset($seen[$key])) {
                $out[] = $r + ['status' => 'duplicate'];

                continue;
            }

            $name = trim((string) ($r['name'] ?? ''));
            if ($name === '' && $r['errors'] === []) {
                // The letter and its own ages and weights (App\Events\Support\GroupName).
                $name = $letter < 26 ? \App\Events\Support\GroupName::make(chr(65 + $letter),
                    $r['min_age'] ?? null, $r['max_age'] ?? null, $r['min_weight'] ?? null, $r['max_weight'] ?? null) : '';
            }

            if ($r['errors'] === []) {
                $input = ['name' => $name, 'is_heading' => false, 'format' => 'knockout'];
                foreach (['gender', 'min_age', 'max_age', 'min_weight', 'max_weight', 'capacity'] as $k) {
                    if (($r[$k] ?? null) !== null && $r[$k] !== '') {
                        $input[$k] = $r[$k];
                    }
                }
                try {
                    $valid = $this->validateDivision(Request::create('/', 'POST', $input), $event);
                    $r['data'] = $this->headingSafe($valid) + ['schedule' => ['preliminary' => 1, 'quarterfinals' => 1, 'finals' => 1]];
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $r['errors'] = collect($e->errors())->flatten()->map(fn ($m) => $name === '' && str_contains((string) $m, 'name')
                        ? 'Give this division a name — the group letters stop at Z.' : (string) $m)->values()->all();
                }
            }

            if ($r['errors'] !== []) {
                $out[] = $r + ['status' => 'error'];

                continue;
            }

            $seen[$key] = true;
            $letter++;
            $r['name'] = $name;
            $out[] = $r + ['status' => 'ready'];
        }

        return $out;
    }

    /**
     * Copy a division — its SETTINGS, and nothing that happened in it.
     *
     * An event is built out of near-identical divisions: the same age band and
     * belt at six weights, the same six again without the gi. Typing each from
     * scratch is how a name ends up spelled two ways and a schedule ends up
     * pointing at the wrong day, and it is the slowest part of setting an event
     * up. So this takes one and makes another exactly like it.
     *
     * What is copied: everything that says what the division is FOR — the
     * range, the belts, the format, the capacity, the schedule, and whether it
     * is a heading.
     *
     * What is NOT, and must never be: entrants, bouts, the draw, the podium, a
     * status further along than "enrolling". A copy is a new, empty division
     * that happens to be configured like its neighbour — it has not been
     * entered by anyone and nothing has been fought in it. Copying a draw would
     * put the same athlete in two brackets with no one having entered them.
     *
     * `replicate()` rather than a hand-built list of fields (the same reasoning
     * as a second entry, below): a column added to divisions later is then
     * copied automatically, and nothing silently stops being duplicated.
     *
     * It lands DIRECTLY BELOW the original rather than at the end, because the
     * copy is nearly always the next thing in the sequence the organiser is
     * building — and on a list ordered by hand, appending would put it a drag
     * away from where it belongs.
     */
    public function duplicateDivision(Request $request, ClubEvent $event, int $division): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $source = $this->divisionOf($event, $division);

        $copy = $source->replicate([
            // Never carried over: what HAPPENED in the original.
            'podium', 'draw_state', 'draw_count', 'created_at', 'updated_at',
        ]);

        $copy->status = 'enrolling';
        $copy->podium = null;
        $copy->draw_state = null;
        $copy->draw_count = null;

        // A name the organiser can see is a copy, and can type over. Kept
        // inside the column's own limit rather than trusting the suffix to fit.
        $copy->name = mb_substr(
            trim(__('events::events.division_copy_name', ['name' => (string) $source->name])), 0, 80
        );

        DB::transaction(function () use ($event, $source, $copy) {
            /*
             * Room for it immediately below the original: everything after the
             * source moves down one, and the copy takes the freed place. Done
             * in the same transaction as the insert, so no other reader ever
             * sees two divisions claiming one position.
             */
            EventCategory::where('event_id', $event->id)
                ->where('sort_order', '>', (int) $source->sort_order)
                ->orderByDesc('sort_order')
                ->increment('sort_order');

            $copy->sort_order = (int) $source->sort_order + 1;
            $copy->save();
        });

        $this->pushDivisionsChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('events::events.division_copied'),
            'division' => $this->divisionPayload($copy->fresh()),
            // Where it went, so the list can drop it in without re-reading.
            'after' => $source->id,
        ]);
    }

    /** Rename a group, or change what it is for. */
    public function updateDivision(Request $request, ClubEvent $event, int $division): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $category = $this->divisionOf($event, $division);
        $category->update($this->headingSafe($this->validateDivision($request, $event, $category), $category));

        /*
         * Re-cut the draw only when something the DRAW depends on changed.
         *
         * A division's name is a label: nothing in a bracket is derived from
         * it. Re-cutting on every save meant renaming one rebuilt its matches
         * — expensive on a real entry list, and published to every console as
         * though the draw had moved. It became visible the day the list started
         * renaming divisions by itself (the generated names re-letter after a
         * drag), which could fire several re-cuts from one gesture.
         *
         * The range, the belts, the cap and the format DO decide who is in the
         * bracket and how it is cut, so those still redraw exactly as before.
         */
        if ($category->wasChanged([
            'gender', 'min_age', 'max_age', 'min_weight', 'max_weight',
            'belts', 'capacity', 'format', 'is_heading', 'schedule',
        ])) {
            $this->redrawDivision($event, $category->fresh());
        }

        $this->pushDivisionsChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('events::events.division_saved'),
            'division' => $this->divisionPayload($category->fresh()),
        ]);
    }

    /**
     * Delete a group.
     *
     * "This group still has people or bouts in it. Empty it first." answered
     * three very different situations with one wall, and was the wrong answer
     * to two of them. What actually stands in the way, in order:
     *
     *  1. **Bouts that have been FOUGHT.** A result is the record of something
     *     that happened in a hall. It is not deleted behind a confirmation, and
     *     this still refuses outright.
     *  2. **A draw that is cut but unfought.** That is a mistake somebody wants
     *     to undo, so it is cleared — through the package's own `clear_draw`
     *     action, never by reaching into `event_matches` from here, so a type
     *     that has locked its draw (the event is running) still says no.
     *  3. **People in the group.** Not an obstacle at all: a division is a
     *     container the organiser drew, and putting the same entrants in a
     *     different one is ordinary work. They are RELEASED — the entry stays
     *     exactly as it is, minus its group, which is the state an entrant is
     *     in before anybody places them.
     *
     * Nothing here happens silently. The browser asks first (409 + what will
     * happen), then repeats the request with `release`; a caller that never
     * asks cannot empty a group by accident.
     */
    public function destroyDivision(Request $request, ClubEvent $event, int $division): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $category = $this->divisionOf($event, $division);

        // Anything that was actually contested — see EventMatch::scopeContested.
        // A BYE is marked done with a winner and is NOT a competition, so a
        // bracket made of byes never blocks a delete.
        $boutIds = $category->matches()->pluck('id');

        $fought = $category->matches()->contested()->exists()
            // Footage is the same answer by another route: a bout somebody
            // filmed is a bout that happened. The recording would survive this
            // (its `match_id` is nulled, never the file), but the honest
            // reading is that this division is not a mistake to undo.
            || ($boutIds->isNotEmpty() && (
                DB::table('event_recordings')->whereIn('match_id', $boutIds)->exists()
                || DB::table('event_camera_clips')->whereIn('match_id', $boutIds)->exists()
            ));

        if ($fought) {
            return response()->json([
                'success' => false,
                'message' => __('events::events.division_has_results'),
            ], 422);
        }

        $bouts = $category->matches()->count();
        $people = $category->registrations()->count();

        if (($bouts > 0 || $people > 0) && ! $request->boolean('release')) {
            return response()->json([
                'success' => false,
                'needs_release' => true,
                'people' => $people,
                'bouts' => $bouts,
                'message' => $this->divisionDeleteAsk($people, $bouts),
            ], 409);
        }

        /*
         * Once the competition is under way its draw is final — the same line
         * App\Events\Support\Tournament\Arrangement::clear() draws before it
         * will empty a slot. A group with no bouts is still deletable then: a
         * container nobody drew into is not part of the running order.
         */
        if ($bouts > 0 && ($event->hasStarted() || $event->hasEnded())) {
            return response()->json([
                'success' => false,
                'message' => __('events::events.division_draw_locked'),
            ], 422);
        }

        DB::transaction(function () use ($category) {
            /*
             * The bouts go with the group.
             *
             * `event_matches.category_id` cascades, but this deletes them
             * explicitly: the cascade is a database detail, and a division's
             * unfought bracket disappearing is the intent, said out loud. What
             * hangs off a bout — a recording, a clip, a stream — keeps its row
             * and loses only the link (`match_id` is nulled, never the file),
             * and none of it can be here anyway: anything filmed was refused
             * above.
             */
            $category->matches()->delete();

            // The entry survives; only its group is forgotten. `category_id` is
            // nullable exactly for the entrant nobody has placed yet, so this
            // returns them to that state rather than inventing one.
            $category->registrations()->update(['category_id' => null]);
            $category->delete();
        });

        $this->pushDivisionsChanged($event);

        return response()->json([
            'success' => true,
            'released' => $people,
            'message' => $people > 0
                ? __('events::events.division_deleted_released', ['count' => $people])
                : __('events::events.division_deleted'),
        ]);
    }

    /**
     * The sentence the organiser is asked before a group goes.
     *
     * Says what will happen to each thing that is in the way, and only about
     * the things that ARE in the way — a group with nobody in it must not be
     * warned about entrants.
     */
    private function divisionDeleteAsk(int $people, int $bouts): string
    {
        // "1 bouts" is the kind of detail that makes a warning look
        // machine-written, and this sentence is asking permission.
        $drawn = trans_choice('events::events.division_bouts_count', $bouts, ['count' => $bouts]);

        if ($people > 0 && $bouts > 0) {
            return __('events::events.division_delete_ask_both', ['count' => $people, 'bouts' => $drawn]);
        }

        if ($bouts > 0) {
            return __('events::events.division_delete_ask_draw', ['bouts' => $drawn]);
        }

        return __('events::events.division_delete_ask_people', ['count' => $people]);
    }

    /**
     * The order of the list — which is the only thing that gives a heading meaning.
     *
     * A heading captions the divisions BELOW it, until the next heading. That
     * is a statement about position and nothing else, so until this existed a
     * heading could be created but never placed: new rows append
     * (`max(sort_order) + 1`), which put every heading underneath the twelve
     * divisions it was written to caption.
     *
     * ── What this writes ───────────────────────────────────────────────────
     * `event_categories.sort_order`, and nothing else. Not a registration, not
     * a bout, not a draw. Reordering the list an organiser reads must never be
     * able to move an athlete or re-cut a bracket — a division keeps every
     * entrant and every match it had, at whatever position it now sits.
     * Membership of a SECTION is positional and therefore does change, which
     * is the point of dragging; membership of a DIVISION cannot.
     *
     * ── Why the whole list, and not "move id X to index N" ─────────────────
     * The client sends every id in its new order and the set must match this
     * event's categories EXACTLY. A page that has been open while somebody else
     * added or deleted a division is holding a list that no longer describes
     * the event, and applying a partial order to it would interleave the two
     * silently. It is refused with 409 instead, and the sheet reloads.
     */
    public function reorderDivisions(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1', 'max:512'],
            'order.*' => ['integer'],
        ]);

        $wanted = array_values(array_unique(array_map('intval', $data['order'])));
        $current = $event->categories()->pluck('id')->map('intval')->all();

        sort($wanted);
        $check = $current;
        sort($check);

        if ($wanted !== $check) {
            return response()->json([
                'success' => false,
                'stale' => true,
                'message' => __('events::events.divisions_order_stale'),
            ], 409);
        }

        // Back to the order as sent, now that the SET is known to be right.
        $order = array_values(array_unique(array_map('intval', $data['order'])));

        DB::transaction(function () use ($order, $event) {
            foreach ($order as $i => $id) {
                EventCategory::where('event_id', $event->id)
                    ->whereKey($id)
                    ->update(['sort_order' => $i + 1]);
            }
        });

        $this->pushDivisionsChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('events::events.divisions_order_saved'),
            'order' => $order,
        ]);
    }

    /**
     * Who could go in this group, and how each one sits against its range.
     *
     * EVERY participant in the event, never a filtered subset: the filtering is
     * the client's job precisely because the organiser must be able to reach
     * past it. Someone already in another division is listed too — putting them
     * in this one as well is the common case — and says so, so nobody is taken
     * out of a bracket by accident.
     *
     * ── ONE ROW PER PERSON, not per entry ──────────────────────────────────
     * An athlete may hold an entry in several divisions of one event (Gi and
     * No-Gi are entered and drawn separately), and each of those is its own
     * `club_event_registrations` row. Listing rows put the same name in the
     * picker twice with nothing to tell them apart, and the second one was a
     * trap: ticking it asked to move an entry into a division that athlete
     * already held, which the unique index refuses.
     *
     * So the roster is grouped by PERSON, and the row carries every entry they
     * hold. What the client may then do with them is stated outright rather
     * than inferred:
     *
     *   `here`             they already hold an entry in THIS division
     *   `entry_here_id`    that entry — what unticking takes out
     *   `enter_id`         the entry ticking acts on, when they are not here
     *   `other_divisions`  EVERY other group they compete in, each named
     *
     * There is deliberately no flag distinguishing "add" from "move": being in
     * this group never takes anything from another one. See
     * updateDivisionMembers().
     */
    /**
     * Who this division WOULD hold, before it exists.
     *
     * An organiser building a weight class is answering one question — "is
     * there anybody in this?" — and until now they could only answer it by
     * saving the division, opening the entrant picker and counting. A range
     * that catches nobody is worth knowing about while it is still being
     * typed: it is either the wrong numbers, or a division the event should
     * not run at all.
     *
     * Judged by the SAME rule the roster is judged by — a throwaway
     * EventCategory carrying the proposed bounds, handed to DivisionRange. No
     * second definition of "does this athlete fit", which is the only way the
     * preview and the picker can be trusted to agree.
     *
     * ⚠️ The category is NEVER saved. It exists for the length of this request
     * so the rule has something to read, and is not touched by any write path.
     *
     * Returns counts and a handful of faces, nothing else: this is a preview
     * beside a form, not a roster. Names and photos only, photos only where the
     * member made them public (Profile Pictures Are Portrait 3:4).
     */
    public function previewDivision(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'min_age' => ['nullable', 'integer', 'min:2', 'max:100'],
            'max_age' => ['nullable', 'integer', 'min:2', 'max:100'],
            'min_weight' => ['nullable', 'numeric', 'min:10', 'max:300'],
            'max_weight' => ['nullable', 'numeric', 'min:10', 'max:300'],
            'belts' => ['nullable', 'array', 'max:12'],
            'belts.*' => ['string', Rule::in(\App\Sports\Combat\BeltRank::accepted($event->sport))],
            // The division being edited, so its own entrants read as "already
            // here" rather than as strangers who happen to fit.
            'division' => ['nullable', 'integer'],
            // The part of the event it is in, as the sheet has it RIGHT NOW —
            // so switching Gi to No-Gi re-counts under the finger, rather than
            // waiting for a save.
            'stream' => ['nullable', 'string', Rule::in(array_column(EventStreams::of($event), 'key'))],
        ]);

        // Bounds the wrong way round are not an error here — the organiser is
        // mid-typing — they simply match nobody, which is what the preview will
        // then say.
        $probe = new EventCategory($data + ['event_id' => $event->id]);

        $current = $data['division'] ?? null;

        /* Which part of the event this division belongs to: what the sheet has
           in hand, else what the division being edited already says. Without
           it the panel would count people the entrant list two taps later
           refuses to show. */
        $probe->stream = $data['stream'] ?? ($current
            ? EventCategory::where('event_id', $event->id)->whereKey($current)->value('stream')
            : null);

        $range = $probe->range();

        $people = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->with(['user:id,full_name,name,gender,birthdate,profile_picture,profile_picture_is_public,updated_at'])
            ->get()
            // One card per PERSON, not per entry: an athlete in three divisions
            // is one human being and must be counted once.
            ->groupBy('user_id');

        // What each entry bought, so the count obeys the same stream rule the
        // entrant list does (DivisionRange::judge).
        $granted = EventStreams::grantedForMany($people->flatten()->pluck('id'));

        $fits = [];
        $unknown = 0;
        $here = 0;

        foreach ($people as $entries) {
            $rep = $entries->first();
            $judged = $range->judge($rep, $entries
                ->flatMap(fn (ClubEventRegistration $r) => $granted[$r->id] ?? [])
                ->unique()->values()->all());

            if ($judged['fit'] === 'out') {
                continue;
            }

            if ($judged['fit'] === 'unknown') {
                $unknown++;
            }

            if ($current && $entries->contains(fn (ClubEventRegistration $r) => (int) $r->category_id === (int) $current)) {
                $here++;
            }

            $fits[] = [
                'name' => $rep->user?->full_name ?: $rep->user?->name ?: __('events::events.athlete'),
                'gender' => $rep->user?->gender ?: null,
                'fit' => $judged['fit'],
                'photo' => $rep->photo
                    ? file_url($rep->photo)
                    : (($rep->user?->profile_picture && $rep->user->profile_picture_is_public)
                        ? file_url($rep->user->profile_picture).'?v='.($rep->user->updated_at?->timestamp ?? 0)
                        : null),
            ];
        }

        usort($fits, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return response()->json([
            'success' => true,
            'total' => $people->count(),          // everybody entered in the event
            'matched' => count($fits),            // who this range would hold
            'unknown' => $unknown,                // matched, but something is missing
            'here' => $here,                      // of those, already in this division
            // A handful of faces. The rest are a number — a preview beside a
            // form must not become a roster nobody asked to load.
            'people' => array_slice($fits, 0, 12),
        ]);
    }

    public function divisionCandidates(Request $request, ClubEvent $event, int $division): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanArrange($event, $me);

        $category = $this->divisionOf($event, $division);

        // A heading is a title in the list, not a division: there is nobody to
        // list because nobody can be in it.
        if ($category->isHeading()) {
            return response()->json(['success' => false, 'message' => __('events::events.division_is_heading')], 422);
        }
        $range = $category->range();

        $names = $event->categories()->pluck('name', 'id');

        /* What each entry signed up for — "Gi", "No-Gi", "Gi + No-Gi" — read
           off the fee options ticked at registration, in ONE query for the
           whole roster. The picker shows it as a badge per person so an
           organiser filling a Gi bracket can see at a glance who is even
           entered in Gi. */
        $regIds = ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')->pluck('id');

        $feeWords = EventFee::optionLabelsForMany($regIds);

        /* The same fee lines read as STREAMS rather than as words: which parts
           of the event each entry actually bought. A Gi division does not offer
           somebody who entered No-Gi only — see DivisionRange::judge(). */
        $granted = EventStreams::grantedForMany($regIds);

        $people = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->with(['user:id,full_name,name,gender,birthdate,profile_picture,profile_picture_is_public,updated_at'])
            ->orderBy('id')
            ->get()
            ->groupBy('user_id')
            ->map(function ($entries) use ($range, $category, $names, $feeWords, $granted) {
                /*
                 * The entry this person is DESCRIBED by: the one in this
                 * division if they hold it, otherwise their earliest. Weight,
                 * belt and the desk photo live on the entry, so the row shows
                 * the ones that belong to the division being edited.
                 */
                $here = $entries->firstWhere('category_id', $category->id);
                $rep = $here ?: $entries->first();

                /* Everything this PERSON bought, across all of their entries
                   — they are one human being, and an athlete who entered Gi on
                   one entry is in the Gi competition whichever entry describes
                   them here. */
                $mine = $entries->flatMap(fn (ClubEventRegistration $r) => $granted[$r->id] ?? [])
                    ->unique()->values()->all();

                $judged = $range->judge($rep, $mine);

                // Every OTHER division they are competing in, named. All of
                // them — a person in three is in three, and a row that admits
                // to one of them is worse than one that admits to none.
                $others = $entries
                    ->filter(fn (ClubEventRegistration $r) => $r->category_id
                        && (int) $r->category_id !== (int) $category->id)
                    ->map(fn (ClubEventRegistration $r) => [
                        'id' => (int) $r->category_id,
                        'name' => $names[$r->category_id] ?? null,
                        'competitor_id' => $r->id,
                    ])
                    ->values();

                return [
                    'person_id' => (int) $rep->user_id,
                    // Kept, and still the entry every other caller means by it.
                    'competitor_id' => $rep->id,
                    'name' => $rep->user?->full_name ?: $rep->user?->name ?: __('events::events.athlete'),
                    'gender' => $rep->user?->gender ?: null,
                    'age' => $range->ageOf($rep),
                    'weight' => $rep->weight === null ? null : (float) $rep->weight,
                    'belt' => $rep->belt_colour ?: null,
                    /* The rank, ready to DRAW. The row is built by JavaScript,
                       so the colours come worked out — the page holds one copy
                       of the belt geometry (<x-belt-icon-defs>) and the row
                       only says which colours to paint it in. Null when no rank
                       is on file, which is the ordinary case and draws nothing. */
                    'belt_icon' => \App\Sports\Combat\BeltIcon::payload($rep->belt_colour, $rep->belt_grade),
                    // The face taken at the desk for THIS event first, then the
                    // member's own picture — and only when they made it public
                    // (CLAUDE.md → Profile Pictures Are Portrait 3:4). No photo
                    // is not a fault; the client draws a gender avatar.
                    'photo' => $rep->photo
                        ? file_url($rep->photo)
                        : (($rep->user?->profile_picture && $rep->user->profile_picture_is_public)
                            ? file_url($rep->user->profile_picture).'?v='.($rep->user->updated_at?->timestamp ?? 0)
                            : null),
                    'country' => $rep->countryCode(),
                    // Where they are now: this group, other ones (all named), or nowhere.
                    'division_id' => $rep->category_id,
                    'division_name' => $others->first()['name'] ?? null,
                    'other_divisions' => $others->all(),
                    'here' => (bool) $here,
                    'entry_here_id' => $here?->id,
                    'entry_ids' => $entries->pluck('id')->all(),
                    /*
                     * The entry the TICK acts on — one field, because there is
                     * one act. Prefer an entry with no division (placing it
                     * costs nobody anything); otherwise their first, which the
                     * second entry is copied from.
                     *
                     * This replaced `addable` / `also_id` / `movable_id` /
                     * `placeable_id` on 2026-09-09: four flags existed only to
                     * let the client choose between adding and moving, and
                     * moving is gone.
                     */
                    'enter_id' => $here
                        ? null
                        : ($entries->firstWhere('category_id', null)?->id ?? $entries->first()->id),
                    'fit' => $judged['fit'],          // in | out | unknown
                    'misses' => $judged['misses'],
                    /* Every activity this PERSON is entered in, across all
                       their entries and de-duplicated: two entries that both
                       bought "Gi" are one badge, because the row is one human
                       being. */
                    'activities' => $entries
                        ->flatMap(fn (ClubEventRegistration $r) => $feeWords[$r->id] ?? [])
                        ->unique()->values()->all(),
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
     * Every group in this event as it stands FOR ONE PERSON — the enrolment
     * sheet behind a participant card.
     *
     * The picker on the divisions screen asks the question from the other end
     * ("who could go in this group?", divisionCandidates). An organiser tapping
     * an athlete on the entry list is asking the mirror of it, and a list of
     * groups with this athlete's answer already worked out is the only shape
     * that can be acted on without opening six screens.
     *
     * ── Compatibility is computed HERE, never in the browser ───────────────
     *
     * `DivisionRange::judge()` is the one rule (age, gender, weight, belt, and
     * which streams of the event this entry actually bought). The sheet is told
     * the verdict and a reason; it is never given the rule to re-derive, which
     * is how two screens start disagreeing about who fits what.
     *
     * ── Incompatible is still offered ─────────────────────────────────────
     *
     * Deliberately, and it matches what updateDivisionMembers already accepts:
     * an organiser putting a fourteen-year-old up into the adults, or a lighter
     * athlete into a heavier bracket so they get a fight at all, is doing their
     * job. The range NARROWS the list and marks the exception; it does not
     * forbid it.
     *
     * Authority is `canArrange` — the same as the picker it mirrors. Which
     * divisions an entrant is drawn in is what arranging a draw means.
     */
    /**
     * A division's bounds in one short line — "80–100 kg · Blue belt".
     *
     * Shown under EVERY division in the picker. A refused row already explains
     * itself ("weight, belt"); a compatible one said nothing, so an organiser
     * was told why they could not tick a box and never what they were ticking.
     *
     * Belt names come from the sport's own ladder so the word matches the chip
     * on the athlete's card, and a bound the organiser left open contributes
     * nothing — a division that narrows by nothing returns null and draws no
     * second line.
     */
    private function divisionBounds(EventCategory $category): ?string
    {
        $bits = [];

        $min = $category->min_weight;
        $max = $category->max_weight;

        if ($min && $max) {
            $bits[] = $min.'–'.$max.' '.__('personal.event_card_kg');
        } elseif ($max) {
            $bits[] = '−'.$max.' '.__('personal.event_card_kg');
        } elseif ($min) {
            $bits[] = '+'.$min.' '.__('personal.event_card_kg');
        }

        $belts = array_values(array_filter((array) ($category->belts ?? [])));

        if ($belts) {
            $bits[] = collect($belts)
                ->map(fn ($b) => \App\Sports\Combat\BeltRank::chip($b)['label'] ?? ucfirst((string) $b))
                ->implode(' / ');
        }

        $minAge = $category->min_age;
        $maxAge = $category->max_age;

        if ($minAge && $maxAge) {
            $bits[] = $minAge.'–'.$maxAge;
        }

        return $bits ? implode(' · ', $bits) : null;
    }

    public function personDivisions(ClubEvent $event, string $uuid): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanArrange($event, $me);

        $person = User::where('uuid', $uuid)->first();

        abort_if($person === null, 404);

        /* Their entries in THIS event. An id from another event resolves to
           nothing rather than being described. */
        $entries = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->where('user_id', $person->id)
            ->with('user:id,uuid,full_name,name,gender,birthdate')
            ->orderBy('id')
            ->get();

        abort_if($entries->isEmpty(), 404);

        /* What this PERSON bought, across all of their entries — they are one
           human being, and somebody who entered Gi on one entry is in the Gi
           competition whichever entry describes them. */
        $granted = collect(EventStreams::grantedForMany($entries->pluck('id')))
            ->flatten()->unique()->values()->all();

        $held = $entries->filter(fn (ClubEventRegistration $r) => $r->category_id !== null)
            ->keyBy('category_id');

        $categories = $event->categories()
            ->orderBy('sort_order')->orderBy('id')->get()
            ->reject(fn (EventCategory $c) => $c->isHeading());

        /*
         * ===== WHAT EVERY DIVISION ACTUALLY IS =====
         *
         * Every row carries its bounds — the weight band, the belt, the age
         * range — under its name. Not only the ambiguous ones: that was the
         * first shape of this, added when two divisions turned out to share a
         * name ("Group F (+80) Adult [Gi]" was 80–100kg BLUE and 80–150kg
         * WHITE, spelt identically), and the owner's answer on 2026-09-18 was
         * the right one — a refused row explains itself and a compatible row
         * said nothing at all, so the reader was told why they COULDN'T tick a
         * box and never what they were ticking.
         *
         * A name is the organiser's shorthand; the bounds are what the division
         * IS, and an organiser placing somebody by hand is choosing between
         * ranges, not between labels.
         */
        $groups = $categories
            ->map(function (EventCategory $category) use ($entries, $granted, $held) {
                /* Judged on the entry that will actually be placed there — the
                   one already in this group if they hold it, otherwise their
                   first. Weight and belt live on the ENTRY, so the verdict has
                   to be about the row that carries them. */
                $here = $held->get($category->id);
                $rep = $here ?: $entries->first();

                $judged = $category->range()->judge($rep, $granted);

                $label = (string) ($category->name ?: $category->weight_class);

                return [
                    'id' => (int) $category->id,
                    'label' => $label,
                    /* The bounds, under the name, on every row. Null only for
                       a division that narrows by nothing at all — an open
                       category has nothing to say here and gets no second
                       line. */
                    'detail' => $this->divisionBounds($category),
                    // `unknown` — a missing weight or birthdate — is NOT
                    // incompatible. Nothing is known to be wrong, and telling an
                    // organiser a group does not fit because nobody has weighed
                    // the athlete yet would be a fact about the desk, not the draw.
                    'compatible' => $judged['fit'] !== 'out',
                    'reason' => $judged['misses'] === [] ? null : $this->missWords($judged['misses']),
                    'enrolled' => $here !== null,
                    // The row this group would REMOVE if unticked. Null when
                    // they are not in it, and the sheet then only ever adds.
                    'registration' => $here?->id,
                ];
            })
            ->values()->all();

        return response()->json([
            'success' => true,
            'person' => [
                'uuid' => $person->uuid,
                'name' => $person->full_name ?: $person->name,
            ],
            // The entry a tick ADDS with. updateDivisionMembers takes it from
            // there: an entrant with no group is moved in, one who already has
            // a group gets a second entry beside the first, and nothing is ever
            // taken away from a division the organiser did not untick.
            'entry' => $entries->first()->id,
            'groups' => $groups,
            /*
             * ⚠️ NOTHING LOCKS THIS PICKER ANY MORE (2026-09-18, at the user's
             * instruction — the same day, and for the same reason, that
             * updateDivisionMembers and Arrangement::move were opened).
             *
             * This flag and the endpoint behind it had DRIFTED. The endpoint
             * now accepts additions and removals once the day has started and
             * refuses exactly one thing — taking somebody out of the bout being
             * fought — which is a per-save question no picker can answer in
             * advance. Meanwhile this still reported the old total lock, so the
             * sheet greyed out Save over a rule the server had stopped
             * applying, and an organiser correcting their groups on the morning
             * was told "the draw is final" by a screen whose own endpoint would
             * have said yes.
             *
             * It stays in the payload because a console left open across a
             * deploy reads it, and false is the answer that lets that console
             * work rather than the one that strands it.
             */
            'locked' => false,
            /*
             * Said, not enforced. Adding a group after the start does not put
             * anybody on a bracket that has already been cut — DrawEngine
             * freezes a drawn division — so they arrive on the BENCH and the
             * organiser places them through Arrange. Without this line the save
             * succeeds, the board looks unchanged, and the next question is
             * "why can't I see him in the brackets".
             */
            'note' => $event->hasStarted() || $event->hasEnded()
                ? __('personal.event_card_groups_after_start')
                : null,
        ]);
    }

    /**
     * A division's unmet requirements, in the words the pickers already use.
     *
     * The same five keys `<x-division-groups>` words in JavaScript
     * (`missWords`), read from the same language file — so a reason printed on
     * the entry list and the same reason printed on the divisions screen can
     * never drift into two vocabularies.
     *
     * @param  array<int, string>  $misses
     */
    private function missWords(array $misses): string
    {
        $words = [
            'gender' => __('personal.division_miss_gender'),
            'age' => __('personal.division_miss_age'),
            'weight' => __('personal.division_miss_weight'),
            'belt' => __('personal.division_miss_belt'),
            'stream' => __('personal.division_miss_stream'),
        ];

        return implode(', ', array_map(fn (string $m) => $words[$m] ?? $m, $misses));
    }

    /**
     * Put people in this group, or take them out of it.
     *
     * Deliberately accepts an entrant the range would reject. An organiser
     * putting a fourteen-year-old up into the adults, or a lighter athlete into
     * a heavier bracket to give them a fight at all, is doing their job — the
     * range narrows the picker and marks the exception, it does not forbid it.
     * See App\Events\Support\DivisionRange.
     *
     * ── ONE VERB: "be in this group", or "do not" ──────────────────────────
     * There used to be two ways in. `add` MOVED an entry here (emptying
     * whatever bracket it came from) and `also` ADDED a second entry beside the
     * first. Two adjacent controls, one destructive, and the organiser had to
     * know which was which — so the destructive one got clicked, and an entry
     * somebody had paid for vanished out of a draw.
     *
     * Removed on 2026-09-09 at the user's request. Being in this group now
     * NEVER takes anything away from anywhere else:
     *
     *   no division yet  → their entry is placed here
     *   already in one   → a SECOND entry is made here, the first untouched
     *   already in this  → nothing to do
     *
     * Moving somebody from X to Y is therefore two ordinary steps an organiser
     * already knows — tick them in Y, untick them in X — each reversible on its
     * own, and neither one a special code path. `remove` is the only verb that
     * takes anything away, and only ever from THIS division.
     *
     * A second entry is `replicate()`d rather than hand-built (CLAUDE.md → the
     * event-copy note): every column travels — weight, belt, the desk's photo,
     * the club it represents, the payment already verified — so the sibling is
     * the same athlete to the board, the bracket and the roster, and only the
     * division differs. Fee lines are NOT copied; they hang off the
     * registration id and the purchase stays on the entry that made it.
     *
     * Authority is this page's own (`assertCanArrange`), not EntryService's.
     * Nobody is being brought INTO the event here — they are already an
     * entrant, and which divisions an entrant is drawn in is exactly what
     * arranging a draw means.
     */
    public function updateDivisionMembers(Request $request, ClubEvent $event, int $division): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanArrange($event, $me);

        /*
         * ⚠️ THIS SCREEN STAYS OPEN ONCE THE DAY HAS STARTED
         * (2026-09-18, at the user's instruction).
         *
         * It used to close at the start, and the intent was right at the time:
         * changing a division's membership re-cut its draw, underneath the
         * people standing on it.
         *
         * Two things changed. The re-cut no longer happens — DrawEngine freezes
         * a division that has already been drawn — and the cost of the lock
         * turned out to be higher than the risk it covered. An athlete entered,
         * weighed and paid but sitting in NO division is invisible to every
         * board: not on a bracket, not even on a bench to be dragged, because a
         * bench IS a division's entrant list. Karl Storholm was found that way
         * mid-competition, with no way to put him in his weight class. A
         * competitor in the WRONG class is the same problem facing the other
         * direction, and an organiser fixing brackets on the morning needs both.
         *
         * So additions and removals both go through, and the guard below is
         * narrow: not the bout on the mat. Putting somebody into a SLOT is
         * still a separate, deliberate act through Arrange.
         */
        $afterStart = $event->hasStarted() || $event->hasEnded();

        $category = $this->divisionOf($event, $division);

        if ($category->isHeading()) {
            return response()->json(['success' => false, 'message' => __('events::events.division_is_heading')], 422);
        }

        $data = $request->validate([
            'add' => ['nullable', 'array', 'max:256'],
            'add.*' => ['integer'],
            'remove' => ['nullable', 'array', 'max:256'],
            'remove.*' => ['integer'],
            /*
             * `also` is the same act as `add` now, and is still accepted for
             * one reason: a console left open across a deploy sends it. Old
             * markup asking for exactly what the new verb does must not be
             * silently dropped on an event day — that would lose an entry the
             * organiser watched themselves tick.
             */
            'also' => ['nullable', 'array', 'max:256'],
            'also.*' => ['integer'],
        ]);

        // Scoped to THIS event's participants — an id from another event, or a
        // spectator's registration, resolves to nothing rather than being moved.
        $scope = fn (array $ids) => ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->whereIn('id', $ids)->pluck('id')->all();

        $remove = $scope($data['remove'] ?? []);
        $enter = $scope(array_merge($data['add'] ?? [], $data['also'] ?? []));

        /*
         * Once the day is under way, the only removal still refused is one that
         * would empty a corner of the bout being FOUGHT — the mat scores
         * against the row it loaded, and pulling a name out from under it
         * leaves the table and the bracket describing different fights.
         *
         * Everything else goes through. A competitor in the wrong weight class
         * has to be movable on the morning, and the re-cut that used to make
         * this dangerous no longer happens: DrawEngine freezes a division that
         * has already been drawn, so a removal now leaves an empty corner for
         * the organiser to fill through Arrange rather than re-laying the draw.
         */
        if ($afterStart && $remove) {
            $onMats = \App\Scoreboard\Fleet::matchesOnMats($event);

            $live = $onMats === [] ? null : EventMatch::whereIn('id', $onMats)
                ->where('category_id', $category->id)
                ->where(fn ($q) => $q->whereIn('a_competitor_id', $remove)
                    ->orWhereIn('b_competitor_id', $remove))
                ->first();

            if ($live) {
                return response()->json([
                    'success' => false,
                    'message' => __('events::events.bracket_bout_on_mat', ['bout' => $live->match_no ?: $live->id]),
                ], 422);
            }
        }

        // Nobody is both put in and taken out by one save.
        $enter = array_values(array_diff(array_unique($enter), $remove));

        DB::transaction(function () use ($enter, $remove, $category, $event) {
            foreach (ClubEventRegistration::whereIn('id', $enter)->get() as $entry) {
                if ((int) $entry->category_id === (int) $category->id) {
                    continue;                       // already here
                }

                /*
                 * Already holding an entry here under another row? Then they
                 * are in this group and there is nothing to add. Checked per
                 * iteration, so two rows of one athlete aimed at one group
                 * cannot both land — which is also what keeps this off the
                 * unique index (event_id, user_id, category_id).
                 */
                $held = ClubEventRegistration::where('event_id', $event->id)
                    ->where('user_id', $entry->user_id)
                    ->where('category_id', $category->id)
                    ->exists();

                if ($held) {
                    continue;
                }

                if ($entry->category_id === null) {
                    // Nobody had placed them anywhere, so this takes nothing
                    // from anybody: the entry they already hold moves in.
                    //
                    // Marked as a PERSON's decision: this screen is an organiser
                    // putting a competitor in a division deliberately, including
                    // into one their weight does not match, and the weigh-in
                    // desk must not undo it afterwards.
                    $entry->update([
                        'category_id' => $category->id,
                        'category_source' => ClubEventRegistration::PLACED_MANUAL,
                    ]);

                    continue;
                }

                $sibling = $entry->replicate();
                $sibling->category_id = $category->id;
                $sibling->category_source = ClubEventRegistration::PLACED_MANUAL;
                $sibling->save();
            }

            if ($remove) {
                // Out of the division, and out of its provenance with it: an
                // entry nobody is placing any more is unplaced, not "placed by
                // hand in nothing".
                ClubEventRegistration::whereIn('id', $remove)
                    ->where('category_id', $category->id)
                    ->update(['category_id' => null, 'category_source' => null]);

                /*
                 * A competitor who has left this division cannot stay in its
                 * draw. Scoped to THIS division's bouts, because this division
                 * is now the only one anybody can leave — and the package
                 * re-cuts it below, which is what fills the gap.
                 */
                EventMatch::where('event_id', $event->id)
                    ->where('category_id', $category->id)
                    ->whereIn('a_competitor_id', $remove)
                    ->update(['a_competitor_id' => null, 'a_name' => null]);

                EventMatch::where('event_id', $event->id)
                    ->where('category_id', $category->id)
                    ->whereIn('b_competitor_id', $remove)
                    ->update(['b_competitor_id' => null, 'b_name' => null]);
            }
        });

        /*
         * One division changed shape, so one division is re-derived. Nothing
         * leaves another group any more, so there is no longer a list of other
         * brackets to re-cut alongside it.
         */
        $this->typeFor($event)->onEntrantsChanged($event, $category->fresh());

        $this->pushDivisionsChanged($event);

        return $this->divisionCandidates($request, $event, $division);
    }

    /* ---------------- Group plumbing ---------------- */

    /**
     * A heading carries a NAME and nothing else.
     *
     * The form stops offering the rest, but the endpoint is the contract: a
     * heading that arrived with a weight range, a capacity or a format would
     * be a division wearing a title's clothes, and every guard that refuses to
     * draw it or put people in it would then be arguing with data that says
     * otherwise. So the fields are dropped here, once, on the way in — and
     * cleared on a division that is being TURNED INTO a heading, so nothing is
     * left behind from what it used to be.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function headingSafe(array $data, ?EventCategory $existing = null): array
    {
        $heading = (bool) ($data['is_heading'] ?? $existing?->is_heading ?? false);

        if (! $heading) {
            return $data + ['is_heading' => false];
        }

        return [
            'name' => $data['name'],
            'is_heading' => true,
            'weight_class' => null,
            'capacity' => null,
            'gender' => null,
            'min_age' => null,
            'max_age' => null,
            'min_weight' => null,
            'max_weight' => null,
            'belts' => null,
            'schedule' => [],
            'note' => $data['note'] ?? null,
        ];
    }

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
            // A title in the list rather than a division. Everything below is
            // still ACCEPTED when it is set — the form simply stops offering
            // it — and the write path drops it, so a heading can never carry a
            // weight range that nothing would ever read.
            'is_heading' => ['nullable', 'boolean'],
            'weight_class' => ['nullable', 'string', 'max:40'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            /*
             * ⚠️ `gte:` only where there IS something to compare against.
             *
             * A bare `gte:min_weight` fails when min_weight is absent — Laravel
             * compares "70" against "" and refuses — so a division with only an
             * upper bound could never be saved. That is "-70 kg": the commonest
             * shape in the sport, and every weight class in the IBJJF table bar
             * the heaviest. The error read "the heaviest weight cannot be lower
             * than the lightest", which is nonsense when no lightest was given,
             * so it looked like a mystery rather than a rule — and the weights
             * ended up typed into division NAMES instead of into the columns.
             *
             * Only relaxed where it was impossible to satisfy: two bounds given
             * the wrong way round are still refused, which is the rule's job.
             */
            'min_age' => ['nullable', 'integer', 'min:2', 'max:100'],
            'max_age' => ['nullable', 'integer', 'min:2', 'max:100',
                Rule::when($request->filled('min_age'), ['gte:min_age'])],
            'min_weight' => ['nullable', 'numeric', 'min:10', 'max:300'],
            'max_weight' => ['nullable', 'numeric', 'min:10', 'max:300',
                Rule::when($request->filled('min_weight'), ['gte:min_weight'])],
            /*
             * Which belts the division is for. A LIST, because a division is
             * regularly opened to several grades at once ("Blue, Purple") to
             * make a runnable bracket out of a small entry list; an empty list
             * means ANY, like every other column here.
             *
             * Held to the sport's own ladder (BeltRank::ladder), lowercase, so
             * the column can be read by the picker and by the generated name
             * rather than being another free-text spelling.
             */
            'belts' => ['nullable', 'array', 'max:12'],
            'belts.*' => ['string', Rule::in(\App\Sports\Combat\BeltRank::accepted($event->sport))],
            // How many may enter. Null is "no cap", which is the normal case —
            // an organiser caps a division to keep a mat's day finite, not
            // because a bracket needs it.
            'capacity' => ['nullable', 'integer', 'min:2', 'max:100000'],
            /*
             * How the division is decided.
             *
             * Restricted to RUNNABLE_FORMATS, not to the full vocabulary: the
             * column understands `round_robin` and the engine does not, so
             * accepting it here would let an organiser configure a competition
             * the mats cannot run. The UI greys it out for the same reason; this
             * is the half that a hand-made request cannot get past.
             */
            'format' => ['nullable', Rule::in(EventCategory::RUNNABLE_FORMATS)],
            // Which day each phase lands on, for an event that runs over more
            // than one. Keys are the type's own phases, so they are not listed.
            'schedule' => ['nullable', 'array'],
            'schedule.*' => ['nullable', 'integer', 'min:1', 'max:60'],
            /*
             * Which part of the event this division belongs to — Gi, No-Gi,
             * Kata, Kumite. Held to the streams the EVENT declares, so a typo
             * cannot invent a stream nobody sells and thereby exclude the whole
             * roster (see EventStreams::ofDivision). Null is "the event runs
             * one thing", which is most events and asks nothing of anybody.
             */
            'stream' => ['nullable', 'string', Rule::in(array_column(EventStreams::of($event), 'key'))],
        ], [
            'max_age.gte' => __('events::events.division_age_backwards'),
            'max_weight.gte' => __('events::events.division_weight_backwards'),
        ]);
    }

    /**
     * Let the owning package re-cut this division after it changed.
     *
     * A division IS what a draw is made from — its range decides who lands in
     * it, its capacity decides how many, and (once the engine can read it) its
     * format decides the shape. Editing one and leaving the bracket alone means
     * the board on the wall and the row in the database disagree, which nobody
     * notices until somebody is called to a mat.
     *
     * Delegated rather than done here, because how a division is drawn is the
     * PACKAGE's business, not this controller's (CLAUDE.md, "Events Are
     * Self-Contained Packages"). Best-effort: a division that cannot be drawn
     * yet — no entrants, or a format the engine does not run — must not stop the
     * organiser saving the name they just typed.
     */
    private function redrawDivision(ClubEvent $event, ?EventCategory $category): void
    {
        rescue(fn () => $this->typeFor($event)->onEntrantsChanged($event, $category), null, false);
    }

    /**
     * Fly the athlete's own passport rather than their club's country.
     *
     * Positional overlay: `$rows` is the payload RosterPeople was built from,
     * one entry per person and in the same order.
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
     * Only fills a BLANK — a club the athlete themselves claimed
     * (`representing_tenant_id`) is their answer and outranks the desk's.
     *
     * @param  array<int, array<string, mixed>>  $people
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachEventClubs(ClubEvent $event, array $people, array $rows): array
    {
        if (! Schema::hasTable('event_club_entrants')) {
            return $people;
        }

        // user id => the club they are down as here.
        $byUser = DB::table('event_club_entrants as l')
            ->join('event_clubs as c', 'c.id', '=', 'l.event_club_id')
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
        if (! Schema::hasTable('event_clubs')) {
            return $clubs;
        }

        $named = collect($clubs)->pluck('name')->map(fn ($n) => mb_strtolower((string) $n))->all();

        $extra = \App\Events\Models\EventClub::where('event_id', $event->id)
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

    /** One group, as the board and the picker read it. */
    /**
     * The faces in each division, for a whole list, in ONE query.
     *
     * The list draws a stack of portraits per division, and asking per card
     * would be a query per division on a page that already runs three of them
     * per card. So every division's entrants are read together and handed back
     * keyed by division.
     *
     * Only what a face needs — a name, a gender for the drawn fallback, and a
     * photo the member made public (CLAUDE.md → Profile Pictures Are Portrait
     * 3:4). A handful each: the card shows a stack and a remainder, never a
     * roster.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function facesFor(array $categoryIds, int $per = 8): array
    {
        if (! $categoryIds) {
            return [];
        }

        $rows = ClubEventRegistration::whereIn('category_id', $categoryIds)
            ->where('role', 'participant')
            ->with(['user:id,full_name,name,gender,profile_picture,profile_picture_is_public,updated_at'])
            ->orderBy('id')
            ->get();

        $out = [];

        foreach ($rows->groupBy('category_id') as $categoryId => $entries) {
            $out[(int) $categoryId] = $entries->take($per)->map(fn (ClubEventRegistration $r) => [
                'name' => $r->user?->full_name ?: $r->user?->name ?: __('events::events.athlete'),
                'gender' => $r->user?->gender ?: null,
                'photo' => $r->photo
                    ? file_url($r->photo)
                    : (($r->user?->profile_picture && $r->user->profile_picture_is_public)
                        ? file_url($r->user->profile_picture).'?v='.($r->user->updated_at?->timestamp ?? 0)
                        : null),
            ])->values()->all();
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $faces  preloaded for a whole
     *         list (facesFor); null reads this one division's own.
     */
    private function divisionPayload(EventCategory $category, ?array $faces = null): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            // A title, not a division. The list draws it as a band and offers
            // none of a division's actions on it.
            'is_heading' => $category->isHeading(),
            'weight_class' => $category->weight_class ?: null,
            'status' => $category->status,
            'entrants' => $category->registrations()->where('role', 'participant')->count(),
            'matches' => $category->matches()->count(),
            // How many of those bouts actually happened. The list uses it to
            // tell a group that can still be undone from one that cannot,
            // before it asks a question whose answer is already no.
            'fought' => $category->matches()->contested()->count(),
            'range' => $category->range()->toArray(),
            'belts' => $category->belts ?: [],
            // Who is in it, as faces. The count is `entrants` above; this is
            // the handful the card draws.
            'people' => $faces ?? ($this->facesFor([$category->id])[$category->id] ?? []),
            'capacity' => $category->capacity,
            'format' => $category->format ?: EventCategory::FORMAT_KNOCKOUT,
            'schedule' => $category->schedule ?: [],
            // Which part of the event it belongs to — Gi, No-Gi, Kata. Null on
            // an event that runs only one thing, where the sheet asks nothing.
            'stream' => EventStreams::ofDivision($category),
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
            'belt_colour' => ['nullable', 'string', Rule::in(\App\Sports\Combat\BeltRank::accepted($event->sport))],
            // The degree, as the federation writes it — "2", "2nd Dan", "1 دان".
            // Free text on purpose: the ladder is a shortcut, not a constraint,
            // and a federation that grades some other way types it in.
            'belt_grade' => ['nullable', 'string', 'max:40'],
            'representing_tenant_id' => ['nullable', 'integer'],
            /*
             * WHICH CLUB they compete for, in the same two key shapes every
             * other club picker on this event posts: a tenant slug, or an
             * `event_clubs` uuid for a visiting team with no account on the
             * platform. Resolved by EntryEditor, which is the one authority on
             * that question — the entry is created first and the club applied
             * through it, rather than teaching a second place how clubs work.
             */
            'club' => ['nullable', 'string', 'max:191'],
            /*
             * WHICH ACTIVITY they are entering.
             *
             * An event may hold several and a paper entrant enters as many as
             * the club is paying for. Naming one also answers the desk's other
             * question: the same name arriving a second time is the athlete's
             * SECOND activity, and EntryClaim adds an entry to the person who
             * already exists rather than refusing the name or minting a second
             * account. Absent, the duplicate-name refusal stands exactly as it
             * did.
             */
            'category_id' => ['nullable', 'integer'],
            /*
             * WHAT THEY ARE ENTERING — the priced options this event sells
             * ("Gi", "No-Gi", "Gi + No-Gi"), by UUID.
             *
             * The same list the public enrolment form posts, because it is the
             * same question: a walk-in entered at the desk is buying the same
             * thing as somebody who entered themselves, and an entry with no
             * option chosen is an entry nobody can price. Only keys travel —
             * EventFee re-prices every entry from the event's own rows when it
             * lands, so nothing here decides what anybody is charged.
             */
            'options' => ['nullable', 'array', 'max:30'],
            'options.*' => ['string', 'max:64'],

            // The late-entry window's further facts (2026-10-07) — every one
            // optional, as everything above. An AGE when nobody knows the date
            // of birth; it is kept as an age and never becomes a birthdate.
            'age' => ['nullable', 'integer', 'min:3', 'max:99'],
            'nationality' => ['nullable', 'string', 'size:2', 'alpha'],
            // A photograph and a new club's logo, as data URIs — the real bytes
            // are checked where they are stored.
            'photo' => ['nullable', 'string', 'starts_with:data:image/', 'max:15000000'],
            'club_name' => ['nullable', 'string', 'min:2', 'max:120'],
            'club_logo' => ['nullable', 'string', 'starts_with:data:image/', 'max:8000000'],
        ]);

        // A club typed at the desk: the event's own by that name, or a new one.
        $logoRejected = false;
        if (empty($data['club']) && ! empty($data['club_name'])) {
            $walkIn = app(\App\Events\Support\Clubs\WalkInClub::class)
                ->findOrCreate($event, $data['club_name'], $data['club_logo'] ?? null, (int) $me->id);
            $logoRejected = $walkIn['logo_rejected'];
            if ($walkIn['club']) {
                $data['club'] = $walkIn['club']->uuid;
            }
        }

        $result = $claims->issue(
            $event, $me, $data['full_name'],
            $data['contact_email'] ?? null,
            $data['contact_phone'] ?? null,
            array_filter([
                'gender' => $data['gender'] ?? null,
                'birthdate' => $data['birthdate'] ?? null,
                'weight' => $data['weight'] ?? null,
                'belt_colour' => $data['belt_colour'] ?? null,
                'belt_grade' => $data['belt_grade'] ?? null,
                'representing_tenant_id' => $data['representing_tenant_id'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            $data['options'] ?? [],
            $data['category_id'] ?? null,
        );

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        /*
         * The club, applied THROUGH the entry editor rather than written here.
         *
         * It is the same call the roster's fix sheet makes, so both key shapes
         * resolve, a visiting team with no account lands on
         * `event_club_entrants` instead of `representing_tenant_id`, and the
         * organiser's authority over the choice is re-checked by the one class
         * that owns that rule. Best-effort: the person is already entered, and
         * failing the whole request over a club would lose the name.
         */
        $registration = ! empty($result['claim']['competitor_id'])
            ? ClubEventRegistration::where('event_id', $event->id)->find((int) $result['claim']['competitor_id'])
            : null;

        // Club, photograph and nationality, through the same editor — the
        // photo then lands in the person's own store (A Person's Photo Has One
        // Home) and the club on whichever table it belongs to.
        $edits = array_filter([
            'club' => $data['club'] ?? null,
            'photo' => $data['photo'] ?? null,
            'nationality' => isset($data['nationality']) ? strtoupper($data['nationality']) : null,
        ], fn ($v) => $v !== null && $v !== '');
        $warning = $logoRejected ? __('events::events.club_logo_rejected') : null;

        if ($registration && $edits) {
            $applied = app(EntryEditor::class)->apply($event, $registration, $edits, $me);
            if (($applied['ok'] ?? true) === false) {
                $warning = $applied['message'] ?? $warning;
            }
        }

        // An age with no date of birth stays an age.
        if ($registration && ! empty($data['age']) && empty($data['birthdate'])) {
            $registration->forceFill(['declared_age' => (int) $data['age']])->saveQuietly();
        }

        // The entrant count moved for everyone looking at this event.
        $this->pushEventRefresh($event);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            // Entered, but something optional (a photo, a logo) was refused.
            'warning' => $warning,
            'claim' => $result['claim'],
            'going' => $event->participantRegistrations()->count(),
            'going_people' => $event->participantRegistrations()->distinct()->count('user_id'),
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
            'going_people' => $event->participantRegistrations()->distinct()->count('user_id'),
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
            'going_people' => $event->participantRegistrations()->distinct()->count('user_id'),
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
            'message' => __('events::events.entry_remove_result', [
                'removed' => count($result['removed']),
                'rejected' => count($result['rejected']),
            ]),
            // The head count as a SENTENCE, rendered here: "12 athletes"
            // pluralises differently in the two languages this platform speaks
            // (Arabic has five forms), and a client that rebuilt it from the
            // number would get one of them wrong.
            //
            // Counted in PEOPLE, because that is what the list this answers is
            // made of: one card per athlete, however many divisions they
            // entered (RosterPeople::byPerson). `going` itself stays the number
            // of ENTRIES — every other reader of it means entries — so the two
            // are computed separately rather than one being bent to the other.
            'going_label' => trans_choice('personal.event_people_athletes', $people = $event->participantRegistrations()->distinct()->count('user_id'), ['count' => $people]),
            'going_people' => $people,
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
                ? __('events::events.public_auto_on_done')
                : __('events::events.public_auto_off_done'),
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
        // The sentence itself moved to App\Events\Support\EventAccess, beside
        // the rule that decides whether the draw may be seen at all — a fourth
        // surface (the entrant's panel) needed it and lives in another module.
        // This stays as the local name its three callers already use.
        return app(EventAccess::class)->drawHiddenMessage($event);
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
            'message' => __('events::events.draw_reveal_saved'),
        ]);
    }

    /**
     * How athletes enter this event — individuals, clubs, or both (owner,
     * 2026-10-03; App\Events\Support\EntryChannels). Organiser only. Only
     * NEW entries are affected; nobody already entered loses their place.
     */
    public function setEntryChannels(Request $request, ClubEvent $event, EventAccess $access): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_if(! $access->canManage($event, $me), 403);

        $data = $request->validate([
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', Rule::in(\App\Events\Support\EntryChannels::ALL)],
        ]);

        $channels = app(\App\Events\Support\EntryChannels::class);
        $channels->set($event, $data['channels']);

        // Readers' join cards change with this — each re-fetches its own view.
        $this->pushEventRefresh($event);

        return response()->json([
            'success' => true,
            'channels' => $channels->allowed($event->fresh()),
            'message' => __('events::events.entry_channels_saved'),
        ]);
    }

    /* ================= The running order ================= */

    /**
     * The old address of the day board, kept alive and pointed at its tab.
     *
     * ORDER is the input and TIME is the output — the organiser arranges parts
     * and `App\Events\Support\RunSheet` works out every clock time from the
     * day's start, each division's bouts and the bout length. Sixteen times
     * nobody has to type, and a break moved once moves everything after it.
     * That board now lives as THE DAY on the running-order page.
     */
    public function runOrder(ClubEvent $event, Request $request): RedirectResponse
    {
        /*
         * The day board is a TAB of the running-order page now, not a page of
         * its own — see runningOrderPage() for why the two boards merged. This
         * address stays alive and lands on that tab: it is printed on nothing,
         * but it is in browser histories, in an organiser's bookmarks and in
         * the odd message, and a dead link there is a support call.
         *
         * The authorisation deliberately does NOT live here any more. Sending a
         * 403 from a redirect stub would answer a question the destination is
         * about to answer properly, and differently: somebody who may score but
         * not manage is not forbidden, they simply land on the mats tab. The
         * page guards itself.
         */
        $day = $this->runOrderDay($request);

        return redirect()->route('me.events.order.page', array_filter([
            'event' => $event->uuid,
            'view' => 'day',
            'day' => $day > 1 ? $day : null,
        ]));
    }

    /**
     * The same payload as JSON, so every write patches in place and a nudge
     * from another organiser re-reads rather than reloading (No-Reload rule).
     */
    public function runOrderData(ClubEvent $event, Request $request): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_unless($this->canManage($event, $me), 403);

        return response()->json([
            'success' => true,
            'sheet' => $this->runOrderPayload($event, $this->runOrderDay($request)),
        ]);
    }

    /** Add divisions to the day, or a part with no bouts. */
    public function storeRunSlot(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_unless($this->canManage($event, $me), 403);

        $data = $request->validate([
            'day' => ['nullable', 'integer', 'min:1', 'max:60'],
            'category_ids' => ['nullable', 'array', 'max:200'],
            'category_ids.*' => ['integer'],
            // Divisions with the PARTS to place (App\Events\Support\BoutPart);
            // no parts is whatever is left of the division.
            'divisions' => ['nullable', 'array', 'max:200'],
            'divisions.*.id' => ['required', 'integer'],
            'divisions.*.parts' => ['nullable', 'array', 'max:4'],
            'divisions.*.parts.*' => ['string', Rule::in(\App\Events\Support\BoutPart::PLACEABLE)],
            'kind' => ['nullable', Rule::in(array_merge([
                \App\Events\Models\EventRunSlot::KIND_CEREMONY,
                \App\Events\Models\EventRunSlot::KIND_BREAK,
                \App\Events\Models\EventRunSlot::KIND_CUSTOM,
            ], \App\Events\Support\RunSessions::enabled() ? [\App\Events\Models\EventRunSlot::KIND_GROUP] : []))],
            // A group's method (RunSessions).
            'mode' => ['nullable', Rule::in(\App\Events\Support\RunSessions::MODES)],
            // Added from a group's box: into that group (RunSessions). `slots`
            // are classes already on the day, moved into it.
            'group' => ['nullable', 'string', 'uuid'],
            'slots' => ['nullable', 'array', 'max:200'],
            'slots.*' => ['string', 'uuid'],
            'label' => ['nullable', 'string', 'max:160'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            // The break set on the event itself (RunOrder::addEventBreak).
            'event_break' => ['nullable', 'boolean'],
            // One medal ceremony for everyone at the end of the day, rather
            // than each division's own awarding (RunOrder::togetherCeremony).
            'together_ceremony' => ['nullable', 'boolean'],
        ]);

        $day = (int) ($data['day'] ?? 1);
        $order = app(\App\Events\Support\RunOrder::class);

        $group = null;
        if (! empty($data['group'])) {
            abort_unless(\App\Events\Support\RunSessions::enabled(), 404);
            // This event's group on this day, or nothing.
            $group = \App\Events\Models\EventRunSlot::where('event_id', $event->id)->where('day', $day)
                ->where('kind', \App\Events\Models\EventRunSlot::KIND_GROUP)->where('uuid', $data['group'])->first();
            abort_unless($group !== null, 404);
        }

        if ($group) {
            if (! empty($data['divisions'])) {
                $order->addDivisions($event, $data['divisions'], $day, false, $group);
            }
            if (! empty($data['slots'])) {
                $order->intoGroup($event, $day, $data['slots'], $group);
            }
        } elseif (! empty($data['event_break'])) {
            $order->addEventBreak($event, $day);
        } elseif (! empty($data['divisions'])) {
            $order->addDivisions($event, $data['divisions'], $day, ! empty($data['together_ceremony']));
        } elseif (! empty($data['category_ids'])) {
            $order->addDivisions($event, $data['category_ids'], $day);
        } elseif (! empty($data['kind'])) {
            $order->addBlock($event, $data['kind'], $data['label'] ?? null, $data['duration_minutes'] ?? null, $day, $data['mode'] ?? null);
        } else {
            return response()->json(['success' => false, 'message' => __('events::events.run_order_saved')], 422);
        }

        return $this->runOrderResponse($event, $day);
    }

    /**
     * When the day starts (owner, 2026-10-16) — the event's own start time,
     * which every unpinned time on the running order flows from. Organiser
     * only. Only the start is written: the rest of the event is left exactly
     * as it was, and a host that keeps its own copy (a federation's dashboard)
     * is told through EventHost::changed, the same as any console edit.
     */
    public function updateDayStart(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_unless($this->canManage($event, $me), 403);

        $data = $request->validate([
            'day' => ['nullable', 'integer', 'min:1', 'max:60'],
            'start' => ['required', 'date_format:H:i'],
        ]);

        // Never at or after the time the event says it ends — the event's own
        // dates would then disagree with themselves.
        $end = $event->end_time ? substr((string) $event->end_time, 0, 5) : null;
        if ($end && $end !== '00:00' && $data['start'] >= $end) {
            return response()->json(['success' => false,
                'message' => 'The day cannot start at or after the event\'s end time ('.$end.'). Change the end time in Edit event first.'], 422);
        }

        $event->forceFill(['start_time' => $data['start'].':00'])->save();
        \App\Events\Support\EventHost::changed($event->fresh());

        return $this->runOrderResponse($event->fresh(), (int) ($data['day'] ?? 1));
    }

    /**
     * Change the event's own break — the one set when the event was created —
     * from the running order (owner, 2026-10-07). It IS the event's break, so
     * the schedule, the PDF and the event page follow; and if it is already on
     * the day, its pinned slot moves with it.
     */
    public function updateEventBreak(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_unless($this->canManage($event, $me), 403);

        $data = $request->validate([
            'day' => ['nullable', 'integer', 'min:1', 'max:60'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
        ]);
        $day = (int) ($data['day'] ?? 1);

        app(\App\Events\Support\RunOrder::class)->moveEventBreak($event, $data['start'], $data['end'], $day);

        return $this->runOrderResponse($event->fresh(), $day);
    }

    /** Set the day's order from the board's own list of slot uuids. */
    public function reorderRunOrder(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_unless($this->canManage($event, $me), 403);

        $data = $request->validate([
            'day' => ['nullable', 'integer', 'min:1', 'max:60'],
            'order' => ['required', 'array', 'max:400'],
            'order.*' => ['string', 'uuid'],
        ]);

        $day = (int) ($data['day'] ?? 1);
        app(\App\Events\Support\RunOrder::class)->reorder($event, $day, $data['order']);

        return $this->runOrderResponse($event, $day);
    }

    /**
     * How one GROUP of the running order calls its bouts — one class at a
     * time, round by round, or manual (App\Events\Support\RunSessions). The
     * mats are renumbered through the same door every running-order save uses,
     * so nothing is renumbered once the day has been fought.
     */
    public function updateRunSession(Request $request, ClubEvent $event): JsonResponse
    {
        abort_unless(\App\Events\Support\RunSessions::enabled(), 404);

        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_unless($this->canManage($event, $me), 403);

        $data = $request->validate([
            'day' => ['nullable', 'integer', 'min:1', 'max:60'],
            'group' => ['required', 'string', 'uuid'],
            'mode' => ['required', Rule::in(\App\Events\Support\RunSessions::MODES)],
        ]);

        // Scoped to THIS event: a uuid from another event resolves to nothing.
        $group = \App\Events\Models\EventRunSlot::where('event_id', $event->id)->where('uuid', $data['group'])->first();
        abort_unless($group !== null, 404);

        if (! \App\Events\Support\RunSessions::set($event, $group, $data['mode'])) {
            return response()->json(['success' => false, 'message' => __('events::events.run_group_not_a_group')], 422);
        }

        return $this->runOrderResponse($event->fresh(), (int) ($data['day'] ?? $group->day ?? 1));
    }

    /**
     * The day board's drag: one item to a place in the main list, or into a
     * group's box (RunOrder::move). Only a class goes into a box.
     */
    public function moveRunSlot(Request $request, ClubEvent $event): JsonResponse
    {
        abort_unless(\App\Events\Support\RunSessions::enabled(), 404);

        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_unless($this->canManage($event, $me), 403);

        $data = $request->validate([
            'day' => ['nullable', 'integer', 'min:1', 'max:60'],
            'slot' => ['required', 'string', 'uuid'],
            'group' => ['nullable', 'string', 'uuid'],
            'index' => ['required', 'integer', 'min:0', 'max:400'],
        ]);

        $day = (int) ($data['day'] ?? 1);

        if (! app(\App\Events\Support\RunOrder::class)->move($event, $day, $data['slot'], $data['group'] ?? null, (int) $data['index'])) {
            return response()->json(['success' => false, 'message' => __('events::events.run_group_cannot_move')], 422);
        }

        return $this->runOrderResponse($event, $day);
    }

    /** Pin a part to a clock time, rename it, or change how long it takes. */
    public function updateRunSlot(Request $request, ClubEvent $event, \App\Events\Models\EventRunSlot $slot): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_unless($this->canManage($event, $me), 403);
        abort_unless($slot->event_id === $event->id, 404);

        $data = $request->validate([
            'pinned_at' => ['nullable', 'integer', 'min:0', 'max:1439'],
            'pin' => ['nullable', 'boolean'],
            'label' => ['nullable', 'string', 'max:160'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        $order = app(\App\Events\Support\RunOrder::class);

        if (array_key_exists('pin', $data)) {
            $order->pin($slot, $data['pin'] ? (int) ($data['pinned_at'] ?? 0) : null);
        }

        if (array_key_exists('label', $data) || array_key_exists('duration_minutes', $data)) {
            $order->edit($slot, $data['label'] ?? null, $data['duration_minutes'] ?? null);
        }

        return $this->runOrderResponse($event, (int) $slot->day);
    }

    /** Take a part off the day. The division itself is untouched. */
    public function destroyRunSlot(ClubEvent $event, \App\Events\Models\EventRunSlot $slot): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_unless($this->canManage($event, $me), 403);
        abort_unless($slot->event_id === $event->id, 404);

        $day = (int) $slot->day;
        app(\App\Events\Support\RunOrder::class)->remove($slot);

        return $this->runOrderResponse($event, $day);
    }

    /* ---------------- running order internals ---------------- */

    private function runOrderDay(Request $request): int
    {
        return max(1, min(60, (int) $request->query('day', 1)));
    }

    /**
     * One write, one answer: the whole day back, plus a nudge to every other
     * organiser's board.
     *
     * A refresh signal rather than a payload — what a board may show differs by
     * who is reading it, so each one re-fetches what it is allowed to see
     * (CLAUDE.md → Realtime, §11).
     */
    /**
     * Auto-schedule (owner, 2026-10-16): every division not on the day yet goes
     * onto it, youngest first, then lightest, then male before female — each
     * on the day its preliminaries are set for. What the organiser already
     * placed stays exactly where it is; this only appends.
     *
     * Then the ordinary save: the mats re-read the sheet (applyRunOrderToMats —
     * numbers set by hand are kept, and nothing is renumbered once a bout has
     * been fought), the hall and other organisers are told.
     */
    public function autoRunOrder(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);
        abort_unless($this->canManage($event, $me), 403);

        $data = $request->validate(['day' => ['nullable', 'integer', 'min:1', 'max:60']]);
        $days = $this->dayCount($event);
        $n = fn ($v, $d) => $v === null ? $d : (float) $v;

        $divisions = $event->categories()->orderBy('sort_order')->orderBy('id')->get()
            ->reject(fn (EventCategory $c) => $c->isHeading())
            ->sortBy(fn (EventCategory $c) => [
                $n($c->min_age, 0), $n($c->max_age, 999),
                ($c->max_weight === null || (float) $c->max_weight <= 0 || (float) $c->max_weight >= 200) ? 999 : (float) $c->max_weight,
                $n($c->min_weight, 0),
                match (strtolower((string) $c->gender)) { 'male' => 0, 'female' => 1, default => 2 },
            ])
            ->groupBy(fn (EventCategory $c) => min($days, max(1, (int) (($c->schedule ?? [])['preliminary'] ?? 1))));

        $order = app(\App\Events\Support\RunOrder::class);
        $wt = \App\Events\Support\RunSessions::enabled();
        $added = 0;
        foreach ($divisions as $day => $list) {
            $day = (int) $day;
            if (! $wt) {
                $added += $order->addDivisions($event, $list->pluck('id')->all(), $day);

                continue;
            }

            /*
             * World Taekwondo (owner, 2026-10-16): on each court, every class's
             * first round, then the next round, the finals last — the running
             * order's ROUND BY ROUND group (RunSessions), which the Scheduler
             * already numbers per court. One group per day: the round-by-round
             * group already there, or a new one. Classes standing on the day in
             * no group join it in their current order; the rest follow, sorted.
             * A group the organiser made with another method is left alone.
             */
            $group = \App\Events\Models\EventRunSlot::where('event_id', $event->id)->where('day', $day)
                ->where('kind', \App\Events\Models\EventRunSlot::KIND_GROUP)
                ->where('mode', \App\Events\Support\RunSessions::ROUND_BY_ROUND)
                ->orderBy('position')->first()
                ?? $order->addBlock($event, \App\Events\Models\EventRunSlot::KIND_GROUP, 'Round by round (WT)', null, $day, \App\Events\Support\RunSessions::ROUND_BY_ROUND);

            $groupIds = \App\Events\Models\EventRunSlot::where('event_id', $event->id)
                ->where('kind', \App\Events\Models\EventRunSlot::KIND_GROUP)->pluck('id')->all();
            $loose = \App\Events\Models\EventRunSlot::where('event_id', $event->id)->where('day', $day)
                ->where('kind', \App\Events\Models\EventRunSlot::KIND_DIVISION)
                ->where(fn ($q) => $q->whereNull('group_id')->orWhereNotIn('group_id', $groupIds))
                ->orderBy('position')->orderBy('id')->pluck('uuid')->all();
            if ($loose) {
                $order->intoGroup($event, $day, $loose, $group);
            }

            $added += $order->addDivisions($event, $list->pluck('id')->all(), $day, false, $group);
        }

        $response = $this->runOrderResponse($event, (int) ($data['day'] ?? 1));
        $payload = $response->getData(true);
        $payload['added'] = $added;
        $how = $wt ? 'bouts run round by round on each court (World Taekwondo).' : 'bouts laid out on the mats.';
        $payload['message'] = $added
            ? ($added === 1 ? 'One division added to the day' : $added.' divisions added to the day').' — '.$how
            : 'Every division is already on the day — '.$how;

        return response()->json($payload);
    }

    private function runOrderResponse(ClubEvent $event, int $day): JsonResponse
    {
        $this->applyRunOrderToMats($event);
        $this->pushEventRefresh($event);
        // …and the hall: every mat's wall boards get their new queue and every
        // scoring console re-reads its Bouts list, the moment the sheet moves.
        // Best-effort — a television that is off never fails a save.
        rescue(fn () => app(\App\Scoreboard\Fleet::class)->boardsChanged($event), null, false);

        return response()->json([
            'success' => true,
            'message' => __('events::events.run_order_saved'),
            'sheet' => $this->runOrderPayload($event->fresh(), $day),
        ]);
    }

    /**
     * THE MATS OBEY THE SHEET.
     *
     * The running order is not a leaflet — an organiser who puts Group B at
     * 10:00 means Group B is what the mat runs first, right through to its
     * final and its podium, and then the next division. The numbering lives in
     * `Scheduler::scheduleAndNumber()`, which reads the sheet; this is the
     * moment it has to be re-read, because the sheet has just changed.
     *
     * ⚠️ NOT ONCE THE DAY HAS STARTED. Renumbering rewrites bout numbers that
     * have already been announced over a PA and written on a whiteboard, so it
     * is done only while nothing has been fought. After the first result the
     * sheet still moves — it is the organiser's plan and the public page
     * follows it — but the mats keep the numbers the hall is working to. A
     * reordered day mid-competition is a conversation with the mat officials,
     * not a silent renumber behind them.
     *
     * Best-effort: an event type with no bouts (a belt test, a league) has
     * nothing to number, and a failure here must never lose the organiser's
     * write, which has already been committed.
     */
    private function applyRunOrderToMats(ClubEvent $event): void
    {
        rescue(function () use ($event) {
            $bouts = \App\Events\Models\EventMatch::where('event_id', $event->id);

            if (! (clone $bouts)->exists()) {
                return;
            }

            $fought = (clone $bouts)
                ->where(fn ($q) => $q->whereNotNull('winner')->orWhere('status', 'done'))
                // A bye is decided at draw time and has never been on a mat,
                // so it is not the day having started.
                ->whereNotNull('match_no')
                ->exists();

            if ($fought) {
                return;
            }

            app(\App\Sports\Combat\Engine\Scheduler::class)->scheduleAndNumber($event->fresh());
        }, null, false);
    }

    /**
     * Everything a board draws, and nothing it does not: the day's parts with
     * their worked-out times, the divisions still on the bench, the
     * back-to-back clashes, and the two numbers the organiser actually sets.
     */
    private function runOrderPayload(ClubEvent $event, int $day): array
    {
        $sheet = app(\App\Events\Support\RunSheet::class);
        $rows = $sheet->rows($event, $day);

        $clock = function (?int $minutes): ?string {
            if ($minutes === null) {
                return null;
            }
            $m = (($minutes % 1440) + 1440) % 1440;

            return \App\Support\Cldr::time(\Carbon\Carbon::today()->setTime(intdiv($m, 60), $m % 60));
        };

        foreach ($rows as $i => $row) {
            $rows[$i]['starts_label'] = $clock((int) $row['starts_at']);
            $rows[$i]['ends_label'] = $clock((int) $row['ends_at']);
        }

        $start = $event->start_time ? \Carbon\Carbon::parse($event->start_time) : null;

        return [
            'day' => $day,
            'days' => $sheet->days($event),
            'rows' => $rows,
            'unscheduled' => $sheet->unscheduled($event)
                ->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name,
                    // What is left of it to place, and what it has at all — a
                    // division with no draw yet has no parts and goes on whole.
                    'remaining' => $c->remaining_parts,
                    'parts' => $c->present_parts,
                ])->values()->all(),
            'part_labels' => collect(\App\Events\Support\BoutPart::PLACEABLE)
                ->mapWithKeys(fn ($p) => [$p => \App\Events\Support\BoutPart::label($p)])->all(),
            'conflicts' => app(\App\Events\Support\RunOrder::class)->conflicts($event, $day),
            // The event's break times, for the edit sheet — on the day or not.
            'break_times' => $event->break_start && $event->break_end
                ? ['start' => substr((string) $event->break_start, 0, 5), 'end' => substr((string) $event->break_end, 0, 5)]
                : null,
            // Is this day's medal ceremony one for everyone? Seeds the add sheet's switch.
            'together' => app(\App\Events\Support\RunOrder::class)->togetherSlot($event, $day) !== null,
            // The break set on the event, while it is not on this day yet.
            'event_break' => ($b = app(\App\Events\Support\RunOrder::class)->eventBreak($event, $day))
                ? $b + ['starts_label' => $clock($b['starts_at']), 'ends_label' => $clock($b['starts_at'] + $b['duration'])]
                : null,
            'finishes_at' => $sheet->finishesAt($event, $day),
            'finishes_label' => $clock($sheet->finishesAt($event, $day)),
            'starts_label' => $start ? \App\Support\Cldr::time($start) : null,
            // The same start as "HH:MM", for an editor to begin from.
            'start_time' => $start ? $start->format('H:i') : null,
            'bout_minutes' => (int) ($event->minutes_per_match ?: \App\Events\Support\RunSheet::DEFAULT_BOUT_MINUTES),
        ];
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

        if (\App\Events\Support\EntryWindow::hasClosed($event)) {
            return response()->json([
                'success' => false,
                'message' => __('events::events.claim_disown_closed'),
            ], 422);
        }

        $entries->disown($event, $me, $user);

        rescue(fn () => \Realtime()->publishToUser($user->id, 'events', [
            'action' => 'refresh', 'event' => $event->uuid,
        ]), null, false);

        return response()->json([
            'success' => true,
            'message' => __('events::events.claim_disowned_done'),
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

        /*
         * EVERY entry this person holds in the event, not the first one found.
         *
         * Since an athlete may hold one entry per division (Gi and No-Gi are
         * two entries in one event), taking the first row would have removed
         * them from one bracket and left them standing in the other — and for
         * `block` and `blacklist` that is not a cosmetic miss: a moderated
         * competitor would still have been called to a mat.
         *
         * Every division they were in is collected first, so each of their
         * draws is re-cut, not just one.
         */
        $regs = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $user->id)->get();
        $catIds = $regs->pluck('category_id')->filter()->unique()->values();

        foreach ($regs as $reg) {
            // One at a time, so any model events on the row still fire — a
            // mass delete would skip them (CLAUDE.md → Delete Files Before
            // Records).
            $reg->delete();
        }

        // A club-wide blacklist needs a club. An event no club hosts (a
        // federation's) can only bar the person from THIS event.
        if ($data['action'] === 'blacklist' && ! $event->tenant_id) {
            $data['action'] = 'block';
        }

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
        // depended on it (for a championship, the affected divisions' draws).
        // Once per division they were in, and once with nothing when they were
        // in none, which is what the packages expect for an unclassified entry.
        $type = $this->typeFor($event);

        if ($catIds->isEmpty()) {
            $type->onEntrantsChanged($event, null);
        } else {
            foreach ($catIds as $id) {
                $type->onEntrantsChanged($event, EventCategory::find($id));
            }
        }

        // Best-effort realtime nudge to the affected member (DB stays source of truth).
        rescue(fn () => \Realtime()->publishToUser($user->id, 'events', [
            'action' => 'moderated',
            'event' => $event->uuid,
            'kind' => $data['action'],
        ]), null, false);

        // …and every other organiser, so a console with the barred list open
        // does not sit on a version of it that is already wrong.
        $this->pushModerationChanged($event);

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
            'going_people' => $event->participantRegistrations()->distinct()->count('user_id'),
            'spectators' => $event->registrations()->where('role', 'spectator')->count(),
        ]);
    }

    /**
     * Tell the OTHER organisers that the barred list moved.
     *
     * The member themselves is told separately, by name, because what they are
     * told is about them. This is the other half of the audience and it needs a
     * different shape: a refresh signal, not a payload. What each manager may
     * read differs (a club-wide bar belongs to the club, an event bar to the
     * event), so the honest push is "look again" and let each console re-ask a
     * door that authorises per request — CLAUDE.md's realtime rule, point 4.
     *
     * Best-effort, like every other push here: the database is the truth and a
     * broker that is down must never fail a moderation write.
     */
    private function pushModerationChanged(ClubEvent $event): void
    {
        rescue(function () use ($event) {
            $ids = app(\App\Events\Support\AudienceResolver::class)->managers($event);

            if (! $ids) {
                return;
            }

            \Realtime()->publishMany(array_map(fn (int $uid) => [
                'topic' => \Realtime()->userTopic($uid, 'events'),
                'payload' => ['action' => 'moderation', 'event' => $event->uuid],
            ], $ids));
        }, null, false);
    }

    /**
     * Who is barred from this event, and why.
     *
     * ── Why this is a door of its own ───────────────────────────────────────
     *
     * The list used to be serialised into every load of the roster partial, for
     * a tab that page does not render — so a moderated member's name sat in the
     * source of a screen nobody could open it from. Asked for, it is read by the
     * console's own sheet and by nothing else.
     *
     * ── Why it carries the reason ───────────────────────────────────────────
     *
     * `moderateParticipant` has always recorded one and no surface has ever
     * shown it. Three weeks after the fact "why is this person barred?" is the
     * only question anybody asks, and the answer was already in the table.
     * `created_by` and `created_at` are the same story: who decided, and when.
     *
     * ── The two scopes ──────────────────────────────────────────────────────
     *
     * `event` reaches this competition; `club` reaches every event the host club
     * runs. Both are returned, because a club-wide blacklist keeps somebody out
     * of THIS event and an organiser who cannot see it has no explanation for
     * why an entry is refused. The sheet says which is which, and says plainly
     * that lifting a club-wide one reaches beyond the event they are standing in
     * — which is what liftBan() has always done.
     */
    public function bans(ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $rows = EventParticipantBan::with([
                'user:id,uuid,full_name,name,gender,profile_picture,profile_picture_is_public,updated_at',
            ])
            ->where(function ($q) use ($event) {
                $q->where(fn ($w) => $w->where('scope', 'event')->where('event_id', $event->id))
                    ->orWhere(fn ($w) => $w->where('scope', 'club')->where('tenant_id', $event->tenant_id));
            })
            ->latest()
            ->get();

        /* Who did the barring, in one query rather than one per row. Only the
           name — an organiser reading this list has no business with the
           moderator's contact details. */
        $byNames = User::whereIn('id', $rows->pluck('created_by')->filter()->unique())
            ->pluck('full_name', 'id');

        $people = $rows
            /* One card per PERSON (CLAUDE.md). Somebody barred from the event
               AND blacklisted club-wide holds two rows; the wider one is the one
               that decides what happens to them, and `latest()` above is not a
               safe way to pick it — so it is chosen explicitly. */
            ->groupBy('user_id')
            ->map(function ($group) use ($byNames) {
                $ban = $group->firstWhere('scope', 'club') ?? $group->first();
                $user = $ban->user;

                return [
                    'id' => (int) $ban->user_id,
                    'name' => $user?->full_name ?: ($user?->name ?: __('personal.event_show_member')),
                    'gender' => $user?->gender,
                    /* Their own choice about their own face is not suspended by
                       being barred (Profile Pictures Are Portrait 3:4). No
                       picture falls back to the silhouette, silently. */
                    'photo' => ($user?->profile_picture && $user->profile_picture_is_public)
                        ? file_url($user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0)
                        : null,
                    'scope' => $ban->scope,           // 'event' | 'club'
                    'reason' => $ban->reason ?: null,
                    'by' => $byNames[$ban->created_by] ?? null,
                    'at' => $ban->created_at ? Cldr::skeleton($ban->created_at, 'EEEdMMM', 'D j M') : null,
                    /* Both rows exist for this person: lifting clears both at
                       once (liftBan), and the sheet says so rather than letting
                       a second bar surprise them afterwards. */
                    'both' => $group->count() > 1,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'people' => $people,
            'club' => $event->host()->name ?: null,
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

        $this->pushModerationChanged($event);

        return response()->json([
            'success' => true,
            'message' => $user->name.' can join again.',
            'user' => ['id' => $user->id, 'name' => $user->name],
        ]);
    }

    /* ===================== Mappers ===================== */

    /**
     * My entry per event, for the "am I in this?" badge on a list of events.
     *
     * One row per event, and WHICH row now matters: an athlete may hold an
     * entry in several divisions of one event, so keyBy() alone would keep
     * whichever came back last. Ordered so a participant row always wins over
     * a spectator one, and the earliest entry wins between equals — the badge
     * then answers "how am I in this event" the same way on every load instead
     * of flickering between two true answers.
     */
    private function myRegistrations(int $meId, $eventIds)
    {
        return ClubEventRegistration::where('user_id', $meId)
            ->whereIn('event_id', $eventIds)
            ->orderByRaw("case when role = 'participant' then 0 else 1 end")
            ->orderBy('id')
            ->get()
            ->reverse()          // keyBy keeps the LAST of a duplicate key
            ->keyBy('event_id');
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

        /*
         * The organiser's own words in the reader's language — the SAME lookup
         * the public poster does (App\Events\Support\PublicEvent::payload).
         *
         * One event, one face: a member reading /me/events/{uuid} and a
         * stranger reading /e/{uuid} render from the same partials, so if only
         * one of them translated, the two would disagree about what the event
         * says. Read-only here too — nothing on a page render starts a job.
         */
        $tr = \App\Translation\Translations::of($e);
        $divisionSections = (new \App\Events\Support\DivisionSections)->build($e, $tr);
        $reg = $myReg->get($e->id);

        // Detail page: full classified, weighed-only roster. List cards: a light teaser.
        $spectatorRows = [];
        $spectatorsTotal = $spectators;
        /*
         * ===== How many PEOPLE are in this event =====
         *
         * `$going` is the number of ENTRIES, and that is the right number for
         * capacity: an athlete entered in a Gi group and a No-Gi group occupies
         * two places in the draw and pays twice. But every surface that says
         * "N in" is labelling a list of PEOPLE, one card each however many
         * divisions they entered (RosterPeople::byPerson, and the public entry
         * list which folds the same way) — so the participants tile read 81
         * over a list of 53 faces (reported 2026-09-13).
         *
         * Counted the same way removeAthletes() counts `going_people`, so the
         * two can never disagree. Detail pages only: a list card never draws
         * this number, and a per-event query there would be an N+1.
         */
        $participantsPeople = null;
        if ($full) {
            $prows = $this->scopeRosterStatus($type->rosterRows($e), $isStaff, $meId);
            $participants = $wholeRoster ? $prows : array_slice($prows, 0, 12);
            $participantsTotal = count($prows);
            $participantsPeople = $e->participantRegistrations()->distinct()->count('user_id');
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
            'mon' => Cldr::skeleton($date, 'MMM', 'M'),
            'wday' => Cldr::skeleton($date, 'EEE', 'D'),
            // Comparable form of the same day. The run-of-show timeline uses it
            // to find which of its phases IS the start of the event, so the
            // date chip can jump straight to that row rather than the section.
            'date_iso' => $date->toDateString(),
            'title' => $tr->get('title', $e->title),
            'club' => $e->host()->name ?: 'TAKEONE',
            'location' => $tr->get('location', $e->location) ?? 'TBA',
            'address' => $tr->get('location', $e->location) ?? '',
            'location_url' => $e->location_url,
            'lat' => $e->gps_lat ? (float) $e->gps_lat : $e->host()->lat,
            'lng' => $e->gps_long ? (float) $e->gps_long : $e->host()->lng,
            'time' => $start ? Cldr::time($start) : __('events::events.tba'),
            'end' => $end ? Cldr::time($end) : '',
            'duration' => $this->duration($start, $end),
            'level' => $tr->get('level', $e->level) ?? 'All',
            'tag' => $this->typeLabel($e->event_type),
            'type' => $this->typeLabel($e->event_type),
            'scope' => $e->scope ?? 'internal',
            'scope_label' => $this->scopeLabel($e->scope ?? 'internal'),
            'host_club' => $e->host()->name ?: null,
            'sections' => $this->typeSections($e->event_type),
            'sport' => $e->sport,
            // Translatable, same as the public poster — see App\Events\Support\SportLabel.
            'sport_label' => \App\Events\Support\SportLabel::forEvent($e, $e->sport ? ($this->sports()[$e->sport]['label'] ?? null) : null),
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
                : ($e->participant_fee ?: __('events::events.fee_free')),
            'spectator' => $e->spectator_enabled ? [
                'fee' => EventFee::amount($e, 'spectator')
                    ? EventFee::display(EventFee::amount($e, 'spectator'), EventFee::currency($e))
                    : ($e->spectator_fee ?: __('events::events.fee_free')),
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
                        // The words, translated; the amount beside them never is.
                        'label' => $tr->get('fees.'.$o->id, (string) $o->label),
                        'amount' => (float) $o->amount,
                        'display' => EventFee::display((float) $o->amount, EventFee::currency($e)),
                    ])->values()->all(),
                'spectator_base' => (float) (EventFee::amount($e, 'spectator') ?? 0),
                'spectator_options' => $e->spectator_enabled
                    ? EventFee::options($e, 'spectator')
                        ->map(fn ($o) => [
                            'key' => $o->uuid,
                            'label' => $tr->get('fees.'.$o->id, (string) $o->label),
                            'amount' => (float) $o->amount,
                            'display' => EventFee::display((float) $o->amount, EventFee::currency($e)),
                        ])->values()->all()
                    : [],
                // Whether the penalty is live RIGHT NOW, decided on the server:
                // a browser with a wrong clock must not be able to talk itself
                // out of a late fee, and the number it shows should match the
                // number it will be charged.
                // Radio or checkbox — one answer, so every picker built from
                // this payload asks the question the same way, and the same way
                // EventFee::quote() will answer it.
                'selection' => EventFee::selection($e),
                'late_active' => EventFee::lateFeeApplies($e, 'participant'),
                'late_amount' => (float) ($e->late_fee_amount ?? 0),
                'late_from' => $e->late_fee_from?->toIso8601String(),
            ],
            'prize' => $tr->get('prize', $e->prize),
            'results' => array_values($e->results ?? []),
            'about' => $tr->get('about', $e->description ?? ''),
            'tags' => $e->tags ?: [],
            'requirements' => $tr->list('requirements', array_values($e->requirements ?: [])),
            // Timeline, run-of-show and final standings all come from the owning
            // package — a bracketed championship derives them from its draw, a
            // simple event just replays what the organiser typed.
            'phases' => $type->timeline($e),
            'agenda' => $e->agenda ?: [],
            'bracket_results' => ($full && ! $type->allowsManualResults()) ? $type->results($e) : [],
            /* Headings excluded, and the sections they describe alongside —
             * one shared reading of the list (App\Events\Support\DivisionSections),
             * so this payload and the public one cannot disagree about what a
             * division is. `division_rows` below is the ORGANISER's list and
             * deliberately still carries headings: on that screen they are
             * rows to edit. */
            'divisions' => $divisionSections['divisions'],
            // Untranslated, same order — the detail card groups by parsing the
            // source shape. See PublicEvent::payload() for why.
            'divisions_source' => $divisionSections['divisions_source'],
            'division_sections' => $divisionSections['sections'],
            'participants' => $participants,
            'participants_total' => $participantsTotal,
            // The head count in PEOPLE — what every "N in" on the event reads.
            // Null on a list card, where the views fall back to the entries.
            'participants_people' => $participantsPeople,
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
            // The competition's clock — every date and time on this event is in it.
            'timezone' => $e->zone(),
            'timezone_chosen' => $e->timezone,
            /*
             * Enough of the divisions for the Draw tile to say how many people
             * are in them — which it could not: this was a one-element sentinel
             * `['_' => true]` carrying no `joined`, and the tile's
             * `collect($e['categories'])->sum('joined')` therefore read ZERO on
             * every event ever rendered, beside a Participants tile that had
             * the right number.
             *
             * Counted in one grouped query rather than per division, because
             * this runs for every card on the events LIST as well as the page.
             */
            'categories' => $this->divisionCounts($e),
            /*
             * The divisions in FULL, for the console's editor.
             *
             * `divisions` above is a list of names — enough for a chip row and
             * nothing else. The editor needs what each one is FOR (its range),
             * how many are in it, whether a draw has been cut, and how it is
             * decided, because those are the things being edited.
             *
             * Organiser only: an entrant has no business reading capacities and
             * ranges, and `$full` is the same gate the moderation data above
             * uses.
             */
            'division_rows' => ($full && $isOrganiser)
                ? (function () use ($e) {
                    $cats = $e->categories()->orderBy('sort_order')->orderBy('id')->get();
                    // One query for every division's faces, rather than one per card.
                    $faces = $this->facesFor($cats->pluck('id')->all());

                    return $cats->map(fn ($c) => $this->divisionPayload($c, $faces[$c->id] ?? []))
                        ->values()->all();
                })()
                : [],
            /*
             * The phases a division can be scheduled across, and how many days
             * there are to spread them over.
             *
             * The create form has carried these as hardcoded Alpine state since
             * it was written. Sending them from the server instead means the
             * console's editor and the form's agree, and — the real reason —
             * that a package which runs a different set of phases can one day
             * supply its own rather than being described by a list typed into a
             * Blade file.
             */
            'phase_defs' => $this->phaseDefs(),
            'day_count' => $this->dayCount($e),
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

        return response()->json(['success' => true, 'message' => 'Draw saved 🥋', 'redirect' => route('me.events.bracket.manage', $event->uuid)]);
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
