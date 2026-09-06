<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use Carbon\Carbon;

/**
 * Exactly what a stranger may see about an event, and nothing else.
 *
 * Phase B of Documentation/EVENTS-PUBLIC-ENTRY.md. The page this feeds is
 * reachable with no account, so the safe answer cannot live in a Blade file
 * where the next person adds one more field to a loop and quietly publishes it.
 * It lives HERE, once: a poster's worth of facts.
 *
 * What it will never carry, however convenient it looks:
 *   · the roster — who ENTERED, as a list of people
 *   · financials, the ledger, anyone's payment state
 *   · the organiser's or anybody's contact details, ids or emails
 *   · internal ids — the event's uuid is the only identifier that leaves
 *
 * The one deliberate exception is THE DRAW, published by `draw()` below: a
 * bracket is pinned to the hall wall and read by whoever walks past it, so
 * withholding it from the page that competition was shared on protected
 * nothing. It is still the narrow version — see that method for what is
 * stripped out of it on the way, and why each piece goes.
 *
 * Opt-in per event (`entry_mode = public`). `isPublic()` is the one gate; a
 * caller that forgets it gets nothing, because `payload()` refuses too.
 */
class PublicEvent
{
    /** Built brackets, per event id — see `drawSummary()`. */
    private array $drawn = [];

    public function __construct(private EventTypeRegistry $registry) {}

    /** May anybody at all open this event's page? */
    public function isPublic(ClubEvent $event): bool
    {
        return ($event->entry_mode ?? 'members') === 'public'
            && ! $event->is_archived;
    }

