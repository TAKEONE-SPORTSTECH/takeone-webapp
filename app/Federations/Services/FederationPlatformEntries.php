<?php

namespace App\Federations\Services;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventFeeOption;
use App\Events\Models\EventPublicEntry;
use App\Events\Support\EntryService;
use App\Events\Support\PublicEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Members\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The clubs' entries, arriving in the platform event (2026-09-28).
 *
 * A federation's competition takes its athletes from its CLUBS: a club enters
 * them through the invitation (FederationInvitation), the federation approves
 * them. On the platform event that is exactly the entry REQUEST the console
 * already knows (`event_public_entries`, reviewed under "Entry requests"), so
 * that is what each athlete becomes — never a second queue:
 *
 *   entered by a club            → ENTERED at once (the request is made and
 *                                  accepted in one go; since 2026-10-02 there
 *                                  is no approval step)
 *   withdrawn                    → the request declined, or the entry removed
 *
 * And back: an organiser who accepts or declines a request in the console is
 * recorded on the federation's side too (decided()), so the dashboard and the
 * club's own "Your entries" page tell the same story.
 *
 * Idempotent — `sync()` brings the whole event into step and may run after
 * every write.
 */
class FederationPlatformEntries
{
    /** While true, the console's decision hook is ours and must not echo back. */
    private static bool $syncing = false;

    public function __construct(private FederationPlatformEvent $events) {}

    public function sync(FederationEvent $fe): void
    {
        if (! $this->events->enabled() || ! ($ev = $this->events->of($fe))) {
            return;
        }
        $actor = $this->actor($ev, $fe);
        if (! $actor) {
            return;
        }

        self::$syncing = true;
        try {
            $rows = FederationEntryAthlete::whereHas('entry', fn ($q) => $q->where('federation_event_id', $fe->id))
                ->with(['entry.club', 'entry.application', 'athlete'])->get();

            foreach ($rows as $row) {
                rescue(fn () => $this->one($ev, $row, $actor), null, report: true);
            }

            // The officials each club names — its team manager and its two
            // referees — become the event's officials, for that club.
            foreach (\App\Federations\Models\FederationEntry::where('federation_event_id', $fe->id)->with(['club', 'application'])->get() as $entry) {
                rescue(fn () => $this->officials($ev, $entry, $actor), null, report: true);
            }
        } finally {
            self::$syncing = false;
        }
    }

