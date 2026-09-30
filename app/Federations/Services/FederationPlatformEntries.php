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
 *   entered, awaiting approval   → a pending request (weight, belt, fees, club)
 *   approved (dashboard)         → the request accepted: they are ENTERED
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

        // Approved on the dashboard: they are in.
        if ($row->approval === 'Approved' && $request && $request->isPending()) {
            $r = app(PublicEntry::class)->accept($request->fresh(), $actor);
            if (! ($r['ok'] ?? false)) {
                Log::info('federation-entries: could not accept', ['event' => $ev->uuid, 'user' => $user->id, 'why' => $r['message'] ?? null]);
            }
        }

        // A federation sorts its athletes by the rule book itself: an entered
        // athlete with no division is placed, the division created if needed.
        $registration ??= ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')->first();
        if ($registration && \App\Events\Support\EventStreams::many($ev)) {
            $this->streams($ev, $user, $types, (float) $row->weight, $actor);
        } elseif ($registration && ! $registration->category_id) {
            app(\App\Events\Support\CategorySystems::class)->placeCreating($ev, $registration);
        }

        // An entered athlete's weight follows the dashboard until they are weighed.
        if ($registration && $row->weight) {
            ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')
                ->whereNull('weighed_in_at')->get()
                ->each(fn ($r) => (float) $r->weight !== (float) $row->weight ? $r->forceFill(['weight' => $row->weight])->save() : null);
        }
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
        $clubLabel = $entry->club?->tenant?->club_name ?? $entry->application?->name ?? '';

        foreach ($slots as $slot => $title) {
            $label = mb_substr(trim($title.($clubLabel !== '' ? ' · '.$clubLabel : '')), 0, 120);
            $name = trim(strip_tags((string) ($named[$slot]['name'] ?? '')));
            $existing = \App\Events\Models\EventOfficial::where('event_id', $ev->id)
                ->where('role', \App\Events\Models\EventOfficial::ROLE_OTHER)->where('role_label', $label)->first();

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

            $user = User::create([
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
                ])->save();
            }
            $this->photo($o->fresh('user'), $named[$slot]['photo'] ?? null);
        }
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
