<?php

namespace App\Scoreboard\Sports\BrazilianJiuJitsu\Controllers;

use App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenChannel;
use App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenDevice;
use App\Events\Sports\BrazilianJiuJitsu\Tournament\RunningOrder;
use App\Events\Support\EventAccess;
use App\Events\Support\EntryClub;
use App\Events\Support\ScreenMedia;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Members\Models\User;
use App\Events\Support\Cameras\MatCameraController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Ledger;
use App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\MatState;
use App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Scoring;

/**
 * The table at a Brazilian Jiu-Jitsu mat: one operator page, and the endpoints
 * behind it.
 *
 * The operator is the only source of truth while a match runs. They load a
 * match, start it, score it, and end it — and each of those is a command posted
 * here, applied by Scoring, recorded in the ledger, and pushed to the mat's
 * screens over MQTT. The screens never decide anything; they draw what they are
 * told.
 *
 * Authorisation is EventAccess::canScore on EVERY call, not just on the page:
 * the page is a convenience, the endpoint is the attack surface. A jury member
 * appointed to one event can score that event and nothing else.
 */
class ScoreboardController extends Controller
{
    use \App\Traits\StoresBase64Images;

    private const SPORT = 'bjj';

    public function __construct(private Scoring $scoring, private Ledger $ledger) {}

    /**
     * The operator page.
     *
     * Bound by the event's uuid, never its id — a scoring URL gets read off a
     * laptop screen at a mat, and a guessable one invites someone to try the
     * next number along.
     */
    public function control(Request $request, ClubEvent $event)
    {
        abort_unless($this->canScore($event), 403);
        abort_unless($event->sport === self::SPORT, 404);

        $mats = $event->matches()->whereNotNull('court')->distinct()->orderBy('court')->pluck('court')->values();
        $court = (string) $request->query('mat', $mats->first() ?? 'Mat 1');
        abort_unless($mats->isEmpty() || $mats->contains($court), 404);

        return $this->consoleView($event, $mats, $court, [
            'commandUrl' => route('bjj-scoreboard.command', $event->uuid),
            // Where this console re-reads itself after the socket says the mat
            // moved under it. Its own door, so the token console never has to
            // hold a session and this one never has to hold a token.
            'consoleStateUrl' => route('bjj-scoreboard.console-state', $event->uuid, false).'?mat='.rawurlencode($court),
            // A face for a corner, from the laptop as well as the tablet. The
            // console appends the side; the mat travels in the body, and the
            // endpoint refuses one this event does not run.
            // The same sounds panel, through the organiser's own authorisation
            // rather than a screen token: one console body, two front doors,
            // and the upload has to work through both or the feature is only
            // there for whoever happens to be holding the tablet.
            'audioUploadBase' => \Illuminate\Support\Str::beforeLast(
                route('me.events.screen-audio.store', [$event->uuid, 'x'], false), 'x'
            ),
            'photoUploadBase' => \Illuminate\Support\Str::beforeLast(
                route('bjj-scoreboard.photo', [$event->uuid, 'blue'], false), 'blue'
            ),
            // The cameras pointed at THIS mat, under the organiser's own
            // authorisation. The panel is shared with every other console; only
            // these two addresses differ per door.
            'cameraUrl' => route('me.events.mat-cameras', $event->uuid, false).'?mat='.rawurlencode($court),
            'cameraCommandBase' => \Illuminate\Support\Str::beforeLast(
                route('me.events.mat-cameras.command', [$event->uuid, 0], false), '0'
            ),
        ]);
    }

