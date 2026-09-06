<?php

namespace App\Scoreboard\Sports\Taekwondo\Controllers;

use App\Scoreboard\Sports\Taekwondo\HallScreen\CourtDisplayDevice;
use App\Scoreboard\Sports\Taekwondo\HallScreen\ScreenChannel;
use App\Events\Sports\Taekwondo\Tournament\RunningOrder;
use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Traits\StoresBase64Images;
use App\Events\Support\Cameras\MatCameraController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Scoreboard\Sports\Taekwondo\Mat\MatState;
use App\Scoreboard\Sports\Taekwondo\Mat\Scoring;

/**
 * The table at a Taekwondo mat: one operator page, and the two endpoints behind it.
 *
 * The operator is the only source of truth while a match runs. They load a
 * match, start the clock, score it, close each round and end it — and each of
 * those is a command posted here, applied by Scoring, and pushed to the mat's
 * screens over MQTT. The screens never decide anything; they draw what they
 * are told.
 *
 * Authorisation is EventAccess::canScore on EVERY call, not just on the page:
 * the page is a convenience, the endpoint is the attack surface. A jury member
 * appointed to one event can score that event and nothing else.
 */
class ScoreboardController extends Controller
{
    use StoresBase64Images;

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
        abort_unless($event->sport === 'taekwondo', 404);

        $mats = $event->matches()->whereNotNull('court')->distinct()->orderBy('court')->pluck('court')->values();
        $court = (string) $request->query('mat', $mats->first() ?? 'Mat 1');
        abort_unless($mats->isEmpty() || $mats->contains($court), 404);

