<?php

namespace App\Scoreboard\Sports\Karate\Controllers;

use App\Scoreboard\Sports\Karate\HallScreen\CourtDisplayDevice;
use App\Scoreboard\Sports\Karate\HallScreen\ScreenChannel;
use App\Events\Sports\Karate\Tournament\RunningOrder;
use App\Events\Support\EventAccess;
use App\Events\Support\ScreenMedia;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Events\Support\Cameras\MatCameraController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Scoreboard\Sports\Karate\Mat\MatState;
use App\Scoreboard\Sports\Karate\Mat\Scoring;

/**
 * The table at a Karate mat: one operator page, and the two endpoints behind it.
 *
 * The operator is the only source of truth while a bout runs. They load a bout,
 * call hajime, score it, and end it — and each of those is a command posted
 * here, applied by Scoring, and pushed to the mat's screens over MQTT. The
 * screens never decide anything; they draw what they are told.
 *
 * Authorisation is EventAccess::canScore on EVERY call, not just on the page:
 * the page is a convenience, the endpoint is the attack surface. A jury member
 * appointed to one event can score that event and nothing else.
 */
class ScoreboardController extends Controller
{
    use \App\Traits\StoresBase64Images;

    public function __construct(private Scoring $scoring) {}

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
        abort_unless($event->sport === 'karate', 404);

        $mats = $event->matches()->whereNotNull('court')->distinct()->orderBy('court')->pluck('court')->values();
        $court = (string) $request->query('mat', $mats->first() ?? 'Mat 1');
        abort_unless($mats->isEmpty() || $mats->contains($court), 404);

