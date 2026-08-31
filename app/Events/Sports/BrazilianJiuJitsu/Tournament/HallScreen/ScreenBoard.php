<?php

namespace App\Events\Sports\BrazilianJiuJitsu\Tournament\HallScreen;

use App\Events\Sports\BrazilianJiuJitsu\Tournament\RunningOrder;
use App\Events\Sports\BrazilianJiuJitsu\Tournament\Scoreboard\MatState;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use Illuminate\Support\Facades\Lang;

/**
 * One mat's hall board — "who is on next here", rendered on a screen bolted to a wall.
 *
 * This is the venue's answer to the same question RunningOrder answers for an
 * athlete's phone, so it is built on exactly that: undecided bouts on this mat in
 * running order, closest first. A result entered anywhere shortens the queue here.
 *
 * Two things make it different from every other payload in the app, and both are
 * consequences of nobody being logged in to a hall screen:
 *
 *  1. It is addressed by PLACE, not by person. A screen is issued a token for one
 *     event and one court and can see nothing else — so this class never takes a
 *     viewer and never consults one.
 *  2. Everything on it is already public to the room. A spectator in the hall can
 *     read the names off the mat and the club off the back of a jacket. So the
 *     board carries names, clubs, flags and bout numbers — and nothing else. No
 *     weight figures, no contact details, no ids the screen could enumerate with.
 *     A photographed board must not be worth anything to whoever photographed it.
 *
 * Competitor photos obey `profile_picture_is_public`: a member who has not made
 * their picture public does not get it projected on a wall, and falls back to the
 * corner silhouette. Putting a face on a public screen is a publication.
 */
class ScreenBoard
{
    /** Rows the board shows at once. The design is built for four. */
    public const ROWS = 4;

    public function __construct(private RunningOrder $order) {}

    /**
     * The whole screen, ready to render — the exact shape board.blade.php draws.
     *
     * `version` is a content hash, not a counter: the agent and the board both use
     * it to skip a repaint when a re-publish carries no visible change, which on a
     * low-powered screen is the difference between a still board and one that restarts its
     * entrance animation every few seconds.
     *
     * @return array{version: string, event: array, court: string, matches: array<int, array>}
     */
    public function payload(ClubEvent $event, string $court): array
    {
        $matches = $this->rows($event, $court);

        $body = [
            'event' => [
                'title' => (string) $event->title,
            ],
            // The full label ("Mat 1") identifies the mat; the header prints only
            // the number, because the design already supplies the word COURT.
            'court' => $court,
            'courtNumber' => $this->courtNumber($court),
            'matches' => $matches,
        ];

        return ['version' => hash('xxh128', json_encode($body)), ...$body];
    }

    /**
     * What goes after the word COURT in the header.
     *
     * Mats are named freely ("Mat 1", "Court 2", "A"), and the design's header
     * reads COURT + value — so "Mat 1" must print as "1", not "Mat 1". A name
     * with no number in it is shown whole rather than blanked.
     */
    private function courtNumber(string $court): string
    {
        return preg_match('/(\d+)/', $court, $m) ? $m[1] : trim($court);
    }

    /**
     * The queued bouts on this mat, closest first, capped at what fits.
     *
     * @return array<int, array>
     */
    private function rows(ClubEvent $event, string $court): array
    {
        // The shared definition — see RunningOrder::matQueue. The board, the
        // scoring table and "next bout" must name the same bout, or the wall
        // announces one thing and the table does another.
        //
        // Minus whatever is ON the mat right now. A bout only leaves
        // upcomingBouts() when it has a WINNER, so one that is being fought at
        // this moment is still, technically, upcoming — and it was landing in
        // row 0 with GET READY printed over it. The hall was being told to
        // prepare for two people already on the mat, and the bout the operator
        // was actually called to next was a row further down than anyone
        // reading the wall expected.
        //
        // The scoring table owns "what is on this mat", so that is where the
        // answer comes from.
        $onMat = MatState::forMat($event, $court)->match_id;

        $queue = $this->order->matQueue($event, $court)
            ->reject(fn (EventMatch $m) => $onMat && $m->id === $onMat)
            ->take(self::ROWS)
            ->values();

        // upcomingBouts() loads the category as id+name only, which is all the
        // athlete's countdown needs. The plate also prints the weight class, so
        // re-load with that column — on the four rows that survive the slice, not
        // on every bout in the event.
        $queue->load('category:id,name,weight_class');

        $entries = $this->entries($queue);

        return $queue->map(fn (EventMatch $m) => [
            // Bout number as the callers announce it; the round and division sit
            // under it on the centre plate. Null before the mat is numbered —
            // the board hides that line rather than printing a placeholder.
            'number' => $m->match_no !== null ? (string) $m->match_no : null,
            'stage' => $this->stage($m),
            'weightClass' => $m->category?->weight_class ?: ($m->category?->name ?: ''),

            ...$this->corner('blue', $m, $entries),
            ...$this->corner('white', $m, $entries),
        ])->all();
    }

