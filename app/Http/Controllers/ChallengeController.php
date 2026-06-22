<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\Duel;
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

    public function index(): View
    {
        $me = Auth::user();

        $challenges = $this->myChallengeViews($me->id);
        $duels      = $this->myDuelViews($me->id);

        return view('personal.challenge', compact('challenges', 'duels'));
    }

    public function show(Challenge $challenge): View
    {
        $me = Auth::user();
        $this->assertCanAccess($challenge, $me);
        $c = $this->challengeView($challenge, $me->id);

        return view('personal.challenge-show', ['c' => $c]);
    }

    public function duel(Duel $duel): View
    {
        $me = Auth::user();
        // Participants only — a pending invite is still private to the two sides.
        abort_unless($duel->involves($me->id), 403);
        $d = $this->duelView($duel, $me->id);

        return view('personal.duel-show', ['d' => $d]);
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
            ->get(['id', 'full_name', 'name'])
            ->map(fn ($u) => $this->pickerRow($u))
            ->values()->all();

        // Platform-wide athletes from OTHER clubs (discover).
        $athletes = User::whereNotIn('id', $mateIds->push($me->id))
            ->whereHas('memberClubs')
            ->limit(30)
            ->get(['id', 'full_name', 'name'])
            ->map(fn ($u) => $this->pickerRow($u, true))
            ->values()->all();

        return view('personal.challenge-create', compact('opponents', 'athletes'));
    }

    public function history(): View
    {
        $me = Auth::user();

        $duels = collect($this->myDuelViews($me->id))
            ->where('status', 'completed')->values()->all();

        $solo = collect($this->myChallengeViews($me->id))
            ->where('status', 'completed')->values()->all();

        return view('personal.challenge-history', compact('duels', 'solo'));
    }

    /* ===================================================================
     |  Duel write actions
     * =================================================================== */

    public function store(Request $request): JsonResponse
    {
        $me = Auth::user();

        $data = $request->validate([
            'type'        => ['required', Rule::in(['athletic', 'fight'])],
            'opponent_id' => ['nullable', 'integer', 'exists:users,id', 'different:__me'],
            'invite'      => ['nullable', 'string', 'max:120'],
            'discipline'  => ['required', 'string', 'max:120'],
            'metric'      => ['required', 'string', 'max:60'],
            'stake'       => ['required', 'integer', 'min:0', 'max:100000'],
            'deadline'    => ['nullable', 'date'],
            'message'     => ['nullable', 'string', 'max:500'],
        ]);

        if (empty($data['opponent_id']) && empty($data['invite'])) {
            return response()->json(['success' => false, 'message' => 'Choose an opponent or enter an invite.'], 422);
        }
        if (! empty($data['opponent_id']) && (int) $data['opponent_id'] === $me->id) {
            return response()->json(['success' => false, 'message' => "You can't challenge yourself."], 422);
        }

        $duel = Duel::create([
            'challenger_id'   => $me->id,
            'opponent_id'     => $data['opponent_id'] ?? null,
            'opponent_handle' => empty($data['opponent_id']) ? ($data['invite'] ?? null) : null,
            'opponent_name'   => empty($data['opponent_id']) ? ($data['invite'] ?? null) : null,
            'type'            => $data['type'],
            'discipline'      => $data['discipline'],
            'metric'          => $data['metric'],
            'stake_points'    => $data['stake'],
            'deadline'        => $data['deadline'] ?? null,
            'message'         => $data['message'] ?? null,
            'status'          => 'pending',
        ]);

        $this->notify($duel->opponent_id, 'duel:new', $duel, $me, 'challenged you to a duel');

        return response()->json([
            'success'  => true,
            'message'  => 'Challenge sent 🔥',
            'redirect' => route('me.challenge'),
            'duel'     => $this->duelView($duel->fresh(['challenger', 'opponent']), $me->id),
        ]);
    }

    public function accept(Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->opponent_id === $me->id && $duel->status === 'pending', 403);

        $duel->update(['status' => 'active', 'responded_at' => now()]);
        $this->notify($duel->challenger_id, 'duel:accepted', $duel, $me, 'accepted your duel');

        return response()->json(['success' => true, 'message' => 'Challenge accepted — game on! 🔥', 'status' => 'active']);
    }

    public function decline(Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->opponent_id === $me->id && $duel->status === 'pending', 403);

        $duel->update(['status' => 'declined', 'responded_at' => now()]);
        $this->notify($duel->challenger_id, 'duel:declined', $duel, $me, 'declined your duel');

        return response()->json(['success' => true, 'message' => 'Duel declined', 'status' => 'declined']);
    }

    public function cancel(Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->challenger_id === $me->id && $duel->status === 'pending', 403);

        $duel->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Invitation cancelled', 'status' => 'cancelled']);
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

        $data = $request->validate([
            'winner'     => ['required', Rule::in(['me', 'rival'])],
            'my_score'   => ['nullable', 'string', 'max:30'],
            'opp_score'  => ['nullable', 'string', 'max:30'],
        ]);

        $rivalId  = $duel->rivalId($me->id);
        $winnerId = $data['winner'] === 'me' ? $me->id : $rivalId;

        // Map "my/opp" scores to challenger/opponent columns.
        $iAmChallenger = $duel->challenger_id === $me->id;
        $duel->update([
            'status'           => 'reported',            // awaiting the rival's confirmation
            'winner_id'        => $winnerId,             // provisional — not credited yet
            'reported_by'      => $me->id,
            'challenger_score' => $iAmChallenger ? ($data['my_score'] ?? null) : ($data['opp_score'] ?? null),
            'opponent_score'   => $iAmChallenger ? ($data['opp_score'] ?? null) : ($data['my_score'] ?? null),
        ]);

        $this->notify($rivalId, 'duel:result', $duel, $me, 'reported a result — confirm it');

        return response()->json([
            'success' => true,
            'message' => 'Result submitted — awaiting your rival’s confirmation',
            'status'  => 'reported',
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
            'status'  => 'completed',
            'won'     => $duel->winner_id === $me->id,
        ]);
    }

    /** The OTHER participant disputes → result is cleared, duel back to active. */
    public function dispute(Duel $duel): JsonResponse
    {
        $me = Auth::user();
        abort_unless($duel->involves($me->id) && $duel->status === 'reported' && $duel->reported_by !== $me->id, 403);

        $reporter = $duel->reported_by;
        $duel->update([
            'status'           => 'active',
            'winner_id'        => null,
            'reported_by'      => null,
            'challenger_score' => null,
            'opponent_score'   => null,
        ]);
        $this->notify($reporter, 'duel:disputed', $duel, $me, 'disputed the result — re-enter it together');

        return response()->json(['success' => true, 'message' => 'Result disputed — duel is active again', 'status' => 'active']);
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
            'success'  => true,
            'message'  => 'You joined ' . $challenge->title,
            'joined'   => true,
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
            'success'   => true,
            'message'   => $p->completed_at ? 'Challenge complete! 🎉' : 'Progress logged · ' . $pct . '%',
            'current'   => $p->progress,
            'progress'  => $pct,
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
        $goal    = max(1, (int) $c->goal);
        $pct     = $c->goal > 0 ? min(100, (int) round($current / $goal * 100)) : ($p?->completed_at ? 100 : 0);

        // Leaderboard (top participants by progress).
        $parts = $c->relationLoaded('participations')
            ? $c->participations
            : $c->participations()->with('user:id,full_name,name')->orderByDesc('progress')->get();
        if (! $parts->first()?->relationLoaded('user')) {
            $parts->load('user:id,full_name,name');
        }
        $ranked = $parts->sortByDesc('progress')->values();

        $rank = null;
        foreach ($ranked as $i => $row) {
            if ($row->user_id === $meId) { $rank = $i + 1; break; }
        }

        $leaders = $ranked->take(5)->map(fn ($row) => [
            'name' => $row->user_id === $meId ? 'You' : ($row->user->full_name ?? $row->user->name ?? 'Member'),
            'val'  => number_format($row->progress) . ($c->unit ?: ''),
            'pts'  => $row->completed_at ? (int) $c->points : (int) round($c->points * ($goal > 0 ? $row->progress / $goal : 0)),
            'me'   => $row->user_id === $meId,
        ])->values()->all();

        return [
            'id'           => $c->id,
            'status'       => $c->lifecycle(),
            'title'        => $c->title,
            'club'         => $c->tenant?->club_name ?? 'TAKEONE',
            'tag'          => $c->tag ?? 'Challenge',
            'icon'         => $c->icon,
            'color'        => $c->color,
            'progress'     => $pct,
            'metric'       => $c->metric,
            'current'      => $current,
            'goal'         => (int) $c->goal,
            'unit'         => $c->unit ?? '',
            'days_left'    => $c->daysLeft(),
            'points'       => (int) $c->points,
            'participants' => $c->participations_count ?? $parts->count(),
            'rank'         => $rank,
            'streak'       => $p?->streak ?? 0,
            'joined'       => (bool) $p,
            'completed'    => (bool) $p?->completed_at,
            'about'        => $c->about ?? '',
            'rules'        => $c->rules ?? [],
            'rewards'      => $c->rewards ?? [],
            'leaders'      => $leaders,
        ];
    }

    private function myDuelViews(int $meId): array
    {
        $duels = Duel::query()
            ->where(fn ($q) => $q->where('challenger_id', $meId)->orWhere('opponent_id', $meId))
            ->whereNotIn('status', ['declined', 'cancelled'])
            ->with(['challenger:id,full_name,name', 'opponent:id,full_name,name'])
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
            $rivalId   = $d->opponent_id;
        } else {
            $rivalName = $d->challenger?->full_name ?? $d->challenger?->name ?? 'Challenger';
            $rivalId   = $d->challenger_id;
        }

        // Per-viewer status label expected by the blades.
        $status = match (true) {
            $d->status === 'pending' && $d->opponent_id === $meId   => 'invite_incoming',
            $d->status === 'pending' && $d->challenger_id === $meId  => 'invite_sent',
            $d->status === 'active'                                  => 'active',
            $d->status === 'reported'                               => 'reported',
            $d->status === 'completed'                               => 'completed',
            default                                                  => $d->status,
        };

        $myScore  = $iAmChallenger ? $d->challenger_score : $d->opponent_score;
        $oppScore = $iAmChallenger ? $d->opponent_score : $d->challenger_score;

        $me = [
            'name'     => 'You',
            'initials' => 'YO',
            'record'   => $this->userRecord($meId),
        ];
        if ($status === 'active' && $myScore !== null) {
            $me['score'] = $myScore;
            $me['pct']   = 50;
        }

        $opp = [
            'name'     => $rivalName,
            'initials' => $this->initials($rivalName),
            'record'   => $rivalId ? $this->userRecord($rivalId) : '—',
            'rank'     => null,
        ];
        if ($status === 'active' && $oppScore !== null) {
            $opp['score'] = $oppScore;
            $opp['pct']   = 50;
        }

        $view = [
            'id'         => $d->id,
            'kind'       => 'versus',
            'type'       => $d->type,
            'status'     => $status,
            'discipline' => $d->discipline,
            'icon'       => $d->type === 'fight' ? 'bi-trophy' : 'bi-lightning-charge-fill',
            'color'      => $d->type === 'fight' ? '#ef4444' : '#7c3aed',
            'me'         => $me,
            'opponent'   => $opp,
            'metric'     => $d->metric,
            'stake'      => $d->stake_points . ' pts',
            'deadline'   => $d->deadline ? $d->deadline->isFuture()
                                ? $d->deadline->diffForHumans(['parts' => 1])
                                : $d->deadline->format('M j')
                            : 'No deadline',
            'location'   => $d->location ?? '—',
            'message'    => $d->message ?? '',
            'when'       => optional($d->created_at)->diffForHumans() ?? '',
        ];

        if ($status === 'completed') {
            $won = $d->winner_id === $meId;
            $view['result']        = $won ? 'win' : 'loss';
            $view['final']         = trim(($myScore ?? '–') . ' — ' . ($oppScore ?? '–'));
            $view['points_earned'] = $won ? (int) $d->stake_points : 0;
            $view['date']          = optional($d->completed_at)->format('M j') ?? '';
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
            'id'       => $u->id,
            'name'     => $name,
            'initials' => $this->initials($name),
            'record'   => $this->userRecord($u->id),
            'tag'      => $club?->club_name ? 'Athlete' : 'Member',
            'club'     => $club?->club_name ?? 'TAKEONE',
            'city'     => $club?->country ?? '',
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

    /** Best-effort realtime ping to a participant (DB stays source of truth). */
    private function notify(?int $userId, string $action, Duel $duel, User $actor, string $text): void
    {
        if (! $userId || ! function_exists('Realtime')) {
            return;
        }
        try {
            Realtime()->publishToUser($userId, 'challenges', [
                'action'     => $action,
                'duel_id'    => $duel->id,
                'actor'      => $actor->full_name ?? $actor->name,
                'discipline' => $duel->discipline,
                'text'       => $text,
            ]);
        } catch (\Throwable $e) {
            // best-effort only
        }
    }
}
