<?php

namespace App\Http\Controllers;

use App\Clubs\Models\Tenant;
use App\Events\Support\Clubs\ClubPromotion;
use App\Events\Support\EventAccess;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventClub;
use App\Support\StoragePath;
use App\Traits\StoresBase64Images;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The clubs standing behind a competition, and the two ways they get there.
 *
 * An organiser filling in a start list meets two kinds of club. One is already
 * on the platform, with an owner who can speak for it — that club is ASKED, and
 * answers for itself. The other is a team with a coach and a crest and no
 * account, and the honest thing to do is write down what is known rather than
 * print "unattached" beside half the hall.
 *
 * So: `invite` for the first, `store` for the second. Both end up as one row in
 * `event_clubs`, which is what every screen then reads, so nothing
 * downstream has to care which door a club came through.
 *
 * Every method re-checks that the actor may manage THIS event. The route group
 * checks too; this is the check that matters, because it is the one standing
 * next to the write.
 */
class EventClubsController extends Controller
{
    use StoresBase64Images;

    /**
     * The page the console's Clubs tile opens.
     *
     * A screen of its own rather than a panel on the console: "who is
     * competing?" is a job with a beginning and an end, answered once at the
     * start, not something to glance at while reading five other things.
     */
    public function page(Request $request, ClubEvent $event)
    {
        $me = $this->guard($event);

        /*
         * The MOBILE view, at every width and on every device.
         *
         * There is no desktop event surface on this platform and there is not
         * going to be one: an event is read in a hall, on a phone, one-handed.
         * `PublicEventController::show()` says the same thing about the public
         * page, and a second layout to keep in step is exactly where the two
         * drifted apart last time. So this does NOT consult `is_mobile`.
         */
        return view('personal.mobile.event-clubs', [
            // The same shape the console's own views read, so the band draws
            // itself from the event exactly as every other page does.
            'e' => [
                'key' => $event->uuid,
                'title' => $event->title,
                'color' => $event->color ?: '#7c3aed',
            ],
            'eventClubs' => $this->all($event),
            'canManage' => true,
        ]);
    }

    /** The same list as JSON — what the panel re-reads after a write. */
    public function index(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        return response()->json(['success' => true, 'clubs' => $this->all($event)]);
    }

    /**
     * Write down a club that is not on the platform.
     *
     * A name and a crest, because those are the two things a competition needs
     * to name a team on a board and tell it apart from the next one. Everything
     * else is optional — an Instagram page is often the only address a small
     * club has, and demanding a website nobody has would just produce a blank.
     */
    public function store(Request $request, ClubEvent $event): JsonResponse
    {
        $actor = $this->guard($event);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            // Real bytes are checked below; this refuses anything that is not a
            // data URI before we spend time decoding it.
            'logo' => ['required', 'string', 'starts_with:data:image/'],
            'instagram' => ['nullable', 'string', 'max:200'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
        ]);

        $name = trim(preg_replace('/\s+/u', ' ', $data['name']));

