<?php

namespace App\Scoreboard\Sports\Karate\HallScreen;

use App\Events\Sports\Karate\Tournament\RunningOrder;
use App\Scoreboard\Sports\Karate\Mat\MatState;
use App\Events\Support\CompetitorPhoto;
use App\Events\Support\EntryClub;
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
class CourtDisplay
{
    /** Rows the board shows at once. The design is built for four. */
    public const ROWS = 4;

    /**
     * The clubs the organiser WROTE DOWN for this event, keyed by entry id.
     *
     * A competitor's club is one of two things and the wall has to print both:
     * a TENANT (a club with an account here) or an EVENT CLUB (a name and a
     * crest the organiser typed in for a team the platform has never met).
     * Most of a real championship is the second kind — at the Victory BJJ
     * Championship, seven of the eight clubs — and this board knew only about
     * the first, so it printed one club's name over and over and left every
     * other competitor with a blank plate.
     *
     * App\Events\Support\EntryClub is the one place that rule lives; the
     * scoring table's running order already reads through it.
     *
     * @var array<int, array{name: string, logo: ?string, country: ?string}>
     */
    private array $written = [];

    public function __construct(private RunningOrder $order, private CompetitorPhoto $photos) {}

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
        $onMat = MatState::load($event, $court)->matchId;

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

            ...$this->corner('red', $m, $entries, $event),
            ...$this->corner('blue', $m, $entries, $event),
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
    private function corner(string $corner, EventMatch $bout, array $entries, ClubEvent $event): array
    {
        $side = $corner === 'red' ? 'a' : 'b';
        $entry = $entries[$bout->{$side.'_competitor_id'}] ?? null;
        $user = $entry?->user;
        // The club they COMPETE FOR — see ClubEventRegistration::competingClub().
        $club = $entry?->competingClub();

        // A tenant WINS when the entry names one: it is the stronger claim and
        // it carries a country and a page. Otherwise the club the organiser
        // wrote down stands in — name, crest and country alike.
        $named = $club
            ? ['name' => (string) $club->club_name, 'logo' => $club->logo ? file_url($club->logo) : null, 'country' => $club->country ?: null]
            : ($entry ? ($this->written[$entry->id] ?? null) : null);

        /*
         * One source for both halves of the same fact — the flag and the word.
         *
         * Three candidates, in this order:
         *
         *  1. What the DRAW recorded, or an official typed at the table. An
         *     override entered by a human beats anything derived.
         *  2. The country of the club they compete for. An athlete representing
         *     a Bahraini club is on the sheet as Bahrain whatever their passport
         *     says — that rule is unchanged and still comes before the passport.
         *  3. ClubEventRegistration::countryCode(), which is the ONE place the
         *     rest of the rule lives: the entry's own country, then the
         *     ATHLETE'S NATIONALITY.
         *
         * ⚠️ The third was missing here, and it is most of a real field. This
         * event has 84 bouts with no country on the draw and seven written-down
         * clubs with no country of their own, while the nationalities were on
         * the athletes all along — Saudi, Norwegian, Iraqi, Brazilian, American,
         * Turkmen, Kuwaiti. Every one of them showed as Bahrain's flag or as
         * nothing, because the chain stopped at the club. The scoring table has
         * always read through countryCode(); the wall did not.
         */
        $country = (string) (
            $bout->{$side.'_country'}
            ?: ($named['country'] ?? '')
            ?: ($entry?->countryCode() ?: '')
        );

        return [
            // Two parts, however many the competitor wrote down. A hall row
            // carries a photo, a flag, a country, a crest, a club and a centre
            // plate beside this, and "Mohammed Abdulla Mansoor Almaqabi" is
            // four of them and 33 characters. App\Support\PersonName::short
            // drops the middle and keeps both names WHOLE — the way a
            // competitor is announced and called to the mat — rather than
            // cutting one off with an ellipsis.
            //
            // Display only: the full name stays on the record, and the
            // member's own profile, the bracket and the medal list print it.
            $corner.'Name' => \App\Support\PersonName::short($bout->{$side.'_name'}) ?: __('scoreboard::karate_messages.court_tbd'),
            $corner.'Club' => $named['name'] ?? '',
            // Derived, not lower-cased: the same country arrives as 'BH',
            // 'BHR' and 'Bahrain' depending on which of the three sources
            // above answered, and all three must reach the same flag file.
            // Null rather than a guess — the row then prints the country
            // without a flag instead of the wrong country's colours.
            $corner.'Flag' => \App\Support\Countries::iso2($country),
            // The country SPELLED OUT, to sit beside the flag above the name:
            // 'BAHRAIN', never 'BH'. Two letters on a wall is a puzzle, and the
            // flag is already the short form of the same fact.
            //
            // App\Support\Countries::label is the one place that rule lives —
            // the same field arrives as a code from a club's `country` column
            // and as a name from an official who picked one at the mat, and it
            // passes through anything it cannot place rather than blanking a
            // value somebody typed on purpose.
            $corner.'Country' => \App\Support\Countries::label($country),
            // The SAME question the introduction and the mat ask, answered in
            // the same place — so a picture added at the scoring desk shows on
            // the upcoming board too, and on every later bout this competitor
            // has in this event. See App\Events\Support\CompetitorPhoto.
            $corner.'Photo' => $this->photos->url($entry, $user, $event->id),
            // The drawn stand-in for a competitor with no picture — the same
            // pair of avatars the rest of the platform falls back to, and the
            // same pair the introduction panel on this very screen already
            // used. A row that hid its photo left a coloured rectangle where a
            // person should be, next to three rows that had one.
            //
            // It is drawn back (see the board's own opacity rule): this panel
            // is two metres of wall, and a drawing at full strength would read
            // as a photograph of the athlete standing on the mat.
            $corner.'Fallback' => \App\Support\Avatar::placeholder($user?->gender),
            $corner.'Logo' => $this->photos->crestUrl($entry, $club, $event->id) ?: ($named['logo'] ?? null),
        ];
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
        $key = 'scoreboard::karate_messages.round_'.strtolower(str_replace(' ', '_', $round));

        if (Lang::has($key)) {
            return __($key);
        }

        // "Round of 16" and friends — one string, the size interpolated.
        if (preg_match('/^Round of (\d+)$/', $round, $m)) {
            return __('scoreboard::karate_messages.round_of', ['n' => $m[1]]);
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
            $this->written = [];

            return [];
        }

        // One query for the whole board — see the $written docblock above.
        $this->written = EntryClub::forMany(
            (int) ($queue->first()?->event_id ?: 0),
            $ids
        );

        return ClubEventRegistration::whereIn('id', $ids)
            ->with([
                'user:id,name,gender,nationality,profile_picture,profile_picture_is_public,updated_at',
                'user.memberClubs:id,club_name,logo,country',
                // The club they compete FOR, which is what the wall prints.
                'representingTenant:id,club_name,logo,country',
            ])
            ->get()
            ->keyBy('id')
            ->all();
    }
}
