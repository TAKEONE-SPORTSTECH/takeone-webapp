<?php

namespace App\Http\Controllers;

use App\Events\Contracts\EventType;
use App\Events\EventTypeRegistry;
use App\Events\Support\EntryService;
use App\Events\Support\EventAccess;
use App\Events\Support\EventFee;
use App\Events\Support\RosterPeople;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Models\EventCategory;
use App\Models\EventChecklistItem;
use App\Models\EventExpense;
use App\Models\EventOfficial;
use App\Models\EventParticipantBan;
use App\Models\Tenant;
use App\Models\User;
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
    private function clubsICanCreateFor(User $me): array
    {
        $ids = $me->memberClubs()->pluck('tenants.id')
            ->merge(app(EntryService::class)->administeredClubIds($me))
            ->unique();

        return \App\Models\Tenant::whereIn('id', $ids)
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

        $owns = \App\Models\Tenant::whereKey($tenantId)->where('owner_user_id', $me->id)->exists();

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

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'personal.mobile.events' : 'personal.desktop.events', compact('demo'));
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

        $canManage = $this->canManage($event, $me);
        $banned = $this->isBanned($event, $me->id);
        $gate = $type->enrolmentGate($event, $me, $myReg->get($event->id));

        $isMobile = (bool) $request->attributes->get('is_mobile');
        $device = $isMobile ? 'mobile' : 'desktop';

        $view = $this->packageView($type, 'show', $device, $isMobile ? 'personal.mobile.event-show' : 'personal.desktop.event-show');

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

        $isMobile = (bool) $request->attributes->get('is_mobile');

        // A package may bring its own console. A sparring session's run-day
        // screen has nothing in common with a championship's — no draw, no
        // weigh-in, no entries to verify — so it supplies its own rather than
        // hiding half of the shared one. Falls back to the shared console for
        // every type that declares nothing, exactly like `show` and `bracket`.
        return view($this->packageView($type, 'manage', $isMobile ? 'mobile' : 'desktop',
            $isMobile ? 'personal.mobile.event-manage' : 'personal.desktop.event-manage'), [
            'e' => $e,
            'canManage' => $canManage,
            'canOfficiate' => $canOfficiate,
            'canWeigh' => $access->canVerifyWeighIn($event, $me),
            'canPay' => $access->canVerifyPayments($event, $me),
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

        $isMobile = (bool) $request->attributes->get('is_mobile');
        $device = $isMobile ? 'mobile' : 'desktop';

        return view(
            $this->packageView($type, 'bracket', $device, 'personal.'.$device.'.event-bracket'),
            [
                'e' => $e,
                'categories' => $categories,
                // The division to open on.
                'initialCategory' => $initialCategory,
                'canManage' => $canManage,
                'canArrange' => $this->canArrangeDraw($event, $type, $canManage),
                // The viewer's own entries, so the board can mark their bouts.
                'myCompetitorIds' => ClubEventRegistration::where('event_id', $event->id)
                    ->where('user_id', $me->id)->pluck('id')->all(),
                'actions' => $canManage ? $type->availableActions($event) : [],
            ] + $type->viewData($event, $me)
        );
    }

    /* ---------------- Officials' console ---------------- */

    /**
     * The officials' desk: the roster with the two gates on its rows.
     *
     * This used to be folded into people(), which made one screen answer two
     * unrelated questions — "who is competing" for everyone walking past, and
     * "is this entry cleared for the draw" for the two people signing it off.
     * They are separate screens now: people() is a reading surface with no
     * controls on it at all, and everything actionable lives here.
     *
     * Officials only. A competitor has no use for a scale and a receipt viewer,
     * and the endpoints each re-authorise anyway — but shipping the wiring to
     * everyone would put the shape of the officials' tools in every page.
     */
    public function verify(ClubEvent $event, Request $request): View
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        // The two officiating jobs, asked separately: a weigh-in official may
        // put an athlete on the scale but must not be able to approve money.
        $access = app(EventAccess::class);
        $canWeigh = $access->canVerifyWeighIn($event, $me);
        $canPay = $access->canVerifyPayments($event, $me);

        abort_unless($canWeigh || $canPay, 403);

        $type = $this->typeFor($event);

        $event->loadCount(['participantRegistrations']);
        $myReg = $this->myRegistrations($me->id, collect([$event->id]));
        $e = $this->eventView($event, $me->id, $myReg, full: true, wholeRoster: true);
        $e['cancelled'] = $event->status === 'cancelled';
        $e['participants'] = $this->attachVerification($event, $e['participants'], $canWeigh, $canPay);

        $canManage = $this->canManage($event, $me);
        $banned = $this->isBanned($event, $me->id);
        $gate = $type->enrolmentGate($event, $me, $myReg->get($event->id));

        return view('personal.event-verification', [
            'e' => $e,
            'canManage' => $canManage,
            'banned' => $banned,
            'canCompete' => $banned ? false : $gate->allowed,
            'eligReason' => $banned ? __('events.banned_by_organiser') : $gate->message,
            'actions' => $canManage ? $type->availableActions($event) : [],
            'finance' => $canManage ? $type->finance($event) : null,
            'canWeigh' => $canWeigh,
            'canPay' => $canPay,
            'payment' => $this->paymentInstructions($event),
        ] + $type->viewData($event, $me));
    }

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
        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return response()->json([
            'success' => true,
            'message' => __('personal.event_photo_saved'),
            'photo' => asset('storage/'.$path),
            'registration' => $registration->id,
        ]);
    }

    /** Take the event photo off an entry, falling back to whatever their profile allows. */
    public function competitorPhotoDestroy(Request $request, ClubEvent $event, ClubEventRegistration $registration): JsonResponse
    {
        abort_unless(app(EventAccess::class)->canManage($event, Auth::user()), 403);
        abort_unless($registration->event_id === $event->id, 404);

        $path = $registration->photo;

        // File first, then the row's reference to it.
        if ($path) {
            Storage::disk('public')->delete($path);
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
        $people = app(RosterPeople::class)->build($e['participants'], $event);

        return view('personal.event-people', [
            'e' => $e,
            'participants' => $people['participants'],
            'clubs' => $people['clubs'],
            // Whether this viewer may put a face on an entry. The roster is
            // readable by everyone in the event; only whoever runs it may edit.
            'canManage' => app(EventAccess::class)->canManage($event, Auth::user()),
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
                    ? route('me.events.verify.proof', [$event->uuid, $reg->id])
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

        if (! $this->canArrangeDraw($event, $type, $canManage)) {
            return redirect()
                ->route('me.events.bracket', $event->uuid)
                ->with('error', __('events.draw_final'));
        }

        return view('personal.event-manage-draw', [
            'e' => $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: true),
            'canArrange' => true,
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
    public function bout(Request $request, ClubEvent $event, int $matchNo): View
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

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

        return view('personal.'.$device.'.event-bout', [
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
                        ? asset('storage/'.$user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0)
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
        foreach (['events.official_'.$role, 'personal.event_officials_role_'.$role] as $key) {
            if (\Illuminate\Support\Facades\Lang::has($key)) {
                return __($key);
            }
        }

        return \Illuminate\Support\Str::title(str_replace('_', ' ', $role));
    }

    /**
     * Tell TAKEONE Play that a bout's competition truth changed.
     *
     * Dispatched from the write paths rather than hung off a model observer: a
     * seeder, an import or a demo purge saves these rows too, and none of those
     * should be pushing to another platform. Being explicit here means the push
     * happens exactly where a human made a decision.
     *
     * Queued and coalesced, so ten edits in a minute are one push. A no-op when
     * the integration is off or the bout has no video.
     */
    private function pushBoutToPlay(?EventMatch $match): void
    {
        if ($match === null || ! config('play.enabled')) {
            return;
        }

        \App\Jobs\PushBoutToPlay::dispatch($match->id);
    }

    /** Push every bout of an event — used when something event-wide changed. */
    private function pushEventBoutsToPlay(ClubEvent $event): void
    {
        if (! config('play.enabled')) {
            return;
        }

        // Only bouts that actually have a video: the rest have nowhere to go.
        \App\Models\EventRecording::where('event_id', $event->id)
            ->where('status', \App\Models\EventRecording::STATUS_LINKED)
            ->whereNotNull('match_id')
            ->pluck('match_id')
            ->unique()
            ->each(fn ($id) => \App\Jobs\PushBoutToPlay::dispatch((int) $id));
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
                        ? asset('storage/'.$user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0)
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
            // Empty string unlinks. A URL must live on the configured Play host:
            // this value ends up as an href on a page other people read.
            'video_url' => ['nullable', 'string', 'max:2048'],
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

        $videoUrl = trim((string) ($data['video_url'] ?? ''));
        $videoKey = null;

        if ($videoUrl !== '') {
            $host = parse_url((string) config('play.url'), PHP_URL_HOST);
            $got  = parse_url($videoUrl);

            if (! $got || ! in_array($got['scheme'] ?? '', ['http', 'https'], true) || ($got['host'] ?? '') !== $host) {
                return response()->json([
                    'success' => false,
                    'message' => __('events.bout_video_host', ['host' => $host]),
                ], 422);
            }

            // .../videos/<key> — the key is the last non-empty path segment.
            $segments = array_values(array_filter(explode('/', (string) ($got['path'] ?? ''))));
            $videoKey = $segments === [] ? null : end($segments);

            if ($videoKey === null || preg_match('/^[A-Za-z0-9_-]{3,64}$/', $videoKey) !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => __('events.bout_video_invalid'),
                ], 422);
            }
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

        $this->linkBoutVideo($event, $match, $videoUrl, $videoKey);

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

        // And the video platform, so the clip's header stops disagreeing with the
        // scoresheet.
        $this->pushBoutToPlay($match);

        return response()->json([
            'success' => true,
            'message' => __('events.bout_saved'),
            'bout' => $this->boutView($event, $match->refresh()),
        ]);
    }

    /**
     * Point this bout at a video on TAKEONE Play, or unlink it.
     *
     * Unlink NEVER deletes: it clears the reference and keeps the row, which is
     * the record that a video once existed. Deletion does not cross between the
     * platforms in either direction (Match Sync Contract).
     */
    private function linkBoutVideo(ClubEvent $event, EventMatch $match, string $url, ?string $key): void
    {
        $recording = \App\Models\EventRecording::where('match_id', $match->id)->latest('id')->first();

        if ($url === '') {
            if ($recording !== null) {
                $recording->forceFill([
                    'status' => \App\Models\EventRecording::STATUS_UNLINKED,
                    'play_url' => null,
                    'play_video_key' => null,
                    'play_video_id' => null,
                ])->save();
            }

            return;
        }

        $recording ??= new \App\Models\EventRecording([
            'event_id' => $event->id,
            'match_id' => $match->id,
            'court' => $match->court,
        ]);

        $recording->forceFill([
            'event_id' => $event->id,
            'match_id' => $match->id,
            'play_url' => $url,
            'play_video_key' => $key,
            'status' => \App\Models\EventRecording::STATUS_LINKED,
        ])->save();
    }

    /**
     * Shape one bout for display, including the links back out to profiles and
     * club pages that the video platform mirrors (§6.6).
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
            'bracket_url' => route('me.events.bracket', array_filter([
                'event' => $event->uuid,
                'category' => $match->category_id,
                'bout' => $match->match_no,
            ])),
            /*
             * Where the bout can be watched, or null.
             *
             * Only a still-linked recording offers a link: an unlinked row is the
             * record that a video ONCE existed (its media was deleted on Play), and
             * pointing at it would be a dead end. A bout that was never filmed has
             * no row at all, which is the ordinary case — so the button is absent
             * rather than disabled.
             */
            'video_url' => \App\Models\EventRecording::where('match_id', $match->id)
                ->where('status', \App\Models\EventRecording::STATUS_LINKED)
                ->whereNotNull('play_url')
                ->latest('id')
                ->value('play_url'),
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
                ? asset('storage/'.$user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0)
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
        $forRole = (string) $request->query('role', '');
        $wide = $forRole !== '' && $this->isMatRole($event, $forRole);

        $candidates = User::query()
            ->distinct()
            ->when($wide,
                fn ($q) => $q->where('is_discoverable', true),
                fn ($q) => $q->whereIn('id', DB::table('memberships')->where('tenant_id', $event->tenant_id)->distinct()->pluck('user_id')))
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q, $phone) {
                $w->where('full_name', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");

                if ($phone !== '') {
                    $w->orWhere('mobile', 'like', "%{$phone}%");
                }
            }))
            // A platform-wide list with no search term is an invitation to browse
            // every member, so the wide pool answers only an actual query.
            ->when($wide && $q === '', fn ($query) => $query->whereRaw('1 = 0'))
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
            'avatar' => $u->profile_picture ? asset('storage/'.$u->profile_picture) : null,
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
            'roles' => collect($this->officialRoleOptions($event))->map(fn ($label, $key) => [
                'value' => $key,
                'label' => $label,
                // Only the platform roles carry a permission, so only they have a
                // hint explaining what it grants.
                'hint' => in_array($key, EventOfficial::roles(), true)
                    ? __('personal.personal_event_officials_role_'.$key.'_hint')
                    : null,
                'group' => in_array($key, EventOfficial::roles(), true) ? 'platform' : 'mat',
            ])->values(),
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
        $out = [];

        $sport = app(\App\Sports\Combat\SportRegistry::class)->get($event->sport);

        if ($sport !== null) {
            foreach ($sport->officialRoles() as $role) {
                if (! empty($role['key'])) {
                    $out[$role['key']] = $role['label'] ?? \Illuminate\Support\Str::title($role['key']);
                }
            }
        }

        foreach (EventOfficial::roles() as $role) {
            $out[$role] = __('personal.personal_event_officials_role_'.$role);
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
            $isMember = DB::table('memberships')
                ->where('tenant_id', $event->tenant_id)
                ->where('user_id', $data['user_id'])
                ->exists();

            if (! $isMember) {
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

        $this->pushEventBoutsToPlay($event);

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
            if (! $this->isMatRole($event, $data['role'])) {
                $isMember = DB::table('memberships')
                    ->where('tenant_id', $event->tenant_id)
                    ->where('user_id', $official->user_id)
                    ->exists();

                if (! $isMember) {
                    return response()->json([
                        'success' => false,
                        'message' => __('personal.personal_event_officials_not_a_member'),
                    ], 422);
                }
            }

            $official->role = $data['role'];
        }

        $official->save();

        $this->recordOfficialNationality($official->user, $data['nationality'] ?? null);

        $this->pushEventBoutsToPlay($event);

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

        $this->pushEventBoutsToPlay($event);

        return response()->json([
            'success' => true,
            'message' => __('personal.personal_event_officials_removed'),
        ]);
    }

    public function create(): View
    {
        $me = Auth::user();
        $clubs = $this->clubsICanCreateFor($me);

        return view('personal.event-create', ['clubs' => $clubs] + $this->schemaPayload());
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

        $clubs = $this->clubsICanCreateFor($me);

        return view('personal.event-create', ['clubs' => $clubs, 'mode' => 'edit', 'event' => $event] + $this->schemaPayload($event));
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
            'max_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'prize' => ['nullable', 'string', 'max:120'],
            'sport' => ['nullable', Rule::in(array_keys($this->sports()))],
        ]);

        // Widening the scope on an EDIT is the same broadcast power as setting
        // it at creation — guard it identically.
        $this->assertMayBroadcast($me, (int) $event->tenant_id, $data['scope'] ?? $event->scope);

        $type = $this->typeForInput($data, $event);
        $data += $request->validate($type->validationRules($event));

        $event->update($type->columnsFromInput($data, $event) + [
            'title' => $data['title'],
            'event_type' => $data['event_type'],
            'scope' => $data['scope'] ?? $event->scope ?? 'internal',
            'icon' => $event->icon ?: ($this->typeIcon($data['event_type'])),
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
        ] + $this->feeColumns($data, EventFee::currency($event)));

        // Divisions, fixtures, re-scheduling — whatever this type keeps outside
        // the event row.
        $type->saveRelatedData($event, $data);

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
    private function feeColumns(array $data, string $currency): array
    {
        $free = (bool) ($data['participant_free'] ?? false);
        $spectators = (bool) ($data['spectator_enabled'] ?? false);

        $pAmount = isset($data['participant_fee_amount']) ? (float) $data['participant_fee_amount'] : null;
        $sAmount = isset($data['spectator_fee_amount']) ? (float) $data['spectator_fee_amount'] : null;

        $columns = ['fee_currency' => $currency, 'spectator_enabled' => $spectators];

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
            'redirect' => route('me.events'),
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
            'redirect' => route('me.events'),
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

        // What it costs is a number now (EventFee), not a word in a sentence.
        $paidFee = EventFee::isPaid($event, 'participant');

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

        ClubEventRegistration::updateOrCreate(
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
            'redirect' => route('me.events.bracket', $event->uuid),
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

    public function ticket(ClubEvent $event): JsonResponse
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

        $paidFee = EventFee::isPaid($event, 'spectator');

        ClubEventRegistration::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $me->id],
            ['role' => 'spectator', 'status' => 'joined', 'paid' => ! $paidFee, 'registered_at' => now()],
        );

        return response()->json([
            'success' => true,
            'message' => $paidFee
                ? 'Ticket booked · '.$event->spectator_fee.' — show this in the app at the door'
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

        return view($isMobile ? 'personal.mobile.event-next-up' : 'personal.desktop.event-next-up', $payload);
    }

    /** @return array<int, int> */
    private function athletesOfClubs(array $clubIds): array
    {
        return \App\Models\User::whereHas('memberClubs', fn ($q) => $q
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
            'e' => ['key' => $event->uuid, 'title' => $event->title],
            'mats' => $mats,
        ];

        return $request->expectsJson()
            ? response()->json(['success' => true] + $payload)
            : view('personal.event-board', $payload);
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
        ]);

        abort_if($entries->administeredClubIds($me) === [] && ! $me->isSuperAdmin(), 403);

        $result = $entries->enterMany($event, $me, $data['user_ids']);

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
            'day' => $date->format('d'),
            'mon' => $date->format('M'),
            'wday' => $date->format('D'),
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
            'time' => $start ? $start->format('g:i A') : 'TBA',
            'end' => $end ? $end->format('g:i A') : '',
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
            'participant_fee' => $e->participant_fee ?: 'Free',
            'spectator' => $e->spectator_enabled ? ['fee' => $e->spectator_fee ?: 'Free', 'count' => $spectators] : null,
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

        return response()->json(['success' => true, 'message' => 'Draw saved 🥋', 'redirect' => route('me.events.bracket', $event->uuid)]);
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
