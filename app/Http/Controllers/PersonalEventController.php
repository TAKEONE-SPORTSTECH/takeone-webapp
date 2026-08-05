<?php

namespace App\Http\Controllers;

use App\Events\Contracts\EventType;
use App\Events\EventTypeRegistry;
use App\Events\Support\EntryService;
use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventExpense;
use App\Models\EventOfficial;
use App\Models\EventParticipantBan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        return view($view, [
            'e' => $e,
            'canManage' => $canManage,
            'banned' => $banned,
            'canCompete' => $banned ? false : $gate->allowed,
            'eligReason' => $banned ? __('events.banned_by_organiser') : $gate->message,
            'actions' => $canManage ? $type->availableActions($event) : [],
            'finance' => $canManage ? $type->finance($event) : null,
            // The verification desk only exists for the people who staff it.
            'canOfficiate' => app(EventAccess::class)->canOfficiate($event, $me),
            // How to pay, for the join sheet.
            'payment' => $this->paymentInstructions($event),
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
        $e = $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: true);
        $canManage = $this->canManage($event, $me);

        $isMobile = (bool) $request->attributes->get('is_mobile');
        $device = $isMobile ? 'mobile' : 'desktop';

        return view(
            $this->packageView($type, 'bracket', $device, 'personal.'.$device.'.event-bracket'),
            [
                'e' => $e,
                'categories' => $categories,
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
     * Where appointed officials do their job: weigh athletes in, and check
     * payments one by one against the club account.
     *
     * Both queues answer the same question — is this entry allowed into the
     * FINAL draw? — so they live on one screen rather than two, and each row
     * says which of the two gates it is still waiting on.
     */
    public function verify(ClubEvent $event, Request $request): View|RedirectResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $access = app(EventAccess::class);
        if (! $access->canOfficiate($event, $me)) {
            return redirect()->route('me.events.show', $event->uuid)
                ->with('error', __('personal.event_verify_not_an_official'));
        }

        $rows = $event->participantRegistrations()
            ->with(['user:id,full_name,name,mobile,profile_picture', 'category:id,name,weight_class'])
            ->get()
            ->map(fn (ClubEventRegistration $r) => [
                'id' => $r->id,
                'name' => $r->user?->full_name ?: ($r->user?->name ?: __('events.athlete')),
                'division' => $r->category?->name,
                'weight' => $r->weight,
                'weighed' => $r->weighed_in_at !== null,
                'weigh_verified' => $r->weighed_in_by !== null,
                'paid' => (bool) $r->paid,
                'pay_verified' => $r->paid_by !== null,
                'has_proof' => (bool) $r->payment_proof,
                'proof_url' => $r->payment_proof ? route('me.events.verify.proof', [$event->uuid, $r->id]) : null,
                // The single question this screen exists to answer.
                'ready' => $r->paid && $r->paid_by && $r->weight !== null && $r->weighed_in_by,
            ])
            ->values()->all();

        return view('personal.event-verify', [
            'e' => $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: true),
            'rows' => $rows,
            'canWeigh' => $access->canVerifyWeighIn($event, $me),
            'canPay' => $access->canVerifyPayments($event, $me),
            'payment' => $this->paymentInstructions($event),
        ]);
    }

    /** Record an official weight. Signing it is the point — hence weighed_in_by. */
    public function verifyWeighIn(Request $request, ClubEvent $event, ClubEventRegistration $registration): JsonResponse
    {
        $me = Auth::user();
        abort_unless(app(EventAccess::class)->canVerifyWeighIn($event, $me), 403);
        abort_unless($registration->event_id === $event->id, 404);

        $data = $request->validate([
            'weight' => ['required', 'numeric', 'min:10', 'max:250'],
        ]);

        $registration->update([
            'weight' => $data['weight'],
            'weighed_in_at' => now(),
            'weighed_in_by' => $me->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('personal.event_verify_weight_recorded'),
            'weight' => (float) $data['weight'],
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
     * Who's joined — the roster on its own screen.
     *
     * Same data show() builds, same partial it used to render inline. On a
     * phone a 48-name list with three tabs sat between the event detail and the
     * join button; here it gets the screen to itself and the event page keeps a
     * card that opens it.
     */
    public function people(ClubEvent $event, Request $request): View
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        $type = $this->typeFor($event);

        $event->loadCount(['participantRegistrations']);
        $myReg = $this->myRegistrations($me->id, collect([$event->id]));
        // The whole roster: this page exists to list everyone, and its search
        // can only narrow names that are actually on the page.
        $e = $this->eventView($event, $me->id, $myReg, full: true, wholeRoster: true);
        $e['cancelled'] = $event->status === 'cancelled';

        $canManage = $this->canManage($event, $me);
        $banned = $this->isBanned($event, $me->id);
        $gate = $type->enrolmentGate($event, $me, $myReg->get($event->id));

        return view('personal.event-people', [
            'e' => $e,
            'canManage' => $canManage,
            'banned' => $banned,
            'canCompete' => $banned ? false : $gate->allowed,
            'eligReason' => $banned ? __('events.banned_by_organiser') : $gate->message,
            'actions' => $canManage ? $type->availableActions($event) : [],
            'finance' => $canManage ? $type->finance($event) : null,
        ] + $type->viewData($event, $me));
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

        $appointed = $event->officials()->with('user:id,full_name,name,email,mobile,profile_picture')->get();

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

        $candidates = User::query()
            ->distinct()
            ->whereIn('id', DB::table('memberships')->where('tenant_id', $event->tenant_id)->distinct()->pluck('user_id'))
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q, $phone) {
                $w->where('full_name', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");

                if ($phone !== '') {
                    $w->orWhere('mobile', 'like', "%{$phone}%");
                }
            }))
            ->orderBy('full_name')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'full_name', 'name', 'email', 'mobile', 'profile_picture']);

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
        ];

        return response()->json([
            'success' => true,
            // `id` is the APPOINTMENT, not the person: the same member can appear
            // twice with two roles, and removing one must not remove the other.
            'officials' => $appointed
                ->map(fn (EventOfficial $o) => $shape($o->user) + ['id' => $o->id, 'user_id' => $o->user_id, 'role' => $o->role])
                ->values(),
            'candidates' => $candidates
                ->map(fn (User $u) => $shape($u) + ['roles' => ($heldRoles[$u->id] ?? collect())->values()])
                ->values(),
            'roles' => collect(EventOfficial::roles())->map(fn ($r) => [
                'value' => $r,
                'label' => __('personal.personal_event_officials_role_'.$r),
                'hint' => __('personal.personal_event_officials_role_'.$r.'_hint'),
            ])->values(),
        ]);
    }

    /** Appoint someone to one officiating job on this event. */
    public function storeOfficial(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', Rule::in(EventOfficial::roles())],
        ]);

        // Only from the host club — the same pool officials() offers.
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
        $official->save();

        return response()->json([
            'success' => true,
            'message' => __('personal.personal_event_officials_added'),
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
            'spectator_enabled' => ['required', 'boolean'],
            'spectator_fee' => ['nullable', 'string', 'max:40'],
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
            'participant_fee' => $data['participant_free'] ? null : ($data['participant_fee'] ?: 'Free'),
            'spectator_enabled' => (bool) $data['spectator_enabled'],
            'spectator_fee' => $data['spectator_enabled'] ? ($data['spectator_fee'] ?: 'Free') : null,
            'prize' => $data['prize'] ?? null,
            'max_capacity' => $data['max_capacity'] ?? null,
            'color' => $this->typeColor($data['event_type']),
            'status' => 'active',
            'is_archived' => false,
        ]);

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
            'spectator_enabled' => ['required', 'boolean'],
            'spectator_fee' => ['nullable', 'string', 'max:40'],
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
            'participant_fee' => $data['participant_free'] ? null : ($data['participant_fee'] ?: 'Free'),
            'spectator_enabled' => (bool) $data['spectator_enabled'],
            'spectator_fee' => $data['spectator_enabled'] ? ($data['spectator_fee'] ?: 'Free') : null,
            'prize' => $data['prize'] ?? null,
            'max_capacity' => $data['max_capacity'] ?? null,
        ]);

        // Divisions, fixtures, re-scheduling — whatever this type keeps outside
        // the event row.
        $type->saveRelatedData($event, $data);

        return response()->json([
            'success' => true,
            'message' => 'Event updated',
            'redirect' => route('me.events.show', $event->uuid),
        ]);
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

        $type = $this->typeFor($event);

        $data = $request->validate([
            'category_id' => ['nullable', 'integer', Rule::exists('event_categories', 'id')->where('event_id', $event->id)],
            // Optional manual proof-of-payment (base64 data-URI). No gateway — the
            // club admin approves it elsewhere; here we only RECORD it.
            'payment_proof' => ['nullable', 'string', 'starts_with:data:image'],
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

        $paidFee = $event->participant_fee && ! str_contains(strtolower($event->participant_fee), 'free');

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
            ]
        );

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

        $paidFee = $event->spectator_fee && ! str_contains(strtolower($event->spectator_fee), 'free');

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
            $hasFee = $fee && ! str_contains(strtolower($fee), 'free');

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
    public function entryRoster(ClubEvent $event, EntryService $entries): JsonResponse
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        abort_if($entries->administeredClubIds($me) === [] && ! $me->isSuperAdmin(), 403);

        return response()->json([
            'success' => true,
            'athletes' => $entries->roster($event, $me),
        ]);
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

        return response()->json([
            'success' => true,
            'message' => __('events.entry_result', [
                'entered' => count($result['entered']),
                'rejected' => count($result['rejected']),
            ]),
        ] + $result);
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
            $prows = $type->rosterRows($e);
            $participants = $wholeRoster ? $prows : array_slice($prows, 0, 12);
            $participantsTotal = count($prows);
            if ($e->spectator_enabled) {
                $srows = $this->spectatorRows($e);
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
            'cap' => $e->max_capacity ?: max($going, 1),
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
            'bans_list' => $full ? $this->bansList($e) : [],
            'joined' => $reg && $reg->role === 'participant',
            // The member's OWN proof-of-payment is awaiting the club's approval.
            'payment_pending' => $reg && $reg->role === 'participant' && ! $reg->paid && (bool) $reg->payment_proof,
            // Holding a place with the fee still outstanding — "pay later", or a
            // proof that no official has approved yet. Distinct from being IN:
            // a screen that says "Booked" over an unpaid entry is lying to them.
            'fee_due' => $reg && ! $reg->paid,
            'watching' => $reg && $reg->role === 'spectator',
            'started' => $e->hasStarted(),
            'ended' => $e->hasEnded(),
            'categories' => $e->categories()->exists() ? ['_' => true] : [],
        ];

        return $view;
    }

    /** Active bans affecting this event (event blocks + club-wide blacklist), for the manager tab. */
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
