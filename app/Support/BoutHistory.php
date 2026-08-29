<?php

namespace App\Support;

use App\Events\Support\EventAccess;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Models\TournamentEvent;
use App\Models\User;
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
        $bouts = EventMatch::query()
            ->where(fn ($q) => $q->whereNotNull('winner')->orWhere('status', 'done'))
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
