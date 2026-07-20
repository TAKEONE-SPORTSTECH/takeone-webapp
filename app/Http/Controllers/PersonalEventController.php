<?php

namespace App\Http\Controllers;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventExpense;
use App\Models\EventParticipantBan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PersonalEventController extends Controller
{
    use \App\Traits\StoresBase64Images;

    public function __construct(
        private \App\Sports\Combat\Engine\DrawEngine $draws,
        private \App\Sports\Combat\Engine\Scheduler $scheduler,
        private \App\Sports\Combat\Engine\Results $results,
    ) {}

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

        $event->loadCount(['participantRegistrations']);
        $myReg = $this->myRegistrations($me->id, collect([$event->id]));
        $e = $this->eventView($event, $me->id, $myReg, full: true);
        $e['cancelled'] = $event->status === 'cancelled';

        // Taekwondo join captures a self-declared weight — prefill from the member's
        // existing registration weight, else their latest health record.
        $myWeight = $myReg->get($event->id)?->weight ?: optional($me->latestHealthRecord)->weight;

        $canManage = $this->canManage($event, $me);
        $banned = $this->isBanned($event, $me->id);
        $elig = $this->competeEligibility($event, $me, $myReg->get($event->id));

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'personal.mobile.event-show' : 'personal.desktop.event-show', [
            'e' => $e,
            'canManage' => $canManage,
            'isTkd' => $event->sport === 'taekwondo',
            'myWeight' => $myWeight,
            'banned' => $banned,
            'canCompete' => $banned ? false : $elig['can'],
            'eligReason' => $banned ? 'You’ve been removed from this event by the organiser.' : $elig['reason'],
            'finance' => $canManage ? $this->computeFinance($event) : null,
        ]);
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
     * Can the current member register as a COMPETITOR? Mirrors register(): for
     * taekwondo they must classify (gender+age+weight) into one of the event's
     * weight divisions. Anyone already a participant stays "eligible" so their
     * confirmed state renders. Non-combat events have no weight gate.
     *
     * @return array{can: bool, reason: ?string}
     */
    private function competeEligibility(ClubEvent $event, User $me, $reg): array
    {
        if ($reg && $reg->role === 'participant') {
            return ['can' => true, 'reason' => null];
        }
        if ($event->sport !== 'taekwondo') {
            return ['can' => true, 'reason' => null];
        }
        if (! $me->gender || ! $me->birthdate) {
            return ['can' => false, 'reason' => 'Add your gender and date of birth to your profile to enter a weight category.'];
        }
        $weight = optional($me->latestHealthRecord)->weight ? (float) $me->latestHealthRecord->weight : null;
        if (! $weight) {
            return ['can' => false, 'reason' => 'Add your current weight to your health profile to register as a competitor.'];
        }
        if (! $this->routeToTaekwondoDivision($event, $me, $weight)) {
            return ['can' => false, 'reason' => 'This championship’s divisions don’t include your age/weight category, so you can’t compete in it.'];
        }

        return ['can' => true, 'reason' => null];
    }

    public function bracket(ClubEvent $event): View
    {
        $me = Auth::user();
        $this->assertVisible($event, $me);

        // Auto-draw: generate/refresh provisional draws before start, and lock a
        // paid-only draw once the event has started.
        $this->draws->ensure($event);

        $categories = $this->categoryViews($event, $me->id);
        $e = $this->eventView($event, $me->id, $this->myRegistrations($me->id, collect([$event->id])), full: true);

        return view('personal.event-bracket', ['e' => $e, 'categories' => $categories, 'canManage' => $this->canManage($event, $me)]);
    }

    public function create(): View
    {
        $me = Auth::user();
        $clubs = $me->memberClubs()->get(['tenants.id', 'club_name', 'currency'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->club_name, 'currency' => $c->currency ?: 'BHD'])->values()->all();

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
            'tkdDivisions' => config('taekwondo_divisions'),
        ];
    }

    /** Is this a combat sport (bracket + weight-class engine)? */
    private function isCombatSport(?string $sport): bool
    {
        return $sport && (($this->sports()[$sport]['family'] ?? null) === 'Combat');
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
        ] + $this->detailRules());

        // Must belong to the club you're creating the event for.
        abort_unless($me->memberClubs()->whereKey($data['tenant_id'])->exists(), 403);

        // Combat events: no event-wide level/capacity/prize (those live per division).
        $combat = $this->isCombatSport($data['sport'] ?? null);

        $event = ClubEvent::create([
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
            'level' => $combat ? null : ($data['level'] ?? null),
            'description' => $data['description'] ?? null,
            'participant_fee' => $data['participant_free'] ? null : ($data['participant_fee'] ?: 'Free'),
            'spectator_enabled' => (bool) $data['spectator_enabled'],
            'spectator_fee' => $data['spectator_enabled'] ? ($data['spectator_fee'] ?: 'Free') : null,
            'prize' => $combat ? null : ($data['prize'] ?? null),
            'max_capacity' => $combat ? null : ($data['max_capacity'] ?? null),
            'color' => $this->typeColor($data['event_type']),
            'status' => 'active',
            'is_archived' => false,
        ] + $this->extractDetails($data));

        $this->syncDivisions($event, $data['divisions'] ?? []);

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

        $clubs = $me->memberClubs()->get(['tenants.id', 'club_name', 'currency'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->club_name, 'currency' => $c->currency ?: 'BHD'])->values()->all();

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
        ] + $this->detailRules());

        $combat = $this->isCombatSport($data['sport'] ?? $event->sport);

        $event->update([
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
            'level' => $combat ? null : ($data['level'] ?? null),
            'description' => $data['description'] ?? null,
            'participant_fee' => $data['participant_free'] ? null : ($data['participant_fee'] ?: 'Free'),
            'spectator_enabled' => (bool) $data['spectator_enabled'],
            'spectator_fee' => $data['spectator_enabled'] ? ($data['spectator_fee'] ?: 'Free') : null,
            'prize' => $combat ? null : ($data['prize'] ?? null),
            'max_capacity' => $combat ? null : ($data['max_capacity'] ?? null),
        ] + $this->extractDetails($data));

        $this->syncDivisions($event, $data['divisions'] ?? []);

        // If a draw already exists and the event hasn't started, re-apply the schedule
        // (court suggestion + per-mat numbers) so day edits take effect immediately.
        if (! $event->hasStarted()) {
            $this->scheduler->scheduleAndNumber($event->fresh());
        }

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

        $isTkd = $event->sport === 'taekwondo';

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

        // Taekwondo: a competitor must fall into one of the weight categories the
        // creator set up. We classify them by gender/age/weight (last weigh reading
        // from the DB; official weight is confirmed at weigh-in). If they don't fit
        // an offered division they CAN'T compete — they're steered to a spectator
        // ticket instead.
        $categoryId = $data['category_id'] ?? null;
        $weight = null;
        $division = null;
        if ($isTkd) {
            if (! $me->gender || ! $me->birthdate) {
                return response()->json([
                    'success' => false,
                    'code' => 'no_profile',
                    'message' => 'Add your gender and date of birth to your profile so we can place you in a weight category.',
                ], 422);
            }

            $weight = optional($me->latestHealthRecord)->weight ? (float) $me->latestHealthRecord->weight : null;
            if (! $weight) {
                return response()->json([
                    'success' => false,
                    'code' => 'no_weight',
                    'message' => 'Add your current weight to your health profile so we can place you in a weight category.',
                ], 422);
            }

            $cat = $this->routeToTaekwondoDivision($event, $me, $weight);
            if (! $cat) {
                // Their weight category isn't being run here → not eligible to compete.
                return response()->json([
                    'success' => false,
                    'code' => 'no_division',
                    'spectator' => (bool) $event->spectator_enabled,
                    'message' => $event->spectator_enabled
                        ? 'Your weight category isn’t one of this championship’s divisions, so you can’t compete — but you’re welcome to attend as a spectator.'
                        : 'Your weight category isn’t one of this championship’s divisions, so you’re not eligible to compete in this event.',
                ], 422);
            }

            $categoryId = $cat->id;
            $division = $cat->name;
        }

        $paidFee = $event->participant_fee && ! str_contains(strtolower($event->participant_fee), 'free');

        // Optional proof-of-payment (paid participant events only). Manual flow —
        // we record the member's proof on the PRIVATE disk and leave paid=false so
        // the club admin still has to approve it. Registration succeeds either way
        // (the member may pay at the venue instead).
        $existing = ClubEventRegistration::where('event_id', $event->id)->where('user_id', $me->id)->first();
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

        $note = $division
            ? ' · '.$division
            : ($isTkd ? ' · you’ll be weighed in at the event to set your division' : '');

        return response()->json([
            'success' => true,
            'message' => ($storedNewProof
                ? 'Spot reserved · payment sent for review'
                : ($paidFee
                    ? 'Spot reserved · '.$event->participant_fee.' — confirm payment at the club'
                    : "You're in! See you there 🎉")).$note,
            'role' => 'participant',
            'division' => $division,
            'pending_payment' => $paidFee && (bool) $proofPath,   // proof recorded, awaiting approval
            'going' => $event->participantRegistrations()->count(),
        ]);
    }

    /**
     * Classify the member by gender/age/weight and return the matching division
     * — but ONLY one the creator actually defined for this event. Never invents a
     * division. Returns null if the member can't be classified (missing
     * gender/birthdate, weight outside any class) or their weight category isn't
     * one this championship is running.
     */
    private function routeToTaekwondoDivision(ClubEvent $event, User $me, float $weight): ?EventCategory
    {
        if (! $me->gender || ! $me->birthdate) {
            return null;
        }

        $age = Carbon::parse($me->birthdate)->age;
        $cls = classifyTaekwondo($me->gender, $age, $weight);
        if (! $cls) {
            return null;
        }

        $genderWord = strtolower($me->gender) === 'female' ? 'Women' : 'Men';
        $name = $cls['age_group'].' '.$genderWord.' '.$cls['category'].' kg'; // "Senior Men -58 kg"

        // Competitors can only enter a weight category the creator set up.
        return EventCategory::where('event_id', $event->id)->where('name', $name)->first();
    }

    /** Manager: (re)generate the draw for every division + renumber. */
    public function generateDraw(ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        $this->assertCanManage($event, $me);

        // Once the event has started the draw is final — no regeneration.
        if ($event->hasStarted()) {
            return response()->json([
                'success' => false,
                'message' => 'The event has started — the draw is final and can’t be regenerated.',
            ], 422);
        }

        $paidOnly = false; // pre-start only → provisional (imaginary) draw
        foreach ($event->categories()->get() as $cat) {
            if ((clone $cat->registrations()->where('role', 'participant'))->count() >= 1) {
                $this->draws->build($event, $cat, paidOnly: $paidOnly);
            }
        }
        $plan = $this->scheduler->scheduleAndNumber($event);

        $courtsLine = collect($plan)->map(fn ($p, $day) => 'Day '.$day.': '.$p['courts'].' '.\Illuminate\Support\Str::plural('mat', $p['courts']))->implode(' · ');

        return response()->json([
            'success' => true,
            'message' => ($paidOnly ? 'Final draw generated 🥋' : 'Provisional draw generated').($courtsLine ? ' · '.$courtsLine : ''),
            'redirect' => route('me.events.bracket', $event->uuid),
        ]);
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

        // Pre-start: rebuild the affected division's provisional draw so the bracket reflects the removal.
        if ($catId && ! $event->hasStarted() && $cat = EventCategory::find($catId)) {
            if ($cat->registrations()->where('role', 'participant')->count() >= 2) {
                $this->draws->build($event, $cat, paidOnly: false);
            } else {
                $cat->matches()->delete();
                $cat->update(['draw_state' => null, 'draw_count' => 0]);
            }
            $this->scheduler->scheduleAndNumber($event->fresh());
        }

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

    private function upcomingEventsQuery(User $me)
    {
        $clubIds = $me->memberClubs()->pluck('tenants.id');

        return ClubEvent::query()
            ->whereIn('tenant_id', $clubIds)
            ->where('is_archived', false)
            ->where('status', '!=', 'cancelled')
            ->withCount(['participantRegistrations'])
            ->with('tenant:id,club_name,country')
            ->orderBy('date');
    }

    private function myRegistrations(int $meId, $eventIds)
    {
        return ClubEventRegistration::where('user_id', $meId)
            ->whereIn('event_id', $eventIds)
            ->get()->keyBy('event_id');
    }

    private function eventView(ClubEvent $e, int $meId, $myReg, bool $full = false): array
    {
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
            $prows = $this->participantRows($e);
            $participants = array_slice($prows, 0, 12);
            $participantsTotal = count($prows);
            if ($e->spectator_enabled) {
                $srows = $this->spectatorRows($e);
                $spectatorRows = array_slice($srows, 0, 12);
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
            'league' => $this->leagueView($e->league),
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
            // Combat events derive their timeline from the real schedule; manual agenda is dropped.
            'phases' => ($full && $this->isCombatSport($e->sport) && $e->categories()->exists())
                ? $this->results->timeline($e)
                : ($e->phases ?: []),
            'agenda' => $this->isCombatSport($e->sport) ? [] : ($e->agenda ?: []),
            // Combat: medalists computed from the brackets (gold/silver/2×bronze per class).
            'bracket_results' => ($full && $this->isCombatSport($e->sport)) ? $this->results->podium($e) : [],
            'divisions' => $e->categories()->orderBy('sort_order')->pluck('name')->all(),
            'participants' => $participants,
            'participants_total' => $participantsTotal,
            'spectators_list' => $spectatorRows,
            'spectators_total' => $spectatorsTotal,
            'bans_list' => $full ? $this->bansList($e) : [],
            'joined' => $reg && $reg->role === 'participant',
            // The member's OWN proof-of-payment is awaiting the club's approval.
            'payment_pending' => $reg && $reg->role === 'participant' && ! $reg->paid && (bool) $reg->payment_proof,
            'watching' => $reg && $reg->role === 'spectator',
            'started' => $e->hasStarted(),
            'ended' => $e->hasEnded(),
            'categories' => $e->categories()->exists() ? ['_' => true] : [],
        ];

        return $view;
    }

    private function participantRows(ClubEvent $e): array
    {
        $isTkd = $e->sport === 'taekwondo';

        $rows = $e->participantRegistrations()
            ->with([
                'user:id,full_name,name,gender,birthdate',
                'user.latestHealthRecord',
                'category:id,name,weight_class',
            ])
            ->latest('registered_at')->get()
            ->map(function ($r) use ($isTkd) {
                $u = $r->user;
                $gender = $u?->gender ?: null;
                $kg = $r->weight ?: $u?->latestHealthRecord?->weight;   // declared/official weight wins

                if ($isTkd) {
                    // Show the member's REGISTERED weight division. Registration guarantees it's
                    // one the creator actually set up AND that it matches the member's own
                    // gender/age/weight — so nobody appears under a category this championship
                    // isn't running, and a male can never show under a women's class. Tokens are
                    // read from the division name ("<Age> <Men|Women> <label> kg"), never from a
                    // live re-classify.
                    $category = null;
                    $weight = null;
                    if ($cat = $r->category) {
                        $parts = preg_split('/\s+(?:Men|Women)\s+/', $cat->name, 2);
                        $category = $parts[0] ?? $cat->name;                  // age group, e.g. "Senior"
                        $weight = $parts[1] ?? ($cat->weight_class ?: null); // weight label, e.g. "-58 kg"
                    }
                    $meta = $gender ?: ($category ? 'Registered' : 'Unclassified');
                } else {
                    $category = $r->category?->name ?: null;
                    $weight = $r->category?->weight_class ?: null;
                    $meta = $r->meta ?: ($r->category?->name ?? ($r->paid ? 'Registered' : 'Pending payment'));
                }

                return [
                    'id' => $u?->id,
                    'name' => $u?->full_name ?? $u?->name ?? 'Member',
                    'gender' => $gender,
                    'category' => $category,
                    'weight_class' => $weight,
                    'meta' => $meta,
                    'paid' => (bool) $r->paid,
                    'has_weight' => $kg !== null,
                ];
            });

        // Taekwondo: show only entrants who have weight info (more likely to compete).
        if ($isTkd) {
            $rows = $rows->filter(fn ($x) => $x['has_weight']);
        }

        return $rows->values()->all();
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

    /** First numeric value in a fee string ("BHD 10" → 10.0). */
    private function feeAmount(?string $fee): float
    {
        return ($fee && preg_match('/[\d.]+/', $fee, $m)) ? (float) $m[0] : 0.0;
    }

    /** Event P&L for the owner: revenue from paid registrations − expenses. */
    private function computeFinance(ClubEvent $event): array
    {
        $pFee = $this->feeAmount($event->participant_fee);
        $sFee = $event->spectator_enabled ? $this->feeAmount($event->spectator_fee) : 0.0;
        $paidP = $event->registrations()->where('role', 'participant')->where('paid', true)->count();
        $paidS = $event->registrations()->where('role', 'spectator')->where('paid', true)->count();
        $pRev = $paidP * $pFee;
        $sRev = $paidS * $sFee;
        $revenue = $pRev + $sRev;

        $expenses = $event->expenses()->latest('id')->get(['id', 'label', 'amount'])
            ->map(fn ($x) => ['id' => $x->id, 'label' => $x->label, 'amount' => (float) $x->amount])->all();
        $expTotal = array_sum(array_column($expenses, 'amount'));

        return [
            'currency' => $event->tenant?->currency ?: 'BHD',
            'participant_fee' => $pFee,
            'paid_participants' => $paidP,
            'participant_revenue' => $pRev,
            'spectator_enabled' => (bool) $event->spectator_enabled,
            'spectator_fee' => $sFee,
            'paid_spectators' => $paidS,
            'spectator_revenue' => $sRev,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'expenses_total' => $expTotal,
            'profit' => $revenue - $expTotal,
        ];
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

    /** Build the per-category bracket view-models for the brackets page. */
    private function categoryViews(ClubEvent $e, int $meId): array
    {
        $cats = $e->categories()->with(['matches', 'registrations.user:id,full_name,name'])->get();

        $out = [];
        foreach ($cats as $c) {
            $joined = $c->registrations->count();

            // Group matches into rounds, preserving slot order; + a flat list for the editor.
            $rounds = [];
            $flat = [];
            foreach ($c->matches as $m) {
                $mday = $this->scheduler->phaseDay($c, $m->phase ?: 'preliminary');
                $mdate = $e->date ? $e->date->copy()->addDays(max(0, $mday - 1))->format('D, M j') : '';
                // Match code = court no. + bout no. (e.g. Mat 1, bout 4 → "1-04"). Unique per day.
                $courtNo = ($m->court && preg_match('/(\d+)/', $m->court, $cm)) ? (int) $cm[1] : null;
                $mcode = ($courtNo && $m->match_no) ? ($courtNo.'-'.str_pad((string) $m->match_no, 2, '0', STR_PAD_LEFT)) : null;
                $rounds[$m->round] ??= ['name' => $m->round, 'matches' => []];
                $rounds[$m->round]['matches'][] = [
                    'no' => $m->match_no, 'phase' => $m->phase, 'date' => $mdate, 'code' => $mcode,
                    'court' => $m->court ?? '', 'time' => $m->scheduled_time ?? '', 'status' => $m->status, 'winner' => $m->winner,
                    'a' => ['name' => $m->a_name, 'country' => $m->a_country, 'seed' => $m->a_seed, 'score' => $m->a_score ?? '–', 'provisional' => (bool) $m->a_provisional],
                    'b' => ['name' => $m->b_name, 'country' => $m->b_country, 'seed' => $m->b_seed, 'score' => $m->b_score ?? '–', 'provisional' => (bool) $m->b_provisional],
                ];
                $flat[] = [
                    'round' => $m->round, 'court' => $m->court ?? '', 'time' => $m->scheduled_time ?? '', 'status' => $m->status, 'winner' => $m->winner ?? '',
                    'a_name' => $m->a_name ?? '', 'a_seed' => $m->a_seed, 'a_score' => $m->a_score ?? '',
                    'b_name' => $m->b_name ?? '', 'b_seed' => $m->b_seed, 'b_score' => $m->b_score ?? '',
                ];
            }

            $out[$c->id] = [
                'key' => 'c'.$c->id,
                'id' => $c->id,
                'name' => $c->name,
                'class' => $c->weight_class ?? '',
                'cap' => $c->capacity,                                  // null = no cap
                'joined' => $joined,
                'open' => $c->capacity ? max(0, $c->capacity - $joined) : null,
                'status' => $c->status,
                'draw_state' => $c->draw_state,
                'provisional' => $c->draw_state === 'provisional',
                // At risk of removal at start: unpaid OR not weighed in (no recorded weight).
                'unpaid_count' => $c->registrations->where('role', 'participant')
                    ->filter(fn ($r) => ! $r->paid || $r->weight === null)->count(),
                'note' => $c->note ?? '',
                'rounds' => array_values($rounds),
                'matches_flat' => $flat,
                'podium' => $c->podium ?? [],
                'roster' => $c->registrations->map(fn ($r) => [
                    'name' => $r->user?->full_name ?? $r->user?->name ?? 'Athlete',
                    'country' => $r->meta ?: '',
                ])->all(),
                'roster_names' => $c->registrations->map(fn ($r) => $r->user?->full_name ?? $r->user?->name ?? 'Athlete')->values()->all(),
                'mine' => $c->registrations->contains('user_id', $meId),
            ];
        }

        return $out;
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
                'a_seed' => $m['a_seed'] ?? null,
                'a_score' => trim((string) ($m['a_score'] ?? '')) ?: null,
                'b_name' => trim((string) ($m['b_name'] ?? '')) ?: null,
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
    private function isEligible(ClubEvent $event, User $me): bool
    {
        if ($me->memberClubs()->whereKey($event->tenant_id)->exists()) {
            return true;
        }

        return match ($event->scope ?? 'internal') {
            'inter_club', 'worldwide' => true,
            // regional currently mirrors nationwide until a region taxonomy exists.
            'nationwide', 'regional' => $this->shareCountry($event, $me),
            default => false, // internal
        };
    }

    /** True when the member belongs to a club in the host club's country. */
    private function shareCountry(ClubEvent $event, User $me): bool
    {
        $hostCountry = $event->tenant?->country ?? $event->tenant()->value('country');

        return $hostCountry
            && $me->memberClubs()->where('tenants.country', $hostCountry)->exists();
    }

    private function assertEligible(ClubEvent $event, User $me): void
    {
        abort_unless($this->isEligible($event, $me) || $this->canManage($event, $me), 403);
    }

    /** Validation rules for the repeatable detail sections (schedule, etc.). */
    private function detailRules(): array
    {
        return [
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_long' => ['nullable', 'numeric', 'between:-180,180'],
            'location_url' => ['nullable', 'url:http,https', 'max:500'],
            'break_start' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'break_end' => ['nullable', 'date_format:H:i', 'after:break_start', 'before_or_equal:end_time'],
            'courts' => ['nullable', 'integer', 'min:1', 'max:50'],

            'agenda' => ['nullable', 'array', 'max:40'],
            'agenda.*.t' => ['nullable', 'date'],   // date+time picker
            'agenda.*.d' => ['nullable', 'string', 'max:200'],
            'requirements' => ['nullable', 'array', 'max:30'],
            'requirements.*' => ['nullable', 'string', 'max:200'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['nullable', 'string', 'max:40'],
            'phases' => ['nullable', 'array', 'max:20'],
            'phases.*.label' => ['nullable', 'string', 'max:60'],
            'phases.*.date' => ['nullable', 'date'],   // real date; status is derived, not stored
            'phases.*.note' => ['nullable', 'string', 'max:160'],

            // sport-aware sections
            'sport' => ['nullable', Rule::in(array_keys($this->sports()))],
            'divisions' => ['nullable', 'array', 'max:64'],
            'divisions.*.name' => ['nullable', 'string', 'max:80'],
            'divisions.*.capacity' => ['nullable', 'integer', 'min:2', 'max:512'],
            'divisions.*.schedule' => ['nullable', 'array'],
            'divisions.*.schedule.preliminary' => ['nullable', 'integer', 'min:1', 'max:60'],
            'divisions.*.schedule.quarterfinals' => ['nullable', 'integer', 'min:1', 'max:60'],
            'divisions.*.schedule.finals' => ['nullable', 'integer', 'min:1', 'max:60'],
            'league' => ['nullable', 'array'],
            'league.teams' => ['nullable', 'array', 'max:64'],
            'league.teams.*' => ['nullable', 'string', 'max:80'],
            'league.fixtures' => ['nullable', 'array', 'max:300'],
            'league.fixtures.*.home' => ['nullable', 'string', 'max:80'],
            'league.fixtures.*.away' => ['nullable', 'string', 'max:80'],
            'league.fixtures.*.date' => ['nullable', 'string', 'max:40'],
            'league.fixtures.*.home_score' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'league.fixtures.*.away_score' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /** Normalise the detail sections into the columns ClubEvent stores. */
    private function extractDetails(array $data): array
    {
        $agenda = collect($data['agenda'] ?? [])
            ->map(fn ($r) => [
                't' => ! empty($r['t']) ? Carbon::parse($r['t'])->format('Y-m-d H:i') : '',
                'd' => trim((string) ($r['d'] ?? '')),
            ])
            ->filter(fn ($r) => $r['t'] !== '' || $r['d'] !== '')
            ->sortBy('t')->values()->all();   // keep the run-of-show in chronological order

        $requirements = collect($data['requirements'] ?? [])
            ->map(fn ($r) => trim((string) $r))->filter()->values()->all();

        $tags = collect($data['tags'] ?? [])
            ->map(fn ($t) => ltrim(trim((string) $t), '#'))->filter()->values()->all();

        // Status is NOT stored — it's derived from the date at display time.
        $phases = collect($data['phases'] ?? [])
            ->map(fn ($p) => [
                'label' => trim((string) ($p['label'] ?? '')),
                'date' => ! empty($p['date']) ? Carbon::parse($p['date'])->toDateString() : null,
                'note' => trim((string) ($p['note'] ?? '')),
                'icon' => 'bi-flag',
            ])
            ->filter(fn ($p) => $p['label'] !== '')->values()->all();

        // League: teams + fixtures (with optional scores).
        $teams = collect($data['league']['teams'] ?? [])
            ->map(fn ($t) => trim((string) $t))->filter()->values()->all();
        $fixtures = collect($data['league']['fixtures'] ?? [])
            ->map(fn ($f) => [
                'home' => trim((string) ($f['home'] ?? '')),
                'away' => trim((string) ($f['away'] ?? '')),
                'date' => trim((string) ($f['date'] ?? '')),
                'home_score' => isset($f['home_score']) && $f['home_score'] !== '' ? (int) $f['home_score'] : null,
                'away_score' => isset($f['away_score']) && $f['away_score'] !== '' ? (int) $f['away_score'] : null,
            ])
            ->filter(fn ($f) => $f['home'] !== '' && $f['away'] !== '')->values()->all();
        $league = ($teams || $fixtures) ? ['teams' => $teams, 'fixtures' => $fixtures] : null;

        return [
            'agenda' => $agenda ?: null,
            'requirements' => $requirements ?: null,
            'tags' => $tags ?: null,
            'phases' => $phases ?: null,
            'sport' => $data['sport'] ?? null,
            'league' => $league,
            'courts' => isset($data['courts']) && $data['courts'] !== '' ? (int) $data['courts'] : null,
        ];
    }

    /** Build the league view-model: teams, fixtures, and a computed standings table. */
    private function leagueView(?array $league): ?array
    {
        if (! $league || (empty($league['teams']) && empty($league['fixtures']))) {
            return null;
        }

        $teams = $league['teams'] ?? [];
        $fixtures = $league['fixtures'] ?? [];

        // Seed the table with every named team.
        $tbl = [];
        $row = fn ($n) => ['team' => $n, 'p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0, 'gd' => 0, 'pts' => 0];
        foreach ($teams as $t) {
            $tbl[$t] = $row($t);
        }

        foreach ($fixtures as $f) {
            $h = $f['home'];
            $a = $f['away'];
            $tbl[$h] ??= $row($h);
            $tbl[$a] ??= $row($a);
            if ($f['home_score'] === null || $f['away_score'] === null) {
                continue; // unplayed fixture
            }
            $hs = (int) $f['home_score'];
            $as = (int) $f['away_score'];
            $tbl[$h]['p']++;
            $tbl[$a]['p']++;
            $tbl[$h]['gf'] += $hs;
            $tbl[$h]['ga'] += $as;
            $tbl[$a]['gf'] += $as;
            $tbl[$a]['ga'] += $hs;
            if ($hs > $as) {
                $tbl[$h]['w']++;
                $tbl[$h]['pts'] += 3;
                $tbl[$a]['l']++;
            } elseif ($hs < $as) {
                $tbl[$a]['w']++;
                $tbl[$a]['pts'] += 3;
                $tbl[$h]['l']++;
            } else {
                $tbl[$h]['d']++;
                $tbl[$a]['d']++;
                $tbl[$h]['pts']++;
                $tbl[$a]['pts']++;
            }
        }

        $standings = collect($tbl)->map(function ($r) {
            $r['gd'] = $r['gf'] - $r['ga'];

            return $r;
        })->sortBy([['pts', 'desc'], ['gd', 'desc'], ['gf', 'desc']])->values()->all();

        return ['teams' => $teams, 'fixtures' => $fixtures, 'standings' => $standings];
    }

    /** Create/keep the event's divisions as event_categories (non-destructive). */
    private function syncDivisions(ClubEvent $event, array $divisions): void
    {
        $names = [];
        foreach ($divisions as $i => $d) {
            $name = trim((string) ($d['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $names[] = $name;
            $cat = EventCategory::firstOrNew(['event_id' => $event->id, 'name' => $name]);
            $cap = $d['capacity'] ?? null;                       // optional — null = no cap
            $cat->capacity = ($cap === null || $cap === '') ? null : (int) $cap;
            $cat->sort_order = $i + 1;
            if (! $cat->exists) {
                $cat->status = 'enrolling';
            }
            // Owner-set day per phase (later phase can't precede an earlier one).
            if (! empty($d['schedule']) && is_array($d['schedule'])) {
                $pre = max(1, (int) ($d['schedule']['preliminary'] ?? 1));
                $qf = max($pre, (int) ($d['schedule']['quarterfinals'] ?? $pre));
                $fin = max($qf, (int) ($d['schedule']['finals'] ?? $qf));
                $cat->schedule = ['preliminary' => $pre, 'quarterfinals' => $qf, 'finals' => $fin];
            }
            $cat->save();
        }
        // Remove divisions the manager deleted — but only EMPTY ones (no entrants/matches).
        $event->categories()->whereNotIn('name', $names ?: [''])
            ->whereDoesntHave('registrations')->whereDoesntHave('matches')->delete();
    }

    /** Only the event's creator may manage it (super-admin kept as a platform override). */
    private function canManage(ClubEvent $event, User $me): bool
    {
        return $event->created_by === $me->id
            || $me->isSuperAdmin();
    }

    private function assertCanManage(ClubEvent $event, User $me): void
    {
        abort_unless($this->canManage($event, $me), 403);
    }

    /** Visible to anyone the event's scope reaches (host-club members + wider). */
    private function assertVisible(ClubEvent $event, User $me): void
    {
        abort_if($event->is_archived, 404);
        $this->assertEligible($event, $me);
    }
}