        return $this->consoleView($event, $mats, $court, [
            'commandUrl' => route('karate-scoreboard.command', $event->uuid),
            // The same panel, through the organiser's own authorisation rather
            // than a screen token: one console body, two front doors, and the
            // upload has to work through both or the feature is only there for
            // whoever happens to be holding the tablet.
            'audioUploadBase' => \Illuminate\Support\Str::beforeLast(
                route('me.events.screen-audio.store', [$event->uuid, 'x'], false), 'x'
            ),
            // A face for a corner, from the laptop as well as the tablet. The
            // console appends the side; the mat travels in the body, and the
            // endpoint refuses one this event does not run.
            'photoUploadBase' => \Illuminate\Support\Str::beforeLast(
                route('karate-scoreboard.photo', [$event->uuid, 'aka'], false), 'aka'
            ),
            // The cameras pointed at THIS mat, from the organiser's own
            // authorisation. The panel is shared with every console; only the
            // two addresses differ per door.
            'cameraUrl' => route('me.events.mat-cameras', $event->uuid, false).'?mat='.rawurlencode($court),
            'cameraCommandBase' => \Illuminate\Support\Str::beforeLast(
                route('me.events.mat-cameras.command', [$event->uuid, 0], false), '0'
            ),
        ], $request->boolean('adjust'), $this->packagePanel($event, $court));
    }

    /**
     * The panel the event's own package contributes to this console, if any.
     *
     * Signed-in operator only. A scoring table paired by DEVICE TOKEN has no
     * user behind it, and the writes such a panel makes are member-authorised
     * endpoints — so rather than open a token-authorised way to put arbitrary
     * names on a mat, that door simply does not get a panel.
     */
    private function packagePanel(ClubEvent $event, string $court): ?array
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        return app(\App\Events\EventTypeRegistry::class)->for($event)->matPanel($event, $court, $user);
    }

    /**
     * The console page itself — one body, two front doors.
     *
     * A signed-in organiser reaches it by event uuid; a paired scoring table
     * reaches it by its own device token. They must render the SAME page, or
     * the two drift and a mat behaves differently depending on how somebody
     * opened it. Only the addresses it posts to differ.
     */
    private function consoleView(ClubEvent $event, $mats, string $court, array $urls, bool $showTimeAdjust = false, ?array $matPanel = null)
    {
        return view('scoreboard::karate.scoreboard.control', [
            'event' => $event,
            'mats' => $mats,
            'court' => $court,
            'state' => MatState::load($event, $court)->toArray(),
            'queue' => $this->queue($event, $court),
            'screens' => CourtDisplayDevice::where('event_id', $event->id)
                ->where('court', $court)->whereNull('revoked_at')->count(),
            // The design's "Scoreboard ↗" link. Only rendered when a screen is
            // actually paired to this mat — the token IS the screen's identity,
            // so this is a real board, not a preview.
            'screenUrl' => null,
            // Feeds the country picker. The app already keeps this list; the
            // picker searches it client-side because it is 200 rows, not 20,000.
            'countries' => $this->countries(),
            // The approved layout ships the clock nudges hidden, because the
            // console is sized to land inside 1080 and that row is what tips it
            // over. ?adjust=1 brings them back for a screen with room.
            'showTimeAdjust' => $showTimeAdjust,
            // Which of this event's sounds are already uploaded, so the panel
            // says what is set without the console having to fetch anything.
            // Names only — the console never sees a path.
            // A panel the event's own PACKAGE contributes to this console
            // (AbstractEventType::matPanel). Null for every championship,
            // which renders nothing and leaves this page as it was. An open
            // mat uses it to set the next pair without the operator ever
            // leaving the scoreboard.
            'matPanel' => $matPanel,
            'audioSlots' => collect(ScreenMedia::forEvent($event))
                ->map(fn ($m) => ['name' => $m->original_name, 'bytes' => $m->bytes])
                ->all(),
        ] + $urls + [
            // Last, so a door that DID pass camera addresses keeps them: `+`
            // on arrays keeps the left-hand value, so defaults must sit to the
            // right of the thing they are defaulting for. A door that passed
            // none renders no camera tab rather than an undefined variable.
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
     * ── What bounds it ──────────────────────────────────────────────────────
     *
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
        // leave. If this console will not open — the screen was unpaired, or
        // revoked, or re-purposed to a board, or the organiser who paired it
        // has since lost the right to score — send it to its own board address,
        // which knows how to show a pairing code and wait to be adopted again.
        //
        // Loop-safe by construction: the board only redirects BACK to here when
        // it has already checked the same conditions, so a refusal here means
        // the board will draw rather than bounce.
        if (! $this->canOpenControl($token)) {
            return redirect()->route('karate-court-display.board', $token);
        }

        [$device, $event] = $this->controlDevice($token);

        $mats = $event->matches()->whereNotNull('court')->distinct()->orderBy('court')->pluck('court')->values();

        return $this->consoleView($event, $mats, $device->court, [
            'commandUrl' => route('karate-scoreboard.token-command', $token),
            // A paired console is a SCREEN, and the console lists it beside the
            // boards with a live dot. Commands alone would show a mat waiting
            // twenty minutes for the next bout as offline — the opposite of the
            // truth, and exactly when an organiser checks. So it beats.
            'heartbeatUrl' => route('karate-court-display.status', $token, false),
            // Uploading from the table itself. Prefixes, with the console
            // appending the slot or the side — see the note on the board's audio
            // base for why these are not built with a placeholder.
            'audioUploadBase' => \Illuminate\Support\Str::beforeLast(
                route('karate-scoreboard.token-audio', [$token, 'x'], false), 'x'
            ),
            'photoUploadBase' => \Illuminate\Support\Str::beforeLast(
                route('karate-scoreboard.token-photo', [$token, 'aka'], false), 'aka'
            ),
            // The same camera panel, through the table's own token. The mat is
            // the DEVICE's, so there is nothing in these addresses to tamper
            // with — no event, no court, just this table's identity.
            'cameraUrl' => route('karate-scoreboard.token-cameras', $token, false),
            'cameraCommandBase' => \Illuminate\Support\Str::beforeLast(
                route('karate-scoreboard.token-cameras.command', [$token, 0], false), '0'
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
     * Would tokenControl() open for this token? Asked before rendering, so a
     * refusal becomes a redirect to something drawable instead of an error.
     *
     * Mirrors controlDevice() exactly. If the two ever disagree, a screen
     * bounces — so they are written to be read side by side.
     */
    /**
     * Upload one of this event's sounds from the scoring table itself.
     *
     * The organiser's console can already do this. The reason this exists too is
     * that the person who discovers the hall is silent is the one AT the mat,
     * ten minutes before the first bout, holding the only device that matters —
     * and telling them to find a laptop and sign in is how a competition starts
     * without its music.
     *
     * Authorised by the SCORING token, which is not a widening of what that
     * token holds: it already writes results into the bracket, which is a far
     * more consequential act than replacing a sound file. It is still only the
     * control surface — a bout board cannot upload anything — and still only for
     * its own event.
     */
    public function tokenAudio(Request $request, string $token, string $slot): JsonResponse
    {
        abort_unless($this->canOpenControl($token), 403);

        [, $event] = $this->controlDevice($token);

        $request->validate([
            'file' => ['required', 'file', 'max:'.(ScreenMedia::MAX_BYTES / 1024)],
        ]);

        $media = ScreenMedia::put($event, $slot, $request->file('file'), null);

        if (! $media) {
            return response()->json([
                'success' => false,
                'message' => __('events.screen_audio_rejected'),
            ], 422);
        }

        // Every screen on this mat reloads, which is how they pick the new file
        // up: the audio elements are built once per page and cache what they
        // fetched, so a replaced track would otherwise keep playing the old one
        // until somebody power-cycled the wall.
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

        if ($media = ScreenMedia::slot($event, $slot)) {
            $media->purge();
        }

        ScreenChannel::notifyCourt($event, null, ['action' => 'reload']);

        return response()->json(['success' => true, 'message' => __('events.screen_audio_removed'), 'slot' => $slot]);
    }

    /**
     * A face for whoever is in one of the corners RIGHT NOW.
     *
     * Scoped to the bout on this mat, not to an arbitrary entry id: the only two
     * competitors this token may photograph are the two standing in front of it.
     * That is both the useful case — the athlete is right there — and the narrow
     * one, which is why the side comes from the state rather than from the
     * request naming a registration.
     */
    public function tokenPhoto(Request $request, string $token, string $side): JsonResponse
    {
        abort_unless($this->canOpenControl($token), 403);
        abort_unless(in_array($side, ['aka', 'ao'], true), 404);

        [$device, $event] = $this->controlDevice($token);

        return $this->storeCornerPhoto($request, $event, $device->court, $side);
    }

    /**
     * The same face, through the organiser's own door.
     *
     * The console is one page with two front doors, and the cropper on it has
     * to work through both — a photo you can only add from the tablet is a
     * feature that is missing exactly when the tablet is not the thing in the
     * official's hands. Authorised by canScore(), the same check that guards
     * every other write on this door, and scoped to the mat in the URL.
     */
    public function photo(Request $request, ClubEvent $event, string $side): JsonResponse
    {
        abort_unless($this->canScore($event), 403);
        abort_unless($event->sport === 'karate', 404);
        abort_unless(in_array($side, ['aka', 'ao'], true), 404);

        $court = (string) $request->input('mat', '');

        // The mat must be one this event actually runs — never a string of the
        // caller's choosing, which would otherwise reach a cache key.
        abort_unless($court !== '' && $this->matExists($event, $court), 404);

        return $this->storeCornerPhoto($request, $event, $court, $side);
    }

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
     * Store a face against the entry standing in one corner of one mat.
     *
     * Scoped to the bout on that mat, not to an arbitrary entry id: the only
     * two competitors that can be photographed are the two the mat says are
     * there. That is both the useful case — the athlete is right in front of
     * whoever is holding the camera — and the narrow one.
     */
    private function storeCornerPhoto(Request $request, ClubEvent $event, string $court, string $side): JsonResponse
    {
        // Either a new face, or the instruction to take the one there away.
        // `remove` is what the cropper's Remove button sends: a badly framed or
        // simply wrong photo is on the wall until somebody can clear it, and
        // "upload a better one" is not a way to clear anything.
        $remove = $request->boolean('remove');

        $request->validate([
            'image' => [$remove ? 'nullable' : 'required', 'string', 'starts_with:data:image/'],
        ]);

        $state = MatState::load($event, $court);

        abort_unless($state->matchId, 422);

        $match = EventMatch::where('event_id', $event->id)->find($state->matchId);
        $column = $side === 'aka' ? 'a_competitor_id' : 'b_competitor_id';
        $registrationId = $match?->{$column};

        // A corner filled in by hand — a name typed at the table with no entry
        // behind it — has nothing to attach a photo to. Said plainly rather than
        // failing silently.
        if (! $registrationId) {
            return response()->json([
                'success' => false,
                'message' => __('scoreboard::karate_messages.ctl_photo_no_entry'),
            ], 422);
        }

        $registration = \App\Models\ClubEventRegistration::where('event_id', $event->id)->find($registrationId);

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

            $fresh = $this->scoring->apply($event, $court, 'resync');
            ScreenChannel::notifyCourt($event, $court, ['action' => 'mat', 'state' => $fresh->toArray()]);

            return response()->json([
                'success' => true,
                'message' => __('scoreboard::karate_messages.ctl_photo_removed'),
                'photo' => null,
                'side' => $side,
                'state' => $fresh->toArray(),
            ]);
        }

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

        // The corner is rebuilt from the registration, so the wall shows the face
        // as soon as it is told the state changed.
        // 'resync' is the defined no-op: it changes nothing, saves the state as
        // it stands, and hands it back — which is exactly what is needed to push
        // a corner the registration behind it just changed.
        $fresh = $this->scoring->apply($event, $court, 'resync');
        ScreenChannel::notifyCourt($event, $court, ['action' => 'mat', 'state' => $fresh->toArray()]);

        return response()->json([
            'success' => true,
            'message' => __('personal.event_photo_saved'),
            'photo' => file_url($path),
            'side' => $side,
            'state' => $fresh->toArray(),
        ]);
    }

    private function canOpenControl(string $token): bool
    {
        $device = CourtDisplayDevice::resolve($token);

        if (! $device || ! $device->isClaimed() || ! $device->event
            || $device->surface !== 'control' || ! $device->court
            || $device->event->sport !== 'karate') {
            return false;
        }

        $by = $device->created_by ? \App\Members\Models\User::find($device->created_by) : null;

        return $by !== null && app(EventAccess::class)->canScore($device->event, $by);
    }

    /**
     * Resolve a screen entitled to score this mat, or refuse.
     *
     * @return array{0: CourtDisplayDevice, 1: ClubEvent}
     */
    private function controlDevice(string $token): array
    {
        $device = CourtDisplayDevice::resolve($token);

        // One answer for every way this can fail — a bad token, a display-only
        // screen, an unpaired one, a pairing organiser who has since lost the
        // right to score. A console in a hall is looked at by strangers, and
        // telling them which of those it was is telling them what to try next.
        abort_unless(
            $device
            && $device->isClaimed()
            && $device->event
            && $device->surface === 'control'
            && $device->court
            && $device->event->sport === 'karate',
            404
        );

        $by = $device->created_by ? \App\Members\Models\User::find($device->created_by) : null;

        abort_unless($by && app(EventAccess::class)->canScore($device->event, $by), 403);

        // From here on this request acts AS that organiser — the same checks,
        // the same scope, the same audit trail as if they were signed in.
        $this->actor = $by;

        $device->touchSeen();

        return [$device, $device->event];
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
        abort_unless($event->sport === 'karate', 404);

        $data = $request->validate([
            'mat' => ['required', 'string', 'max:40'],
            'command' => ['required', 'string', 'in:'.implode(',', Scoring::COMMANDS)],
            'side' => ['nullable', 'string', 'in:aka,ao'],
            'n' => ['nullable', 'integer', 'min:1', 'max:3'],
            'match_id' => ['nullable', 'integer'],
            'minutes' => ['nullable', 'numeric', 'min:0.1', 'max:15'],
            'remaining' => ['nullable', 'numeric', 'min:0', 'max:900'],
            'dir' => ['nullable', 'integer', 'in:-1,1'],
            // The console's five penalty cells ask for a LEVEL rather than a
            // direction. 0 is "no penalty", which is what pressing the current
            // top of the ladder asks for.
            'level' => ['nullable', 'integer', 'min:0', 'max:'.count(MatState::PENALTIES)],
            // The settings panel's clock: a bout in seconds, and how many are
            // left when the warning starts.
            'seconds' => ['nullable', 'numeric', 'min:10', 'max:900'],
            'warning' => ['nullable', 'numeric', 'min:0', 'max:900'],
            // The rules this mat runs. Validated here as well as inside Scoring
            // because this endpoint is the contract and the console is only a
            // convenience — anything else posting here is held to the same
            // vocabulary.
            'gap' => ['nullable', 'integer', 'min:1', 'max:20'],
            'senshuRule' => ['nullable', 'boolean'],
            'autoSenshu' => ['nullable', 'boolean'],
            'winByPenalties' => ['nullable', 'boolean'],
            'atoshiWarn' => ['nullable', 'boolean'],
            'timeUpBuzzer' => ['nullable', 'boolean'],
            'gapOn' => ['nullable', 'boolean'],
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
            // Ending a bout by decision rather than on points. Validated here as
            // well as inside Scoring, because this endpoint is the contract and
            // the console is only a convenience: a second laptop or a replayed
            // request must be held to the same vocabulary.
            'winner' => ['nullable', 'string', 'in:aka,ao'],
            'reason' => ['nullable', 'string', 'in:'.implode(',', Scoring::WIN_REASONS)],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        // The mat must be one this event actually runs — not a string the
        // caller made up, which would mint cache entries at will.
        abort_unless($this->matExists($event, $data['mat']), 404);

        try {
            $state = $this->scoring->apply($event, $data['mat'], $data['command'], $data);
        } catch (\RuntimeException $e) {
            // A refusal the official needs to read and act on. Not a fault.
            //
            // The CURRENT state rides along with it. A console that is refused
            // is very often a console that has fallen behind — a second tab, a
            // page left open while the mat moved on — and the old shape left it
            // showing whatever it still believed while a message it could not
            // act on flashed past. Handing back the truth lets it correct
            // itself on the spot.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'state' => MatState::load($event, $data['mat'])->toArray(),
                'queue' => $this->queue($event, $data['mat']),
            ], 422);
        }

        // Three commands change WHICH bout is on the mat, and all three move the
        // queue behind it — CourtDisplay leaves out whatever is on the mat, so a
        // bout being loaded drops off the running order and a bout being cleared
        // rejoins it. Boards pinned to the running order are showing exactly
        // that list and have to be told.
        //
        // Only these three. The board is rebuilt per mat from the draw, and
        // doing that on every point, penalty and clock correction would put a
        // handful of queries behind each keypress at the scoring table for a
        // list that cannot have changed.
        //
        // COMMIT goes to every mat, the other two only to this one. A result
        // carries its winner into a next-round slot the scheduler is free to
        // have placed on another mat, and that mat's board is now announcing a
        // corner that is no longer "to be decided".
        //
        // Commit is also the case that has to be re-sent from HERE rather than
        // left to the push inside recordOutcome: that one runs mid-commit,
        // before the next bout has been loaded and before the new MatState is
        // saved, so the board it builds fails to exclude the bout that is at
        // that moment walking on, and prints GET READY over it.
        if (in_array($data['command'], ['load', 'commit', 'clear'], true)) {
            ScreenChannel::notifyCourt($event, $data['command'] === 'commit' ? null : $data['mat']);
        }

        // Every screen on this mat, immediately. Best-effort like every other
        // push in the app: the cache is the truth and a screen that missed this
        // re-fetches on reconnect.
        //
        // After the board, deliberately: a screen moving between the queue and
        // a bout changes PAGE, and this is the message that reloads it. Sending
        // it first would have the screen navigate away from a board it is about
        // to be handed.
        ScreenChannel::notifyCourt($event, $data['mat'], [
            'action' => 'mat',
            'state' => $state->toArray(),
        ]);

        // The sledgehammer, and the reason it exists: a screen on a wall has no
        // keyboard, no pointer and nobody standing at it. If one has hung — a
        // dropped websocket that never came back, a board stuck on a
        // celebration, a page that has been up since the morning — there is
        // otherwise no way to make it start again short of pulling the power.
        //
        // Sent AFTER the state, so a screen that is merely behind has already
        // been corrected by the cheap message and this only matters to one that
        // was not listening. Reload is safe by construction: every screen
        // re-fetches everything it shows on load.
        if ($data['command'] === 'resync') {
            ScreenChannel::notifyCourt($event, $data['mat'], ['action' => 'reload']);
        }

        return response()->json([
            'success' => true,
            'state' => $state->toArray(),
            'queue' => $this->queue($event, $data['mat']),
        ]);
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
        $device = CourtDisplayDevice::resolve($token);

        abort_unless($device && $device->isClaimed() && $device->event, 404);

        $device->touchSeen();

        return response()->json(MatState::load($device->event, $device->court)->toArray());
    }

    /**
     * Countries as the picker wants them: ISO-2 code plus a display name.
     *
     * The product's one list (App\Support\Countries), so the picker at the mat,
     * the name announced on the wall and every country dropdown elsewhere can
     * never disagree about what a country is called.
     */
    private function countries(): array
    {
        return \App\Support\Countries::all();
    }

    /* ---------------- Helpers ---------------- */

    /**
     * Who this request is acting as. Set only by controlDevice(), when a paired
     * screen is standing in for the organiser who paired it.
     */
    private ?\App\Members\Models\User $actor = null;

    private function canScore(ClubEvent $event): bool
    {
        $user = $this->actor ?: Auth::user();

        return $user !== null && app(EventAccess::class)->canScore($event, $user);
    }

    private function matExists(ClubEvent $event, string $court): bool
    {
        return $event->matches()->where('court', $court)->exists();
    }

    /**
     * What is still to come on this mat, so the operator loads the next bout
     * rather than typing two names into a form.
     *
     * Only bouts with both competitors known: one still waiting on a feeder has
     * nobody to introduce.
     */
    private function queue(ClubEvent $event, string $court): array
    {
        $order = new RunningOrder;

        // The whole queue, in the same order the wall board shows it. A bout
        // still waiting on a feeder is LISTED rather than hidden — the operator
        // needs to see what is coming — but it cannot be loaded, because nobody
        // can be introduced as "winner of bout 3".
        $queue = $order->matQueue($event, $court)->take(12);

        // matQueue loads the category as id+name only; the rows print the
        // weight class, so pull that column on the twelve that survive.
        $queue->load('category:id,name,weight_class');

        // The Bouts list draws each competitor the way the wall does — club and
        // its flag beside the name — so the official picking a bout off this
        // list is reading the same two people the hall will see. One query for
        // the whole page rather than one per row.
        $entryIds = $queue->flatMap(fn (EventMatch $m) => [$m->a_competitor_id, $m->b_competitor_id])
            ->filter()->unique()->values()->all();

        $entries = $entryIds
            ? ClubEventRegistration::where('event_id', $event->id)
                ->whereIn('id', $entryIds)
                ->with(['user:id,full_name,name,gender,profile_picture,profile_picture_is_public',
                    'user.memberClubs:id,club_name,country',
                    'representingTenant:id,club_name,country'])
                ->get()->keyBy('id')
            : collect();

        // The club they COMPETE FOR, and its country — never the person's own
        // nationality. Same rule as the corners on the board.
        $clubOf = function (?int $id) use ($entries): array {
            $reg = $id ? $entries->get($id) : null;
            $club = $reg?->competingClub();
            $user = $reg?->user;

            return [
                'club' => $club?->club_name ?: '',
                'flag' => preg_match('/^[A-Za-z]{2}$/', (string) $club?->country)
                    ? strtolower((string) $club->country) : null,
                // The same rule the board draws a corner by, and for the same
                // reason: the event's OWN photo needs no gate beyond the
                // organiser who uploaded it, and a member's private profile
                // picture keeps its gate — this list is on a screen at a mat.
                'photo' => $reg?->photo
                    ? file_url($reg->photo)
                    : (($user?->profile_picture && $user->profile_picture_is_public)
                        ? file_url($user->profile_picture)
                        : null),
                // The drawn stand-in, so a row reads as a person rather than
                // as a missing image. Always present — the same rule the member
                // lists have always used.
                'fallback' => \App\Support\Avatar::placeholder($user?->gender),
            ];
        };

        return $queue
            ->map(fn (EventMatch $m) => [
                'id' => $m->id,
                'number' => $m->match_no,
                // The SAME two fields the wall board prints, or the operator
                // and the hall read different things off the same bout.
                // `phase` is the scheduler's day-bucket (preliminary /
                // quarterfinals / finals), not the round: a semifinal carries
                // phase "finals", so using it here labelled semifinals as
                // finals on the console while the wall correctly said
                // Semifinal. Likewise the wall prints the weight class, not
                // the division's full name.
                'stage' => $m->round ?: $m->phase,
                'division' => $m->category?->weight_class ?: $m->category?->name,
                'aka' => $m->a_name ?: null,
                'ao' => $m->b_name ?: null,
                'akaClub' => $clubOf($m->a_competitor_id)['club'],
                'akaFlag' => $clubOf($m->a_competitor_id)['flag'],
                'akaPhoto' => $clubOf($m->a_competitor_id)['photo'],
                'akaFallback' => $clubOf($m->a_competitor_id)['fallback'],
                'aoClub' => $clubOf($m->b_competitor_id)['club'],
                'aoFlag' => $clubOf($m->b_competitor_id)['flag'],
                'aoPhoto' => $clubOf($m->b_competitor_id)['photo'],
                'aoFallback' => $clubOf($m->b_competitor_id)['fallback'],
                'runnable' => $order->isRunnable($m),
            ])
            ->values()->all();
    }
}
