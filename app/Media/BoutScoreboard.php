<?php

namespace App\Media;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventMatch;
use App\Events\Models\EventRecording;
use App\Sports\Combat\SportRegistry;

/**
 * The on-video scoreboard's state for one bout — names, flags, clubs, the
 * rounds and every point against the footage's clock.
 *
 * This is the `msb` payload the bout page's scoreboard reads, as a public
 * entry point of the Media module, so that what is sent OUT of the platform
 * (App\Media\BoutShare, the MCP) is built by the same rules as what is drawn
 * ON it. BoutVideoController::playPayload() still builds its own copy for the
 * page; tests/Feature/BoutShareTest proves the two are identical, and moving
 * the page onto this class is registered in Documentation/HOUSE-CLEANING.md.
 */
class BoutScoreboard
{
    public function __construct(
        private SportRegistry $sports,
        private BoutTimeline $timeline,
    ) {}

    /** @return array<string, mixed> */
    public function for(ClubEvent $event, EventMatch $match, EventRecording $recording, ?\Illuminate\Support\Collection $rows = null): array
    {
        $sport = $this->sports->get($event->sport);
        $timeline = $this->timeline->for($match, $recording, $rows);

        $a = $this->corner($match, 'a');
        $b = $this->corner($match, 'b');
        $red = $a['colour'] === 'red' ? $a : $b;
        $blue = $a['colour'] === 'blue' ? $a : $b;

        $division = $match->category?->weight_class ?: $match->category?->name;

        $subtitle = collect([
            $event->title,
            $division,
            $match->court ? __('events::events.bout_card_court').' '.$match->court : null,
        ])->filter()->implode(' · ');

        return [
            'sport' => $event->sport,
            'discipline' => $sport?->label(),
            'subtitle' => $subtitle,
            'red' => [
                'name' => $red['name'],
                'flag' => strtolower((string) $red['country']),
                'club' => $red['club'],
                'club_logo' => null,
            ],
            'blue' => [
                'name' => $blue['name'],
                'flag' => strtolower((string) $blue['country']),
                'club' => $blue['club'],
                'club_logo' => null,
            ],
            'rounds' => collect($timeline['rounds'])->map(fn (array $r) => [
                'n' => $r['number'],
                'name' => $r['name'],
                'start' => (float) $r['start'],
            ])->values()->all(),
            'points' => $this->flatPoints($timeline),
            'defaults' => [],
        ];
    }

    /** @return array{name: ?string, colour: string, country: ?string, club: ?string} */
    private function corner(EventMatch $match, string $side): array
    {
        $reg = $side === 'a' ? $match->competitorA : $match->competitorB;

        return [
            'name' => $match->{$side.'_name'},
            'colour' => $match->{$side.'_corner'} ?: ($side === 'a' ? 'red' : 'blue'),
            'country' => $reg?->countryCode() ?: $match->{$side.'_country'},
            'club' => $reg?->competingClub()?->club_name,
        ];
    }

    /**
     * Every scoring point, one row per corner — a simultaneous exchange is one
     * moment in a highlights list and two points on the scoreboard.
     *
     * @return list<array{t: float, action: string, pts: int, side: string, sr: int, sb: int}>
     */
    private function flatPoints(array $timeline): array
    {
        $out = [];

        foreach ($timeline['moments'] as $moment) {
            foreach ($moment['deltas'] ?? [] as $delta) {
                $out[] = [
                    't' => (float) $moment['t'],
                    'action' => ucfirst((string) $delta['kind']),
                    'pts' => (int) $delta['points'],
                    'side' => $delta['colour'],
                    'sr' => (int) $delta['score_red'],
                    'sb' => (int) $delta['score_blue'],
                ];
            }
        }

        return $out;
    }
}
