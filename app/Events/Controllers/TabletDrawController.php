<?php

namespace App\Events\Controllers;

use App\Events\Models\EventDrawPin;
use App\Events\Support\TabletDraw;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * /tablet/{token}/draw… — the draw on a scoring tablet (owner, 2026-10-07).
 *
 * ── The two shapes CLAUDE.md demands of a device surface ──────────────────
 *  · The PAGE never 404s: a token that resolves to nothing goes to the
 *    pairing room, a tablet that is fine but has the feature off goes back to
 *    its console. A tablet must always land somewhere it can leave.
 *  · Every JSON door answers 404 for a dead identity, so the page can tell
 *    "this tablet is gone" from "the wifi dropped".
 *
 * ── Nothing is re-implemented here ────────────────────────────────────────
 * Each JSON door resolves the tablet, decides WHO the request runs as —
 * the pairing organiser to read, the PIN's owner to edit — and then calls the
 * SAME controller action the organiser's own board calls. Validation, the
 * package's rules (LateEntry, Arrangement), authorisation and the audit trail
 * are therefore the existing ones, unchanged and not copied. Auth::setUser()
 * is per-request: it never signs the tablet in.
 */
class TabletDrawController extends Controller
{
    /** The page. */
    public function show(string $token): Response
    {
        $tablet = TabletDraw::resolve($token);

        if (! $tablet) {
            return redirect()->route('screen.new');
        }

        if (! TabletDraw::enabled()) {
            return redirect()->to($tablet->consoleUrl);
        }

        $editor = $tablet->editor();
        $public = app(\App\Events\Support\PublicEvent::class);

        return response()->view('events::tablet-draw', [
            'token' => $token,
            'event' => $tablet->event,
            'title' => (string) $tablet->event->title,
            'color' => $public->color($tablet->event),
            'consoleUrl' => $tablet->consoleUrl,
            'editing' => $editor !== null,
            'editorManages' => $editor !== null && $this->manages($tablet, $editor),
            'editorName' => $editor?->full_name,
            'secondsLeft' => $tablet->secondsLeft(),
            'hasPin' => EventDrawPin::where('event_id', $tablet->event->id)->exists(),
            // No PIN (events.tablet_draw_pin off): no pad, no countdown, no lock.
            'pinRequired' => TabletDraw::pinRequired(),
        ]);
    }

    public function data(string $token): JsonResponse
    {
        [$tablet, $editor] = $this->tablet($token);

        Auth::setUser($editor ?? $tablet->pairedBy);

        $res = app()->call([app(PersonalEventController::class), 'bracketData'], ['event' => $tablet->event]);
        $payload = $res->getData(true);

        // Nothing is editable without a PIN. Under one, the board offers what
        // the PIN's OWNER may do: arranging, and — if they manage the event —
        // the bout tools (winner, score, a name), each re-checked server-side.
        $payload['can_arrange'] = $editor !== null && (bool) ($payload['can_arrange'] ?? false);
        $payload['can_manage'] = $editor !== null && $this->manages($tablet, $editor);

        return response()->json($payload);
    }

    public function unlock(Request $request, string $token): JsonResponse
    {
        [$tablet] = $this->tablet($token, needsEditor: false);

        $data = $request->validate(['pin' => ['required', 'string', 'max:8']]);

        $pin = EventDrawPin::where('event_id', $tablet->event->id)->first();

        if (! $pin) {
            return $this->no(__('events::events.tablet_pin_none'));
        }

        if ($pin->isLocked()) {
            return $this->no(__('events::events.tablet_pin_locked', ['minutes' => EventDrawPin::LOCK_MINUTES]), 429);
        }

        if (! $pin->attempt((string) $data['pin'])) {
            Log::warning('Tablet draw PIN refused', ['event' => $tablet->event->uuid, 'device' => $tablet->device->getKey()]);

            return $pin->fresh()->isLocked()
                ? $this->no(__('events::events.tablet_pin_locked', ['minutes' => EventDrawPin::LOCK_MINUTES]), 429)
                : $this->no(__('events::events.tablet_pin_wrong'));
        }

        // The PIN is right; its owner must still run this draw.
        if (! $pin->owner || ! app(\App\Events\Support\EventAccess::class)->canArrange($tablet->event, $pin->owner)) {
            return $this->no(__('events::events.tablet_pin_stale'));
        }

        $tablet->grant($pin);

        Log::info('Tablet draw unlocked', [
            'event' => $tablet->event->uuid, 'device' => $tablet->device->getKey(), 'as' => $pin->user_id,
        ]);

        return response()->json(['success' => true, 'message' => __('events::events.tablet_unlocked')]);
    }