    /**
     * The poster.
     *
     * @return array<string, mixed>|null  null when the event is not public
     */
    public function payload(ClubEvent $event): ?array
    {
        if (! $this->isPublic($event)) {
            return null;
        }

        $type = $this->registry->for($event);
        $date = $event->date ?: now();
        $start = $event->start_time ? strtotime($event->start_time) : null;
        $end = $event->end_time ? strtotime($event->end_time) : null;
        $going = $event->participantRegistrations()->count();

        // The SAME keys the member page's payload uses
        // (PersonalEventController::eventView). Not a coincidence and not
        // convenience: the two pages render from the same partials, so they
        // have to speak the same language or the shared markup breaks on one of
        // them. What differs is which keys are FILLED — this class is still the
        // gate, and the keys it refuses to produce are the ones that would name
        // a competitor, a price paid, or an internal id.
        return [
            // Never the auto-increment id. The map element needs *an* id; it
            // gets a slice of the uuid, which is already the public address.
            'id' => substr($event->uuid, 0, 8),
            'key' => $event->uuid,
            'uuid' => $event->uuid,

            'title' => $event->title,
            'about' => $event->description ?? '',
            'club' => $event->tenant?->club_name ?? '',
            'host' => $event->tenant?->club_name,
            'host_logo' => $event->tenant?->logo ? file_url($event->tenant->logo) : null,
            'country' => $event->tenant?->country,

            // Organiser-supplied and lands in a `style` attribute, so it is
            // whitelisted rather than trusted. Bare `bi-*` name, like the
            // member payload — the templates write `class="bi {{ $e['icon'] }}"`.
            'color' => $this->color($event),
            'icon' => $this->icon($event->icon ?: 'bi-trophy'),
            'type' => $type->label(),
            'sport' => $event->sport,
            'sport_label' => $event->sport ? ucfirst($event->sport) : null,
            'sport_icon' => 'bi-dribbble',
            'scope' => $event->scope ?? 'internal',
            'scope_label' => null,   // an internal audience word; not a poster fact
            'photo' => $this->photo($event),

            /* ⚠️ translatedFormat, never format.
             *
             * `format()` is locale-BLIND: it prints "Fri 18 Sep" whatever the
             * page's language, so the poster's date chip stayed English inside
             * an otherwise Arabic column (reported 2026-09-04). Carbon's
             * `translatedFormat()` takes the same pattern and renders the month
             * and weekday in the active locale, which SetLocale has already
             * put in place by the time this payload is built.
             *
             * `date_iso` and `day` keep raw values on purpose: one is a machine
             * string, the other a bare number with nothing to translate. The
             * TIME goes through Carbon too, so its AM/PM is the locale's own
             * (ص / م in Arabic) — parsed from the same wall clock rather than
             * a timestamp, so no timezone maths creeps into a display string.
             */
            'day' => $date->format('d'),
            'mon' => $date->translatedFormat('M'),
            'wday' => $date->translatedFormat('D'),
            'date_iso' => $date->toDateString(),
            'date' => $event->date?->translatedFormat('l, j F Y'),
            'end_date' => $event->end_date?->translatedFormat('l, j F Y'),
            'time' => $start ? Carbon::parse(date('Y-m-d H:i:s', $start))->translatedFormat('g:i A') : __('events.tba'),
            'end' => $end ? Carbon::parse(date('Y-m-d H:i:s', $end))->translatedFormat('g:i A') : '',
            'deadline' => $event->enrollment_ends_at?->translatedFormat('l, j F'),

            'location' => $event->location ?: 'TBA',
            'address' => $event->location ?: '',
            // An organiser TYPES this, and it lands in an href on a page a
            // stranger opens. A `javascript:` (or `data:`) URI there is script
            // execution, and Blade's escaping does not help in a URL context —
            // it escapes the characters, not the scheme. http(s) only.
            'location_url' => self::safeUrl($event->location_url),
            'lat' => $event->gps_lat ? (float) $event->gps_lat : ($event->tenant?->gps_lat ? (float) $event->tenant->gps_lat : null),
            'lng' => $event->gps_long ? (float) $event->gps_long : ($event->tenant?->gps_long ? (float) $event->tenant->gps_long : null),

            // A count, never the people. "How full is it" is a poster fact; who
            // is entered is not — so `participants` and `results` simply are not
            // in this payload, and a partial that wants them renders nothing.
            'going' => $going,
            'cap' => $event->max_capacity ?: max($going, 1),
            'capped' => (int) $event->max_capacity > 0,
            'entered' => $going,
            'capacity' => (int) $event->max_capacity ?: null,

            /*
             * WHAT IT COSTS.
             *
             * `headline()`, not `display(amount())`. An event now prices itself
             * as a LIST — a Gi entry, a No-Gi entry, a late penalty — and the
             * base amount column is zero for every one of them, so the poster
             * read the cheapest possible answer and told a stranger the
             * competition was FREE (reported 2026-09-06). The headline is the
             * floor with its "from", which is the honest one-line answer to a
             * price that depends on what you tick.
             *
             * `fee_is_paid` follows for the same reason: `isPaid()` is the
             * base-fee predicate on purpose, and money plainly changes hands at
             * an event whose entry types all cost something. `chargesAnything()`
             * is the question the poster is actually asking.
             */
            'participant_fee' => EventFee::headline($event, 'participant'),
            'fee' => EventFee::headline($event, 'participant'),
            'fee_is_paid' => EventFee::chargesAnything($event, 'participant'),

            // The list behind the headline — what the fee chip jumps DOWN to.
            'fees' => self::feeLines($event),

            'spectator' => $event->spectator_enabled
                ? ['fee' => EventFee::headline($event, 'spectator')]
                : null,
            'spectator_fee' => $event->spectator_enabled
                ? EventFee::headline($event, 'spectator')
                : null,
            'prize' => $event->prize,

            // The rules and the shape of the day — what a competitor needs
            // before deciding, and what the packages already publish. The
            // run-of-show carries times and phases, never a name.
            'phases' => $type->timeline($event),
            'divisions' => $event->categories()->orderBy('sort_order')->pluck('name')->all(),

            // The draw, once one exists — a count and a flag here, the bracket
            // itself fetched by the board from `events.public.draw.data`, so a
            // page load never carries every bout of every division.
            'draw' => $this->drawSummary($event),
            'draw_url' => route('events.public.draw.data', ['event' => $event->uuid]),

            // The three sections that follow the draw. All three name people,
            // and all three are things a competition already publishes: the
            // officiating sheet, the footage, and the entry list. Each is
            // narrowed HERE and nowhere else — see the methods below for what
            // is left out of each and why.
            'officials' => $this->officials($event),
            'participants' => $this->participants($event),
            'gallery' => $this->gallery($event),
            'requirements' => array_values($event->requirements ?: []),
            'tags' => array_values($event->tags ?: []),

            // What this link is branded AS. A shared competition wears its own
            // identity, not the platform's — see PublicBrand.
            'icons' => app(PublicBrand::class)->iconUrls($event),
            'manifest' => route('events.public.manifest', ['event' => $event->uuid]),

            // Whether entry is even a possibility right now, in the words the
            // rest of the product already uses.
            // The files the organiser attached. Published because they made the
            // page public, and served by `events.public.document` — never as a
            // storage path, which never leaves this class.
            'documents' => $this->documents($event),
            'entries' => app(EntryService::class)->entriesState($event),
            // Whether the Enrol button exists at all, and why not when it
            // doesn't. Asked of the one class that also answers the endpoint,
            // so a button can never offer what the door would refuse.
            'enrol' => app(PublicEntry::class)->state($event),
            'started' => $event->hasStarted(),
            'ended' => $event->hasEnded(),
        ];
    }

