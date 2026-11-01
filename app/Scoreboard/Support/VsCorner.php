<?php

namespace App\Scoreboard\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventMatch;
use App\Events\Models\EventOfficial;
use App\Sports\Combat\BeltRank;
use Illuminate\Support\Collection;

/**
 * What the VS screen says about a bout and its two corners — ONE answer for
 * every screen that introduces a bout (scoreboard::vs.arena): the Karate mats,
 * the Taekwondo and BJJ mats, the bout page's intro and the boutcam.
 * Docs: docs/events/vs-screen.md
 *
 * Lifted verbatim from the Karate mat's scoring (Sports\Karate\Mat\Scoring),
 * which now calls it, so the mat and everything else read the same name, club,
 * flag, crest, photo and framing, record, belt and stat line.
 */
class VsCorner
{
    /**
     * Whose corner look (names and colours) a sport's VS screen wears — each
     * scoreboard's own spec, so a Taekwondo bout reads Hong / Chung and an
     * event that renamed or recoloured its corners keeps that everywhere.
     * Any sport not listed wears Karate's (red / blue), as its mats do.
     */
    private const LOOKS = [
        'taekwondo' => \App\Scoreboard\Sports\Taekwondo\Mat\CornerLook::class,
        'bjj' => \App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\CornerLook::class,
    ];

    public function __construct(private BeltRank $belts) {}

    /**
     * The VS screen's two looks for this event, as `aka` (the red corner) and
     * `ao` (the blue) — the keys scoreboard::vs.arena reads — and which side red
     * stands on.
     *
     * @return array{look: array{aka: array, ao: array}, redRight: bool}
     */
    public function look(ClubEvent $event): array
    {
        $class = self::LOOKS[$event->sport] ?? null;
        $spec = $class
            ? $class::spec($event)
            : \App\Scoreboard\Sports\Karate\Mat\CornerLook::spec($event);
        $palette = \App\Scoreboard\Corners\CornerLook::for($event, $spec);
        // A sport's corner keys are its own (aka/ao, hong/chung, blue/white);
        // the first is always the red corner's.
        [$red, $blue] = array_values($palette);

        return [
            'look' => ['aka' => $red, 'ao' => $blue],
            'redRight' => \App\Sports\Combat\CornerSide::redRight($event->sport),
        ];
    }

    /**
     * The entries a bout's corners name, with everything a corner reads.
     * Keyed by REGISTRATION id — a_competitor_id / b_competitor_id name the
     * entry in this event, which carries the weigh-in weight and the belt.
     *
     * @param  list<int|null>  $ids
     * @return Collection<int, ClubEventRegistration>
     */
    public function registrations(ClubEvent $event, array $ids): Collection
    {
        return ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', array_filter($ids))
            ->with(['user:id,full_name,name,gender,birthdate,nationality,height_cm,profile_picture,profile_picture_is_public',
                'user.certifications:id,user_id,title,issue_date',
                'user.skillAcquisitions:id,user_id,proficiency_level,start_date',
                'user.memberClubs:id,club_name,logo,country',
                // The club they compete FOR, which is what the screens print.
                'representingTenant:id,club_name,logo,country'])
            ->get()->keyBy('id');
    }

    /**
     * A bout as the VS screen introduces it: the top line, the referee chip and
     * both corners — the shape the mat's state carries.
     *
     * @return array{tournament: string, stage: string, division: string, matchNo: ?string, referee: ?string, court: string, aka: array, ao: array}
     */
    public function forBout(ClubEvent $event, EventMatch $match): array
    {
        $registrations = $this->registrations($event, [$match->a_competitor_id, $match->b_competitor_id]);
        $written = \App\Events\Support\EntryClub::forMany($event, $registrations->keys()->all());
        $match->loadMissing('category:id,name,weight_class');

        // aka is always the RED corner, whichever slot fought in it.
        [$red, $blue] = $match->a_corner === 'blue' ? ['b', 'a'] : ['a', 'b'];

        return [
            'tournament' => (string) $event->title,
            'stage' => (string) ($match->phase ?: $match->round ?: ''),
            'division' => (string) ($match->category?->weight_class ?: $match->category?->name ?: ''),
            'matchNo' => $match->match_no !== null ? (string) $match->match_no : null,
            'referee' => $this->officiatingName($event),
            'court' => (string) ($match->court ?? ''),
            'aka' => self::announced($this->corner($match, $red, $registrations, $written)),
            'ao' => self::announced($this->corner($match, $blue, $registrations, $written)),
        ];
    }

    /**
     * One corner, as a screen should read it.
     *
     * Only the country is touched, and only its presentation: the flag stays
     * the code (it is a filename), and a name we cannot place is passed through
     * as it was given rather than blanked.
     */
    public static function announced(array $corner): array
    {
        if (($corner['country'] ?? '') !== '') {
            $corner['country'] = \App\Support\Countries::label($corner['country']);
        }
        if (($corner['nationality'] ?? '') !== '') {
            $corner['nationalityName'] = \App\Support\Countries::label($corner['nationality']);
        }

        return $corner;
    }

