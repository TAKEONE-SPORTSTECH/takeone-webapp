<?php

namespace App\Events\Sports\Karate\Tournament\Scoreboard;

use App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayDevice;
use App\Events\Sports\Karate\Tournament\CourtDisplay\ScreenChannel;
use App\Events\Sports\Karate\Tournament\RunningOrder;
use App\Events\Support\EventAccess;
use App\Events\Support\ScreenMedia;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Models\EventMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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
            // A face for a corner needs the entry behind it, which only the
            // token door can resolve from the mat state. From a laptop the
            // roster is the place for that, so this door offers no photo upload.
            'photoUploadBase' => null,
        ], $request->boolean('adjust'));
    }

    /**
     * The console page itself — one body, two front doors.
     *
     * A signed-in organiser reaches it by event uuid; a paired scoring table
     * reaches it by its own device token. They must render the SAME page, or
     * the two drift and a mat behaves differently depending on how somebody
     * opened it. Only the addresses it posts to differ.
     */
    private function consoleView(ClubEvent $event, $mats, string $court, array $urls, bool $showTimeAdjust = false)
    {
        return view('event-karate_tournament::scoreboard.control', [
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
            'audioSlots' => collect(ScreenMedia::forEvent($event))
                ->map(fn ($m) => ['name' => $m->original_name, 'bytes' => $m->bytes])
                ->all(),
        ] + $urls);
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

        $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image/'],
        ]);

        $state = MatState::load($event, $device->court);

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
                'message' => __('event-karate_tournament::messages.ctl_photo_no_entry'),
            ], 422);
        }

        $registration = \App\Models\ClubEventRegistration::where('event_id', $event->id)->find($registrationId);

        abort_unless($registration, 404);

        $previous = $registration->photo;

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
            \Illuminate\Support\Facades\Storage::disk('public')->delete($previous);
        }

        // The corner is rebuilt from the registration, so the wall shows the face
        // as soon as it is told the state changed.
        // 'resync' is the defined no-op: it changes nothing, saves the state as
        // it stands, and hands it back — which is exactly what is needed to push
        // a corner the registration behind it just changed.
        $fresh = $this->scoring->apply($event, $device->court, 'resync');
        ScreenChannel::notifyCourt($event, $device->court, ['action' => 'mat', 'state' => $fresh->toArray()]);

        return response()->json([
            'success' => true,
            'message' => __('personal.event_photo_saved'),
            'photo' => asset('storage/'.$path),
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

        $by = $device->created_by ? \App\Models\User::find($device->created_by) : null;

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

        $by = $device->created_by ? \App\Models\User::find($device->created_by) : null;

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
     * Read from the app's own list rather than hard-coded here, so the picker
     * and every country dropdown in the product stay in step.
     */
    private function countries(): array
    {
        // public/data/countries.json is what every country dropdown in the
        // product already reads. Cached because this page is opened once per
        // mat per competition and the file never changes between deploys.
        return Cache::remember('karate.control.countries', now()->addDay(), function () {
            $path = public_path('data/countries.json');

            if (! is_file($path)) {
                return [];
            }

            $rows = json_decode((string) file_get_contents($path), true) ?: [];

            return collect($rows)
                ->map(fn ($c) => ['code' => strtolower((string) ($c['iso2'] ?? '')), 'name' => (string) ($c['name'] ?? '')])
                ->filter(fn ($c) => preg_match('/^[a-z]{2}$/', $c['code']) && $c['name'] !== '')
                ->sortBy('name')->values()->all();
        });
    }

    /* ---------------- Helpers ---------------- */

    /**
     * Who this request is acting as. Set only by controlDevice(), when a paired
     * screen is standing in for the organiser who paired it.
     */
    private ?\App\Models\User $actor = null;

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
                'runnable' => $order->isRunnable($m),
            ])
            ->values()->all();
    }
}