    /**
     * The draw, as a stranger may read it.
     *
     * A published bracket names competitors, and that is the point: it is the
     * sheet on the hall wall, and the person deciding whether to travel to a
     * competition wants to see who is in it. So this does NOT go through a
     * second bracket builder — it asks the event's own package for the one
     * shared shape (App\Events\Support\BracketView, via `EventType::
     * bracketView()`), exactly as the member page does, and then takes things
     * OUT. A type that runs no brackets returns nothing here and the section
     * never renders.
     *
     * What is removed, and why:
     *   · `bench`    — entrants with no slot yet. An organiser's working tray,
     *                  and its `provisional` flag is literally "unpaid or not
     *                  weighed in", which is somebody's money on a public page.
     *   · `photo`    — a face. The athlete's own `profile_picture_is_public`
     *                  already governs this platform-wide, but it was chosen
     *                  before an event page a stranger could open existed, so
     *                  the conservative reading applies until they are asked
     *                  again. Names yes, faces no. (Delete the null and the
     *                  member's own setting governs, nothing else to change.)
     *   · `provisional` on a side — same payment/weigh-in leak as the bench.
     *                  Whether the DRAW is settled is said once, per division,
     *                  by `draw_state`, which is about the bracket and not
     *                  about a person.
     *   · `podium`    — a medal list is a RESULT, not a draw. The bracket
     *                  already shows who won each bout; a podium block would
     *                  be publishing an outcome the organiser may not have
     *                  confirmed yet.
     *
     * The division and bout ids that remain are not addresses: nothing on the
     * public surface accepts one. The board needs them to switch divisions and
     * to key its bout cards, and the only public door — `drawData()` — takes
     * the event's uuid and nothing else.
     *
     * @return array<int, array<string, mixed>>  one entry per division; empty
     *                                           when there is no draw to show
     */
    public function draw(ClubEvent $event): array
    {
        if (! $this->isPublic($event)) {
            return [];
        }

        /*
         * ...and a draw the organiser has not let out yet stays in, even on a
         * page they published. Publishing the POSTER and publishing the DRAW
         * are two decisions (club_events.draw_reveal), and a stranger is the
         * last reader a withheld bracket should reach.
         */
        if (! app(EventAccess::class)->drawVisible($event, null)) {
            return [];
        }

        if (isset($this->drawn[$event->id])) {
            return $this->drawn[$event->id];
        }

        // No viewer: nobody is signed in, and a draw does not vary by reader.
        $divisions = $this->registry->for($event)->bracketView($event, null);

        return $this->drawn[$event->id] = array_values(array_map(function (array $d) {
            $d['bench'] = [];
            unset($d['podium']);

            $d['rounds'] = array_map(function (array $round) {
                $round['matches'] = array_map(function (array $m) {
                    foreach (['a', 'b'] as $side) {
                        if (! isset($m[$side]) || ! is_array($m[$side])) {
                            continue;
                        }
                        $m[$side]['photo'] = null;
                        $m[$side]['provisional'] = false;
                    }

                    return $m;
                }, $round['matches'] ?? []);

                return $round;
            }, $d['rounds'] ?? []);

            return $d;
        }, $divisions));
    }

