<?php

namespace App\EventLab\Controllers;

use App\Http\Controllers\Controller;

use App\EventLab\Support\PublicEntry;
use App\Events\Support\PublicEvent;
use App\EventLab\Models\SandboxEventClub;
use App\Models\ClubEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Enrolling from the public page — Door C.
 *
 * Phase C of Documentation/EVENTS-PUBLIC-ENTRY.md. This is the only place on
 * the platform where a wholly unauthenticated stranger creates a `User`, so it
 * is written as a door rather than a form:
 *
 *   · It exists only for an event an organiser deliberately published. Anything
 *     else 404s with the SAME response an unknown uuid gets, so the address
 *     cannot be used to discover which events exist.
 *   · It is throttled hard, per IP, on both the page and the submit.
 *   · Every rule lives in App\Events\Support\PublicEntry. This controller
 *     carries the request and validates its SHAPE, nothing else — no decision
 *     about money, division, state or capacity is taken here.
 *   · Nothing it accepts is trusted to mean anything: what a stranger types is
 *     an assertion, and the weigh-in desk, the package's gate and the
 *     organiser's accept each outrank it.
 */
/*
 * COPY — this file is `app/Http/Controllers/PublicEntryController.php`, copied verbatim into the
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
class PublicEntryController extends Controller
{
    public function show(Request $request, ClubEvent $event, PublicEvent $publisher, PublicEntry $entries)
    {
        $e = $publisher->payload($event);

        abort_if($e === null, 404);

        $state = $entries->state($event);

        // Entries closed: there is nothing to fill in, so send them back to the
        // page that says so properly rather than showing a form that cannot be
        // submitted (Navigation Integrity).
        if (! $state['open']) {
            return redirect()->route('testcode.e', $event->uuid);
        }

        $me = \Illuminate\Support\Facades\Auth::user();

        // A guest is asked FIRST whether they already have an account, and the
        // yes branch sends them to the ordinary login. Stashing the intended
        // URL here rather than passing it through the query string is what
        // brings them back to this exact event afterwards — `redirect()->
        // intended()` in AuthenticatedSessionController reads this key, so the
        // whole login stack (throttle, verification, two-factor) is untouched
        // and there is no second place that authenticates anybody.
        if ($me === null) {
            $request->session()->put('url.intended', route('testcode.e.enter', $event->uuid));
        }

        // Where they appear to be, from the CDN's own header — used ONLY to
        // open the nationality and dial-code pickers on a likely answer, which
        // both stay theirs to change. No geolocation prompt: the connection
        // already said, and asking for coordinates to answer a question that
        // costs one tap is a worse trade for the person filling this in.
        $guessCountry = \App\Support\VisitorCountry::guess($request);

        return view('eventlab::entry.public.enrol', [
            'e' => $e,
            'belts' => self::belts(),
            'guessCountry' => $guessCountry,
            'guessDial' => \App\Support\VisitorCountry::dialCode($guessCountry),
            // Who is filling this in, if anybody. The template shows the
            // three-step account-building flow to a guest and a one-card
            // confirmation to a member — never both.
            'me' => $me ? [
                'name' => $me->full_name ?: $me->name,
                'email' => $me->email,
                'photo' => $me->profile_picture ? file_url($me->profile_picture).'?v='.($me->updated_at?->timestamp ?? 0) : null,
                'gender' => $me->gender,
            ] : null,
            // Both are dead ends for a form, so the page says so instead of
            // offering a button that would be refused (Navigation Integrity).
            'alreadyIn' => $me ? $entries->isEntered($event, $me) : false,
            'alreadyAsked' => $me ? $entries->pendingFor($event, $me) !== null : false,
        ]);
    }

    /**
     * Clubs a public entrant can name, by search.
     *
     * The entry list, the draw and the flag beside a competitor's name all read
     * `representing_tenant_id`, and a club can disown a claim it did not make —
     * so a REAL club is worth far more than a typed one, and this makes picking
     * it the easy path.
     *
     * Open, because a club's name, mark and country are already public: they are
     * on /explore and on every club's own page. What keeps it from being a
     * scraping door is the shape rather than a permission: two characters
     * minimum, ten results, throttled, and only the four fields a picker row
     * draws. The SLUG is the key it hands back — never the auto-increment id
     * (CLAUDE.md → Unpredictable Resource Identifiers) — and the id is resolved
     * from it server-side when the entry is made.
     */
    public function clubs(Request $request, ClubEvent $event, PublicEvent $publisher): JsonResponse
    {
        abort_if(! $publisher->isPublic($event), 404);

        /*
         * THIS EVENT'S clubs, not the platform's.
         *
         * It used to search every active club on TAKEONE, which made the entry
         * form a directory: somebody entering could claim any club in the
         * country, and the organiser found out when the start list said so.
         * The clubs taking part are decided on the Clubs screen — invited, or
         * written down — and this picker shows that decision and nothing else.
         *
         * Consequently there is no minimum query length any more: the list is
         * small and belongs to this competition, so showing all of it is the
         * helpful answer rather than a scraping door. A declined invitation is
         * left out; a club that said no is not competing.
         */
        $q = trim((string) $request->query('q', ''));

        $clubs = SandboxEventClub::with('tenant:id,slug,club_name,logo,country')
            ->where('event_id', $event->id)
            ->where('state', '!=', 'declined')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            'clubs' => $clubs->map(fn (SandboxEventClub $c) => [
                'name' => $c->tenant?->club_name ?: $c->name,
                // The event club's uuid IS the value: a club written down here
                // has no slug, because it has no tenant behind it.
                'slug' => $c->uuid,
                'country' => ($cc = $c->tenant?->country ?: $c->country) ? strtoupper($cc) : null,
                'logo' => ($logo = $c->tenant?->logo ?: $c->logo) ? file_url($logo) : null,
            ])->all(),
        ]);
    }

    /**
     * The request from somebody who signed in — the "yes, I have an account"
     * branch of the first question.
     *
     * A separate door from `store()` because it asks for something completely
     * different: no name, no email, no password, since all three are already on
     * file. Only what they compete AT, which belongs to the entry rather than
     * to the person.
     *
     * Not behind the `auth` middleware on purpose: a signed-out POST here gets
     * the same 404 every other closed door on this surface gives, rather than a
     * redirect to a login form that an XHR cannot follow.
     */
    public function storeMine(Request $request, ClubEvent $event, PublicEvent $publisher, PublicEntry $entries): JsonResponse
    {
        abort_if(! $publisher->isPublic($event), 404);

        $me = \Illuminate\Support\Facades\Auth::user();

        abort_if($me === null, 404);

        $data = $request->validate([
            // Never required — of anyone. A blank weight sends them to the
            // weigh-in desk, which is the authority anyway.
            'weight' => ['nullable', 'numeric', 'min:15', 'max:250'],
            'belt' => ['nullable', 'string', 'max:32'],
            /*
             * The priced extras they ticked, as option UUIDs only.
             *
             * Never an amount: what an option costs is the event's business and
             * is read server-side by EventFee::quote(). This door is open to
             * anyone with the link, so the request is treated as a list of
             * strings and nothing more — a key naming nothing, an option that
             * has been retired, or one belonging to another event is dropped
             * when the entry is priced.
             */
            'fee_options' => ['nullable', 'array', 'max:30'],
            'fee_options.*' => ['string', 'max:64'],
            // The club is per-ENTRY, not per-person, so it is asked even of
            // somebody whose profile is already on file: an athlete competes
            // for one club this weekend and another next year.
            'club_slug' => ['nullable', 'string', 'max:120'],
            'club_name' => ['nullable', 'string', 'max:120'],
        ]);

        $result = $entries->enrolExisting($event, $me, [
            'weight' => $data['weight'] ?? null,
            'belt' => $data['belt'] ?? null,
            'fee_options' => $data['fee_options'] ?? [],
            'club_slug' => $data['club_slug'] ?? null,
            'club_name' => $data['club_name'] ?? null,
        ], $request->ip());

        // The same envelope store() answers in, so the page has one contract to
        // read rather than two shapes that drift.
        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'state' => $result['state'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'state' => $result['state'],
            'division' => $result['division'],
        ]);
    }

    /** The request. Answered as JSON — the page is a flow, not a form post. */
    public function store(Request $request, ClubEvent $event, PublicEvent $publisher, PublicEntry $entries): JsonResponse
    {
        abort_if(! $publisher->isPublic($event), 404);

        $data = $request->validate([
            // The one thing every entry needs. Everything else about a person
            // may be blank (CLAUDE.md, "Who Fills The Form Decides").
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'max:200'],

            /*
             * The TELEPHONE NUMBER is the account, and the password opens it.
             *
             * Changed 2026-09-04 at the user's request: an athlete signs in
             * with the number they gave, not with an email address. So the
             * number became required and the email became optional, which is
             * the reverse of how this door started.
             *
             * `unique` is deliberately NOT enforced on the number. A telephone
             * belongs to a household — a parent enters two children on one
             * number at a junior competition, and this platform models
             * guardians and dependents because that is the ordinary case.
             * Sign-in resolves it by asking WHO after checking the password
             * (App\Http\Controllers\Auth\AuthenticatedSessionController::choose).
             */
            'mobile' => ['required', 'string', 'max:24'],
            'mobile_code' => ['required', 'string', 'max:8', 'regex:/^\+[0-9]{1,6}$/'],

            /*
             * Optional, and the ONLY way back into an account whose password
             * is forgotten — there is no SMS on this platform. The form says
             * so plainly rather than pretending it is a formality. `unique`
             * because the column is, and a friendly refusal beats a 500.
             */
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],

            // Never required — of anyone, on any form. Only the FORMAT is
            // enforced; a blank weight sends them to the weigh-in desk and a
            // blank birthdate simply leaves them unplaced by age.
            'birthdate' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:Male,Female'],
            'weight' => ['nullable', 'numeric', 'min:15', 'max:250'],
            'belt' => ['nullable', 'string', 'max:32'],
            /*
             * The priced extras they ticked, as option UUIDs only.
             *
             * Never an amount: what an option costs is the event's business and
             * is read server-side by EventFee::quote(). This door is open to
             * anyone with the link, so the request is treated as a list of
             * strings and nothing more — a key naming nothing, an option that
             * has been retired, or one belonging to another event is dropped
             * when the entry is priced.
             */
            'fee_options' => ['nullable', 'array', 'max:30'],
            'fee_options.*' => ['string', 'max:64'],

            // Added 2026-09-02. All optional, like everything except the name.
            //
            // The number and its dial code arrive SEPARATELY, from the shared
            // <x-country-code-dropdown>. `users.mobile` is an array-cast column
            // ({code, number}) and every screen on the platform reads it as
            // one — a bare string went in as a JSON scalar and rendered as
            // nothing on the member's own profile, which is how a phone number
            // could be "stored" and invisible at the same time.
            'nationality' => ['nullable', 'string', 'size:2', 'alpha'],
            // The club they compete for: a SLUG of a real club, or a typed
            // name for one that is not on this platform. Never both — the
            // template only ever sets one, and enrol() ignores the typed name
            // when a slug resolves.
            'club_slug' => ['nullable', 'string', 'max:120'],
            'club_name' => ['nullable', 'string', 'max:120'],

            // OPTIONAL again since 2026-09-05, at the user's request.
            //
            // It was required from 2026-09-02, and it turned out to be the
            // biggest wall in the form: a stranger on a phone had to take or
            // choose a photograph before they could get past the first screen,
            // and that is where people stopped. The photograph is still wanted
            // — the draw and the hall screens introduce a competitor with their
            // face — it is just no longer collected at the DOOR. They are asked
            // for it the moment they are in, on their own entry panel, which
            // can now edit it (and everything else) at leisure.
            //
            // Nothing about the SAFETY of it changed: bytes that are not an
            // image are still refused rather than quietly stored, the real MIME
            // is still sniffed from the decoded bytes by StoresBase64Images and
            // the extension is still assigned by us.
            //
            // Still nothing more trusted than a data URI: the real MIME is
            // sniffed from the decoded bytes and the extension assigned by
            // App\Traits\StoresBase64Images, never taken from this header. A
            // required photo makes that stricter rather than looser — bytes
            // that are not an image now REFUSE the entry instead of quietly
            // leaving somebody without a picture. 8 MB of base64 is roughly a
            // 6 MB image, far more than a 600x800 crop can ever be.
            'photo' => ['nullable', 'string', 'starts_with:data:image/', 'max:8388608'],
        ]);

        $result = $entries->enrol($event, [
            'full_name' => $data['full_name'],
            // Optional since 2026-09-04 — absent is normal now, not an error.
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'birthdate' => $data['birthdate'] ?? null,
            'gender' => $data['gender'] ?? null,
            'weight' => $data['weight'] ?? null,
            'belt' => $data['belt'] ?? null,
            'fee_options' => $data['fee_options'] ?? [],
            'mobile' => $data['mobile'] ?? null,
            'mobile_code' => $data['mobile_code'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'photo' => $data['photo'] ?? null,
            'club_slug' => $data['club_slug'] ?? null,
            'club_name' => $data['club_name'] ?? null,
        ], $request->ip());

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'field' => $result['field'] ?? null,
            ], 422);
        }

        /*
         * Sign them in, here, in the same request.
         *
         * They chose this password thirty seconds ago; making them type it
         * again to see the entry they just made is the sort of step this form
         * exists to remove. It is their own account, created in this request,
         * and the session is regenerated so nothing that came before it is
         * carried forward (session fixation).
         *
         * It also makes the landing possible: `my-entry` is behind `auth`, and
         * that panel is where the photograph and everything else the door no
         * longer asks for is collected.
         */
        $landing = route('testcode.e', ['event' => $event->uuid]);

        if (! empty($result['athlete_id'])) {
            $athlete = \App\Members\Models\User::find($result['athlete_id']);

            if ($athlete) {
                Auth::login($athlete, true);
                $request->session()->regenerate();
                $landing = route('testcode.e.my-entry', ['event' => $event->uuid]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'state' => $result['state'],
            'division' => $result['division'],
            // Where the page should go next. Never a URL from the request.
            'redirect' => $landing,
        ]);
    }

    /**
     * The belt vocabulary the form offers.
     *
     * The same list the claim page uses — a competitor picking their own rank
     * must not be shown a different ladder depending on which door they came
     * through. Whatever they pick, the desk confirms it on the day.
     *
     * @return array<int, array{v: string, label: string, hex: string}>
     */
    public static function belts(): array
    {
        return [
            ['v' => 'white', 'label' => __('events.belt_white'), 'hex' => '#e5e7eb'],
            ['v' => 'yellow', 'label' => __('events.belt_yellow'), 'hex' => '#facc15'],
            ['v' => 'green', 'label' => __('events.belt_green'), 'hex' => '#22c55e'],
            ['v' => 'blue', 'label' => __('events.belt_blue'), 'hex' => '#3b82f6'],
            ['v' => 'brown', 'label' => __('events.belt_brown'), 'hex' => '#92400e'],
            ['v' => 'black', 'label' => __('events.belt_black'), 'hex' => '#111827'],
        ];
    }
}