    /**
     * The console page itself — one body, two front doors.
     *
     * A signed-in organiser reaches it by event uuid; a paired scoring table
     * reaches it by its own device token. They must render the SAME page, or the
     * two drift and a mat behaves differently depending on how somebody opened
     * it. Only the addresses it posts to differ.
     *
     * Desktop and mobile are SEPARATE files (CLAUDE.md → Mobile / Desktop
     * Separation): the ten-inch tablet a referee holds beside the mat and the
     * laptop at the table are not the same screen with different breakpoints —
     * one is a thumb-reachable pair of scoring columns, the other is the full
     * three-column panel with the event log along the bottom.
     */
    private function consoleView(ClubEvent $event, $mats, string $court, array $urls)
    {
        $state = MatState::forMat($event, $court);
        $state->setRelation('event', $event);

        // The shared flag the DetectDevice middleware sets (`is_mobile` on the
        // request, `isMobile` in views). Read from the request rather than the
        // view bag so a token-paired console — which renders outside a normal
        // page context — resolves the same way an organiser's laptop does.
        $isMobile = (bool) request()->attributes->get('is_mobile', view()->shared('isMobile', false));

        // Phase M3: the React console, when the flag is on. It is a REPLACEMENT
        // for the document, not an addition to it — only one of the two ever
        // renders, they share one stylesheet, and both post to the endpoints
        // below. Turning the flag off restores this page exactly, mid-event.
        $react = (bool) config('features.react_scoreboard');

        $view = $react
            ? ($isMobile ? 'scoreboard::bjj.scoreboard.react.control-mobile'
                         : 'scoreboard::bjj.scoreboard.react.control')
            : ($isMobile ? 'scoreboard::bjj.scoreboard.mobile.control'
                         : 'scoreboard::bjj.scoreboard.desktop.control');

        return view($view, [
            'event' => $event,
            'mats' => $mats,
            'court' => $court,
            'state' => $state->present(),
            // The officiating log, newest first. It never leaves this page: an
            // entry names the operator who made it.
            'log' => $this->ledger->timeline($event, $court, $state->match_id),
            'queue' => $this->queue($event, $court),
            // What each slot already holds, so the panel opens showing the
            // event's real sounds rather than ten empty rows.
            'audioSlots' => collect(ScreenMedia::forEvent($event))
                ->map(fn ($m) => ['name' => $m->original_name, 'bytes' => $m->bytes])
                ->all(),
            'pointSources' => Ledger::POINT_SOURCES,
            'penaltyReasons' => Ledger::PENALTY_REASONS,
            'winMethods' => MatState::WIN_METHODS,
            'screens' => ScreenDevice::where('event_id', $event->id)
                ->where('court', $court)->whereNull('revoked_at')->count(),

            // The console's live link, on the MAT's topic rather than a
            // device's — see ScreenChannel::matTopic. Null when realtime is off,
            // and the console then behaves exactly as it did before this
            // existed: it draws whatever its own commands hand back.
            //
            // This is what makes a second console possible at all. The two
            // surfaces at a mat are the laptop at the table and the tablet in
            // the referee's hand, and until now neither heard the other: a point
            // scored on one appeared on the other only when somebody happened to
            // press something. The mat's screens had a socket; the table did
            // not.
            'screenLink' => ($link = ScreenChannel::consoleCredentials($event, $court)) === null ? null
                : $link + ['console_url' => $urls['consoleStateUrl']],
            // Feeds the country picker. The app already keeps this list; the
            // picker searches it client-side because it is 200 rows, not 20,000.
            'countries' => \App\Support\Countries::all(),
        ] + \Illuminate\Support\Arr::except($urls, ['consoleStateUrl']) + [
            // Defaults last: `+` on arrays keeps the LEFT value, so a door that
            // passed camera addresses keeps them and one that did not renders
            // no camera panel rather than an undefined variable.
            'cameraUrl' => null,
            'cameraCommandBase' => null,
        ]);
    }