    /**
     * One corner, as the screens announce it.
     *
     * A competitor's photo obeys profile_picture_is_public — a hall screen is a
     * publication, and a member who has not made their picture public does not
     * get their face projected on a wall.
     */
    public function corner(EventMatch $match, string $side, $registrations, array $written = []): array
    {
        $id = $match->{$side.'_competitor_id'};
        $reg = $id ? $registrations->get($id) : null;
        $user = $reg?->user;
        // The club they COMPETE FOR — see ClubEventRegistration::competingClub().
        $club = $reg?->competingClub();
        $belt = $user ? $this->belts->for($user, $reg) : null;
        // A tenant wins when the entry names one; otherwise the club the
        // organiser wrote down for this event stands in, name and crest alike.
        $other = (! $club && $id) ? ($written[$id] ?? null) : null;

        // The flag in the bracket's order (as HallScreen\CourtDisplay does):
        // countryCode() first, because it alone obeys the event's flag switch
        // (App\Events\Support\FlagSource) — club country, or the athlete's
        // nationality. Then the written-down club, then the bout's copy.
        $country = (string) (($reg?->countryCode() ?: '')
            ?: ($club?->country ?: ($other['country'] ?? ''))
            ?: ($match->{$side.'_country'} ?: ''));

        return [
            'name' => $match->{$side.'_name'} ?: ($user?->full_name ?? $user?->name ?? ''),
            'club' => $club?->club_name ?? ($other['name'] ?? ''),
            // One answer for the word and the flag (see $country above).
            'country' => $country,
            'flag' => strtolower((string) (\App\Support\Countries::iso2($country) ?: $country)) ?: null,
            // The person's own nationality, as an alpha-2 flag code. Read by
            // the Combat style's VS arena only (the owner's call, 2026-10-16):
            // the intro presents the athlete, the scoreboard still presents
            // the club. Null when unknown — the VS falls back to the club.
            'nationality' => \App\Support\Countries::iso2($user?->nationality),
            'logo' => $club?->logo ? file_url($club->logo) : ($other['logo'] ?? null),
            // The event's OWN photo wins, then the member's profile picture if
            // they published it. The first was uploaded by an organiser FOR this
            // competition — including for the many competitors who have no
            // account to have a profile picture on — so it needs no privacy gate
            // beyond the one that put it there. The second is somebody's private
            // picture and keeps its gate: a hall screen is a publication.
            'photo' => $reg?->photo
                ? file_url($reg->photo)
                : (($user?->profile_picture && $user->profile_picture_is_public)
                    ? file_url($user->profile_picture)
                    : null),
            // How that photo is PLACED on the VS screen — a zoom and a focal
            // point over the untouched original (App\Scoreboard\Models\
            // PhotoVsFrame), set per person in the console's Players tab.
            // Null: the panel's own framing, as always.
            'vs_frame' => \App\Scoreboard\Models\PhotoVsFrame::payloadFor($reg?->photo
                ?: (($user?->profile_picture && $user->profile_picture_is_public) ? $user->profile_picture : null)),
            // The drawn stand-in, for a corner with no picture of their own.
            // Always present — see App\Members\Support\Avatar for why an unknown
            // gender takes the male artwork rather than nothing.
            'fallback' => \App\Members\Support\Avatar::placeholder($user?->gender),
            'belt' => $belt['label'] ?? null,
            'record' => $this->record($user?->id),
            // The stat line. Each part is null when unknown, and the screen
            // hides what is null rather than printing a placeholder — invented
            // detail on a hall screen reads as fact.
            'age' => $user?->birthdate ? $user->birthdate->age : null,
            'height' => $user?->height_cm ?: null,
            'weight' => $reg?->weight ? (float) $reg->weight : null,
        ];
    }

    /**
     * The referee the VS screen names: whoever is appointed to the event's jury,
     * as "A. Surname". Nobody appointed means the chip is hidden — a hall screen
     * naming a referee who was never assigned is worse than one that names none.
     */
    public function officiatingName(ClubEvent $event): ?string
    {
        $full = $event->officials()
            ->where('role', EventOfficial::ROLE_JURY)
            ->with('user:id,full_name,name')
            ->first()?->user?->full_name;

        if (! $full) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($full), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($parts) < 2
            ? ($parts[0] ?? null)
            : mb_substr($parts[0], 0, 1).'. '.end($parts);
    }

    /**
     * A competitor's win–loss record across every event they have fought in.
     *
     * The draw stores a REGISTRATION id per corner, and a registration belongs
     * to one event — so a career record means gathering all of this athlete's
     * registrations first, then every decided bout either of them appears in.
     */
    public function record(?int $userId): ?string
    {
        if (! $userId) {
            return null;
        }

        $entries = ClubEventRegistration::where('user_id', $userId)->pluck('id');

        if ($entries->isEmpty()) {
            return null;
        }

        $bouts = EventMatch::whereNotNull('winner')
            ->where(fn ($q) => $q->whereIn('a_competitor_id', $entries)->orWhereIn('b_competitor_id', $entries))
            ->get(['a_competitor_id', 'b_competitor_id', 'winner']);

        if ($bouts->isEmpty()) {
            return null;   // no history yet — the chip is hidden, not zeroed
        }

        $won = $bouts->filter(function (EventMatch $m) use ($entries) {
            $side = $entries->contains($m->a_competitor_id) ? 'a' : 'b';

            return $m->winner === $side;
        })->count();

        return $won.'W – '.($bouts->count() - $won).'L';
    }
}