    /**
     * The face beside a competitor's name, in the order the platform decides it.
     *
     * 1. The ENTRY's own photo. Taken for THIS competition — supplied at the
     *    weigh-in desk by an official, or at enrolment through the public link,
     *    which requires one. It belongs to the entry, is scoped to this event
     *    and goes when the entry goes, so showing it here is showing the thing
     *    it was taken for. It is not a disclosure of anything about their
     *    account.
     * 2. Otherwise their profile picture, and ONLY if they published it
     *    (`profile_picture_is_public`) — their own choice about their face,
     *    governed here exactly as App\Events\Support\BracketView::photo()
     *    governs it on the draw this same page carries.
     * 3. Otherwise nothing, silently. A "hidden" label would disclose the very
     *    fact the setting protects; the gendered silhouette stands in.
     *
     * Same order as RosterPeople and the sports' own CompetitorPhoto. One
     * question, one answer, wherever the platform draws a competitor.
     */
    private function face(ClubEventRegistration $r): ?string
    {
        // `showable` rather than a bare truth test: an entry can NAME a file
        // that is no longer there, and preferring it unconditionally stopped the
        // fall-through — the competitor went blank here while their profile
        // showed a picture from a different path. See EntryPhoto::showable().
        if (EntryPhoto::showable($r->photo)) {
            return file_url($r->photo).'?v='.($r->updated_at?->timestamp ?? 0);
        }

        $user = $r->user;

        if (! $user?->profile_picture || ! $user->profile_picture_is_public) {
            return null;
        }

        return file_url($user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0);
    }

    /**
     * Is there a draw to announce at all?
     *
     * Asks `draw()` rather than counting bouts in SQL, because "is there a
     * draw" is the PACKAGE's answer, not the table's: a type with matches but
     * no bracket (an open mat, a sparring session) must not have a draw
     * section announced over it. `draw()` memoises per event, so the poster
     * payload and this summary cost one build between them.
     *
     * @return array{published: bool, divisions: int, entrants: int}
     */
    private function drawSummary(ClubEvent $event): array
    {
        $divisions = $this->draw($event);

        return [
            'published' => $divisions !== [],
            'divisions' => count($divisions),
            'entrants' => (int) array_sum(array_column($divisions, 'entrants')),
        ];
    }

    /**
     * The officiating sheet — who is running this competition.
     *
     * The three things such a sheet has always printed: the job, the name, and
     * the country beside it. Grouped by job in the SPORT's own order, because
     * a sheet reads Shushin first rather than whoever was appointed first.
     *
     * Left out: the email, the phone number and the fee, which are appointment
     * PAPERWORK and stay behind the members' `assertCanManage()`; the uuid,
     * because /people/{uuid} needs an account and a link a stranger cannot
     * open is worse than no link; and the photo, for the same reason the draw
     * has no faces (see `draw()`).
     *
     * @return array{count: int, groups: array<int, array<string, mixed>>}
     */
    private function officials(ClubEvent $event): array
    {
        $rows = $event->officials()
            ->with('user:id,full_name,name,nationality')
            ->get()
            ->filter(fn ($o) => $o->user !== null);

        $byRole = $rows->groupBy('role');
        $labels = app(OfficialRoles::class)->labels($event);

        $groups = [];

        foreach ($labels as $key => $label) {
            $people = $byRole->get($key);

            if (! $people || $people->isEmpty()) {
                continue;
            }

            $groups[] = [
                'key' => $key,
                'label' => $label,
                'people' => $people->map(fn ($o) => [
                    'name' => $o->user->full_name ?: $o->user->name,
                    'country' => $o->user->nationality ? strtoupper($o->user->nationality) : null,
                ])->values()->all(),
            ];
        }

        return ['count' => $rows->count(), 'groups' => $groups];
    }

