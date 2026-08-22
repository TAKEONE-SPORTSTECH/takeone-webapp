<?php

namespace App\Http\Controllers;

use App\Models\ClubAchievement;
use App\Models\ClubEventRegistration;
use App\Models\ClubMemberSubscription;
use App\Models\Duel;
use App\Models\EventMatch;
use App\Models\User;
use App\Services\PeopleRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * People discovery: a club-scoped member search ("Find People" only surfaces
 * discoverable members who share a club with the viewer) and a SAFE public
 * profile that exposes only non-sensitive fields (no health, billing,
 * documents, contacts, or family). Private data stays on the family/admin-gated
 * member.show. Members opt out via users.is_discoverable.
 */
class PeopleController extends Controller
{
    /** The Find-People search page with a "Suggested for you" default state. */
    public function index(Request $request, PeopleRecommendationService $recommender)
    {
        $me = Auth::user();
        $hasConfirmedClub = $me->hasConfirmedClubMembership();

        // Discovery is club-scoped: until a club actually confirms the member,
        // there is no club-mate pool to suggest from — show the join CTA instead.
        $suggestions = $hasConfirmedClub ? $recommender->suggest($me) : collect();

        $isMobile = (bool) $request->attributes->get('is_mobile');

        // The mobile shell labels its header from $shellTitle for routes that
        // aren't in its own nav list.
        return view($isMobile ? 'people.mobile.index' : 'people.desktop.index', compact('suggestions', 'hasConfirmedClub'))
            ->with('shellTitle', __('personal.find_people'));
    }