        return $this->consoleView($event, $mats, $court, [
            'commandUrl' => route('taekwondo-scoreboard.command', $event->uuid),
            'photoUrl' => route('taekwondo-scoreboard.photo', $event->uuid),
            // Nothing to beat for: this console is a signed-in browser, not a
            // paired screen, and must not mark anybody's device as alive.
            'heartbeatUrl' => null,
            // The cameras pointed at THIS mat, under the organiser's own
            // authorisation. The panel itself is shared with every console;
            // only these two addresses differ per door.
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
     * The console page itself, for whichever door it was reached through.
     *
     * A signed-in official opens it by event uuid; a tablet paired at the mat
     * opens it by its own token. The PAGE is identical — what differs is the
     * two URLs it posts to, and therefore how each request is authorised.
     */
    private function consoleView(ClubEvent $event, $mats, string $court, array $urls, bool $showTimeAdjust = false, ?array $matPanel = null)
    {
        return view('scoreboard::taekwondo.scoreboard.control', [
            'event' => $event,
            'mats' => $mats,
            'court' => $court,
            'state' => MatState::load($event, $court)->toArray(),
            'queue' => $this->queue($event, $court),
            // Which entry each corner is, so the console can attach a photo to
            // the right one. Ids only — the upload endpoint re-checks them.
            'corners' => $this->corners($event, $court),
            'screens' => CourtDisplayDevice::where('event_id', $event->id)
                ->where('court', $court)->whereNull('revoked_at')->count(),
            // Feeds the country picker. The app already keeps this list; the
            // picker searches it client-side because it is 200 rows, not 20,000.
            'countries' => $this->countries(),
            'showTimeAdjust' => $showTimeAdjust,
            // A panel the event's own PACKAGE contributes to this console
            // (AbstractEventType::matPanel). Null for every championship, which
            // renders nothing and leaves this page exactly as it was. An open
            // mat uses it to set the next pair without the operator ever
            // leaving the scoreboard.
            'matPanel' => $matPanel,
        ] + $urls + [
            // Defaults last: `+` on arrays keeps the LEFT value, so a door that
            // passed camera addresses keeps them and one that did not renders
            // no camera panel rather than an undefined variable.
            'cameraUrl' => null,
            'cameraCommandBase' => null,
        ]);
    }

    /**
     * The console on a screen that was paired at the mat, not signed in.
     *
     * ── Why a token may score at all ────────────────────────────────────────
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
     *    event and no court in this URL to tamper with, and the mat the command
     *    names is overwritten with the device's own below.
     *  · Only if it was PAIRED as a control, by an organiser who could score.
     *  · Re-checked on EVERY request against the pairing organiser: take that
     *    person off the jury and every console they paired stops scoring, at
     *    once, without anybody visiting the hall.
     *  · Unpairing or revoking the screen ends it immediately.
     *
     * The token itself is never displayed: what the QR carries before pairing
     * is a six-character pairing code, and the code is spent when used.
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
            return redirect()->route('court-display.board', $token);
        }

        [$device, $event] = $this->controlDevice($token);

        $mats = $event->matches()->whereNotNull('court')->distinct()->orderBy('court')->pluck('court')->values();

        return $this->consoleView($event, $mats, $device->court, [
            'commandUrl' => route('taekwondo-scoreboard.token-command', $token),
            'photoUrl' => route('taekwondo-scoreboard.token-photo', $token),
            // A paired console is a SCREEN, and the console lists it beside the
            // boards with a live dot. It touches `last_seen` on every command,
            // which is fine while a match is being scored and useless between
            // them — a mat waiting twenty minutes for the next bout would show
            // as offline, which is the opposite of the truth and exactly when
            // an organiser is checking. So it beats like every other screen.
            'heartbeatUrl' => route('court-display.status', $token, false),
            // The same camera panel, through the table's own token: the mat is
            // the DEVICE's, so there is nothing in these addresses to tamper
            // with.
            'cameraUrl' => route('taekwondo-scoreboard.token-cameras', $token, false),
            'cameraCommandBase' => \Illuminate\Support\Str::beforeLast(
                route('taekwondo-scoreboard.token-cameras.command', [$token, 0], false), '0'
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

    /** A picture from a paired console. */
    public function tokenPhoto(Request $request, string $token): JsonResponse
    {
        [$device, $event] = $this->controlDevice($token);

        $request->merge(['mat' => $device->court]);

        return $this->photo($request, $event);
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
     * Would tokenControl() open for this token? Asked before rendering, so a
     * refusal becomes a redirect to something drawable instead of an error.
     *
     * Mirrors controlDevice() exactly. If the two ever disagree, a screen
     * bounces — so they are written to be read side by side.
     */
    private function canOpenControl(string $token): bool
    {
        $device = CourtDisplayDevice::resolve($token);

        if (! $device || ! $device->isClaimed() || ! $device->event
            || $device->surface !== 'control' || ! $device->court
            || $device->event->sport !== 'taekwondo') {
            return false;
        }

        $by = $device->created_by ? \App\Members\Models\User::find($device->created_by) : null;

        return $by !== null && app(EventAccess::class)->canScore($device->event, $by);
    }

    /**
     * Resolve a screen that is entitled to score, or refuse.
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
            && $device->event->sport === 'taekwondo',
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
        abort_unless($event->sport === 'taekwondo', 404);

        $data = $request->validate([
            'mat' => ['required', 'string', 'max:40'],
            'command' => ['required', 'string', 'in:'.implode(',', Scoring::COMMANDS)],
            'side' => ['nullable', 'string', 'in:aka,ao'],
            'action' => ['nullable', 'string', 'in:'.implode(',', array_keys(MatState::ACTIONS))],
            'n' => ['nullable', 'integer', 'min:-5', 'max:5'],
            // How many rounds the match is. Rides on `load` so a match arrives
            // on the mat already the right shape, rather than being loaded at
            // the default and corrected a request later.
            'rounds' => ['nullable', 'integer', 'min:1', 'max:5'],
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
            'category' => ['nullable', 'string', 'max:60'],
            'matchNo' => ['nullable', 'string', 'max:12'],
            'courtLabel' => ['nullable', 'string', 'max:20'],
            'stage' => ['nullable', 'string', 'max:40'],
            'referee' => ['nullable', 'string', 'max:60'],
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
                'corners' => $this->corners($event, $data['mat']),
            ], 422);
        }

        // Three commands change WHICH match is on the mat, and all three move
        // the queue behind it — CourtDisplay leaves out whatever is on the mat,
        // so a match being loaded drops off the running order and one being
        // cleared rejoins it. Boards pinned to the running order are showing
        // exactly that list and have to be told.
        //
        // Only these three. Rebuilding the board on every point and every clock
        // correction would put a handful of queries behind each keypress at the
        // scoring table, for a list that cannot have changed.
        //
        // COMMIT goes to every mat, the other two only to this one: a result
        // carries its winner into a next-round slot the scheduler is free to
        // have placed on another mat, and that mat's board is now announcing a
        // corner that is no longer "to be decided".
        if (in_array($data['command'], ['load', 'commit', 'clear'], true)) {
            ScreenChannel::notifyCourt($event, $data['command'] === 'commit' ? null : $data['mat']);
        }

        // Every screen on this mat, immediately. Best-effort like every other
        // push in the app: the cache is the truth and a screen that missed this
        // re-fetches on reconnect. After the board, deliberately — a screen
        // moving between the queue and a match changes PAGE, and this is the
        // message that reloads it.
        ScreenChannel::notifyCourt($event, $data['mat'], [
            'action' => 'mat',
            'state' => $state->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'state' => $state->toArray(),
            'queue' => $this->queue($event, $data['mat']),
            'corners' => $this->corners($event, $data['mat']),
        ]);
    }

    /**
     * A competitor's picture for this event, added at the scoring table.
     *
     * Some entrants simply have no photo, and an introduction screen with an
     * empty panel looks broken from ten metres — so the official at the desk
     * can supply one.
     *
     * ── What this is allowed to touch ───────────────────────────────────────
     * The REGISTRATION, and only for a competitor in a bout on THIS event.
     * Never `users.profile_picture`: the operator is authorised to run a
     * competition, not to change somebody's account. The registration id is
     * checked against this event before anything is written, so the parameter
     * cannot be pointed at an entry in another organiser's competition.
     *
     * The bytes go through StoresBase64Images, which sniffs the real MIME from
     * the decoded data and assigns the extension itself from a whitelist (SVG
     * rejected — it can carry script, and this lands on a public wall). The
     * folder is built here from the event and registration ids; nothing about
     * the path comes from the client.
     */
    public function photo(Request $request, ClubEvent $event): JsonResponse
    {
        abort_unless($this->canScore($event), 403);
        abort_unless($event->sport === 'taekwondo', 404);

        $data = $request->validate([
            'registration_id' => ['required', 'integer'],
            'image' => ['required', 'string', 'starts_with:data:image/', 'max:8000000'],
            // Which mat the desk is working, so the picture can reach the wall
            // behind it without a second press. Optional: the picture is still
            // saved without it, it simply waits for Refresh VS screen.
            'mat' => ['nullable', 'string', 'max:40'],
            // The competitor, or the crest beside their club's name. One code
            // path because the authorisation, the storage rules and the two
            // pushes are identical — only the column and the corner field it
            // lands in differ.
            'kind' => ['nullable', 'string', 'in:competitor,club'],
        ]);

        $isCrest = ($data['kind'] ?? 'competitor') === 'club';
        $column = $isCrest ? 'club_logo' : 'photo';
        $field = $isCrest ? 'logo' : 'photo';

        // Scoped to this event — the id in the request is never trusted on its
        // own. An entry in someone else's competition is a 404 here.
        $entry = ClubEventRegistration::where('event_id', $event->id)
            ->find($data['registration_id']);

        abort_unless($entry, 404);

        $old = $entry->{$column};

        $path = $this->storeBase64Image(
            $data['image'],
            'events/'.$event->uuid.'/competitors',
            ($isCrest ? 'crest' : 'c').$entry->id.'-'.bin2hex(random_bytes(8)),
        );

        if ($path === null) {
            return response()->json([
                'success' => false,
                'message' => __('scoreboard::taekwondo_messages.ctl_photo_bad'),
            ], 422);
        }

        $entry->forceFill([$column => $path])->save();

        // Only once the new file is safely stored, and only if it really moved.
        //
        // Through EntryPhoto, never a raw delete: an entry settled at the public
        // door points at the athlete's OWN profile picture, and replacing the
        // event photo here used to take their face off the disk while
        // users.profile_picture carried on naming it. A crest is unaffected —
        // no user record ever claims one, so it is still deleted.
        if ($old && $old !== $path) {
            \App\Events\Support\EntryPhoto::discard($old);
        }

        // ── And put it on every screen, now ──────────────────────────────────
        // This used to stop at the database and wait for the operator to press
        // Refresh VS screen. The reasoning was that a screen changing under a
        // live introduction is worse than one that waits — but that is exactly
        // backwards: the introduction gaining the competitor's face IS the
        // point of adding it, and an official who has just watched the picture
        // appear on their console has no reason to think a second button is
        // still between them and the hall.
        //
        // Two pushes, because a competitor appears on two kinds of screen.
        //
        // 1. THE MAT they are fighting on, if they are on one. ONE field of one
        //    corner is patched — deliberately not the `refresh` command, which
        //    re-reads both corners from the draw and would silently discard a
        //    name, club or country an official had typed at the table. Nothing
        //    about the CONTEST is touched: not the score, not the clock, not
        //    the rounds. Safe to run mid-match.
        //    Which mats are affected is worked out HERE, by asking the mats. An
        //    earlier cut trusted the request to name the mat, and a console
        //    loaded before that field existed sent nothing — so the picture
        //    saved, the wall was never told, and the whole thing looked broken.
        //    A screen must not depend on how old the tab that fed it is.
        //
        //    A competitor's picture touches one corner. A CREST can touch
        //    several: it is answered for the whole club, so every corner in the
        //    hall showing one of their athletes is now out of date.
        $this->patchMats($event, $entry, $field, file_url($path), $isCrest);

        // 2. EVERY upcoming board in the hall. This competitor may be queued on
        //    a mat other than the one the desk is working — a board is a list of
        //    bouts to come, and the picture belongs to all of them. Sent without
        //    a message, which is the board-rebuild signal: each screen is handed
        //    its own mat's board, and the payload carries a content hash, so a
        //    board this did not actually change skips its repaint.
        ScreenChannel::notifyCourt($event, null);

        // The button stays. It also picks up a name, club or country corrected
        // in the database, which no upload can know about.

        return response()->json([
            'success' => true,
            'url' => file_url($path),
            'registration_id' => $entry->id,
            // Which of the two the console just changed, so it patches the
            // portrait or the crest and not whichever it asked for last.
            'kind' => $isCrest ? 'club' : 'competitor',
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

    /**
     * Put a newly uploaded image straight onto every mat it belongs on.
     *
     * ONE field of the affected corners is rewritten — deliberately not the
     * `refresh` command, which rebuilds both corners from the draw and would
     * silently discard a name, club or country an official had typed at the
     * table. Nothing about the CONTEST is touched: not the score, not the
     * clock, not the rounds. Safe to run mid-match.
     *
     * A competitor's picture matches one registration. A crest matches every
     * competitor of that club who is on a mat right now — except one who has a
     * crest of their own, which outranks the club-wide one.
     */
    private function patchMats(ClubEvent $event, ClubEventRegistration $entry, string $field, string $url, bool $isCrest): void
    {
        $clubId = $isCrest ? $this->clubIdOf($entry) : null;

        // A crest answered for a club nobody can be matched to would repaint
        // every corner in the hall. Better to reach only this one entry.
        if ($isCrest && ! $clubId) {
            $isCrest = false;
        }

        $mats = $event->matches()->whereNotNull('court')->distinct()->orderBy('court')->pluck('court');

        foreach ($mats as $court) {
            $state = MatState::load($event, $court);
            $touched = false;

            foreach ($this->corners($event, $court) as $side => $registrationId) {
                if (! $registrationId) {
                    continue;
                }

                // The entry this upload was for always matches — it is the one
                // that just changed. Everything below is about who ELSE the
                // change reaches.
                $match = $registrationId === $entry->id;

                if (! $match && $isCrest) {
                    $corner = ClubEventRegistration::where('event_id', $event->id)->find($registrationId);
                    // Same club, and no crest of their own — an entry that has
                    // been given its own keeps it over the club-wide one.
                    $match = $corner && ! $corner->club_logo && $this->clubIdOf($corner) === $clubId;
                }

                if (! $match) {
                    continue;
                }

                $payload = $side === 'aka' ? $state->aka : $state->ao;
                $payload[$field] = $url;
                $side === 'aka' ? $state->aka = $payload : $state->ao = $payload;
                $touched = true;
            }

            if ($touched) {
                $state->save($event, $court);
                ScreenChannel::notifyCourt($event, $court, ['action' => 'mat', 'state' => $state->toArray()]);
            }
        }
    }

    /**
     * The club a registration's screens name — the member's own first club,
     * which is the same answer every surface already uses to print the club.
     */
    private function clubIdOf(ClubEventRegistration $entry): ?int
    {
        return $entry->user?->memberClubs->first()?->id;
    }

    /**
     * The registration id behind each corner of whatever is on the mat.
     *
     * @return array{aka: ?int, ao: ?int}
     */
    private function corners(ClubEvent $event, string $court): array
    {
        $id = MatState::load($event, $court)->matchId;
        $m = $id ? EventMatch::where('event_id', $event->id)->find($id) : null;

        return ['aka' => $m?->a_competitor_id, 'ao' => $m?->b_competitor_id];
    }

    /* ---------------- Helpers ---------------- */

    /**
     * Who is acting, when nobody is signed in.
     *
     * Set only by controlDevice(), from the organiser who paired a scoring
     * screen — and only after that organiser's right to score has been checked
     * again for this request. Everything downstream then authorises exactly as
     * it does for a session, against a real person.
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
     * What is still to come on this mat today, so the operator loads the next
     * match rather than typing two names into a form.
     *
     * The whole queue, in the same order the wall board shows it. A match still
     * waiting on a feeder is LISTED rather than hidden — the operator needs to
     * see what is coming — but it cannot be loaded, because nobody can be
     * introduced as "winner of match 3".
     */
    private function queue(ClubEvent $event, string $court): array
    {
        $order = new RunningOrder;

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
