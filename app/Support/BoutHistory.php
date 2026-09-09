<?php

namespace App\Support;

use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Members\Models\TournamentEvent;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What actually happened at a competition, from one athlete's side.
 *
 * A self-logged `tournament_events` row is free text — it carries no event id —
 * but the same competition usually exists on the platform as a `club_events`
 * row the member was entered into, with a draw, opponents and scores. This
 * matches the two up (same day, overlapping titles) and returns the event to
 * link to plus every bout the member fought there, shaped from THEIR corner.
 *
 * The reader of a member profile should not have to re-type what the draw
 * already knows.
 */
class BoutHistory
{
    /**
     * @param  Collection<int, TournamentEvent>  $tournaments
     * @return array<int, array{event: array, bouts: array}>  keyed by tournament_events.id
     */
    public function forTournaments(User $member, Collection $tournaments, ?User $viewer = null): array
    {
        if ($tournaments->isEmpty()) {
            return [];
        }

        $registrations = ClubEventRegistration::query()
            ->where('user_id', $member->id)
            ->with(['event:id,uuid,title,date,sport,is_archived,created_by,tenant_id,scope,event_type',
                'category:id,name,weight_class'])
            ->get();

        if ($registrations->isEmpty()) {
            return [];
        }

        // Which registration (if any) is the platform record of each claim.
        $matched = [];
        foreach ($tournaments as $t) {
            $reg = $this->matchRegistration($t, $registrations);
            if ($reg) {
                $matched[$t->id] = $reg;
            }
        }

        if ($matched === []) {
            return [];
        }

        $regIds = collect($matched)->pluck('id')->unique();

        // Every bout of those entries, in one query. A bout with no winner yet is
        // kept: it was fought, and reporting it as a loss would be untrue.
        //
        // `contested()` keeps a BYE out. A bye is a draw row with one competitor,
        // no opponent and a winner recorded so the bracket can advance somebody —
        // it is scaffolding, not a bout, and listing it gives an athlete a win
        // over nobody.
        $bouts = EventMatch::query()
            ->contested()
            ->where(fn ($q) => $q->whereIn('a_competitor_id', $regIds)->orWhereIn('b_competitor_id', $regIds))
            ->with(['event:id,uuid,title,date', 'category:id,name,weight_class'])
            ->orderBy('match_no')
            ->orderBy('id')
            ->get();

        $faces = $this->opponentFaces($bouts);
        $videos = $this->videos($bouts);
        $access = app(EventAccess::class);

        $out = [];

        foreach ($matched as $tournamentId => $reg) {
            $event = $reg->event;

            if (! $event) {
                continue;
            }

            // The link is offered only to a viewer the event itself would admit —
            // the profile is not a way around an event's own visibility.
            $canOpen = $viewer ? $access->visible($event, $viewer) : false;

            $mine = $bouts->filter(fn (EventMatch $b) => (int) $b->a_competitor_id === (int) $reg->id
                || (int) $b->b_competitor_id === (int) $reg->id);

            $out[$tournamentId] = [
                // What the athlete weighed in at, and the division they were entered
                // in. The official weight is the one on the entry; where no weigh-in
                // was recorded the division is the only honest figure to state.
                'entry' => [
                    'weight' => $reg->weight !== null ? (float) $reg->weight : null,
                    'weighed_in' => $reg->weighed_in_at !== null,
                    // Only a weight class belongs in a weight slot — a category NAME
                    // ("Sparring", "Group 3") is not a weight and must not read as one.
                    'division' => $reg->category?->weight_class,
                    'category' => $reg->category?->name,
                ],
                'event' => [
                    'uuid' => $event->uuid,
                    'title' => $event->title,
                    'url' => $canOpen ? route('me.events.show', $event->uuid) : null,
                ],
                'bouts' => $mine->map(fn (EventMatch $b) => $this->shape($b, $reg, $faces, $videos, $canOpen))
                    ->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * This athlete's bouts at ONE event, from their corner.
     *
     * `forTournaments()` above answers a profile question — "what has this
     * member competed in?" — and has to match a self-claimed tournament to a
     * registration by day and title. Here the event is known, so none of that
     * guesswork applies; what IS shared is the shaping, the opponent faces and
     * the video lookup, which is why this lives beside it rather than in a
     * service of its own (CLAUDE.md → *Shared Stays Shared*).
     *
     * Returns null when this person is not entered. Says nothing about whether
     * the draw may be SHOWN — that is `EventAccess::drawVisible()`, and the
     * caller asks it: a competitor may not read a withheld draw just because
     * they are in it.
     *
     * @return array{entry: array<string, mixed>, bouts: array<int, array<string, mixed>>}|null
     */
    public function forEvent(ClubEvent $event, User $member, ?User $viewer = null): ?array
    {
        $reg = ClubEventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $member->id)
            ->with('category:id,name,weight_class')
            ->first();

        if (! $reg) {
            return null;
        }

        /*
         * ⚠️ BYES ARE KEPT HERE, unlike in the history above.
         *
         * A bye is a draw row with one competitor and a winner already recorded
         * so the bracket can advance somebody. In a PROFILE that is a win over
         * nobody and `contested()` is right to drop it. On the athlete's own
         * entry it is the opposite: "you have a bye, you start in the next
         * round" is one of the things they most need to be told, and dropping it
         * left a drawn competitor looking at an empty list.
         */
        $bouts = EventMatch::query()
            ->where('event_id', $event->id)
            ->where(fn ($q) => $q->where('a_competitor_id', $reg->id)->orWhere('b_competitor_id', $reg->id))
            ->with(['event:id,uuid,title,date', 'category:id,name,weight_class'])
            ->orderBy('match_no')
            ->orderBy('id')
            ->get();

        $faces = $this->opponentFaces($bouts);
        $videos = $this->videos($bouts);

        $canOpen = $viewer !== null && app(EventAccess::class)->visible($event, $viewer);

        return [
            'entry' => [
                'weight' => $reg->weight !== null ? (float) $reg->weight : null,
                'weighed_in' => $reg->weighed_in_at !== null,
                'division' => $reg->category?->weight_class,
                'category' => $reg->category?->name,
            ],
            'bouts' => $bouts->map(function (EventMatch $b) use ($reg, $faces, $videos, $canOpen) {
                $shaped = $this->shape($b, $reg, $faces, $videos, $canOpen);

                // No opponent in the row at all: the bracket is walking this
                // competitor through. Said as a bye rather than as a bout
                // against "TBD", which would have them waiting for a fight that
                // is never going to be called.
                $shaped['bye'] = $b->a_competitor_id === null || $b->b_competitor_id === null;

                return $shaped;
            })->values()->all(),
        ];
    }

    /** Same day, and one title contains the other once normalised. */
    private function matchRegistration(TournamentEvent $t, Collection $registrations): ?ClubEventRegistration
    {
        $day = $t->date ? $t->date->format('Y-m-d') : null;

        if (! $day) {
            return null;
        }

        $claim = $this->normalise($t->title);

        return $registrations->first(function (ClubEventRegistration $reg) use ($day, $claim) {
            $event = $reg->event;

            if (! $event || ! $event->date) {
                return false;
            }

            if (\Carbon\Carbon::parse($event->date)->format('Y-m-d') !== $day) {
                return false;
            }

            $title = $this->normalise($event->title);

            return $title !== '' && $claim !== ''
                && (str_contains($title, $claim) || str_contains($claim, $title));
        });
    }

    private function normalise(?string $value): string
    {
        // Digits go too: "Trials 1" and "Trials 2" are different events, but the
        // claim "Trials" must still match the one whose DAY it shares.
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /** @return array{...} one bout, from this competitor's corner */
    private function shape(EventMatch $b, ClubEventRegistration $reg, Collection $faces, Collection $videos, bool $canOpen): array
    {
        $mine = (int) $b->a_competitor_id === (int) $reg->id ? 'a' : 'b';
        $their = $mine === 'a' ? 'b' : 'a';

        return [
            'opponent' => $b->{$their.'_name'} ?: __('events.bout_tbd'),
            'opponent_photo' => $faces[$b->{$their.'_competitor_id'}] ?? null,
            'decided' => $b->winner !== null,
            'won' => $b->winner === $mine,
            'my_score' => $b->{$mine.'_score'},
            'their_score' => $b->{$their.'_score'},
            'division' => $b->category?->weight_class ?: $b->category?->name,
            'round' => $b->phase ?: $b->round,
            'match_no' => $b->match_no,
            /*
             * WHERE and WHEN — added 2026-09-08 for the entrant's own panel,
             * which had to answer "when do I fight?" and had nothing to answer
             * it with.
             *
             * Both are frequently NULL, and that is not a defect: an organiser
             * publishes a draw long before mats are assigned or a running order
             * exists. The reader is told what is known and told plainly what is
             * not — the same choice `<x-draw-veil>` makes by naming WHEN a draw
             * opens instead of saying nothing at all.
             *
             * Additive keys: the member profile's tournament history reads this
             * same shape and simply ignores them.
             */
            'mat' => $b->court ?: null,
            'at' => $b->scheduled_time ? \Carbon\Carbon::parse($b->scheduled_time)->format('g:i A') : null,
            'phase' => $b->phase ?: null,
            'live' => $b->status === 'live',
            'video_url' => $videos[$b->id] ?? null,
            'bout_url' => ($canOpen && $b->event && $b->match_no !== null)
                ? route('me.events.bout', ['event' => $b->event->uuid, 'matchNo' => $b->match_no])
                : null,
        ];
    }

    /**
     * Opponent faces, one query for the list — and only where that athlete has
     * opted into showing their picture (`profile_picture_is_public`), exactly as
     * BracketView does. Someone else's profile is not the place to override
     * their own decision about their face.
     */
    private function opponentFaces(Collection $bouts): Collection
    {
        if ($bouts->isEmpty()) {
            return collect();
        }

        return ClubEventRegistration::query()
            ->whereIn('id', $bouts->flatMap(fn ($b) => [$b->a_competitor_id, $b->b_competitor_id])->filter()->unique())
            ->with('user:id,profile_picture,profile_picture_is_public,updated_at')
            ->get(['id', 'user_id'])
            ->mapWithKeys(function (ClubEventRegistration $r) {
                $user = $r->user;

                return [$r->id => ($user?->profile_picture && $user->profile_picture_is_public)
                    ? file_url($user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0)
                    : null];
            });
    }

    /**
     * Where a bout can be watched.
     *
     * `event_recordings` is the only join between a bout and its footage, and it
     * can point at either of two places: a video this platform holds itself
     * (`media_file_id`, played on our own review page) or an older one published
     * on TAKEONE Play (`play_url`).
     *
     * This used to read `play_url` ALONE, which is why a member's own profile
     * showed no video for a bout that had been filmed by our cameras, stored,
     * checksummed and transcoded — the footage existed and the history said
     * there was none. Local media wins now: it is ours, it is authorised per
     * viewer, and it opens on the page with the highlights bar.
     */
    private function videos(Collection $bouts): Collection
    {
        if ($bouts->isEmpty()) {
            return collect();
        }

        $rows = DB::table('event_recordings')
            ->join('event_matches', 'event_matches.id', '=', 'event_recordings.match_id')
            ->join('club_events', 'club_events.id', '=', 'event_matches.event_id')
            ->whereIn('event_recordings.match_id', $bouts->pluck('id'))
            ->where(fn ($q) => $q->whereNotNull('event_recordings.play_url')
                ->orWhereNotNull('event_recordings.media_file_id'))
            ->orderBy('event_recordings.id')
            ->get([
                'event_recordings.match_id',
                'event_recordings.play_url',
                'event_recordings.media_file_id',
                'event_matches.match_no',
                'club_events.uuid as event_uuid',
            ]);

        return $rows->mapWithKeys(fn ($row) => [
            $row->match_id => $row->media_file_id
                ? route('me.events.bout.video', ['event' => $row->event_uuid, 'matchNo' => $row->match_no])
                : $row->play_url,
        ]);
    }
}