    /**
     * One corner's four display fields.
     *
     * A name is always present — for an undecided feeder bout it reads
     * "Winner of 1-08" rather than blank, because a blank half looks broken on a
     * wall. Everything else degrades to the design's own placeholders.
     *
     * @param  array<int, ClubEventRegistration>  $entries
     * @return array<string, ?string>
     */
    private function corner(string $corner, EventMatch $bout, array $entries): array
    {
        // Blue is side 'a' and white is side 'b' — the same order on the wall,
        // the console and in the ledger. Red is never a competitor colour here.
        $side = $corner === 'blue' ? 'a' : 'b';
        $entry = $entries[$bout->{$side.'_competitor_id'}] ?? null;
        $user = $entry?->user;
        // The club they COMPETE FOR — see ClubEventRegistration::competingClub().
        $club = $entry?->competingClub();

        return [
            $corner.'Name' => $bout->{$side.'_name'} ?: __('event-bjj_tournament::messages.court_tbd'),
            $corner.'Club' => $club?->club_name ?? '',
            // The match records the competitor's country at draw time; failing
            // that, the country of the club they compete for. Never their own
            // nationality — a competitor here represents a club, and the club
            // has the country. Their passport is a fact about them, not about
            // this bout.
            $corner.'Flag' => strtolower((string) ($bout->{$side.'_country'} ?: $club?->country ?: '')) ?: null,
            $corner.'Photo' => $this->photo($user),
            $corner.'Logo' => $club?->logo ? file_url($club->logo) : null,
        ];
    }

    /**
     * The competitor's picture, or null when they have not published one.
     *
     * `profile_picture_is_public` is the member's own choice about whether their
     * face may be shown to people who are not their club. A hall screen is the
     * most public surface in the product, so a false here means the silhouette.
     */
    private function photo(?\App\Models\User $user): ?string
    {
        if (! $user?->profile_picture || ! $user->profile_picture_is_public) {
            return null;
        }

        return file_url($user->profile_picture);
    }

    /**
     * "Semifinal", "Final", "Round of 16" — the round, for a wall.
     *
     * DrawEngine already stores these as readable English, so this only has to
     * translate the fixed rounds and pass anything else through. A missing
     * translation shows the English rather than a raw key: a board with
     * "round_semifinal" on it would be worse than one in the wrong language.
     */
    private function stage(EventMatch $bout): string
    {
        $round = trim((string) $bout->round);
        $key = 'event-bjj_tournament::messages.round_'.strtolower(str_replace(' ', '_', $round));

        if (Lang::has($key)) {
            return __($key);
        }

        // "Round of 16" and friends — one string, the size interpolated.
        if (preg_match('/^Round of (\d+)$/', $round, $m)) {
            return __('event-bjj_tournament::messages.round_of', ['n' => $m[1]]);
        }

        return $round;
    }

    /**
     * The entries fighting in this slice, keyed by id.
     *
     * One query for the whole board rather than two per row — a hall screen
     * re-renders on every result entered on any mat, so this runs often.
     *
     * @param  \Illuminate\Support\Collection<int, EventMatch>  $queue
     * @return array<int, ClubEventRegistration>
     */
    private function entries($queue): array
    {
        $ids = $queue
            ->flatMap(fn (EventMatch $m) => [$m->a_competitor_id, $m->b_competitor_id])
            ->filter()
            ->unique()
            ->all();

        if (! $ids) {
            return [];
        }

        return ClubEventRegistration::whereIn('id', $ids)
            ->with([
                'user:id,name,nationality,profile_picture,profile_picture_is_public,updated_at',
                'user.memberClubs:id,club_name,logo,country',
                // The club they compete FOR, which is what the wall prints.
                'representingTenant:id,club_name,logo,country',
            ])
            ->get()
            ->keyBy('id')
            ->all();
    }
}
