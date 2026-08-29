<?php

namespace App\Http\Controllers;

use App\Events\Support\EventAccess;
use App\Media\BoutFilm;
use App\Media\BoutTimeline;
use App\Models\BoutCoachNote;
use App\Models\BoutComment;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Models\EventOfficial;
use App\Models\User;
use App\Sports\Combat\SportRegistry;
use App\Support\BoutStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Watching a bout back.
 *
 * This is the page the platform used to send people off-site for. Everything it
 * shows is already ours: the footage came off our cameras into our vaults, and
 * the highlights bar is the officiating log replayed against the recording's
 * anchor. Nothing is re-typed and nothing leaves the building.
 *
 * ── Who may watch ──────────────────────────────────────────────────────────
 *
 * Two doors, and the second is narrow on purpose:
 *
 *   1. Anyone the EVENT is visible to (`EventAccess::visible`) — the same rule
 *      that governs its draw, its roster and its documents.
 *   2. The two athletes who fought THIS bout, whatever the event's scope and
 *      whether or not it has been archived. A visiting club's competitor was
 *      filmed by us at an event they were entered into; an event being archived
 *      is how competitions end, not a reason to take somebody's own fight away
 *      from them. This grants their bout and nothing else — not the division,
 *      not the next mat, not the event page.
 *
 * The same pair is enforced independently on every byte in MediaStreamController,
 * because a page guard is not a media guard.
 */
class BoutVideoController extends Controller
{
    public function __construct(
        private EventAccess $access,
        private BoutFilm $film,
        private BoutTimeline $timeline,
        private SportRegistry $sports,
    ) {}

    /* ──────────────────────────────────────────────────────────────────────
     | The page
     ────────────────────────────────────────────────────────────────────── */

