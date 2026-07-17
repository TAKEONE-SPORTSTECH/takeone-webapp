<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\Duel;
use App\Models\DuelMedia;
use App\Models\DuelWitness;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChallengeController extends Controller
{
    /* ===================================================================
     |  Hub
     * =================================================================== */

    public function index(Request $request): View
    {
        $me = Auth::user();

        $challenges = $this->myChallengeViews($me->id);
        $duels = $this->myDuelViews($me->id);

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'personal.mobile.challenge' : 'personal.desktop.challenge', compact('challenges', 'duels'));
    }

    public function show(Challenge $challenge): View
    {
        $me = Auth::user();
        $this->assertCanAccess($challenge, $me);
        $c = $this->challengeView($challenge, $me->id);

        return view('personal.challenge-show', ['c' => $c]);
    }

    public function duel(Duel $duel, Request $request): View
    {
        $me = Auth::user();
        // Participants and the duel's witnesses may view it.
        abort_unless($duel->involves($me->id) || $this->isWitness($duel, $me->id), 403);
        $this->expireIfStale($duel);   // auto-cancel if unanswered for 3+ days
        $duel->load(['challenger', 'opponent', 'event', 'media.user', 'witnesses.user']);
        $d = $this->duelView($duel, $me->id);

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'personal.mobile.duel-show' : 'personal.desktop.duel-show', ['d' => $d]);
    }

    public function create(): View
    {
        $me = Auth::user();
        $clubIds = $me->memberClubs()->pluck('tenants.id');

        // Club-mates (real users sharing a club with me).
        $mateIds = DB::table('memberships')
            ->whereIn('tenant_id', $clubIds)
            ->where('user_id', '!=', $me->id)
            ->distinct()->pluck('user_id');

        $opponents = User::whereIn('id', $mateIds)
            ->get(['id', 'full_name', 'name', 'profile_picture', 'updated_at'])
            ->map(fn ($u) => $this->pickerRow($u))
            ->values()->all();

        // Platform-wide athletes from OTHER clubs (discover).
        $athletes = User::whereNotIn('id', $mateIds->push($me->id))
            ->whereHas('memberClubs')
            ->limit(30)
            ->get(['id', 'full_name', 'name', 'profile_picture', 'updated_at'])
            ->map(fn ($u) => $this->pickerRow($u, true))
            ->values()->all();

        $myAvatar = $this->avatarUrl($me);

        // Facilities of clubs the user belongs to — for the location "facility" picker.
        $facilities = \App\Models\ClubFacility::whereIn('tenant_id', $clubIds)
            ->with('tenant:id,club_name')
            ->get(['id', 'tenant_id', 'name', 'address', 'gps_lat', 'gps_long', 'maps_url'])
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'club' => $f->tenant?->club_name,
                'address' => $f->address,
                'lat' => $f->gps_lat,
                'lng' => $f->gps_long,
                'url' => $f->maps_url ?: (($f->gps_lat && $f->gps_long) ? "https://maps.google.com/?q={$f->gps_lat},{$f->gps_long}" : null),
            ])
            ->values()->all();

        // Upcoming events of the user's clubs — a challenge can be attached to one and inherit its location.
        $events = \App\Models\ClubEvent::whereIn('tenant_id', $clubIds)
            ->where('status', 'published')
            ->whereDate('date', '>=', now()->toDateString())
            ->with('tenant:id,club_name')
            ->orderBy('date')
            ->get(['id', 'uuid', 'title', 'date', 'location', 'location_url', 'gps_lat', 'gps_long', 'tenant_id'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'club' => $e->tenant?->club_name,
                'date' => optional($e->date)->format('M j, Y'),
                'location' => $e->location,
                'url' => $this->safeUrl($e->location_url)
                                ?: (($e->gps_lat && $e->gps_long) ? "https://maps.google.com/?q={$e->gps_lat},{$e->gps_long}" : null),
                'lat' => $e->gps_lat,
                'lng' => $e->gps_long,
            ])
            ->values()->all();

        return view('personal.challenge-create', compact('opponents', 'athletes', 'myAvatar', 'facilities', 'events'));
    }

    public function history(Request $request): View
    {
        $me = Auth::user();

        $duels = collect($this->myDuelViews($me->id))
            ->where('status', 'completed')->values()->all();

        $solo = collect($this->myChallengeViews($me->id))
            ->where('status', 'completed')->values()->all();

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'personal.mobile.challenge-history' : 'personal.desktop.challenge-history', compact('duels', 'solo'));
    }

    /* ===================================================================
     |  Duel write actions
     * =================================================================== */

    public function store(Request $request): JsonResponse
    {
        $me = Auth::user();

        $data = $request->validate([
            'type' => ['required', Rule::in(['athletic', 'fight'])],
            'opponent_id' => ['nullable', 'integer', 'exists:users,id', 'different:__me'],
            'invite' => ['nullable', 'string', 'max:120'],
            'discipline' => ['required', 'string', 'max:120'],
            'metric' => ['nullable', 'string', 'max:60'],
            'format' => ['nullable', Rule::in(Duel::FORMATS)],
            'event_id' => ['nullable', 'integer', 'exists:club_events,id'],
            'stake' => ['required', 'integer', 'min:0', 'max:100000'],
            'deadline' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:160'],
            'location_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_long' => ['nullable', 'numeric', 'between:-180,180'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        if (empty($data['opponent_id']) && empty($data['invite'])) {
            return response()->json(['success' => false, 'message' => 'Choose an opponent or enter an invite.'], 422);
        }

        // If attached to an event, the event must belong to a club the user is in, and the
        // duel inherits the event's location (overriding any manually-entered location).
        $eventId = null;
        if (! empty($data['event_id'])) {
            $clubIds = $me->memberClubs()->pluck('tenants.id');
            $event = \App\Models\ClubEvent::whereKey($data['event_id'])->whereIn('tenant_id', $clubIds)->first();
            if (! $event) {
                return response()->json(['success' => false, 'message' => 'That event is not available.'], 422);
            }
            $eventId = $event->id;
            $data['location'] = $event->location ?: $event->title;
            $data['location_url'] = $this->safeUrl($event->location_url)
                ?: (($event->gps_lat && $event->gps_long) ? "https://maps.google.com/?q={$event->gps_lat},{$event->gps_long}" : null);
            $data['gps_lat'] = $event->gps_lat;
            $data['gps_long'] = $event->gps_long;
        }
        if (! empty($data['opponent_id']) && (int) $data['opponent_id'] === $me->id) {
            return response()->json(['success' => false, 'message' => "You can't challenge yourself."], 422);
        }

        $duel = Duel::create([
            'challenger_id' => $me->id,
            'opponent_id' => $data['opponent_id'] ?? null,
            'opponent_handle' => empty($data['opponent_id']) ? ($data['invite'] ?? null) : null,
            'opponent_name' => empty($data['opponent_id']) ? ($data['invite'] ?? null) : null,
            'type' => $data['type'],
            'discipline' => $data['discipline'],
            'metric' => $data['metric'] ?? $this->metricForFormat($data['format'] ?? 'single'),
            'format' => $data['format'] ?? 'single',
            'event_id' => $eventId,
            'stake_points' => $data['stake'],
            'deadline' => $data['deadline'] ?? null,
            'location' => $data['location'] ?? null,
            'location_url' => $data['location_url'] ?? null,
            'gps_lat' => $data['gps_lat'] ?? null,
            'gps_long' => $data['gps_long'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        $this->notify($duel->opponent_id, 'duel:new', $duel, $me, 'challenged you to a duel');

        return response()->json([
            'success' => true,
            'message' => 'Challenge sent 🔥',
            'redirect' => route('me.challenge'),
            'duel' => $this->duelView($duel->fresh(['challenger', 'opponent']), $me->id),
        ]);
    }

    public function accept(Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->opponent_id === $me->id && $duel->status === 'pending', 403);

        // A challenge auto-expires if not accepted within 3 days of being sent.
        if ($this->expireIfStale($duel)) {
            return response()->json(['success' => false, 'message' => 'This challenge expired — it wasn’t accepted within 3 days.', 'status' => 'cancelled'], 422);
        }

        $duel->update(['status' => 'active', 'responded_at' => now()]);
        $this->notify($duel->challenger_id, 'duel:accepted', $duel, $me, 'accepted your duel');

        return response()->json(['success' => true, 'message' => 'Challenge accepted — game on! 🔥', 'status' => 'active']);
    }

    /** Cancel a still-pending duel that has gone unanswered for 3+ days. Returns true if it expired. */
    private function expireIfStale(Duel $duel): bool
    {
        if ($duel->status !== 'pending' || ! $duel->created_at || $duel->created_at->gt(now()->subDays(3))) {
            return false;
        }
        $duel->update(['status' => 'cancelled', 'cancel_reason' => 'Not accepted within 3 days']);
        $this->notify($duel->challenger_id, 'duel:cancelled', $duel, $duel->opponent ?? $duel->challenger, 'challenge expired — not accepted within 3 days');

        return true;
    }

    public function decline(Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->opponent_id === $me->id && $duel->status === 'pending', 403);

        $duel->update(['status' => 'declined', 'responded_at' => now()]);
        $this->notify($duel->challenger_id, 'duel:declined', $duel, $me, 'declined your duel');

        return response()->json(['success' => true, 'message' => 'Duel declined', 'status' => 'declined']);
    }

    public function cancel(Request $request, Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->involves($me->id), 403);
        abort_unless(in_array($duel->status, ['pending', 'active', 'reported'], true), 422, 'This duel can no longer be cancelled.');
        // A pending invite can only be withdrawn by the challenger (the opponent declines instead).
        if ($duel->status === 'pending') {
            abort_unless($duel->challenger_id === $me->id, 403);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $duel->update(['status' => 'cancelled', 'cancel_reason' => $data['reason'] ?? null]);

        $note = 'cancelled the duel'.(! empty($data['reason']) ? ': '.$data['reason'] : '');
        $this->notify($duel->rivalId($me->id), 'duel:cancelled', $duel, $me, $note);

        return response()->json(['success' => true, 'message' => 'Challenge cancelled', 'status' => 'cancelled']);
    }

    /**
     * One participant PROPOSES the result; it is NOT credited until the rival
     * confirms. winner_id is only honoured once status === 'completed'.
     */
    public function report(Request $request, Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->involves($me->id) && $duel->status === 'active', 403);

        // Pure external-opponent duels have no second human to confirm — block.
        abort_if($duel->opponent_id === null, 422, 'This duel has no confirmed opponent yet.');

        $rivalId = $duel->rivalId($me->id);
        $iAmChallenger = $duel->challenger_id === $me->id;
        $format = in_array($duel->format, Duel::FORMATS, true) ? $duel->format : 'single';

        // Translate a viewer-relative 'me'/'rival' into a canonical 'challenger'/'opponent'.
        $sideOf = fn (string $who) => (($who === 'me') === $iAmChallenger) ? 'challenger' : 'opponent';

        $rounds = null;
        $cScore = null;
        $oScore = null;
        $winnerSide = null;

        if ($format === 'bo3' || $format === 'bo5') {
            $max = $format === 'bo3' ? 3 : 5;
            $data = $request->validate([
                'rounds' => ['required', 'array', 'min:1', 'max:'.$max],
                'rounds.*' => ['required', Rule::in(['me', 'rival'])],
            ]);
            $rounds = array_map($sideOf, $data['rounds']);                 // store canonical
            $cWins = count(array_filter($rounds, fn ($s) => $s === 'challenger'));
            $oWins = count($rounds) - $cWins;
            abort_if($cWins === $oWins, 422, 'Rounds are tied — log a deciding round.');
            $winnerSide = $cWins > $oWins ? 'challenger' : 'opponent';
            $cScore = (string) $cWins;
            $oScore = (string) $oWins;
        } elseif ($format === 'points' || $format === 'time') {
            $data = $request->validate([
                'my_score' => ['required', 'numeric'],
                'opp_score' => ['required', 'numeric'],
            ]);
            $my = (float) $data['my_score'];
            $opp = (float) $data['opp_score'];
            abort_if($my === $opp, 422, 'Scores are tied — a duel needs a winner.');
            $iWin = $format === 'points' ? ($my > $opp) : ($my < $opp);   // points: high wins; time: low wins
            $winnerSide = $iWin ? $sideOf('me') : $sideOf('rival');
            $cScore = $iAmChallenger ? (string) $data['my_score'] : (string) $data['opp_score'];
            $oScore = $iAmChallenger ? (string) $data['opp_score'] : (string) $data['my_score'];
        } else { // single
            $data = $request->validate([
                'winner' => ['required', Rule::in(['me', 'rival'])],
                'my_score' => ['nullable', 'string', 'max:30'],
                'opp_score' => ['nullable', 'string', 'max:30'],
            ]);
            $winnerSide = $sideOf($data['winner']);
            $cScore = $iAmChallenger ? ($data['my_score'] ?? null) : ($data['opp_score'] ?? null);
            $oScore = $iAmChallenger ? ($data['opp_score'] ?? null) : ($data['my_score'] ?? null);
        }

        $winnerId = $winnerSide === 'challenger' ? $duel->challenger_id : $duel->opponent_id;

        $duel->update([
            'status' => 'reported',            // awaiting the rival's confirmation
            'winner_id' => $winnerId,             // provisional — not credited yet
            'reported_by' => $me->id,
            'rounds' => $rounds,
            'challenger_score' => $cScore,
            'opponent_score' => $oScore,
        ]);

        $this->notify($rivalId, 'duel:result', $duel, $me, 'reported a result — confirm it');

        return response()->json([
            'success' => true,
            'message' => 'Result submitted — awaiting your rival’s confirmation',
            'status' => 'reported',
        ]);
    }

    /** The OTHER participant confirms a reported result → it becomes official. */
    public function confirm(Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->involves($me->id) && $duel->status === 'reported' && $duel->reported_by !== $me->id, 403);

        $duel->update(['status' => 'completed', 'completed_at' => now()]);
        $this->notify($duel->reported_by, 'duel:confirmed', $duel, $me, 'confirmed the duel result');

        return response()->json([
            'success' => true,
            'message' => $duel->winner_id === $me->id ? 'Confirmed — you won! 🏆' : 'Result confirmed',
            'status' => 'completed',
            'won' => $duel->winner_id === $me->id,
        ]);
    }

    /** The OTHER participant disputes → result is cleared, duel back to active. */
    public function dispute(Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->involves($me->id) && $duel->status === 'reported' && $duel->reported_by !== $me->id, 403);

        $reporter = $duel->reported_by;
        $duel->update([
            'status' => 'active',
            'winner_id' => null,
            'reported_by' => null,
            'rounds' => null,
            'challenger_score' => null,
            'opponent_score' => null,
        ]);
        $this->notify($reporter, 'duel:disputed', $duel, $me, 'disputed the result — re-enter it together');

        return response()->json(['success' => true, 'message' => 'Result disputed — duel is active again', 'status' => 'active']);
    }

    /** The challenger (owner) edits the duel's core details — only before it's completed. */
    public function updateDuel(Request $request, Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->challenger_id === $me->id, 403);
        abort_if(in_array($duel->status, ['completed', 'cancelled', 'declined'], true), 422, 'This duel can no longer be edited.');

        $data = $request->validate([
            'discipline' => ['required', 'string', 'max:120'],
            'metric' => ['nullable', 'string', 'max:60'],
            'format' => ['required', Rule::in(Duel::FORMATS)],
            'stake' => ['required', 'integer', 'min:0', 'max:100000'],
            'deadline' => ['nullable', 'date'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        // Changing the format mid-flight invalidates any provisional result.
        if ($duel->status === 'reported' && $data['format'] !== $duel->format) {
            $duel->forceFill(['status' => 'active', 'winner_id' => null, 'reported_by' => null, 'rounds' => null, 'challenger_score' => null, 'opponent_score' => null]);
        }

        $duel->update([
            'discipline' => $data['discipline'],
            'metric' => $data['metric'] ?? $this->metricForFormat($data['format']),
            'format' => $data['format'],
            'stake_points' => $data['stake'],
            'deadline' => $data['deadline'] ?? null,
            'message' => $data['message'] ?? null,
        ]);

        $this->notify($duel->opponent_id, 'duel:updated', $duel, $me, 'updated the duel details');

        return response()->json([
            'success' => true,
            'message' => 'Duel updated',
            'duel' => $this->duelView($duel->fresh(['challenger', 'opponent', 'event']), $me->id),
        ]);
    }

    /** Super-admin moderation — permanently delete a challenge (and its files). */
    public function destroyDuel(Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($me->isSuperAdmin(), 403);

        // Purge uploaded media files first (DB cascade would bypass the file-delete trait).
        $duel->media()->get()->each->delete();
        $duel->delete();   // cascades witnesses + any remaining media rows

        return response()->json([
            'success' => true,
            'message' => 'Challenge deleted',
            'redirect' => route('me.challenge'),
        ]);
    }

    /** A participant or witness attaches media — an uploaded photo/video, or a video link. */
    public function addMedia(Request $request, Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->involves($me->id) || $this->isWitness($duel, $me->id), 403);

        $type = $request->input('type');
        abort_unless(in_array($type, ['image', 'video', 'link'], true), 422, 'Invalid media type.');

        if ($type === 'link') {
            $data = $request->validate([
                'url' => ['required', 'string', 'max:1000', 'url:http,https'],
                'caption' => ['nullable', 'string', 'max:160'],
            ]);
            $url = $data['url'];
        } else {
            // Explicit allowlist — exclude SVG (script-bearing) since uploads are served same-origin.
            $fileRules = $type === 'image'
                ? ['mimes:jpg,jpeg,png,gif,webp', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:8192']
                : ['mimetypes:video/mp4,video/quicktime,video/webm', 'max:102400'];
            $request->validate([
                'file' => array_merge(['required', 'file'], $fileRules),
                'caption' => ['nullable', 'string', 'max:160'],
            ]);
            $url = $request->file('file')->store("duel-media/{$duel->id}", 'public');
        }

        $media = $duel->media()->create([
            'user_id' => $me->id,
            'type' => $type,
            'url' => $url,
            'caption' => $request->input('caption'),
        ]);

        $this->notify($duel->rivalId($me->id), 'duel:media', $duel, $me, 'added media to your duel');

        return response()->json([
            'success' => true,
            'message' => 'Media added',
            'media' => $this->mediaView($media, $me->id),
        ]);
    }

    /** Remove a media item — the uploader, or the duel owner, may delete it. */
    public function deleteMedia(Duel $duel, DuelMedia $media): JsonResponse
    {
        $me = Auth::user();
        abort_unless($media->duel_id === $duel->id, 404);
        abort_unless($media->user_id === $me->id || $duel->challenger_id === $me->id, 403);

        $media->delete();   // DeletesUploadedFiles purges the stored file first

        return response()->json(['success' => true, 'message' => 'Media removed', 'id' => $media->id]);
    }

    /** Look up platform members to add as a witness — by name, email or phone number. */
    public function searchWitnesses(Request $request, Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->involves($me->id), 403);

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'users' => []]);
        }

        $exclude = collect([$duel->challenger_id, $duel->opponent_id, $me->id])
            ->merge($duel->witnesses()->pluck('user_id'))
            ->filter()->unique()->all();

        $digits = preg_replace('/\D+/', '', $q);   // for phone matching

        $users = User::query()
            ->whereNotIn('id', $exclude)
            ->where(function ($w) use ($q, $digits) {
                $w->where('full_name', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
                if ($digits !== '') {
                    $w->orWhere('mobile', 'like', "%{$digits}%");   // mobile is JSON {code,number}
                }
            })
            ->orderBy('full_name')
            ->limit(15)
            ->get(['id', 'full_name', 'name', 'profile_picture', 'updated_at']);

        return response()->json([
            'success' => true,
            'users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->full_name ?? $u->name ?? 'Member',
                'avatar' => $this->avatarUrl($u),
            ])->values(),
        ]);
    }

    /** Add a witness (only meaningful when the duel isn't part of an event). */
    public function addWitness(Request $request, Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->involves($me->id), 403);
        abort_if($duel->event_id !== null, 422, 'Event duels are witnessed by the event.');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $uid = (int) $data['user_id'];

        abort_if(in_array($uid, [$duel->challenger_id, $duel->opponent_id], true), 422, 'A participant can’t be their own witness.');
        abort_if($duel->witnesses()->where('user_id', $uid)->exists(), 422, 'That person is already a witness.');

        $user = User::find($uid);
        $witness = $duel->witnesses()->create([
            'user_id' => $uid,
            'name' => $user->full_name ?? $user->name ?? 'Member',
            'added_by' => $me->id,
        ]);
        $witness->setRelation('user', $user);

        $this->notify($duel->rivalId($me->id), 'duel:witness', $duel, $me, 'added a witness to your duel');
        $this->notify($uid, 'duel:witness', $duel, $me, 'named you as a witness to a duel');

        return response()->json([
            'success' => true,
            'message' => 'Witness added',
            'witness' => $this->witnessView($witness, $me->id),
        ]);
    }

    /** Is this user a witness of the duel? */
    private function isWitness(Duel $duel, int $uid): bool
    {
        return $duel->witnesses()->where('user_id', $uid)->exists();
    }

    /** A witness accepts (will attend) or declines the request to witness. */
    public function respondWitness(Request $request, Duel $duel, DuelWitness $witness): JsonResponse
    {
        $me = Auth::user();
        abort_unless($witness->duel_id === $duel->id, 404);
        abort_unless($witness->user_id === $me->id, 403);

        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'declined'])]]);
        $witness->update(['status' => $data['status']]);

        $verb = $data['status'] === 'accepted' ? 'is attending as a witness' : 'declined to witness';
        $this->notify($duel->challenger_id, 'duel:witness', $duel, $me, $verb);
        if ($duel->opponent_id) {
            $this->notify($duel->opponent_id, 'duel:witness', $duel, $me, $verb);
        }

        return response()->json([
            'success' => true,
            'message' => $data['status'] === 'accepted' ? "You're in — thanks for witnessing 🙌" : 'You declined to witness',
            'witness' => $this->witnessView($witness->fresh('user'), $me->id),
        ]);
    }

    /** A witness rates and comments on the challenge — only the witness themselves. */
    public function witnessFeedback(Request $request, Duel $duel, DuelWitness $witness): JsonResponse
    {
        $me = Auth::user();
        abort_unless($witness->duel_id === $duel->id, 404);
        abort_unless($witness->user_id === $me->id, 403);   // only the named witness can vouch

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $witness->update(['rating' => $data['rating'], 'comment' => $data['comment'] ?? null]);
        $witness->setRelation('user', $witness->user);   // keep avatar for the view

        $this->notify($duel->challenger_id, 'duel:witness', $duel, $me, 'rated your duel');
        if ($duel->opponent_id) {
            $this->notify($duel->opponent_id, 'duel:witness', $duel, $me, 'rated your duel');
        }

        return response()->json([
            'success' => true,
            'message' => 'Thanks — your feedback was saved',
            'witness' => $this->witnessView($witness, $me->id),
        ]);
    }

    /** Remove a witness — the person who added it, or the duel owner. */
    public function deleteWitness(Duel $duel, DuelWitness $witness): JsonResponse
    {
        $me = Auth::user();
        abort_unless($witness->duel_id === $duel->id, 404);
        abort_unless($witness->added_by === $me->id || $duel->challenger_id === $me->id, 403);

        $witness->delete();

        return response()->json(['success' => true, 'message' => 'Witness removed', 'id' => $witness->id]);
    }

    /** Shape a witness row for the view. */
    private function witnessView(DuelWitness $w, int $meId): array
    {
        return [
            'id' => $w->id,
            'name' => $w->name,
            'avatar' => $this->avatarUrl($w->user),
            'status' => $w->status,                 // invited | accepted | declined
            'rating' => $w->rating,
            'comment' => $w->comment,
            'mine' => $w->added_by === $meId,    // the person who added this witness can remove it
            'is_me' => $w->user_id === $meId,      // I am this witness → I can attend/rate/comment
        ];
    }

    /** Shape a media row for the view (viewer-relative ownership). */
    private function mediaView(DuelMedia $m, int $meId): array
    {
        return [
            'id' => $m->id,
            'type' => $m->type,
            'url' => $m->full_url,
            'caption' => $m->caption,
            'mine' => $m->user_id === $meId,
        ];
    }

    /* ===================================================================
     |  Solo challenge write actions
     * =================================================================== */

    public function join(Challenge $challenge): JsonResponse
    {
        $me = Auth::user();
        // You can only join a challenge run by a club you belong to.
        abort_unless($me->memberClubs()->whereKey($challenge->tenant_id)->exists(), 403);

        $p = ChallengeParticipation::firstOrCreate(
            ['challenge_id' => $challenge->id, 'user_id' => $me->id],
            ['progress' => 0]
        );

        return response()->json([
            'success' => true,
            'message' => 'You joined '.$challenge->title,
            'joined' => true,
            'progress' => $p->progress,
            'participants' => $challenge->participations()->count(),
        ]);
    }

    public function leave(Challenge $challenge): JsonResponse
    {
        $me = Auth::user();
        // Only ever removes the caller's own participation (scoped by user_id).
        ChallengeParticipation::where('challenge_id', $challenge->id)->where('user_id', $me->id)->delete();

        return response()->json(['success' => true, 'message' => 'You left this challenge', 'joined' => false]);
    }

    public function progress(Request $request, Challenge $challenge): JsonResponse
    {
        $me = Auth::user();

        $p = ChallengeParticipation::where('challenge_id', $challenge->id)->where('user_id', $me->id)->first();
        if (! $p) {
            return response()->json(['success' => false, 'message' => 'Join the challenge first.'], 422);
        }

        $data = $request->validate([
            'amount' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $goal = max(1, (int) $challenge->goal);
        $step = $data['amount'] ?? max(1, (int) round($goal * 0.12));
        $p->progress = min($goal, $p->progress + $step);
        if ($p->progress >= $goal && ! $p->completed_at) {
            $p->completed_at = now();
            $p->streak += 1;
        }
        $p->save();

        $pct = $goal > 0 ? min(100, (int) round($p->progress / $goal * 100)) : 0;

        return response()->json([
            'success' => true,
            'message' => $p->completed_at ? 'Challenge complete! 🎉' : 'Progress logged · '.$pct.'%',
            'current' => $p->progress,
            'progress' => $pct,
            'completed' => (bool) $p->completed_at,
        ]);
    }

    /* ===================================================================
     |  View-model mappers (DB → the shapes the Blade views expect)
     * =================================================================== */

    private function myChallengeViews(int $meId): array
    {
        $me = User::find($meId);
        $clubIds = $me->memberClubs()->pluck('tenants.id');

        $myParts = ChallengeParticipation::where('user_id', $meId)->get()->keyBy('challenge_id');

        $challenges = Challenge::query()
            ->where('is_active', true)
            ->where(function ($q) use ($clubIds, $myParts) {
                $q->whereIn('tenant_id', $clubIds)
                    ->orWhereIn('id', $myParts->keys());
            })
            ->withCount('participations')
            ->with('tenant:id,club_name')
            ->orderByDesc('id')
            ->get();

        return $challenges->map(fn ($c) => $this->challengeView($c, $meId, $myParts->get($c->id)))->values()->all();
    }

    private function challengeView(Challenge $c, int $meId, ?ChallengeParticipation $p = null): array
    {
        $p = $p ?? ChallengeParticipation::where('challenge_id', $c->id)->where('user_id', $meId)->first();

        $current = $p?->progress ?? 0;
        $goal = max(1, (int) $c->goal);
        $pct = $c->goal > 0 ? min(100, (int) round($current / $goal * 100)) : ($p?->completed_at ? 100 : 0);

        // Leaderboard (top participants by progress).
        $parts = $c->relationLoaded('participations')
            ? $c->participations
            : $c->participations()->with('user:id,full_name,name,profile_picture,updated_at')->orderByDesc('progress')->get();
        if (! $parts->first()?->relationLoaded('user')) {
            $parts->load('user:id,full_name,name,profile_picture,updated_at');
        }
        $ranked = $parts->sortByDesc('progress')->values();

        $rank = null;
        foreach ($ranked as $i => $row) {
            if ($row->user_id === $meId) {
                $rank = $i + 1;
                break;
            }
        }

        $leaders = $ranked->take(5)->map(fn ($row) => [
            'name' => $row->user_id === $meId ? 'You' : ($row->user->full_name ?? $row->user->name ?? 'Member'),
            'avatar' => $this->avatarUrl($row->user),
            'val' => number_format($row->progress).($c->unit ?: ''),
            'pts' => $row->completed_at ? (int) $c->points : (int) round($c->points * ($goal > 0 ? $row->progress / $goal : 0)),
            'me' => $row->user_id === $meId,
        ])->values()->all();

        return [
            'id' => $c->id,
            'status' => $c->lifecycle(),
            'title' => $c->title,
            'club' => $c->tenant?->club_name ?? 'TAKEONE',
            'tag' => $c->tag ?? 'Challenge',
            'icon' => $c->icon,
            'color' => $c->color,
            'progress' => $pct,
            'metric' => $c->metric,
            'current' => $current,
            'goal' => (int) $c->goal,
            'unit' => $c->unit ?? '',
            'days_left' => $c->daysLeft(),
            'points' => (int) $c->points,
            'participants' => $c->participations_count ?? $parts->count(),
            'rank' => $rank,
            'streak' => $p?->streak ?? 0,
            'joined' => (bool) $p,
            'completed' => (bool) $p?->completed_at,
            'about' => $c->about ?? '',
            'rules' => $c->rules ?? [],
            'rewards' => $c->rewards ?? [],
            'leaders' => $leaders,
        ];
    }

    private function myDuelViews(int $meId): array
    {
        $duels = Duel::query()
            ->where(fn ($q) => $q->where('challenger_id', $meId)->orWhere('opponent_id', $meId))
            ->whereNotIn('status', ['declined', 'cancelled'])
            ->with(['challenger:id,full_name,name,profile_picture,updated_at,gender', 'opponent:id,full_name,name,profile_picture,updated_at,gender', 'event:id,uuid,title'])
            ->orderByDesc('id')
            ->get();

        return $duels->map(fn ($d) => $this->duelView($d, $meId))->values()->all();
    }

    private function duelView(Duel $d, int $meId): array
    {
        $iAmChallenger = $d->challenger_id === $meId;

        // Resolve the rival's display + record.
        if ($iAmChallenger) {
            $rivalName = $d->opponent?->full_name ?? $d->opponent?->name ?? $d->opponent_name ?? 'Invited player';
            $rivalId = $d->opponent_id;
        } else {
            $rivalName = $d->challenger?->full_name ?? $d->challenger?->name ?? 'Challenger';
            $rivalId = $d->challenger_id;
        }

        // Per-viewer status label expected by the blades.
        $status = match (true) {
            $d->status === 'pending' && $d->opponent_id === $meId => 'invite_incoming',
            $d->status === 'pending' && $d->challenger_id === $meId => 'invite_sent',
            $d->status === 'active' => 'active',
            $d->status === 'reported' => 'reported',
            $d->status === 'completed' => 'completed',
            default => $d->status,
        };

        $myScore = $iAmChallenger ? $d->challenger_score : $d->opponent_score;
        $oppScore = $iAmChallenger ? $d->opponent_score : $d->challenger_score;

        $meUser = $iAmChallenger ? $d->challenger : $d->opponent;
        $rivalUser = $iAmChallenger ? $d->opponent : $d->challenger;

        $me = [
            'name' => 'You',
            'initials' => 'YO',
            'avatar' => $this->avatarUrl($meUser),
            'gender' => $meUser?->gender,
            'record' => $this->userRecord($meId),
        ];
        if ($myScore !== null && $myScore !== '') {
            $me['score'] = $myScore;
            $me['pct'] = 50;
        }

        $opp = [
            'name' => $rivalName,
            'initials' => $this->initials($rivalName),
            'avatar' => $this->avatarUrl($rivalUser),
            'gender' => $rivalUser?->gender,
            'record' => $rivalId ? $this->userRecord($rivalId) : '—',
            'rank' => null,
        ];
        if ($oppScore !== null && $oppScore !== '') {
            $opp['score'] = $oppScore;
            $opp['pct'] = 50;
        }

        // Round winners from the viewer's perspective ('me'/'rival') for best-of-N duels.
        $roundsView = is_array($d->rounds)
            ? array_map(fn ($side) => (($side === 'challenger') === $iAmChallenger) ? 'me' : 'rival', $d->rounds)
            : null;

        $view = [
            'id' => $d->id,
            'kind' => 'versus',
            'type' => $d->type,
            'status' => $status,
            'discipline' => $d->discipline,
            'icon' => $d->type === 'fight' ? 'bi-trophy' : 'bi-lightning-charge-fill',
            'color' => $d->type === 'fight' ? '#ef4444' : '#7c3aed',
            'me' => $me,
            'opponent' => $opp,
            'metric' => $d->metric,
            'format' => $d->format ?? 'single',
            'format_label' => Duel::formatLabel($d->format),
            'rounds' => $roundsView,
            'stake' => $d->stake_points.' pts',
            'deadline' => $d->deadline ? $d->deadline->format('M j, g:i A') : 'Not set',
            'challenge_time' => $d->deadline ? $d->deadline->format('D, M j · g:i A') : null,
            'arrival_by' => $d->deadline ? $d->deadline->copy()->subMinutes(30)->format('g:i A') : null,
            'event' => $d->event_id ? ['title' => $d->event?->title, 'uuid' => $d->event?->uuid] : null,
            'location' => $d->location ?: '—',
            // Only ever expose http(s) URLs to the href — blocks javascript:/data: XSS from a stored value.
            'location_url' => $this->safeUrl($d->location_url)
                                ?: (($d->gps_lat && $d->gps_long) ? "https://maps.google.com/?q={$d->gps_lat},{$d->gps_long}" : null),
            'message' => $d->message ?? '',
            'cancel_reason' => $d->cancel_reason,
            'stats' => ['me' => $this->duelStats($meId), 'opp' => $this->duelStats($rivalId)],
            'when' => optional($d->created_at)->diffForHumans() ?? '',
            // Owner controls + chat target + attached media.
            'can_edit' => $iAmChallenger && ! in_array($d->status, ['completed', 'cancelled', 'declined'], true),
            'opponent_user_id' => $rivalId,                                   // null for an external invite → no chat
            'media' => $d->relationLoaded('media')
                                    ? $d->media->map(fn ($m) => $this->mediaView($m, $meId))->values()->all()
                                    : [],
            'witnesses' => $d->relationLoaded('witnesses')
                                    ? $d->witnesses->map(fn ($w) => $this->witnessView($w, $meId))->values()->all()
                                    : [],
            // The current viewer's own witness entry (if they were named) + whether they may add media.
            'my_witness' => ($mw = ($d->relationLoaded('witnesses') ? $d->witnesses->firstWhere('user_id', $meId) : null))
                                    ? ['id' => $mw->id, 'status' => $mw->status]
                                    : null,
            'can_add_media' => $d->involves($meId) || ($d->relationLoaded('witnesses') && $d->witnesses->contains('user_id', $meId)),
            'edit' => [
                'discipline' => $d->discipline,
                'metric' => $d->metric,
                'format' => $d->format ?? 'single',
                'stake' => (int) $d->stake_points,
                'deadline' => optional($d->deadline)->toDateTimeString() ?? '',   // keep date + time
                'message' => $d->message ?? '',
            ],
        ];

        if ($status === 'completed') {
            $won = $d->winner_id === $meId;
            $view['result'] = $won ? 'win' : 'loss';
            $view['final'] = trim(($myScore ?? '–').' — '.($oppScore ?? '–'));
            $view['points_earned'] = $won ? (int) $d->stake_points : 0;
            $view['date'] = optional($d->completed_at)->format('M j') ?? '';
        }

        if ($status === 'reported') {
            $view['reported_by_me'] = $d->reported_by === $meId;
            $view['proposed_winner'] = $d->winner_id === $meId ? 'You' : $rivalName;
        }

        return $view;
    }

    /* ===================================================================
     |  Helpers
     * =================================================================== */

    /** A challenge is visible to club members or anyone already participating. */
    private function assertCanAccess(Challenge $challenge, User $me): void
    {
        abort_unless(
            $me->memberClubs()->whereKey($challenge->tenant_id)->exists()
                || $challenge->participations()->where('user_id', $me->id)->exists(),
            403
        );
    }

    private function pickerRow(User $u, bool $discover = false): array
    {
        $name = $u->full_name ?? $u->name ?? 'Member';
        $club = $u->memberClubs()->first();

        return [
            'id' => $u->id,
            'name' => $name,
            'initials' => $this->initials($name),
            'avatar' => $this->avatarUrl($u),
            'record' => $this->userRecord($u->id),
            'tag' => $club?->club_name ? 'Athlete' : 'Member',
            'club' => $club?->club_name ?? 'TAKEONE',
            'city' => $club?->country ?? '',
            'verified' => false,
        ];
    }

    private function userRecord(int $uid): string
    {
        $wins = Duel::where('status', 'completed')->where('winner_id', $uid)->count();
        $losses = Duel::where('status', 'completed')
            ->whereNotNull('winner_id')->where('winner_id', '!=', $uid)
            ->where(fn ($q) => $q->where('challenger_id', $uid)->orWhere('opponent_id', $uid))
            ->count();

        return "{$wins}W · {$losses}L";
    }

    private function initials(string $name): string
    {
        $name = ltrim($name, '@');
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $ini = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $ini ?: mb_strtoupper(mb_substr($name, 0, 2));
    }

    /** Win-condition label derived from the scoring format (replaces the old separate field). */
    private function metricForFormat(string $format): string
    {
        return [
            'single' => 'Best result',
            'bo3' => 'Best of 3',
            'bo5' => 'Best of 5',
            'points' => 'Highest score',
            'time' => 'Fastest time',
        ][$format] ?? 'Best result';
    }

    /** Real per-player record from completed duels: total, win-rate, best (most-won) discipline. */
    private function duelStats(?int $uid): array
    {
        if (! $uid) {
            return ['total' => 0, 'win_rate' => '—', 'best' => '—'];
        }
        $rows = Duel::where('status', 'completed')
            ->where(fn ($q) => $q->where('challenger_id', $uid)->orWhere('opponent_id', $uid))
            ->get(['discipline', 'winner_id']);

        $total = $rows->count();
        $wins = $rows->where('winner_id', $uid)->count();
        $best = $rows->where('winner_id', $uid)
            ->groupBy('discipline')->map->count()->sortDesc()->keys()->first();

        return [
            'total' => $total,
            'win_rate' => $total ? round($wins / $total * 100).'%' : '—',
            'best' => $best ?: '—',
        ];
    }

    /** Return the URL only if it's a safe http(s) link; otherwise null (blocks javascript:/data: hrefs). */
    private function safeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /** Cache-busted avatar URL for a user, or null when they have no picture (falls back to initials). */
    private function avatarUrl(?User $u): ?string
    {
        if (! $u || ! $u->profile_picture) {
            return null;
        }

        return asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp;
    }

    /** Best-effort realtime ping to a participant (DB stays source of truth). */
    private function notify(?int $userId, string $action, Duel $duel, User $actor, string $text): void
    {
        if (! $userId || $userId === $actor->id) {
            return;
        }

        // Persistent, tappable notification (DB row + MQTT push with a deep link to the duel).
        try {
            \App\Models\UserNotification::notifyUser($userId, $action, ($actor->full_name ?? $actor->name).' '.$text, [
                'actor_id' => $actor->id,
                'subject_type' => Duel::class,
                'subject_id' => $duel->id,
                'action_url' => route('me.challenge.duel', $duel->id),
                'icon' => 'bi-lightning-charge-fill',
                'body' => $duel->discipline,
            ]);
        } catch (\Throwable $e) {
            // best-effort
        }

        // Lightweight live ping on the challenges channel for any open challenge view.
        if (function_exists('Realtime')) {
            try {
                Realtime()->publishToUser($userId, 'challenges', [
                    'action' => $action,
                    'duel_id' => $duel->id,
                    'actor' => $actor->full_name ?? $actor->name,
                    'discipline' => $duel->discipline,
                    'text' => $text,
                ]);
            } catch (\Throwable $e) {
                // best-effort only
            }
        }
    }
}