    public function lock(string $token): JsonResponse
    {
        [$tablet] = $this->tablet($token, needsEditor: false);

        $tablet->revoke();

        return response()->json(['success' => true]);
    }

    // ── Edits: each one the organiser's own action, run as the PIN owner ──

    public function arrange(Request $request, string $token): Response
    {
        return $this->asEditor($token, fn ($tablet) => app()->call(
            [app(PersonalEventController::class), 'arrangeBracket'], ['request' => $request, 'event' => $tablet->event]));
    }

    public function admit(Request $request, string $token): Response
    {
        return $this->asEditor($token, fn ($tablet) => app()->call(
            [app(PersonalEventController::class), 'admitLateEntry'], ['request' => $request, 'event' => $tablet->event]));
    }

    public function candidates(Request $request, string $token): Response
    {
        return $this->asEditor($token, fn ($tablet) => app()->call(
            [app(PersonalEventController::class), 'lateEntryCandidates'], ['request' => $request, 'event' => $tablet->event]));
    }

    public function unnamed(Request $request, string $token): Response
    {
        return $this->asEditor($token, fn ($tablet) => app()->call(
            [app(PersonalEventController::class), 'storeUnnamedEntry'], ['request' => $request, 'event' => $tablet->event]));
    }

    public function clubs(Request $request, string $token): Response
    {
        return $this->asEditor($token, fn ($tablet) => app()->call(
            [app(EventClubsController::class), 'index'], ['request' => $request, 'event' => $tablet->event]));
    }

    /** Decide or correct a bout — the organiser's own outcome action. */
    public function outcome(Request $request, string $token, int $unit): Response
    {
        return $this->asEditor($token, fn ($tablet) => app()->call(
            [app(PersonalEventController::class), 'recordOutcome'], ['request' => $request, 'event' => $tablet->event, 'unit' => $unit]));
    }

    /**
     * Where one bout stands in the running order — its mat, its number, and
     * how many bouts each mat of that day holds — for the tablet's "edit bout
     * number" sheet. Organiser only, like the order board it reads.
     */
    public function numberPlace(string $token, int $match): Response
    {
        return $this->asEditor($token, function ($tablet) use ($match) {
            abort_unless($this->manages($tablet, Auth::user()), 403);

            $bout = \App\Events\Models\EventMatch::where('event_id', $tablet->event->id)->find($match);
            abort_unless($bout !== null, 404);

            $plan = app(\App\Events\Support\Tournament\OrderBoard::class)->plan($tablet->event, $bout->day ?: null);

            return response()->json([
                'success' => true,
                'match' => [
                    'id' => $bout->id, 'no' => $bout->match_no, 'court' => $bout->court,
                    'day' => $plan['day'], 'decided' => $bout->winner !== null,
                    'pinned' => (bool) $bout->number_pinned,
                ],
                'mats' => array_map(fn ($m) => ['court' => $m['court'], 'count' => count($m['bouts'])], $plan['mats']),
            ]);
        });
    }

