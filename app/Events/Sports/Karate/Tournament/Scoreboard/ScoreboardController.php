<?php

namespace App\Events\Sports\Karate\Tournament\Scoreboard;

use App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayDevice;
use App\Events\Sports\Karate\Tournament\CourtDisplay\ScreenChannel;
use App\Events\Sports\Karate\Tournament\RunningOrder;
use App\Events\Support\EventAccess;
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
            'showTimeAdjust' => $request->boolean('adjust'),
        ]);
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

    private function canScore(ClubEvent $event): bool
    {
        $user = Auth::user();

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