    /** AJAX club-scoped member search (discoverable club-mates only). */
    public function search(Request $request)
    {
        $me = Auth::user();
        $q = trim((string) $request->query('q', ''));

        // No confirmed club membership yet → no club-mate pool to search.
        if (! $me->hasConfirmedClubMembership()) {
            return response()->json(['success' => true, 'query' => $q, 'people' => []]);
        }

        // Blocks are mutual: hide anyone the viewer blocked or who blocked them.
        $blockedIds = \App\Models\UserBlock::idsBlockedEitherWayWith($me->id);

        // Only members who share at least one club with the viewer are discoverable.
        $clubMateIds = $this->clubMateIds($me);

        $users = User::query()
            ->where('is_discoverable', true)
            ->whereNotIn('id', $blockedIds)
            ->whereIn('id', $clubMateIds)
            // Match by name, email, or phone (mobile is stored as a
            // {"code","number"} JSON blob, so LIKE matches the digits too).
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('mobile', 'like', "%{$q}%")))
            ->orderBy('full_name')
            ->limit(24)
            ->get(['id', 'uuid', 'slug', 'full_name', 'name', 'profile_picture', 'gender', 'is_personal_trainer', 'updated_at']);

        $followingIds = $me->following()->pluck('users.id');

        return response()->json([
            'success' => true,
            'query' => $q,
            'people' => $users->map(fn ($u) => [
                'uuid' => $u->uuid,
                'slug' => $u->slug,
                'name' => $u->full_name ?: $u->name,
                'avatar' => $u->profile_picture ? asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp : null,
                'gender' => $u->gender,
                'is_trainer' => (bool) $u->is_personal_trainer,
                'is_following' => $followingIds->contains($u->id),
                'profile_url' => route('people.show', $u->uuid),
            ])->values(),
        ]);
    }

    /** SAFE public profile — broadly viewable, sensitive data omitted. */
    public function show(Request $request, string $uuid)
    {
        $me = Auth::user();
        $person = User::where('uuid', $uuid)->firstOrFail();

        // Your own public profile → go to your full private profile instead, UNLESS the
        // caller explicitly asked for the public view (?public=1) — e.g. an instructor
        // badge that should always land on the safe, minimal profile, never the private one.
        if ($me !== null && $person->id === $me->id && ! $request->boolean('public')) {
            return redirect()->route('member.show', $person->uuid);
        }

        abort_unless($person->canViewPublicProfile($me), 404);

        $clubIds = $person->memberClubs()->pluck('tenants.id');

        $affiliations = $person->clubAffiliations()
            ->with(['tenant:id,slug,country,club_name', 'skillAcquisitions:id,club_affiliation_id,skill_name'])
            ->orderByDesc('start_date')
            ->get();
        $activeAffil = $affiliations->whereNull('end_date')->values();
        $pastAffil = $affiliations->whereNotNull('end_date')->values();

        // Real club memberships (memberships table) aren't always mirrored into a
        // manually-entered ClubAffiliation record — merge in any active membership
        // that doesn't already have one, so a real member is never shown as clubless.
        $affiliatedTenantIds = $affiliations->pluck('tenant_id')->filter()->map(fn ($id) => (int) $id)->all();
        $unaffiliatedMemberships = $person->memberClubs()
            ->wherePivot('status', 'active')
            ->get(['tenants.id', 'tenants.club_name', 'tenants.logo', 'tenants.slug', 'tenants.country'])
            ->reject(fn ($tenant) => in_array((int) $tenant->id, $affiliatedTenantIds, true))
            ->map(fn ($tenant) => (object) [
                'tenant_id' => $tenant->id,
                'tenant' => $tenant,
                'club_name' => $tenant->club_name,
                'logo' => $tenant->logo,
                'start_date' => $tenant->pivot->created_at,
                'end_date' => null,
            ])
            ->values();
        $activeAffil = $activeAffil->concat($unaffiliatedMemberships)->values();

        // Medals earned via the person's clubs' achievements (safe, public).
        $awards = $clubIds->isEmpty() ? collect() : ClubAchievement::whereIn('tenant_id', $clubIds)
            ->where('status', 'active')
            ->orderByDesc('achievement_date')
            ->with('tenant:id,club_name,slug,translations')
            ->get()
            ->map(function ($a) use ($person) {
                $athletes = is_array($a->athletes) ? $a->athletes : [];
                $mine = collect($athletes)->first(fn ($x) => is_array($x) && (int) ($x['user_id'] ?? 0) === (int) $person->id);
                $a->member_award = $mine['role'] ?? null;

                return $mine ? $a : null;
            })->filter()->values();

        // Tournament medals the person self-recorded AND a club has VERIFIED. Safe to show
        // publicly precisely because they're attested; self-reported/pending never appear here.
        $verifiedMedals = \App\Models\TournamentEvent::where('user_id', $person->id)
            ->where('verification_status', \App\Models\TournamentEvent::STATUS_VERIFIED)
            ->with(['performanceResults:id,tournament_event_id,medal_type', 'verifiedByTenant:id,club_name,slug,translations'])
            ->orderByDesc('date')
            ->get();

        // Claims that CAN'T be club-confirmed (no platform club) and still need attestation —
        // the peer/coach vouch path. Shown clearly as unverified, with a vouch CTA for eligible
        // viewers. Never counted as medals; the server re-checks vouch eligibility on submit.
        $vouchable = \App\Models\TournamentEvent::where('user_id', $person->id)
            ->whereIn('verification_status', [\App\Models\TournamentEvent::STATUS_SELF_REPORTED, \App\Models\TournamentEvent::STATUS_PENDING])
            ->where(fn ($q) => $q->whereNull('club_affiliation_id')
                ->orWhereHas('clubAffiliation', fn ($qq) => $qq->whereNull('tenant_id')))
            ->with('performanceResults:id,tournament_event_id,medal_type')
            ->orderByDesc('date')
            ->get();

        // Best-effort UI gate; AchievementVerificationService enforces the real rule on submit.
        // A guest has no family tie, no follow state and cannot vouch or message —
        // every viewer-relative fact below collapses to false for them.
        $isFamily = $me !== null && \App\Models\UserRelationship::where(fn ($q) => $q->where('guardian_user_id', $me->id)->where('dependent_user_id', $person->id))
            ->orWhere(fn ($q) => $q->where('guardian_user_id', $person->id)->where('dependent_user_id', $me->id))
            ->exists();
        $canVouch = $me !== null && $me->id !== $person->id && ! $isFamily;

        // Challenge (duel) win-rate.
        $duelsTotal = Duel::where('status', 'completed')
            ->where(fn ($q) => $q->where('challenger_id', $person->id)->orWhere('opponent_id', $person->id))
            ->count();
        $duelWins = Duel::where('status', 'completed')->where('winner_id', $person->id)->count();
        $winRate = $duelsTotal > 0 ? round(($duelWins / $duelsTotal) * 100) : 0;

        /*
         * Competition record — bouts actually fought at club events.
         *
         * Deliberately separate from the challenge win-rate above: a friendly
         * duel and a national final are not the same achievement, and averaging
         * them into one percentage would let either flatter or bury the other.
         *
         * Read from the draw itself (event_matches), not from anything a member
         * can type, so it cannot be self-reported. Only bouts with a decided
         * winner count — an unplayed or abandoned bout is not a loss.
         *
         * Two queries regardless of how many events the person has entered.
         */
        $compRegs = ClubEventRegistration::where('user_id', $person->id)->get(['id', 'event_id']);
        $compRegIds = $compRegs->pluck('id');
        /*
         * A bout belongs on the record once it has been FOUGHT — not only once
         * someone has typed a winner. Filtering on a recorded winner made a
         * fought-but-unscored bout vanish, which left one profile looking
         * structurally different from another for no reason the reader could see.
         * The result is reported separately below: won, lost, or awaiting one.
         */
        $compBouts = $compRegIds->isEmpty()
            ? collect()
            : EventMatch::where(fn ($q) => $q->whereNotNull('winner')->orWhere('status', 'done'))
                ->where(fn ($q) => $q->whereIn('a_competitor_id', $compRegIds)
                    ->orWhereIn('b_competitor_id', $compRegIds))
                ->with(['event:id,uuid,title,date,sport', 'category:id,name,weight_class'])
                ->orderByDesc('id')
                ->get();

        $compDecided = $compBouts->filter(fn ($b) => $b->winner !== null);
        $compWon = $compDecided->filter(fn ($b) =>
            ($b->winner === 'a' && $compRegIds->contains($b->a_competitor_id))
            || ($b->winner === 'b' && $compRegIds->contains($b->b_competitor_id))
        )->count();

        /*
         * The bouts themselves, shaped from this person's side: which corner
         * they were in decides who the opponent is and which score is theirs.
         * Names come off the draw row, so a bout still reads correctly after a
         * competitor's account is renamed or removed.
         */
        /*
         * Where each bout can be watched. One query for the whole list, keyed by
         * bout: `event_recordings` is the only join between a bout here and its
         * video on TAKEONE Play (VIDEO-INTEGRATION.md §5.2), so a bout that was
         * never filmed simply has no link and falls back to its own page.
         */
        /*
         * Opponent faces, one query for the whole list.
         *
         * Honours the athlete's own "show my picture" choice via
         * profile_picture_is_public, exactly as BracketView does — this profile is
         * seen by everyone the person's public profile reaches, so it is not a
         * place to override someone else's decision about their face. An opponent
         * who has not opted in keeps the initials tile.
         */
        $opponentFaces = $compBouts->isEmpty() ? collect() : ClubEventRegistration::query()
            ->whereIn('id', $compBouts->flatMap(fn ($b) => [$b->a_competitor_id, $b->b_competitor_id])->filter()->unique())
            ->with('user:id,profile_picture,profile_picture_is_public,updated_at')
            ->get(['id', 'user_id'])
            ->mapWithKeys(function (ClubEventRegistration $r) {
                $user = $r->user;

                return [$r->id => ($user?->profile_picture && $user->profile_picture_is_public)
                    ? asset('storage/'.$user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 0)
                    : null];
            });

        $compVideos = $compBouts->isEmpty() ? collect() : \Illuminate\Support\Facades\DB::table('event_recordings')
            ->whereIn('match_id', $compBouts->pluck('id'))
            ->whereNotNull('play_url')
            ->pluck('play_url', 'match_id');

        $competitionBouts = $compBouts->map(function ($b) use ($compRegIds, $compVideos, $opponentFaces) {
            $mine  = $compRegIds->contains($b->a_competitor_id) ? 'a' : 'b';
            $their = $mine === 'a' ? 'b' : 'a';

            return [
                'opponent_photo' => $opponentFaces[$b->{$their.'_competitor_id'}] ?? null,
                'decided'     => $b->winner !== null,
                'won'         => $b->winner === $mine,
                'opponent'    => $b->{$their.'_name'} ?: __('events.bout_tbd'),
                'my_score'    => $b->{$mine.'_score'},
                'their_score' => $b->{$their.'_score'},
                'event'       => $b->event?->title,
                'event_uuid'  => $b->event?->uuid,
                'date'        => $b->event?->date,
                'division'    => $b->category?->weight_class ?: $b->category?->name,
                'round'       => $b->phase ?: $b->round,
                'match_no'    => $b->match_no,
                'video_url'   => $compVideos[$b->id] ?? null,
                'bout_url'    => ($b->event && $b->match_no !== null)
                    ? route('me.events.bout', ['event' => $b->event->uuid, 'matchNo' => $b->match_no])
                    : null,
            ];
        })->values()->all();

        $competition = [
            'fought'    => $compBouts->count(),
            'won'       => $compWon,
            // Only a decided bout can be a loss; one still awaiting a result is
            // neither, and counting it as a loss would misreport the athlete.
            'lost'      => $compDecided->count() - $compWon,
            'undecided' => $compBouts->count() - $compDecided->count(),
            // Rate over DECIDED bouts, so an unscored bout does not drag it down.
            // Null (not zero) when nothing is decided yet — there is no rate to state.
            'rate'      => $compDecided->count() > 0
                ? (int) round($compWon / $compDecided->count() * 100)
                : null,
            'events'    => $compBouts->pluck('event_id')->unique()->count(),
        ];

        $duels = Duel::where('status', 'completed')
            ->where(fn ($q) => $q->where('challenger_id', $person->id)->orWhere('opponent_id', $person->id))
            ->with(['challenger:id,full_name,profile_picture', 'opponent:id,full_name,profile_picture'])
            ->orderByDesc('completed_at')
            ->get()
            ->map(function ($duel) use ($person) {
                $isChallenger = (int) $duel->challenger_id === (int) $person->id;
                $rival = $isChallenger ? $duel->opponent : $duel->challenger;
                $duel->rival_name = $rival->full_name ?? ($duel->opponent_name ?: __('shared.unknown'));
                $duel->rival_picture = $rival->profile_picture ?? null;
                $duel->result = $duel->winner_id === null ? 'draw' : ((int) $duel->winner_id === (int) $person->id ? 'win' : 'loss');

                return $duel;
            });

        $skills = $affiliations->flatMap(fn ($a) => $a->skillAcquisitions->pluck('skill_name'))
            ->filter()->unique()->take(12)->values();

        /*
         * Honours, flattened into one shape the profile can tally and filter.
         *
         * Two very different records land here — a medal a club awarded from its
         * own achievement list, and a tournament the member entered themselves
         * that a club later VERIFIED — but a reader does not care which table a
         * medal came out of, only what it was and when. Tiering by keyword (and
         * by medal_type on the verified side) is what lets the hero show
         * "2 gold, 1 silver" without either source knowing about the other.
         */
        $honours = collect();

        foreach ($awards as $a) {
            $honours->push([
                'tier' => $this->honourTier((string) $a->member_award),
                'place' => $a->member_award ?: __('member.award_default'),
                'event' => $a->tenant?->tr('club_name') ?? $a->tenant?->club_name,
                'date' => $a->achievement_date,
            ]);
        }

        foreach ($verifiedMedals as $t) {
            foreach ($t->performanceResults as $r) {
                $honours->push([
                    'tier' => match ($r->medal_type) {
                        '1st' => 'gold',
                        '2nd' => 'silver',
                        '3rd' => 'bronze',
                        default => 'trophy',
                    },
                    'place' => match ($r->medal_type) {
                        '1st' => __('personal.honour_gold'),
                        '2nd' => __('personal.honour_silver'),
                        '3rd' => __('personal.honour_bronze'),
                        default => __('personal.honour_trophy'),
                    },
                    'event' => $t->title,
                    'date' => $t->date,
                ]);
            }
        }

        $honours = $honours->sortByDesc(fn ($h) => optional($h['date'])->timestamp ?? 0)->values();

        $honourTally = [
            'gold' => $honours->where('tier', 'gold')->count(),
            'silver' => $honours->where('tier', 'silver')->count(),
            'bronze' => $honours->where('tier', 'bronze')->count(),
            'trophy' => $honours->where('tier', 'trophy')->count(),
        ];

        /*
         * Certifications are self-managed and carry no attestation of their own,
         * so the profile states only what it can stand behind: the title, the
         * issuer, and whether the certificate is still inside its own expiry —
         * never "verified".
         */
        $certifications = \App\Models\MemberCertification::where('user_id', $person->id)
            ->orderByDesc('issue_date')
            ->get(['id', 'title', 'issuer', 'issue_date', 'expiry_date', 'credential_id', 'credential_url']);

        /*
         * What she competes in, by sport.
         *
         * This is the other half of "which sports does this person do", and for
         * many members it is the ONLY half: a club can enter an athlete into a
         * championship without ever selling them a package, so reading sports
         * from subscriptions alone left a real competitor showing none. Entering
         * an event is also the harder evidence of the two — it comes off the
         * draw, not off a form the member filled in.
         */
        $entered = $compRegs->pluck('event_id')->filter()->unique();
        $enteredEvents = $entered->isEmpty() ? collect() : \App\Models\ClubEvent::whereIn('id', $entered)
            ->whereNotNull('sport')
            ->get(['id', 'sport', 'date']);

        $competitionBySport = [];

        foreach ($enteredEvents as $event) {
            $key = mb_strtolower((string) $event->sport);
            $competitionBySport[$key]['events'] = ($competitionBySport[$key]['events'] ?? 0) + 1;
            $competitionBySport[$key]['bouts'] ??= 0;

            // Earliest evidence of them doing this sport, for the duration chip.
            if ($event->date !== null) {
                $at = \Illuminate\Support\Carbon::parse($event->date);
                $competitionBySport[$key]['first'] = isset($competitionBySport[$key]['first'])
                    ? $competitionBySport[$key]['first']->min($at)
                    : $at;
            }
        }

        foreach ($compBouts as $bout) {
            $key = mb_strtolower((string) ($bout->event?->sport ?? ''));
            if ($key === '') {
                continue;
            }
            $competitionBySport[$key]['events'] ??= 0;
            $competitionBySport[$key]['bouts'] = ($competitionBySport[$key]['bouts'] ?? 0) + 1;
        }

        $sports = $this->sportsPractised($person, $competitionBySport);

        /*
         * The flag beside a competitor is their CLUB's country, never the
         * nationality on their account — at an event a person represents the
         * club that entered them.
         */
        $countryCode = $person->memberClubs()->value('tenants.country')
            ?: $affiliations->first()?->tenant?->country;


        $data = [
            'person' => $person,
            'isMe' => false,
            'isFollowing' => $me !== null && $me->isFollowing($person->id),
            'canMessage' => $me !== null && $me->canMessage($person),
            // Drives the actions block: a guest is offered sign-in, not a Follow
            // button that would fail the moment they pressed it.
            'isGuest' => $me === null,
            'activeAffil' => $activeAffil,
            'pastAffil' => $pastAffil,
            'awards' => $awards,
            'verifiedMedals' => $verifiedMedals,
            'vouchable' => $vouchable,
            'canVouch' => $canVouch,
            'skills' => $skills,
            'honours' => $honours,
            'honourTally' => $honourTally,
            'certifications' => $certifications,
            'sports' => $sports,
            'countryCode' => $countryCode,
            'winRate' => $winRate,
            'competition' => $competition,
            'competitionBouts' => $competitionBouts,
            'duelsTotal' => $duelsTotal,
            'duelWins' => $duelWins,
            'duels' => $duels,
        ];

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'people.mobile.show' : 'people.desktop.show', $data);
    }

    /**
     * Which medal a free-text club award describes.
     *
     * Clubs type their own award wording ("Gold medal, -61 kg"), so the tier is
     * read out of the words rather than a column. Anything that is not one of
     * the three podium places is a trophy — the catch-all the hero counts
     * separately, so an unrecognised award is still shown rather than dropped.
     */
    private function honourTier(string $label): string
    {
        $l = mb_strtolower($label);

        return match (true) {
            str_contains($l, 'gold') || str_contains($l, '1st') => 'gold',
            str_contains($l, 'silver') || str_contains($l, '2nd') => 'silver',
            str_contains($l, 'bronze') || str_contains($l, '3rd') => 'bronze',
            default => 'trophy',
        };
    }

    /**
     * The sports this person does, from both things that can prove it.
     *
     * TRAINING comes from the activities inside the packages they subscribed to;
     * COMPETING comes from the events they were entered into. Either alone is an
     * incomplete answer — a member can train for years without ever entering a
     * championship, and a club can enter an athlete who never bought a package —
     * so the two are merged on the sport's name and the row says which it is.
     *
     * Time practised is the UNION of the training spans that include the
     * activity: two overlapping enrolments count once, and a gap between them is
     * excluded, so a member who left and came back is not credited for the years
     * they were away. A competing-only sport has no such span and simply shows no
     * duration rather than a guessed one.
     *
     * @param  array<string, array{events?:int, bouts?:int}>  $competitionBySport  keyed by lowercased sport
     */
    private function sportsPractised(User $person, array $competitionBySport = []): \Illuminate\Support\Collection
    {
        $byKey = [];

        // --- Training: the activities inside subscribed packages.
        $subs = ClubMemberSubscription::where('user_id', $person->id)
            ->with('package.activities:id,name')
            ->get(['id', 'user_id', 'package_id', 'start_date', 'end_date', 'status']);

        foreach ($subs as $sub) {
            foreach ($sub->package?->activities ?? [] as $activity) {
                $name = trim((string) $activity->name);
                if ($name === '') {
                    continue;
                }

                $key = mb_strtolower($name);
                $byKey[$key]['name'] ??= $name;
                $byKey[$key]['spans'][] = [
                    $sub->start_date ? \Illuminate\Support\Carbon::parse($sub->start_date) : null,
                    $sub->end_date ? \Illuminate\Support\Carbon::parse($sub->end_date) : null,
                ];
                $byKey[$key]['active'] = ($byKey[$key]['active'] ?? false) || $sub->status === 'active';
            }
        }

        // --- Competing: the sports of the events they were entered into. The
        // registry supplies the proper label so a stored "karate" reads "Karate".
        $registry = app(\App\Sports\Combat\SportRegistry::class);

        foreach ($competitionBySport as $key => $tally) {
            $byKey[$key]['name'] ??= $registry->get($key)?->label() ?? \Illuminate\Support\Str::title($key);
            $byKey[$key]['events'] = $tally['events'] ?? 0;
            $byKey[$key]['bouts'] = $tally['bouts'] ?? 0;

            /*
             * Competing in a sport is itself evidence of doing it, so the first
             * event counts towards time in the sport. This matters for the many
             * members a club enters into championships without ever selling them
             * a package: without it their only sport would show no duration at all.
             * It merges into the same union as the enrolment spans, so a member who
             * both trains and competes is never counted twice for the same period.
             */
            if (isset($tally['first'])) {
                $byKey[$key]['spans'][] = [$tally['first'], null];
            }
        }

        if ($byKey === []) {
            return collect();
        }

        $names = collect($byKey)->pluck('name')->all();

        // Catalogue icons are keyed by the activity's own name, so a sport that
        // matches a catalogue entry inherits its icon for free.
        $icons = \App\Models\ActivityCatalog::whereIn('name', $names)
            ->pluck('icon', 'name')
            ->mapWithKeys(fn ($icon, $name) => [mb_strtolower($name) => $icon]);

        // A grade is only shown when a club has attested it; a self-typed belt
        // rank is a claim, not a grade, and this profile is public.
        $grades = \App\Models\SkillAcquisition::where('user_id', $person->id)
            ->where('verification_status', \App\Models\SkillAcquisition::STATUS_VERIFIED)
            ->whereNotNull('activity_name')
            ->orderByDesc('id')
            ->pluck('proficiency_level', 'activity_name')
            ->mapWithKeys(fn ($level, $name) => [mb_strtolower((string) $name) => $level]);

        return collect($byKey)->map(function ($s, $key) use ($icons, $grades) {
            $bouts = $s['bouts'] ?? 0;
            $events = $s['events'] ?? 0;

            return [
                'name' => $s['name'],
                // Stored bare (bi-*) and rendered as `class="bi {{ icon }}"`, the
                // convention the activity pages already use.
                'icon' => $this->sportIcon($icons[$key] ?? null),
                'grade' => $grades[$key] ?? null,
                'experience' => $this->unionDuration($s['spans'] ?? []),
                'bouts' => $bouts,
                'events' => $events,
                // Competing outranks enrolled: having been entered into a
                // championship is the stronger statement about the athlete.
                'competing' => $events > 0 || $bouts > 0,
                'active' => (bool) ($s['active'] ?? false),
            ];
        })->sortByDesc(fn ($s) => ($s['competing'] ? 2 : 0) + ($s['active'] ? 1 : 0))->values();
    }

    /** Normalise a catalogue icon to a bare `bi-*` class, with a sport-ish default. */
    private function sportIcon(?string $icon): string
    {
        $icon = trim((string) $icon);
        // Catalogue rows store either "bi-foo" or occasionally "bi bi-foo".
        $icon = preg_replace('/^bi\s+/', '', $icon) ?? '';

        return preg_match('/^bi-[a-z0-9-]+$/', $icon) === 1 ? $icon : 'bi-person-arms-up';
    }

    /**
     * Human duration covered by a set of (possibly overlapping) date spans.
     *
     * Sorting by start and merging forward is what makes overlaps count once;
     * an open-ended span runs to today. Returns null when nothing is datable,
     * so the caller can omit the chip instead of printing "0 months".
     */
    private function unionDuration(array $spans): ?string
    {
        $ranges = collect($spans)
            ->filter(fn ($s) => $s[0] !== null)
            ->map(fn ($s) => [$s[0], $s[1] ?? \Illuminate\Support\Carbon::now()])
            ->sortBy(fn ($s) => $s[0]->timestamp)
            ->values();

        if ($ranges->isEmpty()) {
            return null;
        }

        $days = 0;
        $cur = null;

        foreach ($ranges as $r) {
            if ($cur === null || $r[0]->greaterThan($cur[1])) {
                if ($cur !== null) {
                    $days += $cur[0]->diffInDays($cur[1]);
                }
                $cur = $r;

                continue;
            }
            $cur[1] = $r[1]->greaterThan($cur[1]) ? $r[1] : $cur[1];
        }

        $days += $cur[0]->diffInDays($cur[1]);

        $months = (int) round($days / 30.44);
        $years = intdiv($months, 12);
        $rest = $months % 12;

        if ($years === 0) {
            return trans_choice('personal.duration_months', max($months, 1), ['count' => max($months, 1)]);
        }

        $out = trans_choice('personal.duration_years', $years, ['count' => $years]);

        return $rest > 0
            ? $out.' '.__('personal.duration_months_short', ['count' => $rest])
            : $out;
    }

    /** IDs of every user sharing a club-owner-confirmed membership with the given user. */
    private function clubMateIds(User $user): \Illuminate\Support\Collection
    {
        return ClubMemberSubscription::confirmedClubMateIds($user->id);
    }
}