    /**
     * Give a bout a new number (and, if asked, a new mat). A bout's number IS
     * its position on its mat, so this is the order board's own move — the
     * same renumbering, the same feeder rule, the same refresh push.
     */
    public function renumber(Request $request, string $token): Response
    {
        return $this->asEditor($token, function ($tablet) use ($request) {
            abort_unless($this->manages($tablet, Auth::user()), 403);

            $data = $request->validate([
                'match' => ['required', 'integer'],
                'court' => ['required', 'string', 'max:40'],
                'no' => ['required', 'integer', 'min:1', 'max:999'],
                'day' => ['nullable', 'integer', 'min:1', 'max:60'],
            ]);

            abort_unless(\App\Events\Models\EventMatch::where('event_id', $tablet->event->id)
                ->whereKey((int) $data['match'])->exists(), 404);

            // Only a mat the day actually has — never a label typed into the request.
            $mats = array_column(app(\App\Events\Support\Tournament\OrderBoard::class)
                ->plan($tablet->event, $data['day'] ?? null)['mats'], 'court');
            abort_unless(in_array($data['court'], $mats, true), 422);

            $request->replace([
                'match' => (int) $data['match'],
                'court' => $data['court'],
                'position' => (int) $data['no'] - 1,
                'day' => $data['day'] ?? null,
            ]);

            return app()->call([app(PersonalEventController::class), 'moveInRunningOrder'],
                ['request' => $request, 'event' => $tablet->event]);
        });
    }

    /** Hand a bout's number back to automatic numbering. Organiser only. */
    public function releaseNumber(string $token, int $match): Response
    {
        return $this->asEditor($token, function ($tablet) use ($match) {
            abort_unless($this->manages($tablet, Auth::user()), 403);

            $result = app(\App\Events\Support\Tournament\OrderBoard::class)->release($tablet->event, $match);

            if (! ($result['ok'] ?? false)) {
                return $this->no($result['message'] ?? __('events::events.action_failed'));
            }

            rescue(fn () => app(\App\Events\EventTypeRegistry::class)->for($tablet->event)
                ->onEntrantsChanged($tablet->event, null), null, false);
            \App\Events\Support\HallWalls::refresh($tablet->event);

            return response()->json(['success' => true, 'message' => $result['message']]);
        });
    }

    /**
     * Correct a competitor's NAME, and nothing else.
     *
     * The organiser's entry editor also takes a photo, a weight, a belt, a
     * club. A tablet at a mat has no business changing any of those, so the
     * request is cut down to the one field the board edits before it is
     * handed on.
     */
    public function entrant(Request $request, string $token, int $registration): Response
    {
        return $this->asEditor($token, function ($tablet) use ($request, $registration) {
            $entry = \App\Events\Models\ClubEventRegistration::where('event_id', $tablet->event->id)->find($registration);
            abort_unless($entry !== null, 404);

            $request->replace(['name' => $request->input('name')]);

            return app()->call([app(EntryPanelController::class), 'updateEntrant'],
                ['request' => $request, 'event' => $tablet->event, 'registration' => $entry]);
        });
    }

    private function manages(TabletDraw $tablet, \App\Members\Models\User $user): bool
    {
        return app(\App\Events\Support\EventAccess::class)->canManage($tablet->event, $user);
    }

    // ── Plumbing ─────────────────────────────────────────────────────────

    /**
     * The tablet and (when asked) the grant behind it — or the 404 every dead
     * identity gets. An expired grant is a 403 with a message, because the
     * tablet is fine and the board should say "unlock again".
     *
     * @return array{0: TabletDraw, 1: ?\App\Members\Models\User}
     */
    private function tablet(string $token, bool $needsEditor = false): array
    {
        abort_unless(TabletDraw::enabled(), 404);

        $tablet = TabletDraw::resolve($token);
        abort_unless($tablet !== null, 404);

        $editor = $tablet->editor();

        if ($needsEditor && ! $editor) {
            abort(response()->json(['success' => false, 'message' => __('events::events.tablet_locked_again'), 'locked' => true], 403));
        }

        return [$tablet, $editor];
    }

    private function asEditor(string $token, \Closure $run): Response
    {
        [$tablet, $editor] = $this->tablet($token, needsEditor: true);

        Auth::setUser($editor);
        $tablet->extend();

        return $run($tablet);
    }

    private function no(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