    /**
     * The scoring table as a PAIRED SCREEN — no session, the device token is
     * the whole identity.
     *
     * Because the alternative is worse. A tablet at a mat is handed between
     * officials for eight hours; signing in on it means a session belonging to
     * one named person left unattended in a public hall, with rights over
     * everything that person can reach in the product. A screen token reaches
     * exactly one thing.
     *
     * What bounds it:
     *  · ONE event and ONE mat, both read off the device record. There is no
     *    event and no court in this URL to tamper with, and the mat a command
     *    names is overwritten with the device's own below.
     *  · Only if it was PAIRED as a control, by an organiser who could score.
     *  · Re-checked on EVERY request against the pairing organiser: take that
     *    person off the jury and every console they paired stops scoring, at
     *    once, without anybody visiting the hall.
     *  · Unpairing or revoking the screen ends it immediately.
     */
    public function tokenControl(Request $request, string $token)
    {
        // A screen bolted to a wall must never be stranded somewhere it cannot
        // leave: if this console will not open, send it to its own board
        // address, which knows how to show a pairing code and wait.
        if (! $this->canOpenControl($token)) {
            return redirect()->route('bjj-screen.board', $token);
        }

        [$device, $event] = $this->controlDevice($token);

        $mats = $event->matches()->whereNotNull('court')->distinct()->orderBy('court')->pluck('court')->values();

        return $this->consoleView($event, $mats, $device->court, [
            'commandUrl' => route('bjj-scoreboard.token-command', $token),
            'consoleStateUrl' => route('bjj-scoreboard.token-console-state', $token, false),
            // A paired console is a SCREEN, and the organiser's panel lists it
            // beside the boards with a live dot. Commands alone would show a mat
            // waiting twenty minutes for the next match as offline — the
            // opposite of the truth, and exactly when an organiser checks.
            'heartbeatUrl' => route('bjj-screen.status', $token, false),
            // Uploading a sound from the table itself. A prefix, with the
            // console appending the slot — see the note on the board's audio
            // base for why these are not built with a placeholder.
            'audioUploadBase' => \Illuminate\Support\Str::beforeLast(
                route('bjj-scoreboard.token-audio', [$token, 'x'], false), 'x'
            ),
            'photoUploadBase' => \Illuminate\Support\Str::beforeLast(
                route('bjj-scoreboard.token-photo', [$token, 'blue'], false), 'blue'
            ),
            // The same camera panel, through the table's own token: the mat is
            // the DEVICE's, so there is nothing in these addresses to tamper
            // with.
            'cameraUrl' => route('bjj-scoreboard.token-cameras', $token, false),
            'cameraCommandBase' => \Illuminate\Support\Str::beforeLast(
                route('bjj-scoreboard.token-cameras.command', [$token, 0], false), '0'
            ),
        ]);
    }

    /** A command from a paired console. Same body, its own front door. */
    public function tokenCommand(Request $request, string $token): JsonResponse
    {
        [$device, $event] = $this->controlDevice($token);

        // The device's mat, never the request's. A console paired to Mat 2
        // cannot score Mat 1 by editing its own payload.
        $request->merge(['mat' => $device->court]);

        return $this->command($request, $event);
    }