        // The same club twice under two spellings is a start list that adds up
        // wrong, so an exact repeat is refused rather than silently duplicated.
        $exists = EventClub::where('event_id', $event->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'field' => 'name',
                'message' => __('events.club_exists'),
            ], 422);
        }

        $instagram = $this->instagramUrl($data['instagram'] ?? null);

        if (($data['instagram'] ?? '') !== '' && $instagram === null) {
            return response()->json([
                'success' => false,
                'field' => 'instagram',
                'message' => __('events.club_instagram_invalid'),
            ], 422);
        }

        // Server-named, server-typed, under the event that owns it.
        $logo = $this->storeBase64Image(
            $data['logo'],
            StoragePath::event($event, 'clubs'),
            (string) Str::uuid()
        );

        if (! $logo) {
            return response()->json([
                'success' => false,
                'field' => 'logo',
                'message' => __('events.club_logo_rejected'),
            ], 422);
        }

        $club = EventClub::create([
            'event_id' => $event->id,
            'name' => $name,
            'logo' => $logo,
            'instagram' => $instagram,
            'country' => isset($data['country']) ? strtoupper($data['country']) : null,
            'state' => 'listed',
            'created_by' => $actor->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('events.club_added', ['name' => $club->name]),
            'club' => $club->present(),
            'clubs' => $this->all($event),
        ]);
    }

    /**
     * Ask a club that is already here.
     *
     * The club is resolved from the database by slug — never from anything the
     * form claims about it beyond which one to ask — and the invitation is
     * addressed to its owner at this moment, recorded, so a later change of
     * owner cannot silently re-address an invitation that was already sent.
     */
    public function invite(Request $request, ClubEvent $event): JsonResponse
    {
        $actor = $this->guard($event);

        $data = $request->validate([
            'club' => ['required', 'string', 'max:120'],
        ]);

        $tenant = Tenant::where('slug', $data['club'])->first();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => __('events.club_not_found'),
            ], 422);
        }

        $already = EventClub::where('event_id', $event->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($already) {
            return response()->json([
                'success' => false,
                'message' => __('events.club_already_listed'),
                'clubs' => $this->all($event),
            ], 422);
        }

        /*
         * ADDED, not invited (2026-09-06, at the owner's request).
         *
         * This used to write `state = 'invited'`, stamp the club owner into
         * `invited_user_id`, notify them, and then wait for an answer they gave
         * through a banner on the public page. That is a whole negotiation, and
         * the organiser standing at the desk does not need one to write down who
         * turned up — they already know.
         *
         * So a club found in the search is simply listed, exactly as a club
         * typed in by hand is. The invitation columns are LEFT IN PLACE and left
         * null: the flow is switched off, not deleted, and `respond()` below is
         * kept for the day it comes back.
         */
        $club = EventClub::create([
            'event_id' => $event->id,
            'tenant_id' => $tenant->id,
            'name' => $tenant->club_name,
            'logo' => $tenant->logo,
            'country' => $tenant->country,
            'state' => 'listed',
            'created_by' => $actor->id,
        ]);


        return response()->json([
            'success' => true,
            'message' => __('events.club_added', ['name' => $tenant->club_name]),
            'club' => $club->present(),
            'clubs' => $this->all($event),
        ]);
    }

    /**
     * The picker's search.
     *
     * Two characters minimum, ten results, slug not id, and only what a chooser
     * needs to see — the same shape the public club picker already uses, for
     * the same reason: this must answer "which club?", not hand over a list of
     * every club on the platform.
     */
    public function search(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'clubs' => []]);
        }

        $taken = EventClub::where('event_id', $event->id)
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id');

        $clubs = Tenant::query()
            ->where('status', 'active')
            ->whereNotIn('id', $taken)
            ->where('club_name', 'like', '%'.$q.'%')
            ->orderBy('club_name')
            ->limit(10)
            ->get(['id', 'slug', 'club_name', 'logo', 'country'])
            ->map(fn (Tenant $t) => [
                'slug' => $t->slug,
                'name' => $t->club_name,
                'logo' => $t->logo ? file_url($t->logo) : null,
                'country' => $t->country,
            ]);

        return response()->json(['success' => true, 'clubs' => $clubs]);
    }

    /**
     * Change a club that was written down here.
     *
     * Only that kind. A club with an account owns its own name, crest and
     * country — those live on its `tenants` row, it maintains them, and an
     * organiser editing them from inside one competition would be editing
     * somebody else's record through a side door. What an organiser may do to
     * an invited club is invite it or remove it.
     *
     * The logo is optional here and only here: on the way in it is required,
     * because a club with no crest cannot be told from the next one on a board
     * — but an edit that only fixes a spelling should not demand the picture be
     * chosen again.
     */
    public function update(Request $request, ClubEvent $event, EventClub $eventClub): JsonResponse
    {
        $this->guard($event);

        abort_unless((int) $eventClub->event_id === (int) $event->id, 404);

        if ($eventClub->isOnPlatform()) {
            return response()->json([
                'success' => false,
                'message' => __('events.club_not_editable'),
            ], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            // Absent means "leave the crest alone". Only a data URI replaces it.
            'logo' => ['nullable', 'string', 'starts_with:data:image/'],
            'instagram' => ['nullable', 'string', 'max:200'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
        ]);

        $name = trim(preg_replace('/\s+/u', ' ', $data['name']));

        // Same rule as creating one: two spellings of one club is a start list
        // that adds up wrong. Itself excepted, or renaming nothing would fail.
        $clash = EventClub::where('event_id', $event->id)
            ->where('id', '!=', $eventClub->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($clash) {
            return response()->json([
                'success' => false,
                'field' => 'name',
                'message' => __('events.club_exists'),
            ], 422);
        }

        $instagram = $this->instagramUrl($data['instagram'] ?? null);

        if (($data['instagram'] ?? '') !== '' && $instagram === null) {
            return response()->json([
                'success' => false,
                'field' => 'instagram',
                'message' => __('events.club_instagram_invalid'),
            ], 422);
        }

        $previousLogo = $eventClub->logo;
        $logo = $previousLogo;

        if (! empty($data['logo'])) {
            $stored = $this->storeBase64Image(
                $data['logo'],
                StoragePath::event($event, 'clubs'),
                (string) Str::uuid()
            );

            if (! $stored) {
                return response()->json([
                    'success' => false,
                    'field' => 'logo',
                    'message' => __('events.club_logo_rejected'),
                ], 422);
            }

            $logo = $stored;
        }

        $eventClub->forceFill([
            'name' => $name,
            'logo' => $logo,
            'instagram' => $instagram,
            'country' => isset($data['country']) ? strtoupper($data['country']) : $eventClub->country,
        ])->save();

        // The old crest goes only once the new one is safely stored, and only
        // if it actually moved (CLAUDE.md, "Delete Files Before Records" — the
        // replace case).
        if ($previousLogo && $previousLogo !== $logo) {
            rescue(fn () => Storage::disk('public')->delete($previousLogo), null, false);
        }

        return response()->json([
            'success' => true,
            'message' => __('events.club_updated', ['name' => $name]),
            'club' => $eventClub->fresh()->present(),
            'clubs' => $this->all($event),
        ]);
    }

    /**
     * Remove a club from the event.
     *
     * The crest goes before the row (CLAUDE.md, "Delete Files Before Records"),
     * and only when this row is the one that owns the file: an invited club's
     * logo belongs to the club, not to this event, and deleting it here would
     * take it off their own profile.
     */
    public function destroy(Request $request, ClubEvent $event, EventClub $eventClub): JsonResponse
    {
        $this->guard($event);

        abort_unless((int) $eventClub->event_id === (int) $event->id, 404);

        if ($eventClub->logo && ! $eventClub->isOnPlatform()) {
            rescue(fn () => Storage::disk('public')->delete($eventClub->logo), null, false);
        }

        $name = $eventClub->name;

        /*
         * ⚠️ The ATHLETES STAY. Removing a club removes the club, not the people.
         *
         * They entered the competition, most of them have paid for it, and the
         * draw may already have them in it — none of which is undone because
         * the organiser struck a club off the list. What goes is the LINK
         * between them: `event_club_entrants` cascades from the club,
         * and it holds nothing but a pair of ids. Their entry, their fee, their
         * weigh-in and their bouts are on `club_event_registrations` and are
         * not touched by anything here.
         *
         * Counted BEFORE the delete so the answer can say what happened to
         * them, rather than leaving an organiser to wonder.
         */
        $athletes = $eventClub->entrants()->count();

        $eventClub->delete();

        return response()->json([
            'success' => true,
            'message' => $athletes
                ? __('events.club_removed_athletes', ['name' => $name, 'n' => $athletes])
                : __('events.club_removed', ['name' => $name]),
            'athletes_kept' => $athletes,
            'clubs' => $this->all($event),
        ]);
    }

    /**
     * An invited club answers.
     *
     * Answered by the club's OWNER, not by the organiser — which is the whole
     * point of an invitation. The person asked is the one recorded when it was
     * sent, so passing the club's ownership to somebody else does not hand them
     * an invitation they were never sent.
     */
    public function respond(Request $request, ClubEvent $event, EventClub $eventClub): JsonResponse
    {
        $me = Auth::user();

        abort_unless($me instanceof User, 403);
        abort_unless((int) $eventClub->event_id === (int) $event->id, 404);
        abort_unless($eventClub->isAwaiting(), 404);

        $mine = (int) $eventClub->invited_user_id === (int) $me->id
            || ($eventClub->tenant_id && (int) ($eventClub->tenant?->owner_user_id) === (int) $me->id);

        abort_unless($mine, 403);

        $data = $request->validate([
            'answer' => ['required', 'string', 'in:accepted,declined'],
        ]);

        $eventClub->forceFill([
            'state' => $data['answer'],
            'responded_at' => now(),
        ])->save();

        // The organiser hears it where they are working.
        if ($organiser = $event->created_by) {
            UserNotification::notifyUser(
                (int) $organiser,
                'event_club_invite_answer',
                __('events.club_answer_title', [
                    'club' => $eventClub->name,
                    'answer' => __('events.club_state_'.$data['answer']),
                ]),
                [
                    'actor_id' => $me->id,
                    'body' => $event->title,
                    'action_url' => route('me.events.manage', $event->uuid),
                    'icon' => $data['answer'] === 'accepted' ? 'bi-check-circle-fill' : 'bi-x-circle-fill',
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => __('events.club_answered'),
            'club' => $eventClub->fresh()->present(),
        ]);
    }


    /**
     * The event's athletes, and which club each of them is down as.
     *
     * Both kinds of club in one list, because to the person at the desk there
     * is only one question — "who is this athlete with?" — and an answer that
     * could only ever be half the clubs would be worse than useless.
     */
    public function entrants(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        // Where a temporary club claims somebody.
        $links = DB::table('event_club_entrants as l')
            ->join('event_clubs as c', 'c.id', '=', 'l.event_club_id')
            ->where('c.event_id', $event->id)
            ->pluck('c.uuid', 'l.registration_id');

        $rows = ClubEventRegistration::with(['user:id,full_name,name,profile_picture', 'representingTenant:id,club_name'])
            ->where('event_id', $event->id)
            ->where('role', 'participant')
            ->get()
            ->map(fn (ClubEventRegistration $r) => [
                'id' => (int) $r->id,
                'name' => $r->user?->full_name ?: ($r->user?->name ?: '—'),
                // The club they are ALREADY down as, whichever kind it is.
                'club' => $links[$r->id] ?? null,
                'real_club' => $r->representingTenant?->club_name,
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return response()->json(['success' => true, 'entrants' => $rows]);
    }

    /**
     * Say which athletes a temporary club brought.
     *
     * This is not bookkeeping — it is the test the promotion runs on. A club
     * with athletes here becomes a real club after the competition; a club with
     * none expires with the event. So the write is exact: the ids sent become
     * the whole of this club's list, and an athlete moved to another club is
     * moved rather than duplicated (one athlete, one club, one competition).
     */
    public function assign(Request $request, ClubEvent $event, EventClub $eventClub): JsonResponse
    {
        $actor = $this->guard($event);

        abort_unless((int) $eventClub->event_id === (int) $event->id, 404);

        $data = $request->validate([
            'entrants' => ['present', 'array'],
            'entrants.*' => ['integer'],
        ]);

        // Ids are filtered through THIS event's entries, so one from another
        // competition cannot be attached by posting its number.
        $ids = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', array_map('intval', $data['entrants']))
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($eventClub, $ids, $actor) {
            $before = $eventClub->entrants()->pluck('club_event_registrations.id')->all();

            $eventClub->entrants()->detach();

            foreach ($ids as $id) {
                // The unique key is the registration: attaching them here takes
                // them off whichever club on this event had them before.
                DB::table('event_club_entrants')->updateOrInsert(
                    ['registration_id' => $id],
                    [
                        'event_club_id' => $eventClub->id,
                        'added_by' => $actor->id,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            /*
             * A club with an account is also named the way the rest of the
             * platform names one.
             *
             * `representing_tenant_id` is what the draw, the entry list and the
             * flag beside a competitor read — so for an INVITED club the two
             * records are kept in step: added here means competing for them
             * there. A temporary club has no tenant to point at, and its link is
             * the whole of the answer.
             */
            if ($eventClub->tenant_id) {
                if ($ids) {
                    ClubEventRegistration::whereIn('id', $ids)
                        ->update(['representing_tenant_id' => $eventClub->tenant_id]);
                }

                // Taken off the club here is taken off it there — but only
                // where it was THIS club they named. Somebody's own affiliation
                // with another club is not ours to clear.
                $dropped = array_diff($before, $ids);

                if ($dropped) {
                    ClubEventRegistration::whereIn('id', $dropped)
                        ->where('representing_tenant_id', $eventClub->tenant_id)
                        ->update(['representing_tenant_id' => null]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => __('events.club_athletes_saved', ['n' => count($ids), 'name' => $eventClub->name]),
            'clubs' => $this->all($event),
        ]);
    }

    /**
     * Register the clubs that competed.
     *
     * The manual half of `events:promote-clubs`, for an organiser who has
     * finished and wants it done now rather than on the next sweep. Same
     * service, same rules — including the one that refuses to answer "who
     * competed" before the competition is over.
     */
    public function promote(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        $result = app(ClubPromotion::class)->forEvent($event);

        if ($result['reason'] === 'not_over') {
            return response()->json([
                'success' => false,
                'message' => __('events.club_promote_too_early'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('events.club_promoted_done', [
                'n' => count($result['promoted']),
                'dropped' => count($result['skipped']),
            ]),
            'clubs' => $this->all($event),
        ]);
    }

    /* ==================== Shared ==================== */

    /** @return array<int, array<string, mixed>> */
    private function all(ClubEvent $event): array
    {
        return EventClub::with(['tenant:id,club_name,logo,country,owner_user_id', 'entrants:id'])
            ->where('event_id', $event->id)
            ->orderByRaw("case state when 'accepted' then 0 when 'listed' then 1 when 'invited' then 2 else 3 end")
            ->orderBy('name')
            ->get()
            ->map(fn (EventClub $c) => $c->present())
            ->all();
    }

    /**
     * A pasted Instagram page, reduced to one canonical URL.
     *
     * Accepts what people actually paste — `@club`, `club`,
     * `instagram.com/club`, the full https URL with a query string — and emits
     * one shape. Anything else is refused rather than stored, because this
     * value ends up in an `href`: a `javascript:` scheme in a link an organiser
     * typed is a stored XSS on every screen that shows the club.
     */
    private function instagramUrl(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        // A bare handle, with or without the @.
        if (preg_match('/^@?([A-Za-z0-9._]{1,30})$/', $raw, $m)) {
            return 'https://instagram.com/'.$m[1];
        }

        // A URL — and it must be Instagram's, over http(s), or it is not an
        // Instagram page whatever it claims.
        $url = preg_match('#^https?://#i', $raw) ? $raw : 'https://'.$raw;
        $parts = parse_url($url);

        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');

        if (! in_array($host, ['instagram.com', 'www.instagram.com', 'm.instagram.com'], true)) {
            return null;
        }

        $handle = trim((string) ($parts['path'] ?? ''), '/');

        if (! preg_match('/^([A-Za-z0-9._]{1,30})$/', $handle, $m)) {
            return null;
        }

        return 'https://instagram.com/'.$m[1];
    }

    /** Only somebody who runs this event. */
    private function guard(ClubEvent $event): User
    {
        $me = Auth::user();

        abort_unless($me instanceof User && app(EventAccess::class)->canManage($event, $me), 403);

        return $me;
    }
}