    /**
     * The entry list — who is competing, and for whom.
     *
     * A competition publishes its entry list; that is what an entry list is
     * FOR. So this is the poster version of it: the name, the division, and the
     * club they compete for with that club's country beside it (the flag on a
     * competition sheet is the CLUB's, never the athlete's passport — see
     * `ClubEventRegistration::countryCode()`).
     *
     * Left out, and none of it by accident: the weight and the belt grade (a
     * measurement of a person, not a fact about the event), `paid` /
     * `payment_proof` / `paid_at` / `entry_state` (money), `weighed_in_at` and
     * `entered_by` (the desk's working state), the registration id, the user
     * uuid, and the face. What remains is what is pinned up beside the mat.
     *
     * Competitors only — a registration list also holds spectators and
     * coaches, and neither is an entrant.
     *
     * @return array{count: int, clubs: int, rows: array<int, array<string, mixed>>}
     */
    private function participants(ClubEvent $event): array
    {
        $rows = $event->participantRegistrations()
            ->with([
                'user:id,uuid,full_name,name,gender,birthdate,is_discoverable,profile_picture,profile_picture_is_public,updated_at',
                'representingTenant:id,club_name,country,logo',
                'category:id,name,weight_class',
            ])
            ->get();

        $people = $rows
            ->filter(fn (ClubEventRegistration $r) => $r->user !== null)
            ->map(function (ClubEventRegistration $r) {
                $club = $r->representedTenantId() ? $r->representingTenant : null;

                return [
                    'name' => $r->user->full_name ?: $r->user->name,
                    /* The GROUP, not the raw weight class — the two lists must
                       agree, and the group's own name already carries the range
                       it covers ("Group D (80+)"). Falls back to the weight
                       class for an event whose categories are unnamed. */
                    'division' => $r->category?->name ?: $r->category?->weight_class,
                    'club' => $club?->club_name,
                    'club_logo' => $club?->logo ? file_url($club->logo) : null,
                    'country' => $r->countryCode() ? strtoupper($r->countryCode()) : null,
                    /*
                     * A face, and only if THEY said so.
                     *
                     * `profile_picture_is_public` is the athlete's own choice
                     * about their photograph and it governs here exactly as it
                     * does on the bracket — App\Events\Support\BracketView::
                     * photo() is the reference implementation. Someone who has
                     * not opted in gets the gendered silhouette, silently: a
                     * "hidden" label would disclose the very thing the setting
                     * protects.
                     */
                    'photo' => $this->face($r),
                    /*
                     * The BELT COLOUR — the rank, added 2026-09-05 at the
                     * user's request.
                     *
                     * Deliberately the colour and NOT the grade. The docblock
                     * above withholds "the weight and the belt grade" as a
                     * measurement of a PERSON, and the degree stays withheld;
                     * but in a combat sport the colour is the rank a
                     * competition is organised by — divisions are named for it
                     * and every bracket prints it. It is a fact about the
                     * event, which is the line this list is drawn on.
                     *
                     * Drawn by <x-belt-chip> on both breakpoints — the card
                     * on mobile, the row on desktop — so the same list reads
                     * the same way at either width. It was the card's coloured
                     * EDGE until 2026-09-06, when the edge became the gender.
                     */
                    'belt' => $r->belt_colour ?: null,
                    // Drawn beside the name on every entry list in the sport.
                    // Both are already on the draw this same page publishes.
                    'gender' => $r->user->gender ?: null,
                    'age' => $r->user->birthdate ? $r->user->age : null,
                    /*
                     * The PUBLIC key, and whether the public profile behind it
                     * will actually open for a stranger.
                     *
                     * `/people/{uuid}` is the platform's safe profile and
                     * enforces its own rule (User::canViewPublicProfile) — an
                     * anonymous reader may see only a member who opted into
                     * being found and is not a minor. The uuid is here so the
                     * entry list can LINK; the flag is here so it only links
                     * where the link opens. A card that leads to a 404 is a
                     * dead end, and half this event's entrants are minors or
                     * came in through the public door with discovery off.
                     *
                     * Viewer-independent on purpose: this payload is cached per
                     * event, and the ANONYMOUS answer is the conservative one.
                     * A signed-in reader gets a wider rule, applied in the view.
                     */
                    'uuid' => $r->user->uuid,
                    'public_profile' => $r->user->canViewPublicProfile(null),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()->all();

        /*
         * The clubs with somebody in it, biggest squad first — the other end of
         * the same question the entry list answers, and the second tab on the
         * page. Built FROM the rows rather than queried again, so the two tabs
         * can never disagree about who is here: a club appearing in a list that
         * no competitor row shows would read as a mistake in the draw.
         *
         * Nothing new is disclosed. A club's name, mark and country are already
         * on every row of the entry list above, and on /explore.
         */
        $clubs = collect($people)
            ->filter(fn (array $r) => filled($r['club']))
            ->groupBy('club')
            ->map(fn ($group, $name) => [
                'name' => $name,
                'logo' => $group->first()['club_logo'],
                'country' => $group->first()['country'],
                'athletes' => $group->count(),
            ])
            ->sortByDesc('athletes')
            ->values()->all();

        return [
            'count' => count($people),
            'clubs' => count($clubs),
            'club_rows' => $clubs,
            'rows' => $people,
        ];
    }

    /**
     * The footage — every bout that was filmed, grouped by division.
     *
     * The shelves come from App\Media\VideoLibrary, the same builder the
     * members' gallery reads, so the two galleries can never disagree about
     * what was filmed. Then the tiles are narrowed:
     *
     *   · `url` is dropped. It points at `me.events.bout.video`, which is
     *     behind auth + verified + 2FA — a tile whose link lands a stranger on
     *     a login form is worse than a tile that simply plays where it is. The
     *     public page plays the ladder inline instead.
     *   · `arena` is dropped: the review page's whole view model, none of
     *     which a gallery tile draws.
     *
     * `poster` and `preview` are the HLS ladder and its first frame, both
     * served by App\Media\Http\MediaStreamController — which, since
     * 2026-09-02, answers an anonymous request for a PUBLIC event's footage
     * and refuses everything else exactly as before. Playback only: the
     * original file is not offered here and its door did not open.
     *
     * @return array{count: int, divisions: array<int, array<string, mixed>>}
     */
    private function gallery(ClubEvent $event): array
    {
        $divisions = app(\App\Media\VideoLibrary::class)->forEvent($event);

        $divisions = array_map(function (array $d) {
            $d['bouts'] = array_map(function (array $b) {
                unset($b['url'], $b['arena']);

                return $b;
            }, $d['bouts'] ?? []);

            return $d;
        }, $divisions);

        return [
            'count' => (int) array_sum(array_column($divisions, 'count')),
            'divisions' => $divisions,
        ];
    }

    /** The event's own colour, whitelisted — it lands in a `style` attribute. */
    public function color(ClubEvent $event): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $event->color) ? $event->color : '#7c3aed';
    }

    /**
     * A `bi-*` icon name we are willing to put in a class attribute.
     *
     * Organiser-supplied, so it is matched rather than trusted — and it is
     * returned BARE (no leading `bi `), because that is the shape the shared
     * templates expect: `class="bi {{ $e['icon'] }}"`.
     */
    private function icon(string $name): string
    {
        return preg_match('/^bi-[a-z0-9-]{1,40}$/', $name) ? $name : 'bi-trophy';
    }

    /**
     * A URL we are willing to put in an href, or null.
     *
     * An allowlist of schemes, not a blocklist of bad ones: `javascript:`,
     * `data:`, `vbscript:` and whatever comes next all fail the same test, and
     * so does a stray `//evil.com` or a leading tab/newline that a browser
     * strips before parsing.
     */
    private static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url, " \t\n\r\0\x0B");

