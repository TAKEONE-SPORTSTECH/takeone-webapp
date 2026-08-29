<?php

namespace App\Media;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Models\EventOfficial;
use App\Sports\Combat\SportRegistry;
use App\Support\BoutStage;

/**
 * A bout, dressed as a broadcast card.
 *
 * The gallery tile is not a filename and a duration — it is the fight. Two
 * fighters facing each other, their clubs and countries, the stage, the weight,
 * the mat. Everything here is what a viewer would see on the wall of the hall,
 * and every value comes from the competition record rather than from anything
 * typed against the video.
 *
 * ── What is deliberately absent ────────────────────────────────────────────
 *
 * A face appears only when the athlete chose to show it
 * (`profile_picture_is_public`), exactly as the bracket and the bout page do.
 * A club, a country, a referee or a weight that does not exist is returned as
 * NULL, and the card hides the whole row rather than printing a placeholder —
 * an empty chip reading "Referee —" is worse than no chip.
 */
class BoutArena
{
    public function __construct(
        private SportRegistry $sports,
        private BoutTimeline $timeline,
    ) {}

    /**
     * The card payload for one bout.
     *
     * @param  array<int, array<string, mixed>>  $angles  from BoutFilm
     * @return array<string, mixed>
     */
    public function for(ClubEvent $event, EventMatch $match, array $angles): array
    {
        $sport = $this->sports->get($event->sport);
        $labels = $sport?->cornerLabels() ?? [
            'red' => __('events.corner_red'),
            'blue' => __('events.corner_blue'),
        ];

        // Which side fought in which corner. The mat decides this, not the draw.
        $redSide = ($match->a_corner === 'blue') ? 'b' : 'a';
        $blueSide = $redSide === 'a' ? 'b' : 'a';

        return [
            'event' => $event->title,
            'stage' => BoutStage::label($match),
            'weight' => $match->category?->weight_class ?: $match->category?->name,
            'match_no' => $match->match_no,
            'court' => $match->court,
            'referee' => $this->referee($event),
            'red' => $this->corner($match, $redSide, 'red', $labels),
            'blue' => $this->corner($match, $blueSide, 'blue', $labels),
            // The scoring, for the ticker that runs over the hover preview.
            'scoring' => $this->scoring($match, $angles),
        ];
    }

    /**
     * Who refereed.
     *
     * `event_officials` has no match column — an appointment is to the EVENT,
     * not to one bout — so this is the event's jury, and it is null when nobody
     * was appointed rather than a guess at who was on that mat.
     */
    private function referee(ClubEvent $event): ?string
    {
        $official = EventOfficial::with('user:id,full_name,name')
            ->where('event_id', $event->id)
            ->where('role', EventOfficial::ROLE_JURY)
            ->orderBy('id')
            ->first();

        return $official?->user?->full_name ?: $official?->user?->name;
    }

    /** One corner of the card. */
    private function corner(EventMatch $match, string $side, string $colour, array $labels): array
    {
        /** @var ClubEventRegistration|null $reg */
        $reg = $side === 'a' ? $match->competitorA : $match->competitorB;
        $user = $reg?->user;
        $club = $reg?->competingClub();

        return [
            'name' => $match->{$side.'_name'},
            'tag' => $labels[$colour] ?? strtoupper($colour),
            // Two letters, lowercased, for the flag sprite. Anything else is
            // dropped rather than rendered as a broken tile.
            'country' => $this->iso($reg?->countryCode() ?: $match->{$side.'_country'}),
            'club' => $club?->club_name,
            'club_logo' => $club?->logo ? asset('storage/'.$club->logo) : null,
            // Their face, only if they chose to show it.
            'photo' => ($user?->profile_picture && $user->profile_picture_is_public)
                ? asset('storage/'.$user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0)
                : null,
            'score' => $match->{$side.'_score'},
            'won' => $match->winner === $side,
        ];
    }

    private function iso(?string $code): ?string
    {
        $c = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $code), 0, 2));

        return strlen($c) === 2 ? $c : null;
    }

    /**
     * The scoring, as the ticker consumes it.
     *
     * Derived from the officiating log against the recording's anchor — the same
     * source the highlights bar uses, so a card and the review page can never
     * disagree about when a point landed.
     *
     * @param  array<int, array<string, mixed>>  $angles
     */
    private function scoring(EventMatch $match, array $angles): ?array
    {
        if ($angles === []) {
            return null;
        }

        $recording = app(BoutFilm::class)->recordings($match)->first();

        if ($recording === null) {
            return null;
        }

        $timeline = $this->timeline->for($match, $recording);

        if ($timeline['moments'] === []) {
            return null;
        }

        return [
            'rounds' => array_map(fn (array $r) => [
                'n' => $r['number'],
                'name' => $r['name'],
                'start' => $r['start'],
            ], $timeline['rounds']),
            'points' => array_map(fn (array $m) => [
                't' => $m['t'],
                'label' => $m['label'],
                'side' => $m['side'],
                'sr' => $m['score_red'],
                'sb' => $m['score_blue'],
            ], $timeline['moments']),
        ];
    }
}