    public function show(Request $request, ClubEvent $event, int $matchNo): View
    {
        $me = Auth::user();
        $match = $this->boutOr404($event, $matchNo);

        abort_unless($this->mayWatch($event, $match, $me), 404);

        $angles = $this->film->angles($match);

        // A bout with nothing to play is not a 404 — the bout is real, it simply
        // was not filmed. The page says so and offers the bout itself.
        $recording = $this->film->recordings($match)->first();

        $timeline = $recording
            ? $this->timeline->for($match, $recording)
            : ['anchored' => false, 'rounds' => [], 'moments' => []];

        $notes = BoutCoachNote::where('match_id', $match->id)
            ->orderBy('start_seconds')
            ->get()
            ->map(fn (BoutCoachNote $n) => $n->present())
            ->all();

        $isMobile = (bool) $request->attributes->get('is_mobile');

        /*
         * The review screens are the standalone designs in drafts/ — their own
         * shell, their own scroll model, a pinned player. They are handed the
         * exact data shapes their DATA block declares (ROUNDS / REVIEWS /
         * OFFICIALS / COMMENTS / DURATION) so the markup is used verbatim and
         * only the values change.
         */
        return view($isMobile ? 'personal.mobile.bout-video' : 'personal.desktop.bout-video', [
            'rounds' => $this->roundsPayload($timeline),
            'reviews' => $this->reviewsPayload($notes),
            'officials' => $this->officialsPayload($event),
            'comments' => $this->commentsPayload($match, $me),
            'duration' => $angles[0]['duration'] ?? 0,
            'canComment' => true,
            // The slim event payload the hero band needs — same shape the board
            // and next-up screens already use, rather than the full eventView():
            // this page is about the bout, and the event is its backdrop.
            'e' => [
                'key' => $event->uuid,
                'title' => $event->title,
                'club' => $event->tenant?->club_name,
                'color' => $event->color ?: '#7c3aed',
                'date' => $event->date ? \Illuminate\Support\Carbon::parse($event->date)->format('M j, Y') : null,
                'sport_label' => $this->sports->get($event->sport)?->label(),
            ],
            'event' => $event,
            'match' => $match,
            'bout' => $this->header($event, $match),
            'angles' => $angles,
            'timeline' => $timeline,
            'notes' => $notes,
            'canAnnotate' => $this->mayAnnotate($event, $match, $me),
            'canManage' => $this->access->canManage($event, $me),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────────
     | The shapes the review screens declare
     ────────────────────────────────────────────────────────────────────── */

    /** ROUNDS: rounds, each with its scoring moments, in the design's own keys. */
    private function roundsPayload(array $timeline): array
    {
        $byRound = collect($timeline['moments'])->groupBy('round');

        return collect($timeline['rounds'])->map(fn (array $r) => [
            'name' => $r['name'],
            'points' => $byRound->get($r['number'], collect())->map(fn (array $m) => [
                'time' => $m['clock'],
                // The design colours a marker by corner: aka is red, ao is blue.
                // A simultaneous exchange has no single side, so it is marked
                // 'both' and the stylesheet's split treatment applies.
                'side' => match ($m['side']) {
                    'red' => 'aka',
                    'blue' => 'ao',
                    default => 'both',
                },
                'action' => $m['label'],
                'who' => $m['who'],
                'pts' => '',
                'score' => $m['score_red'].'–'.$m['score_blue'],
                'secs' => (int) round($m['t']),
            ])->values()->all(),
        ])->values()->all();
    }

    /** REVIEWS: the coach notes, as the design's timeline items. */
    private function reviewsPayload(array $notes): array
    {
        return collect($notes)->map(function (array $n) {
            $from = $this->clock($n['start']);
            $to = $n['end'] !== null ? $this->clock($n['end']) : null;

            return [
                'range' => $to ? $from.' → '.$to : $from,
                'emoji' => $n['emoji'],
                'note' => $n['note'],
                'author' => $n['coach'],
                'secs' => (int) round($n['start']),
                // Only a ranged note can be replayed slowly; the design shows a
                // SLOW-MO affordance and it must not lie.
                'slowmo' => $n['end'] !== null,
            ];
        })->values()->all();
    }

    /**
     * OFFICIALS: who ran the event.
     *
     * Appointments are to the EVENT — `event_officials` has no match column —
     * so this is the event's panel, and it is empty rather than invented when
     * nobody was appointed.
     */
    private function officialsPayload(ClubEvent $event): array
    {
        return EventOfficial::with('user:id,full_name,name')
            ->where('event_id', $event->id)
            ->orderBy('id')
            ->get()
            ->map(fn (EventOfficial $o) => [
                'name' => $o->user?->full_name ?: ($o->user?->name ?: __('shared.unknown')),
                'role' => match ($o->role) {
                    EventOfficial::ROLE_JURY => __('events.official_referee'),
                    EventOfficial::ROLE_WEIGH_IN => __('events.official_weigh_in'),
                    EventOfficial::ROLE_PAYMENTS => __('events.official_payments'),
                    EventOfficial::ROLE_ORGANISER => __('events.official_organiser'),
                    default => ucfirst(str_replace('_', ' ', (string) $o->role)),
                },
            ])
            ->values()
            ->all();
    }

    /** COMMENTS: the conversation, newest first, one level of replies. */
    private function commentsPayload(EventMatch $match, ?User $viewer): array
    {
        return BoutComment::with(['author:id,full_name,name', 'likes', 'replies.author:id,full_name,name', 'replies.likes'])
            ->where('match_id', $match->id)
            ->whereNull('parent_id')
            ->latest('id')
            ->get()
            ->map(function (BoutComment $c) use ($viewer) {
                $row = $c->present($viewer);
                $row['replies'] = $c->replies->map(fn (BoutComment $r) => $r->present($viewer) + [
                    // The design prefixes a reply with who it answers.
                    'mention' => '@'.Str::before($c->author?->full_name ?: 'someone', ' '),
                ])->values()->all();

                return $row;
            })
            ->values()
            ->all();
    }

    private function clock(float $seconds): string
    {
        $whole = max(0, (int) floor($seconds));

        return sprintf('%02d:%02d', intdiv($whole, 60), $whole % 60);
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Coach notes
     ────────────────────────────────────────────────────────────────────── */

    public function storeNote(Request $request, ClubEvent $event, int $matchNo): JsonResponse
    {
        $me = Auth::user();
        $match = $this->boutOr404($event, $matchNo);

        abort_unless($this->mayAnnotate($event, $match, $me), 403);

        $data = $this->validateNote($request);

        $note = BoutCoachNote::create($data + [
            'event_id' => $event->id,
            'match_id' => $match->id,
            'user_id' => $me->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('events.bout_video_note_saved'),
            'note' => $note->present(),
        ]);
    }

    public function updateNote(Request $request, ClubEvent $event, int $matchNo, BoutCoachNote $note): JsonResponse
    {
        $me = Auth::user();
        $match = $this->boutOr404($event, $matchNo);

        abort_unless($this->mayAnnotate($event, $match, $me), 403);
        // The note must belong to the bout in the URL. Without this a valid
        // uuid would let one bout's page edit another's note.
        abort_unless($note->match_id === $match->id, 404);

        $note->update($this->validateNote($request));

        return response()->json([
            'success' => true,
            'message' => __('events.bout_video_note_saved'),
            'note' => $note->fresh()->present(),
        ]);
    }

    public function destroyNote(Request $request, ClubEvent $event, int $matchNo, BoutCoachNote $note): JsonResponse
    {
        $me = Auth::user();
        $match = $this->boutOr404($event, $matchNo);

        abort_unless($this->mayAnnotate($event, $match, $me), 403);
        abort_unless($note->match_id === $match->id, 404);

        $uuid = $note->uuid;
        $note->delete();

        return response()->json([
            'success' => true,
            'message' => __('events.bout_video_note_deleted'),
            'uuid' => $uuid,
        ]);
    }

    /**
     * A note's fields.
     *
     * `end >= start` is checked HERE and not only in the browser: an inverted
     * range makes the replay silently do nothing, and a rule the server does not
     * hold is a rule the next client will not either. The emoji is an allow-list
     * rather than a sanitised string — there are seven, and anything else is a
     * caller doing something other than picking one.
     */
    private function validateNote(Request $request): array
    {
        $data = $request->validate([
            'start_seconds' => ['required', 'numeric', 'min:0', 'max:86400'],
            'end_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400', 'gte:start_seconds'],
            'note' => ['required', 'string', 'max:1000'],
            'coach_name' => ['required', 'string', 'max:100'],
            'emoji' => ['nullable', 'string', 'in:'.implode(',', BoutCoachNote::EMOJI)],
            'position_x' => ['nullable', 'numeric', 'between:0,1'],
            'position_y' => ['nullable', 'numeric', 'between:0,1'],
        ]);

        $data['emoji'] = $data['emoji'] ?? '🔥';

        return $data;
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Comments
     ────────────────────────────────────────────────────────────────────── */

    /**
     * Say something under a bout.
     *
     * Anyone who may WATCH may comment — the conversation belongs to the same
     * audience as the footage. A reply must belong to this bout and must itself
     * be top-level, so the thread can never nest past one level however the
     * request is shaped.
     */
    public function storeComment(Request $request, ClubEvent $event, int $matchNo): JsonResponse
    {
        $me = Auth::user();
        $match = $this->boutOr404($event, $matchNo);

        abort_unless($this->mayWatch($event, $match, $me), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'parent' => ['nullable', 'string', 'size:36'],
            'stamp_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
        ]);

        $parent = null;

        if (filled($data['parent'] ?? null)) {
            $parent = BoutComment::where('uuid', $data['parent'])
                ->where('match_id', $match->id)
                ->whereNull('parent_id')
                ->first();

            abort_if($parent === null, 404);
        }

        $comment = BoutComment::create([
            'event_id' => $event->id,
            'match_id' => $match->id,
            'user_id' => $me->id,
            'parent_id' => $parent?->id,
            'body' => $data['body'],
            'stamp_seconds' => $data['stamp_seconds'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('events.bout_video_comment_posted'),
            'comment' => $comment->fresh(['author', 'likes'])->present($me) + ['replies' => []],
            'parent' => $parent?->uuid,
        ]);
    }

    /** Remove one's own comment. Replies go with a parent, by cascade. */
    public function destroyComment(Request $request, ClubEvent $event, int $matchNo, BoutComment $comment): JsonResponse
    {
        $me = Auth::user();
        $match = $this->boutOr404($event, $matchNo);

        abort_unless($comment->match_id === $match->id, 404);
        // The author, or whoever runs the event — the same pair who can moderate
        // anything else about it.
        abort_unless($comment->user_id === $me->id || $this->access->canManage($event, $me), 403);

        $uuid = $comment->uuid;
        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => __('events.bout_video_comment_deleted'),
            'uuid' => $uuid,
        ]);
    }

    /** Like, or take it back. A row per person, so the heart knows its own state. */
    public function likeComment(Request $request, ClubEvent $event, int $matchNo, BoutComment $comment): JsonResponse
    {
        $me = Auth::user();
        $match = $this->boutOr404($event, $matchNo);

        abort_unless($this->mayWatch($event, $match, $me), 403);
        abort_unless($comment->match_id === $match->id, 404);

        $existing = $comment->likes()->where('user_id', $me->id)->first();

        if ($existing) {
            $existing->delete();
        } else {
            $comment->likes()->create(['user_id' => $me->id]);
        }

        return response()->json([
            'success' => true,
            'uuid' => $comment->uuid,
            'liked' => ! $existing,
            'likes' => $comment->likes()->count(),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Authorisation and shaping
     ────────────────────────────────────────────────────────────────────── */

    private function boutOr404(ClubEvent $event, int $matchNo): EventMatch
    {
        $match = EventMatch::with(['category', 'event', 'competitorA.user', 'competitorB.user'])
            ->where('event_id', $event->id)
            ->where('match_no', $matchNo)
            ->first();

        abort_if($match === null, 404);

        return $match;
    }

    /** Did this person fight in this bout? */
    private function competed(EventMatch $match, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return ClubEventRegistration::where('user_id', $user->id)
            ->whereIn('id', array_filter([$match->a_competitor_id, $match->b_competitor_id]))
            ->exists();
    }

    private function mayWatch(ClubEvent $event, EventMatch $match, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->access->visible($event, $user) || $this->competed($match, $user);
    }

    /**
     * Who may write on the footage.
     *
     * The organiser, because it is their event; and the athletes themselves,
     * because a fighter reviewing their own bout is exactly who this feature is
     * for and their own footage is not somebody else's to gatekeep. A spectator
     * who can merely SEE the event cannot write on it.
     */
    private function mayAnnotate(ClubEvent $event, EventMatch $match, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->access->canManage($event, $user) || $this->competed($match, $user);
    }

    /**
     * The header above the picture.
     *
     * Deliberately thinner than the bout page's own payload: this screen is for
     * watching, and the bout page one tap away carries the profiles, the clubs
     * and the officials. The only disclosure guard needed here is the athlete's
     * own choice about their face.
     */
    private function header(ClubEvent $event, EventMatch $match): array
    {
        $sport = $this->sports->get($event->sport);
        $labels = $sport?->cornerLabels() ?? [
            'red' => __('events.corner_red'),
            'blue' => __('events.corner_blue'),
        ];

        return [
            'match_no' => $match->match_no,
            // Humanised, for the same reason the gallery tile is: 'open_mat'
            // is a machine key and a chip reading OPEN_MAT is the database
            // showing through the page. Either column may hold either shape.
            'round' => BoutStage::label($match),
            'court' => $match->court,
            'division' => $match->category?->weight_class ?: $match->category?->name,
            'winner' => $match->winner,
            'a' => $this->corner($match, 'a', $labels),
            'b' => $this->corner($match, 'b', $labels),
            'bout_url' => route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $match->match_no]),
            /*
             * Where "back" goes.
             *
             * An athlete can reach their OWN bout on an event they cannot
             * otherwise see — that is the whole point of the second door. Their
             * back button therefore cannot assume the gallery: for them it would
             * bounce off the event's own guard and land on the home page. They
             * go back to their own videos instead, which is where they came from.
             */
            'gallery_url' => $this->access->visible($event, Auth::user())
                ? route('me.events.gallery', ['event' => $event->uuid])
                : route('me.videos'),
        ];
    }

    private function corner(EventMatch $match, string $side, array $labels): array
    {
        $reg = $side === 'a' ? $match->competitorA : $match->competitorB;
        $user = $reg?->user;

        $colour = $match->{$side.'_corner'} ?: ($side === 'a' ? 'red' : 'blue');

        return [
            'name' => $match->{$side.'_name'},
            'colour' => $colour,
            'corner_label' => $labels[$colour] ?? $colour,
            'country' => $reg?->countryCode() ?: $match->{$side.'_country'},
            'club' => $reg?->competingClub()?->club_name,
            'score' => $match->{$side.'_score'},
            'won' => $match->winner === $side,
            // Their face, only if they chose to show it — the same rule the
            // bracket and the bout page follow. Absence is silent.
            'photo' => ($user?->profile_picture && $user->profile_picture_is_public)
                ? file_url($user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0)
                : null,
            'gender' => $user?->gender,
        ];
    }
}