        return preg_match('#^https?://[^\s]+$#i', $url) ? $url : null;
    }

    /**
     * What an entry actually costs, itemised.
     *
     * The poster's fee chip is a one-line answer ("From BHD 5") and a DOOR: it
     * scrolls to the way in, and what a reader deciding whether to compete
     * needs to find there is the list — which entry types exist, what each one
     * costs, and whether leaving it late costs more. Sending them to a "Enter"
     * button that names no prices is the same as not answering.
     *
     * Display strings only. The amounts are formatted here, once, so no view
     * has to know what a currency label looks like, and no view is handed a
     * number it might total up and get wrong: the real arithmetic belongs to
     * App\Events\Support\EventFee::quote(), which is what the entry form and
     * the desk both charge from.
     *
     * Labels are organiser-typed and land in HTML — they are escaped by Blade
     * like every other value in this payload, and nothing here is a URL.
     *
     * @return array<string, mixed>
     */
    private static function feeLines(ClubEvent $event): array
    {
        $currency = EventFee::currency($event);

        $lines = fn (string $role) => EventFee::options($event, $role)
            ->map(fn ($o) => [
                'label' => (string) $o->label,
                'amount' => EventFee::display((float) $o->amount, $currency),
            ])->values()->all();

        $participant = $lines('participant');
        $spectator = $event->spectator_enabled ? $lines('spectator') : [];

        $lateAmount = (float) ($event->late_fee_amount ?? 0);
        $late = $lateAmount > 0 && $event->late_fee_from !== null
            ? [
                'amount' => EventFee::display($lateAmount, $currency),
                // The moment it starts applying, in the reader's own language.
                'from' => $event->late_fee_from->translatedFormat('l, j F'),
            ]
            : null;

        return [
            'currency' => $currency,
            'participant' => $participant,
            'spectator' => $spectator,
            'late' => $late,
            // "Is there anything to show?" — asked once here rather than as
            // three conditions in each of the two posters.
            'any' => $participant !== [] || $spectator !== [] || $late !== null,
            // Ticking more than one is allowed and is the whole point of a
            // list, so the poster says so rather than letting a reader assume
            // the prices are alternatives.
            'multiple' => count($participant) > 1,
        ];
    }

    /**
     * The poster image.
     *
     * Served through the ordinary file door, which App\Support\FileAccess lets
     * an anonymous visitor read ONLY for an event in public mode — see the
     * `event()` branch there. No image is not an error: the page draws its own
     * backdrop from the event's colour.
     */
    /**
     * The event's attached documents, in the shape the download list reads.
     *
     * Only the fields a stranger may have: a uuid, the human title, the size and
     * an icon. Never `path` — the file is reached through the route, which
     * re-asks whether the event is still public on every request.
     */
    private function documents(ClubEvent $event): array
    {
        return $event->documents()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($doc) => [
                'uuid'       => $doc->uuid,
                'title'      => $doc->title,
                'extension'  => $doc->extension,
                'size'       => $doc->size,
                'size_label' => $doc->readableSize(),
                'icon'       => $doc->icon(),
                'url'        => route('events.public.document', [$event->uuid, $doc->uuid]),
            ])
            ->all();
    }

    private function photo(ClubEvent $event): ?string
    {
        $images = is_array($event->images) ? $event->images : [];

        return $images ? file_url($images[0]) : null;
    }
}