    /**
     * Make sure ONE athlete the federation holds as Active is in the platform
     * event (the Participants tab's edit window, owner 2026-10-15). An athlete
     * whose request was decided long ago but who holds no entry any more — the
     * row went, the request stayed — is never re-entered by sync(), which only
     * acts on a pending request. Here the request is re-opened and accepted
     * through the platform's own door (PublicEntry::accept, via one()), so the
     * entry, its fee and its division are made exactly as for anybody else.
     */
    public function ensureEntered(FederationEvent $fe, FederationEntryAthlete $row): void
    {
        if (! $this->events->enabled() || ! ($ev = $this->events->of($fe)) || $row->status === 'Withdrawn') {
            return;
        }
        $actor = $this->actor($ev, $fe);
        $userId = $row->athlete?->user_id;
        if (! $actor || ! $userId) {
            return;
        }

        self::$syncing = true;
        try {
            $entered = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $userId)->where('role', 'participant')->exists();
            $request = EventPublicEntry::where('event_id', $ev->id)->where('user_id', $userId)->first();
            if (! $entered && $request && ! $request->isPending()) {
                $request->forceFill(['state' => 'pending', 'registration_id' => null])->save();
                Log::info('federation-entries: re-opened an entry with no registration', ['event' => $ev->uuid, 'user' => $userId]);
            }
            $this->one($ev, $row->fresh(['entry.club', 'entry.application', 'athlete']), $actor);
        } finally {
            self::$syncing = false;
        }
    }

    /** The console decided a request on a federation's event: record it on the federation's side. */
    public function decided(EventPublicEntry $entry): void
    {
        if (self::$syncing || ! $entry->event || $entry->event->host_type !== 'federation') {
            return;
        }
        $fe = FederationEvent::where('club_event_id', $entry->event_id)->first();
        if (! $fe) {
            return;
        }
        $row = FederationEntryAthlete::whereHas('entry', fn ($q) => $q->where('federation_event_id', $fe->id))
            ->whereHas('athlete', fn ($q) => $q->where('user_id', $entry->user_id))->first();
        if (! $row) {
            return;
        }

        if ($entry->state === 'accepted') {
            $row->forceFill(['approval' => 'Approved'])->save();
        } elseif ($entry->state === 'declined' && $row->status !== 'Withdrawn') {
            $row->forceFill(['status' => 'Withdrawn', 'withdrawn_at' => now()])->save();
        }
    }

    /**
     * The event's entrant list changed on the platform — an organiser removed
     * or re-entered somebody in the console, moved them to another division,
     * weighed them in. The federation's record follows, and every open
     * dashboard re-reads (a refresh signal, never data).
     *
     * Removed: once they hold no participant entry in the event any more, the
     * club's row is marked Withdrawn — otherwise the dashboard keeps counting
     * an athlete the event no longer has. Re-entered: a Withdrawn row is made
     * Active again — otherwise the next sync() would take them back out.
     * Skipped while sync() itself is the one writing, so neither echoes back.
     */
    public function entrantChanged(ClubEvent $ev, ClubEventRegistration $registration, string $change): void
    {
        $fe = FederationEvent::where('club_event_id', $ev->id)->first();
        if (! $fe) {
            return;
        }

        if (! self::$syncing && $registration->role === 'participant' && in_array($change, ['created', 'deleted'], true)) {
            $rows = FederationEntryAthlete::whereHas('entry', fn ($q) => $q->where('federation_event_id', $fe->id))
                ->whereHas('athlete', fn ($q) => $q->where('user_id', $registration->user_id));

            if ($change === 'deleted') {
                $stillIn = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $registration->user_id)
                    ->where('role', 'participant')->exists();
                if (! $stillIn) {
                    $rows->where('status', '!=', 'Withdrawn')->get()
                        ->each(fn ($row) => $row->forceFill(['status' => 'Withdrawn', 'withdrawn_at' => now()])->save());
                }
            } else {
                $rows->where('status', 'Withdrawn')->get()
                    ->each(fn ($row) => $row->forceFill(['status' => 'Active', 'withdrawn_at' => null])->save());
            }
        }

        \App\Federations\Support\FederationPulse::forEvent($fe);
    }

    private function one(ClubEvent $ev, FederationEntryAthlete $row, User $actor): void
    {
        $user = $row->athlete?->user_id ? User::find($row->athlete->user_id) : null;
        if (! $user) {
            return;
        }
        $request = EventPublicEntry::where('event_id', $ev->id)->where('user_id', $user->id)->first();
        $registration = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')->first();

        if ($row->status === 'Withdrawn') {
            if ($request && $request->isPending()) {
                app(PublicEntry::class)->decline($request, $actor);
            } elseif ($registration) {
                // Every entry they hold — one per type (Kumite, Kata).
                ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')->get()
                    ->each(fn ($r) => app(EntryService::class)->remove($ev, $actor, $r, notify: false));
            }

            return;
        }

        // What they are entered in, as the event's own fee options — matched
        // by the competition type's name, which is how the options were made.
        $types = array_values((array) (is_string($row->types) ? json_decode($row->types, true) : $row->types));
        $options = EventFeeOption::where('event_id', $ev->id)->where('role', 'participant')->where('is_active', true)
            ->whereIn('label', $types)->pluck('uuid')->all();
        $club = $row->entry?->club;
        $tenantId = $club?->tenant_id;

        $values = [
            'birthdate' => $user->birthdate,
            'gender' => $user->gender,
            'weight' => $row->weight,
            'fee_options' => $options,
            'representing_tenant_id' => $tenantId,
            'club_name' => $tenantId ? null : ($row->entry?->application?->name ?? $club?->tenant?->club_name),
        ];

        if (! $request && ! $registration) {
            $request = new EventPublicEntry;
            $request->forceFill($values + [
                'uuid' => (string) Str::uuid(),
                'event_id' => $ev->id,
                'user_id' => $user->id,
                'state' => 'pending',
            ])->save();
        } elseif ($request && $request->isPending()) {
            $request->forceFill($values)->save();
        }

        // A club's entry IS the entry (owner's decision, 2026-10-02): an athlete
        // a club enters is in straight away, with no approval step. The
        // federation's own row is marked Approved to match, so the dashboard
        // and the club's "Your entries" tell the same story. Withdrawing (on
        // either side) is still how somebody is taken off.
        if ($request && $request->isPending()) {
            $r = app(PublicEntry::class)->accept($request->fresh(), $actor);
            if (! ($r['ok'] ?? false)) {
                Log::info('federation-entries: could not accept', ['event' => $ev->uuid, 'user' => $user->id, 'why' => $r['message'] ?? null]);
            }
        }
        if ($row->approval !== 'Approved'
            && ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')->exists()) {
            $row->forceFill(['approval' => 'Approved'])->save();
        }

        // The CLUB entered them, so the club owes their fee (PLAN.md Phase 7):
        // its platform club when it has one, the federation's own club record
        // otherwise. Only the payer — the entry stays on the federation's own
        // lifecycle (withdrawals go through the dashboard), so its channel is
        // left as it is rather than handing coaches platform-side squad controls.
        ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')
            ->update($tenantId
                ? ['payer_type' => 'club', 'payer_id' => $tenantId]
                : ($club ? ['payer_type' => 'federation_club', 'payer_id' => $club->id] : []) + ['updated_at' => now()]);

        // Entered while the club was still an application, the entry names no
        // club; now that the club IS a platform club, it does (crest + flag on
        // every screen). Only fills a blank. Behind duplicates.guard.federation_links.
        if ($tenantId && config('duplicates.guard.federation_links')) {
            ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')
                ->whereNull('representing_tenant_id')
                ->update(['representing_tenant_id' => $tenantId]);
        }

        // A federation sorts its athletes by the rule book itself: an entered
        // athlete with no division is placed, the division created if needed.
        $registration ??= ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')->first();
        // Once weighed, the scale's reading is the weight — never the declared one.
        $scale = \App\Events\Support\BodyWeight::weighed($ev->id, $user->id);
        if ($scale !== null && \App\Events\Support\BodyWeight::spread($ev->id, $user->id) && $registration) {
            $registration->refresh();
        }
        // Once the event has started (or is over) the draw is the record of what
        // happened: this sync runs on every dashboard save — editing somebody's
        // nationality included — and must never place a stray entry or create
        // a division then. On 2026-10-26 it did, on a finished championship.
        $settled = $ev->hasStarted() || $ev->hasEnded();
        if ($settled) {
            // nothing: divisions are fixed
        } elseif ($registration && \App\Events\Support\EventStreams::many($ev)) {
            $this->streams($ev, $user, $types, $scale ?? (float) $row->weight, $actor);
        } elseif ($registration && ! $registration->category_id) {
            app(\App\Events\Support\CategorySystems::class)->placeCreating($ev, $registration);
        }

        // An entered athlete's weight follows the dashboard until they are weighed
        // — on ANY of their entries: after that the scale's reading is on them all.
        if ($scale === null && $registration && $row->weight) {
            ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')
                ->whereNull('weighed_in_at')->get()
                ->each(fn ($r) => (float) $r->weight !== (float) $row->weight ? $r->forceFill(['weight' => $row->weight])->save() : null);
        }

        // A club that is not on the platform yet (a federation's club
        // applicant) is written down as one of the EVENT's clubs, exactly as an
        // organiser writes one down at the desk (EventClub, read through
        // EntryClub), so it is listed beside its athletes on every surface.
        $applicant = trim((string) ($row->entry?->application?->name ?? ''));
        if (! $tenantId && $applicant !== '' && $registration) {
            $this->eventClub($ev, $user, $applicant, $actor, $row->entry?->application?->logo);
        }
    }

    /** The event's own club of this name, created once, holding every entry this athlete has. */
    private function eventClub(ClubEvent $ev, User $user, string $name, User $actor, ?string $logo = null): void
    {
        $club = $this->eventClubNamed($ev, $name, $actor, $logo);

        $ids = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)
            ->where('role', 'participant')->pluck('id')->all();
        $club->entrants()->syncWithoutDetaching(array_fill_keys($ids, ['added_by' => $actor->id]));
    }

    /** The event's own club of this name (one per name), its crest filled in from the application. */
    private function eventClubNamed(ClubEvent $ev, string $name, User $actor, ?string $logo = null): \App\Events\Models\EventClub
    {
        $name = mb_substr($name, 0, 120);
        $club = \App\Events\Models\EventClub::where('event_id', $ev->id)->whereNull('tenant_id')
            ->get()->first(fn ($c) => mb_strtolower($c->name) === mb_strtolower($name));
        if (! $club) {
            $club = \App\Events\Models\EventClub::create([
                'event_id' => $ev->id,
                'name' => $name,
                'state' => 'listed',
                'created_by' => $actor->id,
            ]);
        }

        // The crest the club uploaded through the invitation. COPIED into the
        // event's own clubs folder, because that is where a written-down club's
        // crest is served from to everyone (FileAccess::isEventClubCrest), and
        // the application's file moves away when the club is approved. Only
        // ever fills a blank: a crest the organiser set by hand is theirs.
        if (! $club->logo && $logo) {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            $ext = strtolower(pathinfo($logo, PATHINFO_EXTENSION));
            if ($disk->exists($logo) && in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
                $target = \App\Support\StoragePath::event($ev, 'clubs').'/'.Str::uuid().'.'.$ext;
                if (rescue(fn () => $disk->copy($logo, $target), false, report: true)) {
                    $club->forceFill(['logo' => $target])->save();
                }
            }
        }

        return $club;
    }

    /**
     * An athlete entered in several individual types — Kumite AND Kata —
     * holds one entry per type, each in that stream's own division (drawn
     * apart, signed off apart). The first entry the acceptance made is used
     * for the first type still unplaced; the rest are entered through
     * EntryService, every rule of entry applying. Team types are the club's
     * entry on the federation's side and are not persons here.
     */
    private function streams(ClubEvent $ev, User $user, array $types, float $weight, User $actor): void
    {
        $systems = app(\App\Events\Support\CategorySystems::class);
        $declared = collect(\App\Events\Support\EventStreams::of($ev))->keyBy('key');

        foreach ($types as $type) {
            $type = (string) $type;
            $key = \App\Events\Support\EventStreams::key($type);
            if (FederationPlatformEvent::isTeam($type) || ! $declared->has($key)) {
                continue;
            }
            $regs = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')->with('category')->get();
            if ($regs->contains(fn ($r) => $r->category && \App\Events\Support\EventStreams::ofDivision($r->category) === $key)) {
                continue;
            }
            $division = $systems->streamDivision($ev, $user->fresh(), $weight ?: null, $key, $declared[$key]['label'], FederationPlatformEvent::weighed($type));
            if (! $division) {
                continue;
            }
            if ($free = $regs->first(fn ($r) => ! $r->category_id)) {
                $free->forceFill(['category_id' => $division->id, 'category_source' => ClubEventRegistration::PLACED_AUTO])->save();
            } else {
                $r = app(EntryService::class)->enter($ev, $actor, $user, [], $division->id);
                if (! ($r['ok'] ?? false)) {
                    Log::info('federation-entries: second type not entered', ['event' => $ev->uuid, 'user' => $user->id, 'type' => $type]);
                }
            }
        }
    }

    /**
     * A club's team manager and referees, as the event's officials.
     *
     * They are named, not signed up: a club types a referee's name. So each
     * becomes an unclaimed person (the invitation's own way with a new
     * athlete — no email, no password, not discoverable), recorded as an
     * official with the role 'other' and their title as its label. 'other'
     * grants NONE of the event's powers (EventOfficial::ROLE_OTHER), so being
     * named by a club lets nobody arrange a draw. The same name for the same
     * club and title is the same person on the next sync, never a second one;
     * a slot the club emptied is taken off.
     */
    private function officials(ClubEvent $ev, \App\Federations\Models\FederationEntry $entry, User $actor): void
    {
        $slots = ['mgr' => __('federations::invite.ui.manager'), 'ref1' => __('federations::invite.ui.referee1'), 'ref2' => __('federations::invite.ui.referee2')];
        $named = (array) (is_string($entry->officials) ? json_decode($entry->officials, true) : $entry->officials);
        $tenantId = $entry->club?->tenant_id;
        // The club is a FIELD on the appointment, never part of the job's
        // wording (2026-10-02): a platform club through representing_tenant_id,
        // a club that is not on the platform through its event club. The
        // officials sheet then groups by the job ("Team manager", "Referee 1")
        // instead of drawing one group per club per job.
        // The application's name — still known once the club is approved, through
        // the application that became it.
        $applicant = trim((string) ($entry->application?->name
            ?? ($entry->federation_club_id ? \App\Federations\Models\FederationClubApplication::where('federation_club_id', $entry->federation_club_id)->value('name') : null)
            ?? ''));
        $evClub = (! $tenantId && $applicant !== '') ? $this->eventClubNamed($ev, $applicant, $actor, $entry->application?->logo) : null;
        // What this sync used to write, so a row made before is found and moved over.
        $legacyClub = $entry->club?->tenant?->club_name ?? $entry->application?->name ?? '';

        /*
         * Act on what the CLUB changed, never on what the federation did since
         * (2026-10-15). This used to compare names on every run: a manager who
         * renamed an official or took one off had it undone by the next sync,
         * which created a NEW person under the old name and swapped them in.
         * `officials_synced` remembers the name last applied per slot; a slot
         * whose form still says that is left exactly as it stands.
         */
        $applied = is_array($entry->officials_synced) ? $entry->officials_synced : [];
        $appliedBefore = $applied;

        foreach ($slots as $slot => $title) {
            $label = mb_substr(trim($title), 0, 120);
            $legacy = mb_substr(trim($title.($legacyClub !== '' ? ' · '.$legacyClub : '')), 0, 120);
            $name = trim(strip_tags((string) ($named[$slot]['name'] ?? '')));
            // "Nil", "None", "-" in the box means the club has nobody for this slot.
            if (\App\Federations\Support\NobodyName::is($name)) {
                $name = '';
            }
            if (array_key_exists($slot, $applied) && (string) $applied[$slot] === $name) {
                continue;
            }
            $applied[$slot] = $name;
            $existing = \App\Events\Models\EventOfficial::where('event_id', $ev->id)
                ->where('role', \App\Events\Models\EventOfficial::ROLE_OTHER)
                ->whereIn('role_label', array_unique([$label, $legacy]))
                // Oldest first: the slot's own official is the first one made
                // for it. A club can carry EXTRA officials under the same job
                // (two clubs merged, 2026-10-02), and those must never be taken
                // for the slot and overwritten.
                ->orderBy('id')
                ->get()
                ->first(fn ($o) => $tenantId
                    // A club approved AFTER its officials arrived: they were
                    // filed under the event's own club of the application's
                    // name, and are the same people, not new ones.
                    ? ((int) $o->representing_tenant_id === (int) $tenantId
                        || ($o->representing_tenant_id === null && $o->event_club_id && $applicant !== ''
                            && mb_strtolower((string) \App\Events\Models\EventClub::whereKey($o->event_club_id)->value('name')) === mb_strtolower($applicant)))
                    : ($evClub && ((int) $o->event_club_id === (int) $evClub->id
                        || ($o->event_club_id === null && $o->representing_tenant_id === null && $o->role_label === $legacy && $legacy !== $label))));
            if ($existing && ($existing->role_label !== $label || (int) $existing->event_club_id !== (int) ($evClub?->id ?? 0))) {
                $existing->forceFill(['role_label' => $label, 'event_club_id' => $evClub?->id, 'representing_tenant_id' => $tenantId])->saveQuietly();
            }

            if ($name === '') {
                if ($existing) {
                    // The photo is the PERSON's (ProfilePhoto), not this
                    // appointment's: unhook it so the row's file hook leaves it.
                    $existing->forceFill(['photo' => null])->saveQuietly();
                    $existing->delete();
                }

                continue;
            }
            if ($existing && trim((string) ($existing->user?->full_name ?? '')) === $name) {
                $this->photo($existing, $named[$slot]['photo'] ?? null);

                continue;
            }

            $user = $this->knownOfficial($ev, $tenantId, $name) ?? User::create([
                'full_name' => mb_substr($name, 0, 150), 'name' => mb_substr($name, 0, 150),
                'email' => null, 'password' => null, 'is_unclaimed' => true, 'is_discoverable' => false,
            ]);
            if ($existing) {
                $existing->forceFill(['user_id' => $user->id, 'photo' => null])->save();
                $o = $existing;
            } else {
                $o = new \App\Events\Models\EventOfficial;
                $o->forceFill([
                    'event_id' => $ev->id, 'user_id' => $user->id,
                    'role' => \App\Events\Models\EventOfficial::ROLE_OTHER, 'role_label' => $label,
                    'assigned_by' => $actor->id, 'compensation' => \App\Events\Models\EventOfficial::COMP_VOLUNTEER,
                    'representing_tenant_id' => $tenantId,
                    'event_club_id' => $evClub?->id,
                ])->save();
            }
            $this->photo($o->fresh('user'), $named[$slot]['photo'] ?? null);
        }

        if ($applied !== $appliedBefore) {
            $this->rememberApplied($entry, $applied);
        }
    }

    /**
     * The person this club already named under this name at another of its
     * events — the same referee, not a new one per competition. Only the
     * club's OWN earlier officials are looked at (its own data; nothing of
     * another club's is consulted), only for a platform club, and never
     * somebody who already holds an official's role at this event (one
     * person, one role here). Behind duplicates.guard.officials.
     */
    private function knownOfficial(ClubEvent $ev, ?int $tenantId, string $name): ?User
    {
        if (! $tenantId || ! config('duplicates.guard.officials')) {
            return null;
        }
        $people = app(\App\Events\Support\KnownPerson::class);
        $key = $people->normalised($name);
        if ($key === '') {
            return null;
        }
        $here = \App\Events\Models\EventOfficial::where('event_id', $ev->id)->pluck('user_id')->flip();

        return \App\Events\Models\EventOfficial::with('user')
            ->where('representing_tenant_id', $tenantId)->where('event_id', '!=', $ev->id)
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn ($o) => $o->user)
            ->first(fn ($u) => $u && ! isset($here[$u->id]) && $people->normalised((string) ($u->full_name ?: $u->name)) === $key);
    }

    /** Remember what was applied from this entry's named officials (see officials()). */
    private function rememberApplied(\App\Federations\Models\FederationEntry $entry, array $applied): void
    {
        \Illuminate\Support\Facades\DB::table('federation_entries')->where('id', $entry->id)
            ->update(['officials_synced' => json_encode($applied, JSON_UNESCAPED_UNICODE)]);
        $entry->setAttribute('officials_synced', $applied);
    }

    /**
     * The photo the club gave for its official, as that official's face.
     *
     * The club's copy stays where the invitation keeps it; the official is a
     * person here, so their picture goes through ProfilePhoto into their own
     * store (real bytes checked, name generated) and the appointment holds a
     * reference. Only ever fills a blank — a face already on file is kept.
     */
    private function photo(\App\Events\Models\EventOfficial $official, mixed $source): void
    {
        $user = $official->user;
        if (! $user || ! is_string($source) || $source === '' || str_contains($source, '..')
            || ! str_starts_with($source, 'federations/')) {
            return;
        }
        if (trim((string) $official->photo) !== '') {
            return;
        }
        $path = trim((string) $user->profile_picture);
        if ($path === '') {
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            if (! $disk->exists($source) || $disk->size($source) > 8 * 1024 * 1024) {
                return;
            }
            $bytes = $disk->get($source);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
            $path = (string) \App\Members\Support\ProfilePhoto::store($user, 'data:'.$mime.';base64,'.base64_encode($bytes));
        }
        if ($path !== '') {
            $official->forceFill(['photo' => $path])->saveQuietly();
        }
    }

    /** Who acts for the federation on its event: whoever created it, else the federation's owner. */
    private function actor(ClubEvent $ev, FederationEvent $fe): ?User
    {
        $candidates = array_filter([$ev->created_by, $fe->created_by, $fe->federation?->owner_user_id]);
        foreach ($candidates as $id) {
            if ($u = User::find($id)) {
                return $u;
            }
        }

        return null;
    }
}