    /**
     * Apply one command and tell the mat.
     *
     * Returns the whole new state rather than an acknowledgement, so the page
     * that issued it patches from the same payload every other screen gets —
     * one shape, no second code path that could disagree.
     */
    public function command(Request $request, ClubEvent $event): JsonResponse
    {
        abort_unless($this->canScore($event), 403);
        abort_unless($event->sport === self::SPORT, 404);

        $data = $request->validate([
            'mat' => ['required', 'string', 'max:40'],
            'command' => ['required', 'string', 'in:'.implode(',', Scoring::COMMANDS)],
            'side' => ['nullable', 'string', 'in:blue,white'],

            // NOT a point value. The console names the ACTION and the server
            // prices it (Ledger::POINT_SOURCES) — see the note in Scoring.
            'source' => ['nullable', 'string', 'max:32', 'alpha_dash'],

            'match_id' => ['nullable', 'integer'],
            'ledger_id' => ['nullable', 'integer'],
            // A correction must say why, and the reason reaches the record.
            'reason' => ['nullable', 'string', 'max:200'],

            'seconds' => ['nullable', 'numeric', 'min:10', 'max:3600'],
            'minutes' => ['nullable', 'numeric', 'min:0.1', 'max:60'],
            'remaining' => ['nullable', 'numeric', 'min:0', 'max:3600'],

            'phase' => ['nullable', 'string', 'in:start,cancel,apply'],

            // The rules this mat runs. Validated here as well as inside Scoring
            // because this endpoint is the contract and the console is only a
            // convenience — anything else posting here is held to the same
            // vocabulary.
            'warning' => ['nullable', 'numeric', 'min:0', 'max:3600'],
            'penalty_limit' => ['nullable', 'integer', 'min:1', 'max:10'],
            'penalty_warn_at' => ['nullable', 'integer', 'min:1', 'max:10'],
            'stall_seconds' => ['nullable', 'integer', 'min:3', 'max:60'],
            'referee_decision' => ['nullable', 'boolean'],
            'time_up_buzzer' => ['nullable', 'boolean'],
            'theme' => ['nullable', 'string', 'in:arena,venue'],

            // Ending a match. Held to the same closed vocabulary the engine is.
            'winner' => ['nullable', 'string', 'in:blue,white'],
            'method' => ['nullable', 'string', 'in:'.implode(',', MatState::WIN_METHODS)],
            'note' => ['nullable', 'string', 'max:200'],

            // Free text an official types at the table; it lands on a public
            // screen, so it is length-capped here and escaped there.
            'name' => ['nullable', 'string', 'max:60'],
            'club' => ['nullable', 'string', 'max:60'],
            'country' => ['nullable', 'string', 'max:60'],
            'flag' => ['nullable', 'string', 'max:2'],
            'tournament' => ['nullable', 'string', 'max:80'],
            'division' => ['nullable', 'string', 'max:60'],
            'matchNo' => ['nullable', 'string', 'max:12'],
            'courtLabel' => ['nullable', 'string', 'max:20'],
            'stage' => ['nullable', 'string', 'max:40'],
            'referee' => ['nullable', 'string', 'max:60'],
            'ruleset' => ['nullable', 'string', 'max:40'],
        ]);

        // The mat must be one this event actually runs — not a string the caller
        // made up, which would otherwise mint a state row at will.
        abort_unless($this->matExists($event, $data['mat']), 404);

        try {
            $state = $this->scoring->apply($event, $data['mat'], $data['command'], $data, $this->actingUser());
        } catch (\RuntimeException $e) {
            // A refusal the official needs to read and act on. Not a fault.
            //
            // The CURRENT state rides along with it: a console that is refused
            // is very often a console that has fallen behind — a second tab, a
            // page left open while the mat moved on — and handing back the truth
            // lets it correct itself on the spot.
            $fresh = MatState::forMat($event, $data['mat']);
            $fresh->setRelation('event', $event);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'state' => $fresh->present(),
                'log' => $this->ledger->timeline($event, $data['mat'], $fresh->match_id),
                'queue' => $this->queue($event, $data['mat']),
            ], 422);
        }

        $payload = $state->present();

        // Three commands change WHICH match is on the mat, and all three move
        // the queue behind it. COMMIT goes to every mat, the other two only to
        // this one: a result carries its winner into a next-round slot the
        // scheduler is free to have placed on another mat, and that mat's board
        // is now announcing a corner that is no longer "to be decided".
        if (in_array($data['command'], ['load', 'commit', 'clear'], true)) {
            ScreenChannel::notifyCourt($event, $data['command'] === 'commit' ? null : $data['mat']);
        }

        // Every screen on this mat, immediately. Best-effort like every other
        // push in the app: the database is the truth and a screen that missed
        // this re-fetches on reconnect.
        ScreenChannel::notifyCourt($event, $data['mat'], ['action' => 'mat', 'state' => $payload]);

        // The sledgehammer, and the reason it exists: a screen on a wall has no
        // keyboard and nobody standing at it. Sent AFTER the state, so a screen
        // that is merely behind has already been corrected by the cheap message.
        if ($data['command'] === 'resync') {
            ScreenChannel::notifyCourt($event, $data['mat'], ['action' => 'reload']);
        }

        return response()->json([
            'success' => true,
            'state' => $payload,
            // The log comes back with every command, because every command is
            // in it: that is what "audit, not erase" means from the operator's
            // side — they can see what they just did, and undo it by naming it.
            'log' => $this->ledger->timeline($event, $data['mat'], $state->match_id),
            'queue' => $this->queue($event, $data['mat']),
            // The referee's stalling countdown, which travels ONLY here. It is
            // deliberately absent from present(): a wall must never be told a
            // penalty is coming before the referee has decided to give one.
            'stall' => $this->stallOf($state),
        ]);
    }

    /**
     * The whole console, re-read.
     *
     * A console is not a screen: a screen draws one payload, a console draws
     * three — the state, the officiating log and the queue behind it. So when
     * the socket says this mat moved (a bout committed on the other console, the
     * running order behind it re-flowed), the console cannot patch from the
     * board payload a screen would get. It asks for its own, which is the
     * refresh-signal shape CLAUDE.md prescribes for exactly this case: the same
     * change renders differently per surface, so send a nudge and let each
     * surface re-read what it may see.
     *
     * Read-only, and authorised identically to the command endpoint beside it —
     * this returns nothing an operator could not already POST for.
     */
    public function consoleState(Request $request, ClubEvent $event): JsonResponse
    {
        abort_unless($this->canScore($event), 403);
        abort_unless($event->sport === self::SPORT, 404);

        $court = (string) $request->query('mat', '');
        abort_unless($court !== '' && $this->matExists($event, $court), 404);

        return $this->consolePayload($event, $court);
    }

    /** The same read, through a paired scoring table's own door. */
    public function tokenConsoleState(Request $request, string $token): JsonResponse
    {
        [$device, $event] = $this->controlDevice($token);

        return $this->consolePayload($event, $device->court);
    }

    /**
     * The three things a console draws, in the shape its command responses
     * already use — so the page has one way to absorb an update, not two.
     */
    private function consolePayload(ClubEvent $event, string $court): JsonResponse
    {
        $state = MatState::forMat($event, $court);
        $state->setRelation('event', $event);

        return response()->json([
            'success' => true,
            'state' => $state->present(),
            'log' => $this->ledger->timeline($event, $court, $state->match_id),
            'queue' => $this->queue($event, $court),
            'stall' => $this->stallOf($state),
        ]);
    }

    /**
     * The referee's stalling countdown, or null.
     *
     * Per MAT rather than per console: it lives on the state row, so the second
     * official's console shows the same count the first one started. It still
     * never reaches a wall — present() does not carry it, and this is only ever
     * returned to a surface entitled to score.
     *
     * @return array{side: string, until: string}|null
     */
    private function stallOf(MatState $state): ?array
    {
        return $state->stall_side && $state->stall_until?->isFuture()
            ? ['side' => $state->stall_side, 'until' => $state->stall_until->toIso8601String()]
            : null;
    }

    /**
     * The mat's current state, for a screen that has just loaded or reconnected.
     *
     * Authorised by the DEVICE's token rather than a session — a wall screen has
     * nobody signed in to it. The token already names one event and one mat, so
     * there is nothing in this URL to tamper with.
     */
    public function state(Request $request, string $token): JsonResponse
    {
        $device = ScreenDevice::resolve($token);

        abort_unless($device && $device->isClaimed() && $device->event, 404);

        $device->touchSeen();

        $state = MatState::forMat($device->event, $device->court);
        $state->setRelation('event', $device->event);

        return response()->json($state->present());
    }

    /**
     * A face for whoever is in one of the corners RIGHT NOW.
     *
     * Scoped to the match on this mat, not to an arbitrary entry id: the only
     * two competitors this endpoint may photograph are the two standing in
     * front of it. That is both the useful case — the athlete is right there —
     * and the narrow one.
     */
    public function photo(Request $request, ClubEvent $event, string $side): JsonResponse
    {
        abort_unless($this->canScore($event), 403);
        abort_unless($event->sport === self::SPORT, 404);
        abort_unless(in_array($side, ['blue', 'white'], true), 404);

        $court = (string) $request->input('mat', '');
        abort_unless($court !== '' && $this->matExists($event, $court), 404);

        return $this->storeCornerPhoto($request, $event, $court, $side);
    }

    /** The same face, through a paired scoring table's own door. */
    public function tokenPhoto(Request $request, string $token, string $side): JsonResponse
    {
        abort_unless($this->canOpenControl($token), 403);
        abort_unless(in_array($side, ['blue', 'white'], true), 404);

        [$device, $event] = $this->controlDevice($token);

        return $this->storeCornerPhoto($request, $event, $device->court, $side);
    }

    private function storeCornerPhoto(Request $request, ClubEvent $event, string $court, string $side): JsonResponse
    {
        // Either a new face, or the instruction to take the one there away — a
        // badly framed photo is on the wall until somebody can clear it, and
        // "upload a better one" is not a way to clear anything.
        $remove = $request->boolean('remove');

        $request->validate([
            'image' => [$remove ? 'nullable' : 'required', 'string', 'starts_with:data:image/'],
        ]);

        $state = MatState::forMat($event, $court);
        $state->setRelation('event', $event);

        abort_unless($state->match_id, 422);

        $match = EventMatch::where('event_id', $event->id)->find($state->match_id);
        $registrationId = $match?->{$side === 'blue' ? 'a_competitor_id' : 'b_competitor_id'};

        // A corner filled in by hand — a name typed at the table with no entry
        // behind it — has nothing to attach a photo to. Said plainly rather than
        // failing silently.
        if (! $registrationId) {
            return response()->json([
                'success' => false,
                'message' => __('scoreboard::bjj_messages.photo_no_entry'),
            ], 422);
        }

        $registration = ClubEventRegistration::where('event_id', $event->id)->find($registrationId);
        abort_unless($registration, 404);

        $previous = $registration->photo;

        if ($remove) {
            $registration->update(['photo' => null]);

            if ($previous) {
                // Through EntryPhoto, never a raw delete: an entry settled at
                // the public door points at the athlete's OWN profile picture,
                // and removing the event photo here used to take their face off
                // the disk while users.profile_picture carried on naming it.
                \App\Events\Support\EntryPhoto::discard($previous);
            }

            return $this->photoResponse($event, $court, $side, null, __('scoreboard::bjj_messages.photo_removed'));
        }

        // Real bytes, server-assigned extension, SVG refused — see the
        // StoresBase64Images trait. The folder is built by the app from the
        // event's public id, never passed through from the client.
        $path = $this->storeBase64Image(
            $request->input('image'),
            'events/'.$event->uuid.'/competitors',
            'c'.$registration->id.'-'.\Illuminate\Support\Str::random(16),
        );

        if ($path === null) {
            return response()->json([
                'success' => false,
                'message' => __('personal.event_photo_rejected'),
            ], 422);
        }

        $registration->update(['photo' => $path]);

        if ($previous && $previous !== $path) {
            // See above: this file may be the athlete's profile picture rather
            // than an entry photo this console owns.
            \App\Events\Support\EntryPhoto::discard($previous);
        }

        return $this->photoResponse($event, $court, $side, file_url($path), __('personal.event_photo_saved'));
    }

    /**
     * Push the corner the registration behind it just changed.
     *
     * 'resync' is the defined no-op: it changes nothing, saves the state as it
     * stands, and hands it back — which is exactly what is needed to rebuild a
     * corner from its registration and put it on the wall.
     */
    private function photoResponse(ClubEvent $event, string $court, string $side, ?string $photo, string $message): JsonResponse
    {
        $fresh = $this->scoring->apply($event, $court, 'resync', [], $this->actingUser());
        $payload = $fresh->present();

        ScreenChannel::notifyCourt($event, $court, ['action' => 'mat', 'state' => $payload]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'photo' => $photo,
            'side' => $side,
            'state' => $payload,
        ]);
    }

    /* ---------------- Token plumbing ---------------- */

    /**
     * The cameras on THIS console's own mat — the paired-tablet door.
     *
     * The signed-in organiser reaches the same panel by event uuid
     * (`me.events.mat-cameras`); a table paired to a mat reaches it here. Both
     * run the same body, so the two cannot drift into two different camera
     * panels — see MatCameraController.
     *
     * The mat is the DEVICE's, never the request's, exactly as it is for a
     * scoring command: a table paired to Mat 2 has no way to phrase a question
     * about Mat 1's cameras. `controlDevice` re-checks on every request that
     * the organiser who paired this table may still score, so removing them
     * from the jury takes the camera controls away with everything else.
     */
    public function tokenCameras(Request $request, string $token): JsonResponse
    {
        [$device, $event] = $this->controlDevice($token);

        return MatCameraController::panelFor($event, (string) $device->court);
    }

    /** An order to one camera on this console's mat. Same body, its own door. */
    public function tokenCameraCommand(Request $request, string $token, int $camera): JsonResponse
    {
        [$device, $event] = $this->controlDevice($token);

        return MatCameraController::apply($request, $event, (string) $device->court, $camera);
    }

    /**
     * Would tokenControl() open for this token? Asked before rendering, so a
     * refusal becomes a redirect to something drawable instead of an error.
     * Mirrors controlDevice() exactly — if the two ever disagree, a screen
     * bounces, so they are written to be read side by side.
     */
    private function canOpenControl(string $token): bool
    {
        $device = ScreenDevice::resolve($token);

        if (! $device || ! $device->isClaimed() || ! $device->event
            || $device->surface !== 'control' || ! $device->court
            || $device->event->sport !== self::SPORT) {
            return false;
        }

        $by = $device->created_by ? User::find($device->created_by) : null;

        return $by !== null && app(EventAccess::class)->canScore($device->event, $by);
    }

    /**
     * Resolve a screen entitled to score this mat, or refuse.
     *
     * One answer for every way this can fail — a bad token, a display-only
     * screen, an unpaired one, a pairing organiser who has since lost the right
     * to score. A console in a hall is looked at by strangers, and telling them
     * which of those it was is telling them what to try next.
     *
     * @return array{0: ScreenDevice, 1: ClubEvent}
     */
    private function controlDevice(string $token): array
    {
        $device = ScreenDevice::resolve($token);

        abort_unless(
            $device
            && $device->isClaimed()
            && $device->event
            && $device->surface === 'control'
            && $device->court
            && $device->event->sport === self::SPORT,
            404
        );

        $by = $device->created_by ? User::find($device->created_by) : null;

        abort_unless($by && app(EventAccess::class)->canScore($device->event, $by), 403);

        // From here on this request acts AS that organiser — the same checks,
        // the same scope, and the same name against every ledger row it writes.
        $this->actor = $by;

        $device->touchSeen();

        return [$device, $device->event];
    }

    /* ---------------- Helpers ---------------- */

    /**
     * Who this request is acting as. Set only by controlDevice(), when a paired
     * screen is standing in for the organiser who paired it.
     */
    private ?User $actor = null;

    private function actingUser(): ?User
    {
        return $this->actor ?: Auth::user();
    }

    private function canScore(ClubEvent $event): bool
    {
        $user = $this->actingUser();

        return $user !== null && app(EventAccess::class)->canScore($event, $user);
    }

    private function matExists(ClubEvent $event, string $court): bool
    {
        return $event->matches()->where('court', $court)->exists();
    }

    /**
     * What is still to come on this mat, so the operator loads the next match
     * rather than typing two names into a form.
     *
     * A match still waiting on a feeder is LISTED rather than hidden — the
     * operator needs to see what is coming — but it cannot be loaded, because
     * nobody can be introduced as "winner of match 3".
     */
    private function queue(ClubEvent $event, string $court): array
    {
        $order = new RunningOrder;

        $queue = $order->matQueue($event, $court)->take(12);
        $queue->load('category:id,name,weight_class');

        $entryIds = $queue->flatMap(fn (EventMatch $m) => [$m->a_competitor_id, $m->b_competitor_id])
            ->filter()->unique()->values()->all();

        $entries = $entryIds
            ? ClubEventRegistration::where('event_id', $event->id)
                ->whereIn('id', $entryIds)
                // ⚠️ `nationality` is load-bearing, not decoration: countryCode()
                // falls back to it for an athlete representing no club, and a
                // constrained eager load that omits the column makes that
                // fallback silently return null for everybody. Same trap as the
                // `updated_at` note in CLAUDE.md.
                ->with(['user:id,full_name,name,gender,nationality,profile_picture,profile_picture_is_public',
                    // `logo` is selected for the same reason `nationality` is:
                    // a constrained eager load that omits a column makes every
                    // read of it silently null, and the crest beside a club's
                    // name simply never appeared.
                    'user.memberClubs:id,club_name,logo,country',
                    'representingTenant:id,club_name,logo,country'])
                ->get()->keyBy('id')
            : collect();

        // The clubs the organiser WROTE DOWN for this event — teams the platform
        // has never met. Most of a real competition is these, and without them
        // the running order showed a club for the handful of entrants attached
        // to a tenant and nothing at all for everybody else.
        $written = EntryClub::forMany($event, $entryIds);

        // The club they COMPETE FOR, and its country — never the person's own
        // nationality. Same rule as the corners on the board.
        $sideOf = function (?int $id) use ($entries, $written): array {
            $reg = $id ? $entries->get($id) : null;
            $club = $reg?->competingClub();
            $user = $reg?->user;

            // A tenant wins when the entry names one — it is the stronger claim
            // and it carries a country and a page. Otherwise the written-down
            // club stands in, name and crest alike.
            $named = $club
                ? ['name' => $club->club_name, 'logo' => $club->logo ? file_url($club->logo) : null, 'country' => $club->country]
                : ($id ? ($written[$id] ?? null) : null);

            // The club's country first, then countryCode() — purely additive,
            // so nobody who had a flag loses one. Each candidate is checked for
            // a real ISO-2 code because a club's `country` is not always stored
            // as one ("Bahrain" would ask flagcdn for /bahrain.png and get
            // nothing back).
            $iso = collect([$named['country'] ?? null, $reg?->countryCode()])
                ->first(fn ($c) => preg_match('/^[A-Za-z]{2}$/', (string) $c));
            $iso = $iso ? strtolower((string) $iso) : null;

            // ⚠️ The flag comes from ClubEventRegistration::countryCode(), which
            // is the ONE place that rule lives — never from the club alone.
            //
            // The club's country still comes first: an athlete representing a
            // Bahraini club is on the sheet as Bahrain whatever their passport
            // says. But 16 of this event's 34 entrants represent no club at
            // all, and deriving the flag from `$club?->country` gave every one
            // of them nothing — so two competitors in the same bout, entered
            // the same way, showed one flag between them. countryCode() falls
            // back to the entry's own country and then the athlete's
            // nationality, which is what the board was already meant to do.
            return [
                'club' => $named['name'] ?? '',
                'logo' => $named['logo'] ?? null,
                // The club's country first, then countryCode() — purely
                // additive, so nobody who had a flag loses one. Each candidate
                // is checked for a real ISO-2 code because a club's `country`
                // is not always stored as one ("Bahrain" would ask flagcdn for
                // /bahrain.png and get nothing).
                'flag' => $iso,
                'photo' => $reg?->photo
                    ? file_url($reg->photo)
                    : (($user?->profile_picture && $user->profile_picture_is_public)
                        ? file_url($user->profile_picture)
                        : null),
                'fallback' => \App\Support\Avatar::placeholder($user?->gender),
            ];
        };

        return $queue->map(function (EventMatch $m) use ($order, $sideOf) {
            $blue = $sideOf($m->a_competitor_id);
            $white = $sideOf($m->b_competitor_id);

            return [
                'id' => $m->id,
                'number' => $m->match_no,
                // The SAME two fields the wall board prints, or the operator and
                // the hall read different things off the same match.
                'stage' => $m->round ?: $m->phase,
                'division' => $m->category?->weight_class ?: $m->category?->name,
                'blue' => ['name' => $m->a_name ?: null] + $blue,
                'white' => ['name' => $m->b_name ?: null] + $white,
                'runnable' => $order->isRunnable($m),
            ];
        })->values()->all();
    }

    /* ── The event's sounds, uploaded from the table ─────────────────────────
       The same feature the organiser gets through their own session door
       (me.events.screen-audio.*); this is the paired tablet, whose whole
       identity is its token. Both write the one shared ScreenMedia store, so a
       track uploaded from either place plays on every board of this event. */

    public function tokenAudio(Request $request, string $token, string $slot): JsonResponse
    {
        abort_unless($this->canOpenControl($token), 403);

        [, $event] = $this->controlDevice($token);

        $request->validate([
            'file' => ['required', 'file', 'max:'.(ScreenMedia::MAX_BYTES / 1024)],
        ]);

        // ScreenMedia decides the slot, the real mime from the bytes, the disk
        // and the stored name — nothing here trusts the upload's own filename.
        $media = ScreenMedia::put($event, $slot, $request->file('file'), $this->actingUser()?->id);

        if (! $media) {
            return response()->json([
                'success' => false,
                'message' => __('events.screen_audio_rejected'),
            ], 422);
        }

        // Every screen on this event reloads, which is how they pick the new
        // file up: the audio elements are built once per page and cache what
        // they fetched, so a replaced track would otherwise keep playing the
        // old one until somebody power-cycled the wall.
        ScreenChannel::notifyCourt($event, null, ['action' => 'reload']);

        return response()->json([
            'success' => true,
            'message' => __('events.screen_audio_saved'),
            'slot' => $slot,
            'name' => $media->original_name,
            'bytes' => $media->bytes,
        ]);
    }

    /** Take a sound away again, from the same place it was uploaded. */
    public function tokenAudioDestroy(string $token, string $slot): JsonResponse
    {
        abort_unless($this->canOpenControl($token), 403);

        [, $event] = $this->controlDevice($token);

        // The file goes before the row (CLAUDE.md → Delete Files Before
        // Records); purge() is what owns that order.
        if ($media = ScreenMedia::slot($event, $slot)) {
            $media->purge();
        }

        ScreenChannel::notifyCourt($event, null, ['action' => 'reload']);

        return response()->json(['success' => true, 'message' => __('events.screen_audio_removed'), 'slot' => $slot]);
    }

}
