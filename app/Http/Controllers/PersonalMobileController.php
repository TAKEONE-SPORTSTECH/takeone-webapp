<?php

namespace App\Http\Controllers;

use App\Models\ClubEvent;
use App\Models\ClubMemberSubscription;
use App\Models\ClubPackageActivity;
use App\Models\ClubTimelinePost;
use App\Models\Goal;
use App\Models\UserNotification;
use App\Models\UserScheduleSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The Personal ("member") mobile experience — renders inside the shared
 * mobile shell (layouts/personal-mobile) so switching Personal⇄Business keeps
 * the same chrome (top bar + switcher dropdown + bottom tabs).
 */
class PersonalMobileController extends Controller
{
    use \App\Traits\BuildsMemberPayments;
    use \App\Traits\HandlesClubAuthorization;
    use \App\Traits\StoresBase64Images;

    private function clubIds()
    {
        return Auth::user()->memberClubs()->pluck('tenants.id');
    }

    public function home(Request $request): View
    {
        $user = Auth::user();

        $posts = ClubTimelinePost::whereIn('tenant_id', $this->clubIds())
            ->where('status', 'published')
            ->with('tenant:id,club_name,logo,translations')
            ->withCount(['likes', 'comments'])
            ->latest('posted_at')
            ->limit(20)
            ->get();

        $postWith = [
            'user:id,slug,full_name,profile_picture,updated_at',
            'likes:id,user_post_id,user_id',
            'comments.user:id,full_name,profile_picture,updated_at',
            'pollVotes:id,user_post_id,user_id,option',
        ];

        // "My Feed" — the member's own persisted posts (newest first). Hidden
        // (moderated) posts drop out for everyone but super-admins.
        $personalPosts = \App\Models\UserPost::where('user_id', $user->id)
            ->visibleTo($user)
            ->with($postWith)->withCount(['likes', 'views'])->latest()->get()
            ->map(fn ($p) => $p->toFeedArray($user))->values();

        // "Following" — blended posts from people you follow, minus anyone
        // blocked either way.
        $followIds = $user->following()->pluck('users.id');
        $blockedIds = \App\Models\UserBlock::where('blocker_id', $user->id)->pluck('blocked_id')
            ->merge(\App\Models\UserBlock::where('blocked_id', $user->id)->pluck('blocker_id'));

        $audience = $followIds->unique()->diff($blockedIds)->values();

        $followingPosts = $audience->isEmpty()
            ? collect()
            : \App\Models\UserPost::whereIn('user_id', $audience)
                ->visibleTo($user)
                ->with($postWith)->withCount(['likes', 'views'])->latest()->limit(50)->get()
                ->map(fn ($p) => $p->toFeedArray($user))->values();

        // "People you may know" — club-mates you can follow / visit.
        $followingIdSet = $followIds->flip();
        $mateIds = \Illuminate\Support\Facades\DB::table('memberships')
            ->whereIn('tenant_id', $this->clubIds())
            ->where('user_id', '!=', $user->id)
            ->distinct()->pluck('user_id')
            ->diff($blockedIds)
            ->take(20);

        $suggestions = \App\Models\User::whereIn('id', $mateIds)
            ->get(['id', 'uuid', 'slug', 'full_name', 'profile_picture', 'updated_at'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'slug' => $u->slug,
                'name' => $u->full_name,
                'avatar' => $u->profile_picture
                    ? asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp
                    : null,
                'url' => route('people.show', $u->uuid),
                'following' => $followingIdSet->has($u->id),
            ])->values();

        // "All" — one stream blending club timeline posts (clubs you joined),
        // plus every member post from your club-mates, the people you follow and
        // yourself, newest first. Minus anyone blocked either way.
        $clubMemberIds = \Illuminate\Support\Facades\DB::table('memberships')
            ->whereIn('tenant_id', $this->clubIds())
            ->pluck('user_id')
            ->push($user->id)
            ->unique()
            ->diff($blockedIds)
            ->values();

        // Audience for the All member stream = club-mates + you + people you follow.
        $allMemberIds = $clubMemberIds->merge($followIds)
            ->unique()
            ->diff($blockedIds)
            ->values();

        $memberFeed = \App\Models\UserPost::whereIn('user_id', $allMemberIds)
            ->visibleTo($user)
            ->with($postWith)->withCount(['likes', 'views'])->latest()->limit(80)->get()
            ->map(fn ($p) => $p->toFeedArray($user) + ['kind' => 'member']);

        $clubFeed = $posts->map(fn ($p) => [
            'kind' => 'club',
            'id' => $p->id,
            'ts' => optional($p->posted_at)->timestamp ?? 0,
            'club' => [
                'name' => $p->tenant->club_name ?? __('personal.club'),
                'logo' => $p->tenant && $p->tenant->logo ? asset('storage/'.$p->tenant->logo) : null,
            ],
            'category' => $p->category ?? __('personal.update'),
            'time' => optional($p->posted_at)->diffForHumans(),
            'body' => $p->body ?? '',
            'image' => $p->image_path ? asset('storage/'.$p->image_path) : null,
            'cover' => $p->cover,
            'likes' => (int) $p->likes_count,
            'comments' => (int) $p->comments_count,
        ]);

        $allPosts = $clubFeed->concat($memberFeed)->sortByDesc('ts')->values();

        // Unseen (red-dot) indicators for the feed sub-tabs. The "All" tab is the
        // default active view, so mark it seen now — its dot clears on open.
        $activity = app(\App\Support\SectionActivity::class);
        $activity->markSeen($user, 'feed:all');
        $feedTabDots = $activity->feedTabDots($user);

        $isMobile = (bool) $request->attributes->get('is_mobile');
        $viewData = compact('user', 'posts', 'personalPosts', 'followingPosts', 'suggestions', 'allPosts', 'feedTabDots');

        return view($isMobile ? 'personal.mobile.home' : 'personal.desktop.home', $viewData);
    }

    /**
     * Assemble the current user's full weekly schedule: the week-day strip, the
     * family roster, and the merged session stream (personal + enrolled + taught
     * + covering, with substitutes overlaid). Shared by the page and the live
     * JSON refresh endpoint so both render identical data.
     */
    private function assembleSchedule(): array
    {
        $now = \Carbon\Carbon::now();
        $weekStart = $now->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);

        $weekDays = collect(range(0, 6))->map(function ($i) use ($weekStart, $now) {
            $d = $weekStart->copy()->addDays($i);

            return [
                'key' => strtolower($d->format('l')),
                'short' => $d->format('D'),
                'd' => $d->format('j'),
                'isToday' => $d->isSameDay($now),
                'isPast' => $d->lt($now->copy()->startOfDay()),
            ];
        });

        // Build the family roster (real) + merge personal (editable, DB), synced
        // (enrolled packages) and teaching (classes the user coaches) streams.
        [$members, $subjectKeys] = $this->scheduleMembers();
        $teaching = $this->teachingSessions();
        // If a class is both taught and enrolled-in by "me", the teaching card wins.
        $taughtKeys = $teaching->map(fn ($c) => $c['token'].'|'.$c['who'])->flip();
        $synced = $this->syncedSessions($subjectKeys)
            ->reject(fn ($c) => isset($taughtKeys[($c['token'] ?? '').'|'.$c['who']]))
            ->values();

        // Apply substitute trainers + cancellations to this week's occurrences.
        $club = $this->applySubstitutions($synced->merge($teaching), $weekStart);

        // Classes the user is covering as a substitute this week show in their schedule too.
        $covering = $this->substitutingSessions($weekStart);

        $sessions = $this->applyCancellations(
            $this->personalSessions($subjectKeys)->merge($club)->merge($covering),
            $weekStart
        );

        // Stamp each session with a status relative to today (weekly recurring).
        $todayKey = strtolower($now->format('l'));
        $order = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        $sessions = $sessions->map(function ($s) use ($order, $todayKey) {
            $cmp = ($order[$s['day']] ?? 0) <=> $order[$todayKey];
            $s['status'] = $cmp < 0 ? 'done' : ($cmp === 0 ? 'today' : 'upcoming');

            return $s;
        })->values();

        return [
            'weekDays' => $weekDays,
            'sessions' => $sessions,
            'members' => $members,
            'todayKey' => $todayKey,
            'todayShort' => $now->format('D'),
        ];
    }

    public function schedule(Request $request): View
    {
        ['weekDays' => $weekDays, 'sessions' => $sessions, 'members' => $members, 'todayKey' => $todayKey, 'todayShort' => $todayShort] = $this->assembleSchedule();

        $subjectsList = collect($members)->values();   // for the create-form subject picker
        $iconChoices = $this->scheduleIconChoices();
        $colorChoices = $this->scheduleColorChoices();

        $isMobile = (bool) $request->attributes->get('is_mobile');
        $viewData = compact(
            'weekDays', 'sessions', 'members', 'todayKey', 'todayShort',
            'subjectsList', 'iconChoices', 'colorChoices'
        );

        return view($isMobile ? 'personal.mobile.schedule' : 'personal.desktop.schedule', $viewData);
    }

    /** Live JSON of the schedule — used to re-render in place on a realtime 'refresh'. */
    public function scheduleData(): JsonResponse
    {
        ['weekDays' => $weekDays, 'sessions' => $sessions, 'members' => $members, 'todayKey' => $todayKey] = $this->assembleSchedule();

        return response()->json([
            'weekDays' => $weekDays,
            'sessions' => $sessions,
            'members' => $members,
            'todayKey' => $todayKey,
        ]);
    }

    /** Personal-session detail with the full workout breakdown (DB-backed). */
    public function scheduleShow(int $session, Request $request): View
    {
        [$members, $subjectKeys] = $this->scheduleMembers();

        $model = UserScheduleSession::where('id', $session)
            ->whereIn('subject_user_id', array_keys($subjectKeys))
            ->firstOrFail();

        $whoKey = $subjectKeys[$model->subject_user_id] ?? 'me';
        $s = $model->toCardArray($whoKey);

        // Status relative to today so the detail's "complete" state matches.
        $order = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        $cmp = ($order[$s['day']] ?? 0) <=> $order[strtolower(\Carbon\Carbon::now()->format('l'))];
        $s['status'] = $cmp < 0 ? 'done' : ($cmp === 0 ? 'today' : 'upcoming');

        $member = $members[$whoKey] ?? ($members['me'] ?? null);
        $subjectsList = collect($members)->values();
        $isOwner = $model->user_id === Auth::id();

        $synced = false;

        // Structured location (map + Google link) for the detail.
        $location = $this->locationView($s['location_type'] ?? null, [
            'lat' => $s['location_lat'] ?? null,
            'lng' => $s['location_lng'] ?? null,
            'address' => $s['location_address'] ?? null,
            'text' => $s['location'] ?? null,
        ]);
        $clubFacilities = collect();   // personal sessions have no facilities / instructors
        $clubInstructors = collect();
        $coachLink = null;             // personal coach is free text — no profile link
        $coachAvatar = null;
        $canEngage = false;            // no club reactions/rating on personal sessions

        $isMobile = (bool) $request->attributes->get('is_mobile');
        $viewData = compact('s', 'member', 'subjectsList', 'isOwner', 'synced', 'location', 'clubFacilities', 'clubInstructors', 'coachLink', 'coachAvatar', 'canEngage');

        return view($isMobile ? 'personal.mobile.schedule-show' : 'personal.desktop.schedule-show', $viewData);
    }

    /**
     * The schedule's family roster: "me" + the owner's dependents that have a
     * linked user account. Returns [membersByKey, subjectUserId => key].
     */
    private function scheduleMembers(): array
    {
        $user = Auth::user();

        $members = [
            'me' => [
                'key' => 'me',
                'user_id' => $user->id,
                'name' => 'You',
                'relation' => 'Me',
                'initials' => $this->initialsFor($user->name ?? 'You'),
                'color' => '#7c3aed',
                'avatar' => $this->avatarUrl($user),
            ],
        ];
        $subjectKeys = [$user->id => 'me'];

        $palette = ['#ec4899', '#0ea5e9', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444'];
        $i = 0;
        foreach ($user->dependents()->with('dependent:id,name,profile_picture,updated_at')->get() as $rel) {
            $dep = $rel->dependent;
            if (! $dep) {
                continue;
            }                       // skip name-only dependents
            if (isset($subjectKeys[$dep->id])) {
                continue;
            }
            $key = 'u'.$dep->id;
            $members[$key] = [
                'key' => $key,
                'user_id' => $dep->id,
                'name' => $dep->name,
                'relation' => ucfirst($rel->relationship_type ?? 'Family'),
                'initials' => $this->initialsFor($dep->name),
                'color' => $palette[$i % count($palette)],
                'avatar' => $this->avatarUrl($dep),
            ];
            $subjectKeys[$dep->id] = $key;
            $i++;
        }

        return [$members, $subjectKeys];
    }

    /** Member-authored personal sessions for me + my dependents. */
    private function personalSessions(array $subjectKeys): \Illuminate\Support\Collection
    {
        return UserScheduleSession::whereIn('subject_user_id', array_keys($subjectKeys))
            ->orderBy('start_time')
            ->get()
            ->map(fn ($m) => $m->toCardArray($subjectKeys[$m->subject_user_id] ?? 'me'))
            ->toBase()   // ensure a base collection even when empty (empty get() keeps the Eloquent type, breaking merge())
            ->values();
    }

    /**
     * Read-only sessions reflected from the member's (and dependents') active
     * club-package enrolments. One card per weekly class slot defined on the
     * package's activities. These mirror the club (editable only by managers).
     */
    private function syncedSessions(array $subjectKeys): \Illuminate\Support\Collection
    {
        $weekdays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        $subs = ClubMemberSubscription::whereIn('user_id', array_keys($subjectKeys))
            ->whereIn('status', ['active', 'pending'])
            ->with([
                'package:id,name,translations',
                'package.packageActivities.activity:id,name',
                'package.packageActivities.instructor.user:id,name',
                'tenant:id,club_name,slug,translations',
            ])
            ->get();

        $out = collect();
        foreach ($subs as $sub) {
            $whoKey = $subjectKeys[$sub->user_id] ?? 'me';
            $club = $sub->tenant?->club_name ?? 'Club';
            $slug = $sub->tenant?->slug;
            foreach ($sub->package?->packageActivities ?? [] as $pa) {
                $sched = is_array($pa->schedule) ? $pa->schedule : (json_decode($pa->schedule ?? '[]', true) ?: []);
                foreach ($sched as $slot) {
                    $day = strtolower($slot['day'] ?? '');
                    if (! in_array($day, $weekdays, true)) {
                        continue;
                    }
                    $out->push($this->syncedCard($pa, $slot, $whoKey, $club, $slug, $sub->package?->name, 'synced'));
                }
            }
        }

        return $out->unique('id')->values();
    }

    /**
     * Classes the logged-in user TEACHES — package activities where they are the
     * assigned instructor (across every club they coach at). Shown alongside the
     * classes they're enrolled in; editable because they own/coach the class.
     */
    private function teachingSessions(): \Illuminate\Support\Collection
    {
        $weekdays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        $instructorIds = \App\Models\ClubInstructor::where('user_id', Auth::id())->pluck('id');
        if ($instructorIds->isEmpty()) {
            return collect();
        }

        $pas = ClubPackageActivity::whereIn('instructor_id', $instructorIds)
            ->with([
                'package:id,name,tenant_id,translations',
                'package.tenant:id,club_name,slug,translations',
                'activity:id,name',
                'instructor.user:id,name',
            ])
            ->get();

        $out = collect();
        foreach ($pas as $pa) {
            $club = $pa->package?->tenant?->club_name ?? 'Club';
            $slug = $pa->package?->tenant?->slug;
            $sched = is_array($pa->schedule) ? $pa->schedule : (json_decode($pa->schedule ?? '[]', true) ?: []);
            foreach ($sched as $slot) {
                $day = strtolower($slot['day'] ?? '');
                if (! in_array($day, $weekdays, true)) {
                    continue;
                }
                $out->push($this->syncedCard($pa, $slot, 'me', $club, $slug, $pa->package?->name, 'teaching'));
            }
        }

        return $out->unique('id')->values();
    }

    /**
     * Classes the user is COVERING as a substitute this week — appears in their
     * own schedule for the dated occurrence(s) they were assigned to.
     */
    private function substitutingSessions(\Carbon\Carbon $weekStart): \Illuminate\Support\Collection
    {
        // Upcoming covers (from today on) — the weekly strip renders them on their
        // weekday, so a cover assigned for next week still shows immediately.
        $rows = \App\Models\ClassSubstitution::where('substitute_user_id', Auth::id())
            ->whereDate('date', '>=', \Carbon\Carbon::now()->toDateString())
            ->with([
                'packageActivity.package:id,name,tenant_id,translations',
                'packageActivity.package.tenant:id,club_name,slug,translations',
                'packageActivity.activity:id,name',
                'packageActivity.instructor.user:id,name',
            ])
            ->get();

        $out = collect();
        foreach ($rows as $r) {
            $pa = $r->packageActivity;
            if (! $pa) {
                continue;
            }
            $sched = is_array($pa->schedule) ? $pa->schedule : (json_decode($pa->schedule ?? '[]', true) ?: []);
            $slot = collect($sched)->first(fn ($s) => strtolower($s['day'] ?? '') === $r->slot_day && (string) ($s['start_time'] ?? '') === (string) $r->slot_start);
            if (! $slot) {
                continue;
            }
            $club = $pa->package?->tenant?->club_name ?? 'Club';
            $out->push($this->syncedCard($pa, $slot, 'me', $club, $pa->package?->tenant?->slug, $pa->package?->name, 'substituting'));
        }

        return $out->unique('id')->values();
    }

    /**
     * Build one club-class card from a package activity slot. The slot may carry
     * optional rich content (intensity/focus/notes/workout/icon/color/title/coach)
     * authored by the coach or club manager — falling back to club defaults.
     */
    private function syncedCard($pa, array $slot, string $whoKey, ?string $club, ?string $slug, ?string $packageName, string $source = 'synced'): array
    {
        $day = strtolower($slot['day'] ?? '');
        $club = $club ?: 'Club';
        $token = $this->syncedToken($pa->id, $day, $slot['start_time'] ?? '');

        $workout = $slot['workout'] ?? null;

        return [
            'id' => $source.'-'.$token.'-'.$whoKey,
            'token' => $token,
            'pa_id' => $pa->id,
            'source' => $source,                       // 'synced' (enrolled) | 'teaching' (coach)
            'editable' => false,
            'club' => $club,
            'club_slug' => $slug,
            'detail_url' => route('me.schedule.synced', ['token' => $token]),
            'who' => $whoKey,
            'day' => $day,
            'start' => $this->fmtTime($slot['start_time'] ?? null),
            'end' => $this->fmtTime($slot['end_time'] ?? null),
            'start_raw' => $slot['start_time'] ?? null,
            'end_raw' => $slot['end_time'] ?? null,
            'duration' => $this->slotDuration($slot['start_time'] ?? null, $slot['end_time'] ?? null),
            'title' => $slot['title'] ?? ($pa->activity->name ?? ($packageName ?? 'Class')),
            'discipline' => $slot['discipline'] ?? ($packageName ?? $club),
            'icon' => $slot['icon'] ?? match ($source) {
                'teaching' => 'bi-person-video3',
                'substituting' => 'bi-person-check-fill',
                default => 'bi-calendar-check',
            },
            'color' => $slot['color'] ?? match ($source) {
                'teaching' => '#f59e0b',
                'substituting' => '#10b981',
                default => '#0ea5e9',
            },
            'coach' => $slot['coach'] ?? ($pa->instructor?->user?->name ?? null),
            'location' => $this->slotLocationLabel($slot),
            'location_type' => $slot['location_type'] ?? (! empty($slot['facility_id']) ? 'facility' : (isset($slot['location_lat']) ? 'map' : 'text')),
            'facility_id' => $slot['facility_id'] ?? null,
            'location_lat' => $slot['location_lat'] ?? null,
            'location_lng' => $slot['location_lng'] ?? null,
            'location_address' => $slot['location_address'] ?? null,
            'location_text' => $slot['location_text'] ?? null,
            'intensity' => $slot['intensity'] ?? null,
            'focus' => $slot['focus'] ?? [],
            'notes' => $slot['notes'] ?? null,
            'workout' => [
                'warmup' => $workout['warmup'] ?? [],
                'main' => $workout['main'] ?? [],
                'cooldown' => $workout['cooldown'] ?? [],
            ],
        ];
    }

    /** URL-safe token identifying a club class slot: paId|day|startTime. */
    private function syncedToken(int $paId, string $day, string $start): string
    {
        return \App\Support\SyncedClassToken::encode($paId, $day, $start);
    }

    /** Short display label for a club slot's location (facility name / address / text). */
    private function slotLocationLabel(array $slot): ?string
    {
        $type = $slot['location_type'] ?? (! empty($slot['facility_id']) ? 'facility' : (isset($slot['location_lat']) ? 'map' : 'text'));

        return match ($type) {
            'facility' => $slot['facility_name'] ?? null,
            'map' => $slot['location_address'] ?? 'Pinned location',
            default => $slot['location_text'] ?? ($slot['facility_name'] ?? null),
        };
    }

    private function gmapUrl(?float $lat, ?float $lng): ?string
    {
        return ($lat !== null && $lng !== null) ? "https://www.google.com/maps?q={$lat},{$lng}" : null;
    }

    private function gsearchUrl(?string $q): ?string
    {
        return $q ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($q) : null;
    }

    /** Only allow http(s) URLs through (blocks javascript:/data: etc. from club-entered fields). */
    private function safeUrl(?string $url): ?string
    {
        return ($url && preg_match('#^https?://#i', trim($url))) ? trim($url) : null;
    }

    /**
     * Build the structured location for the detail page: a label, optional
     * coordinates for an embedded map, and a Google Maps link to navigate.
     * Pass a club tenant id to resolve a facility's saved location.
     */
    private function locationView(?string $type, array $opts = []): ?array
    {
        $type = $type ?: 'text';

        if ($type === 'facility' && ! empty($opts['facility_id'])) {
            $f = \App\Models\ClubFacility::find($opts['facility_id']);
            if ($f) {
                $lat = $f->gps_lat !== null ? (float) $f->gps_lat : null;
                $lng = $f->gps_long !== null ? (float) $f->gps_long : null;

                return [
                    'type' => 'facility',
                    'label' => $f->name,
                    'address' => $f->address,
                    'lat' => $lat,
                    'lng' => $lng,
                    'maps_url' => $this->safeUrl($f->maps_url) ?: ($this->gmapUrl($lat, $lng) ?: $this->gsearchUrl($f->name)),
                ];
            }

            // facility gone — fall back to cached name
            return ['type' => 'facility', 'label' => $opts['facility_name'] ?? 'Facility', 'address' => null, 'lat' => null, 'lng' => null, 'maps_url' => $this->gsearchUrl($opts['facility_name'] ?? null)];
        }

        if ($type === 'map') {
            $lat = isset($opts['lat']) && $opts['lat'] !== null && $opts['lat'] !== '' ? (float) $opts['lat'] : null;
            $lng = isset($opts['lng']) && $opts['lng'] !== null && $opts['lng'] !== '' ? (float) $opts['lng'] : null;

            return [
                'type' => 'map',
                'label' => $opts['address'] ?: 'Pinned location',
                'address' => $opts['address'] ?? null,
                'lat' => $lat,
                'lng' => $lng,
                'maps_url' => $this->gmapUrl($lat, $lng) ?: $this->gsearchUrl($opts['address'] ?? null),
            ];
        }

        $text = $opts['text'] ?? null;
        if (! $text) {
            return null;
        }

        return [
            'type' => 'text',
            'label' => $text,
            'address' => $text,
            'lat' => null,
            'lng' => null,
            'maps_url' => $this->gsearchUrl($text),
        ];
    }

    private array $dayOrder = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];

    /** Parse "HH:MM" / "6:30 AM" → minutes since midnight (null if unknown). */
    private function toMinutes(?string $t): ?int
    {
        if (! $t) {
            return null;
        }
        try {
            $c = \Carbon\Carbon::parse($t);

            return $c->hour * 60 + $c->minute;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Schedule slots of a package activity falling on a given weekday. */
    private function slotsForDay($pa, string $day): array
    {
        $sched = is_array($pa->schedule) ? $pa->schedule : (json_decode($pa->schedule ?? '[]', true) ?: []);

        return array_values(array_filter($sched, fn ($s) => strtolower($s['day'] ?? '') === $day));
    }

    /**
     * Is the candidate trainer already busy at this weekday/time on $date?
     * Checks their personal sessions, classes they coach, classes they're
     * enrolled in, and other classes they're already covering. Returns a short
     * human reason, or null when free. The class being assigned ($excludePaId)
     * is ignored.
     */
    private function trainerBusyConflict(int $userId, string $day, ?string $start, ?string $end, string $date, int $excludePaId): ?string
    {
        $ts = $this->toMinutes($start);
        if ($ts === null) {
            return null;
        }                 // no start time → can't judge
        $te = $this->toMinutes($end) ?? ($ts + 60);

        $overlaps = function ($s, $e) use ($ts, $te) {
            $bs = $this->toMinutes($s);
            if ($bs === null) {
                return false;
            }
            $be = $this->toMinutes($e) ?? ($bs + 60);

            return $bs < $te && $ts < $be;             // half-open interval overlap
        };

        // 1) Their own personal sessions on that weekday.
        foreach (UserScheduleSession::where('subject_user_id', $userId)->where('day', $day)->get(['start_time', 'end_time']) as $p) {
            if ($overlaps($p->start_time, $p->end_time)) {
                return 'a personal session';
            }
        }

        // 2) Classes they coach.
        $instrIds = \App\Models\ClubInstructor::where('user_id', $userId)->pluck('id');
        if ($instrIds->isNotEmpty()) {
            foreach (ClubPackageActivity::whereIn('instructor_id', $instrIds)->where('id', '!=', $excludePaId)->get() as $pa) {
                foreach ($this->slotsForDay($pa, $day) as $sl) {
                    if ($overlaps($sl['start_time'] ?? null, $sl['end_time'] ?? null)) {
                        return 'another class they coach';
                    }
                }
            }
        }

        // 3) Classes they're enrolled in.
        $subs = ClubMemberSubscription::where('user_id', $userId)
            ->whereIn('status', ['active', 'pending'])
            ->with('package.packageActivities')->get();
        foreach ($subs as $sub) {
            foreach ($sub->package?->packageActivities ?? [] as $pa) {
                if ((int) $pa->id === $excludePaId) {
                    continue;
                }
                foreach ($this->slotsForDay($pa, $day) as $sl) {
                    if ($overlaps($sl['start_time'] ?? null, $sl['end_time'] ?? null)) {
                        return 'a class they’re enrolled in';
                    }
                }
            }
        }

        // 4) Other classes they're already covering that same date.
        $others = \App\Models\ClassSubstitution::where('substitute_user_id', $userId)
            ->whereDate('date', $date)
            ->where('package_activity_id', '!=', $excludePaId)
            ->get();
        foreach ($others as $os) {
            if ($os->slot_day !== $day) {
                continue;
            }
            if ($overlaps($os->slot_start, null)) {
                return 'another class they’re covering';
            }
        }

        return null;
    }

    /** Past occurrence dates (Y-m-d) of a weekly slot between two dates (inclusive), most-recent first. Capped. */
    private function pastOccurrences(string $day, \Carbon\Carbon $from, \Carbon\Carbon $to): array
    {
        $target = $this->dayOrder[$day] ?? 0;
        $cursor = $from->copy()->startOfDay();
        $guard = 0;
        while ($cursor->dayOfWeek !== $target && $guard < 7) {
            $cursor->addDay();
            $guard++;
        }
        $dates = [];
        while ($cursor->lte($to) && count($dates) < 200) {
            $dates[] = $cursor->toDateString();
            $cursor->addDays(7);
        }

        return array_reverse($dates);   // recent first
    }

    /** The next calendar date (>= today) whose weekday matches $day. */
    private function nextOccurrence(string $day): \Carbon\Carbon
    {
        $target = $this->dayOrder[$day] ?? 0;
        $today = \Carbon\Carbon::now()->startOfDay();
        $diff = (($target - $today->dayOfWeek) + 7) % 7;   // 0 = today

        return $today->copy()->addDays($diff);
    }

    /**
     * Overlay substitute trainers onto club cards for this week's occurrences:
     * a covered card shows the substitute as coach + a substitute badge.
     */
    private function applySubstitutions(\Illuminate\Support\Collection $cards, \Carbon\Carbon $weekStart): \Illuminate\Support\Collection
    {
        $paIds = $cards->pluck('pa_id')->filter()->unique()->values();
        if ($paIds->isEmpty()) {
            return $cards->values();
        }

        $rows = \App\Models\ClassSubstitution::whereIn('package_activity_id', $paIds)
            ->whereDate('date', '>=', $weekStart->toDateString())
            ->whereDate('date', '<=', $weekStart->copy()->addDays(6)->toDateString())
            ->with('substitute:id,name,full_name')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $key = $r->package_activity_id.'|'.$r->slot_day.'|'.(string) $r->slot_start.'|'.$r->date->toDateString();
            $map[$key] = $r;
        }

        return $cards->map(function ($c) use ($map, $weekStart) {
            $occ = $weekStart->copy()->addDays($this->dayOrder[$c['day']] ?? 0)->toDateString();
            $key = ($c['pa_id'] ?? '').'|'.$c['day'].'|'.(string) ($c['start_raw'] ?? '').'|'.$occ;
            if (isset($map[$key])) {
                $sub = $map[$key]->substitute;
                $name = $sub ? ($sub->full_name ?: $sub->name) : 'Substitute';
                $c['coach'] = $name;
                $c['substitute'] = ['name' => $name, 'date' => $occ];
                $c['is_substituted'] = true;
            }

            return $c;
        })->values();
    }

    /** Flag club cards whose this-week occurrence has been cancelled. */
    private function applyCancellations(\Illuminate\Support\Collection $cards, \Carbon\Carbon $weekStart): \Illuminate\Support\Collection
    {
        $paIds = $cards->pluck('pa_id')->filter()->unique()->values();
        if ($paIds->isEmpty()) {
            return $cards->values();
        }

        $rows = \App\Models\ClassCancellation::whereIn('package_activity_id', $paIds)
            ->whereDate('date', '>=', $weekStart->toDateString())
            ->whereDate('date', '<=', $weekStart->copy()->addDays(6)->toDateString())
            ->get();

        $set = [];
        foreach ($rows as $r) {
            $set[$r->package_activity_id.'|'.$r->slot_day.'|'.(string) $r->slot_start.'|'.$r->date->toDateString()] = true;
        }

        return $cards->map(function ($c) use ($set, $weekStart) {
            $occ = $weekStart->copy()->addDays($this->dayOrder[$c['day']] ?? 0)->toDateString();
            $key = ($c['pa_id'] ?? '').'|'.$c['day'].'|'.(string) ($c['start_raw'] ?? '').'|'.$occ;
            if (isset($set[$key])) {
                $c['is_cancelled'] = true;
            }

            return $c;
        })->values();
    }

    /** Load a club class (package activity) from a token, with the relations needed for auth/display. */
    private function classFromToken(string $token): ?ClubPackageActivity
    {
        [$paId] = $this->decodeSyncedToken($token);
        if ($paId === null) {
            return null;
        }

        return ClubPackageActivity::with(['package.tenant', 'instructor.user', 'activity:id,name'])->find($paId);
    }

    /** May the current user edit / manage this class (assigned coach OR club manager)? */
    private function canEditClass(?ClubPackageActivity $pa): bool
    {
        if (! $pa) {
            return false;
        }
        $tenant = $pa->package?->tenant;
        $manages = $tenant && $this->canManageClub($tenant);
        $teaches = $pa->instructor && $pa->instructor->user_id === Auth::id();

        return $manages || $teaches;
    }

    /**
     * Notify everyone affected by a class change — the members enrolled in its
     * package, the regular coach, and any substitute(s) — and deep-link them to
     * the class detail. Best-effort; notifyUser() also pushes over MQTT and skips
     * the actor (whoever made the change).
     */
    private function notifyClassRecipients(ClubPackageActivity $pa, string $title, ?string $body, string $token, array $extraUserIds = []): void
    {
        $memberIds = ClubMemberSubscription::where('package_id', $pa->package_id)
            ->whereIn('status', ['active', 'pending'])
            ->distinct()->pluck('user_id');

        $recipients = collect($memberIds)
            ->push($pa->instructor?->user_id)        // the regular coach
            ->merge($extraUserIds)                    // substitute(s)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $actionUrl = route('me.schedule.synced', ['token' => $token]);
        $tenantId = $pa->package?->tenant_id;

        foreach ($recipients as $uid) {
            UserNotification::notifyUser($uid, 'class_update', $title, [
                'actor_id' => Auth::id(),
                'tenant_id' => $tenantId,
                'action_url' => $actionUrl,
                'icon' => 'bi-calendar-week',
                'body' => $body,
                'subject_type' => 'class',
                'subject_id' => $pa->id,
            ]);
        }
    }

    /** User ids of substitutes covering this slot from today onward (notify on edits). */
    private function slotSubstituteUserIds(int $paId, string $day, string $start): array
    {
        return \App\Models\ClassSubstitution::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->whereDate('date', '>=', \Carbon\Carbon::now()->toDateString())
            ->pluck('substitute_user_id')->all();
    }

    /**
     * Search platform users (inside OR outside the club) to pick a substitute —
     * by id, name, email or phone. Gated to those who can edit the class.
     */
    public function substituteSearch(Request $request, string $token): JsonResponse
    {
        $pa = $this->classFromToken($token);
        if (! $this->canEditClass($pa)) {
            return response()->json(['results' => []], 403);
        }

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json(['results' => []]);
        }

        $users = \App\Models\User::query()
            ->whereKeyNot(Auth::id())
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('full_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%");
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                }
            })
            ->limit(15)
            ->get(['id', 'name', 'full_name', 'profile_picture', 'updated_at']);

        // Flag who's already busy at this class's time on the chosen date so the
        // picker can warn / disable them.
        [$paId, $day, $start] = $this->decodeSyncedToken($token);
        $slot = collect($this->slotsForDay($pa, $day))->first(fn ($s) => (string) ($s['start_time'] ?? '') === (string) $start);
        $end = $slot['end_time'] ?? null;
        $date = (string) $request->query('date', $this->nextOccurrence($day)->toDateString());

        // Each candidate's trainer rating (avg of their InstructorReviews across clubs).
        $ratings = \Illuminate\Support\Facades\DB::table('instructor_reviews as ir')
            ->join('club_instructors as ci', 'ci.id', '=', 'ir.instructor_id')
            ->whereIn('ci.user_id', $users->pluck('id'))
            ->groupBy('ci.user_id')
            ->selectRaw('ci.user_id, AVG(ir.rating) as avg_rating, COUNT(*) as cnt')
            ->get()->keyBy('user_id');

        return response()->json([
            'results' => $users->map(function ($u) use ($day, $start, $end, $date, $paId, $ratings) {
                $busy = $this->trainerBusyConflict($u->id, $day, $start, $end, $date, (int) $paId);
                $r = $ratings->get($u->id);

                return [
                    'id' => $u->id,
                    'name' => $u->full_name ?: $u->name,
                    'avatar' => $this->avatarUrl($u),
                    'initials' => $this->initialsFor($u->full_name ?: ($u->name ?: 'U')),
                    'rating' => $r ? round((float) $r->avg_rating, 1) : null,
                    'rating_count' => $r ? (int) $r->cnt : 0,
                    'busy' => $busy !== null,
                    'busy_reason' => $busy,
                ];
            })->values(),
        ]);
    }

    /** Assign (or replace) a substitute trainer for one dated occurrence of the class. */
    public function substituteAssign(Request $request, string $token): JsonResponse
    {
        $pa = $this->classFromToken($token);
        if (! $this->canEditClass($pa)) {
            return response()->json(['success' => false, 'message' => 'You can’t manage this class.'], 403);
        }
        [$paId, $day, $start] = $this->decodeSyncedToken($token);

        $data = $request->validate([
            'substitute_user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        // The slot's time window — used for the busy/conflict check.
        $slot = collect($this->slotsForDay($pa, $day))->first(fn ($s) => (string) ($s['start_time'] ?? '') === (string) $start) ?: [];
        $end = $slot['end_time'] ?? null;

        $sub = \App\Models\User::find($data['substitute_user_id']);

        // Don't double-book: the substitute must be free at that time on that date.
        $conflict = $this->trainerBusyConflict($sub->id, $day, $start, $end, $data['date'], (int) $paId);
        if ($conflict !== null) {
            $who = $sub->full_name ?: $sub->name;

            return response()->json([
                'success' => false,
                'message' => $who.' is already busy at that time ('.$conflict.'). Pick someone else or another date.',
            ], 422);
        }

        \App\Models\ClassSubstitution::updateOrCreate(
            ['package_activity_id' => $paId, 'slot_day' => $day, 'slot_start' => $start, 'date' => $data['date']],
            [
                'original_user_id' => $pa->instructor?->user_id,
                'substitute_user_id' => $sub->id,
                'assigned_by' => Auth::id(),
                'note' => $data['note'] ?? null,
            ]
        );

        $name = $sub->full_name ?: $sub->name;
        $whenLabel = \Carbon\Carbon::parse($data['date'])->format('D, M j');
        $className = ($pa->activity->name ?? 'your class');

        // Notify enrolled members, the regular coach, and the substitute.
        $this->notifyClassRecipients(
            $pa,
            'Substitute trainer for '.$className,
            $name.' will cover the '.$whenLabel.' class.',
            $token,
            [$sub->id],
        );

        // Live-refresh everyone's open schedule — the substitute (class appears),
        // enrolled members + coach (substitute shown), and the actor.
        $this->pushScheduleRefresh($this->classAudience($pa, [$sub->id, Auth::id()]));

        return response()->json([
            'success' => true,
            'message' => $name.' will cover this class on '.$whenLabel.'.',
            'substitute' => ['name' => $name, 'date' => $data['date']],
            'redirect' => route('me.schedule'),
        ]);
    }

    /** Remove a substitute for a given dated occurrence. */
    public function substituteRemove(Request $request, string $token): JsonResponse
    {
        $pa = $this->classFromToken($token);
        if (! $this->canEditClass($pa)) {
            return response()->json(['success' => false, 'message' => 'You can’t manage this class.'], 403);
        }
        [$paId, $day, $start] = $this->decodeSyncedToken($token);

        $data = $request->validate(['date' => ['required', 'date']]);

        // Capture who was covering (to notify them their cover was cancelled).
        $removedIds = \App\Models\ClassSubstitution::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->whereDate('date', $data['date'])
            ->pluck('substitute_user_id')->all();

        \App\Models\ClassSubstitution::where('package_activity_id', $paId)
            ->where('slot_day', $day)
            ->where('slot_start', (string) $start)
            ->whereDate('date', $data['date'])
            ->delete();

        $whenLabel = \Carbon\Carbon::parse($data['date'])->format('D, M j');
        $className = ($pa->activity->name ?? 'your class');

        // Tell enrolled members + the regular coach it reverts (NOT the dropped sub).
        $this->notifyClassRecipients(
            $pa,
            'Substitute cancelled for '.$className,
            'The '.$whenLabel.' class will run with its regular trainer.',
            $token,
        );

        // Clear the dropped substitutes' class notifications from their bell.
        foreach (array_unique($removedIds) as $uid) {
            $this->removeUserClassNotifications((int) $uid, (int) $paId);
        }

        // Live-refresh everyone's open schedule (members + coach + dropped subs + actor):
        // the cover disappears from the sub's schedule and reverts on members'/coach's.
        $this->pushScheduleRefresh($this->classAudience($pa, array_merge($removedIds, [Auth::id()])));

        return response()->json([
            'success' => true,
            'message' => 'Substitute removed.',
            'redirect' => route('me.schedule'),
        ]);
    }

    /** Delete one user's notifications for a class (subject) + push a live bell removal. */
    private function removeUserClassNotifications(int $userId, int $paId): void
    {
        $ids = UserNotification::where('user_id', $userId)
            ->where('subject_type', 'class')->where('subject_id', $paId)
            ->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        UserNotification::whereIn('id', $ids)->delete();

        try {
            if (function_exists('Realtime') && Realtime()->enabled()) {
                Realtime()->publishToUser($userId, 'notifications', [
                    'action' => 'remove',
                    'ids' => $ids->map(fn ($i) => (int) $i)->all(),
                ]);
            }
        } catch (\Throwable) {
            // best-effort
        }
    }

    /** Read-only detail for a club-class slot — viewable by enrolled members, the coach, or club managers. */
    public function scheduleSyncedShow(Request $request, string $token): View
    {
        [$paId, $day, $start] = $this->decodeSyncedToken($token);
        abort_if($paId === null, 404);

        $pa = ClubPackageActivity::with([
            'package:id,name,tenant_id,translations,session_count,duration_months',
            // owner_user_id + business_id are REQUIRED by canManageClub() — never trim them.
            'package.tenant:id,club_name,slug,translations,owner_user_id,business_id',
            'package.packageActivities:id,package_id,schedule',     // all the package's weekly slots
            'activity:id,name',
            'instructor.user:id,name,full_name,profile_picture,updated_at',
        ])->find($paId);
        abort_unless($pa, 404);

        $tenant = $pa->package?->tenant;
        [$members, $subjectKeys] = $this->scheduleMembers();

        // Who may see this class? An enrolled member/dependent, the assigned coach,
        // a club manager, or someone assigned as a substitute for it.
        $manages = $tenant && $this->canManageClub($tenant);
        $teaches = $pa->instructor && $pa->instructor->user_id === Auth::id();
        $enrolled = ClubMemberSubscription::whereIn('user_id', array_keys($subjectKeys))
            ->where('package_id', $pa->package_id)
            ->whereIn('status', ['active', 'pending'])
            ->exists();
        $isSubstitute = \App\Models\ClassSubstitution::where('package_activity_id', $paId)
            ->where('substitute_user_id', Auth::id())
            ->whereDate('date', '>=', \Carbon\Carbon::now()->toDateString())
            ->exists();
        abort_unless($manages || $teaches || $enrolled || $isSubstitute, 404);

        $sched = is_array($pa->schedule) ? $pa->schedule : (json_decode($pa->schedule ?? '[]', true) ?: []);
        $slot = collect($sched)->first(function ($s) use ($day, $start) {
            return strtolower($s['day'] ?? '') === $day && (string) ($s['start_time'] ?? '') === $start;
        });
        abort_unless($slot, 404);

        $source = $teaches ? 'teaching' : ($isSubstitute && ! $enrolled ? 'substituting' : 'synced');
        $club = $tenant?->club_name ?? 'Club';
        $s = $this->syncedCard($pa, $slot, 'me', $club, $tenant?->slug, $pa->package?->name, $source);
        $s = $this->stampStatus($s);
        $member = $members['me'] ?? null;

        // The coach of the class and club owners/admins may edit the schedule slot.
        $canEditClub = $manages || $teaches;
        $editSlot = [
            'day' => $day,
            'start_time' => $slot['start_time'] ?? '',
            'end_time' => $slot['end_time'] ?? '',
            'facility_name' => $slot['facility_name'] ?? '',
        ];
        $updateUrl = route('me.schedule.synced.update', ['token' => $token]);

        // Substitute trainer for the next occurrence of this class (>= today).
        $occurrence = $this->nextOccurrence($day);
        $occDate = $occurrence->toDateString();
        // If the caller tapped a specific dated card (?on=Y-m-d), show THAT occurrence
        // instead of defaulting to the next one — as long as it matches the class weekday.
        $on = (string) $request->query('on', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $on)) {
            try {
                $d = \Carbon\Carbon::createFromFormat('Y-m-d', $on)->startOfDay();
                if ($d && strtolower($d->format('l')) === $day) {
                    $occurrence = $d;
                    $occDate = $d->toDateString();
                }
            } catch (\Throwable $e) { /* bad date → keep the next occurrence */
            }
        }

        // A one-time program variation for THIS exact date overrides the recurring
        // intensity/focus/notes/workout for display, without touching the base slot.
        $programOverride = \App\Models\ClassProgramOverride::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->whereDate('date', $occDate)
            ->with('setBy:id,name,full_name')
            ->first();
        if ($programOverride) {
            $s['intensity'] = $programOverride->intensity;
            $s['focus'] = $programOverride->focus ?? [];
            $s['notes'] = $programOverride->notes;
            $s['workout'] = array_merge(['warmup' => [], 'main' => [], 'cooldown' => []], $programOverride->workout ?? []);
        }
        $programOverridden = (bool) $programOverride;
        $programOverrideBy = $programOverride?->setBy
            ? ($programOverride->setBy->full_name ?: $programOverride->setBy->name)
            : null;

        // Attendance is markable only DURING the class — not before it starts, not after
        // it ends. Interpret the wall-clock start/end in the CLUB's timezone (server runs
        // UTC) so the window is correct in real time.
        $clubTz = $tenant?->timezone ?: config('app.timezone');
        $classEnded = ! empty($slot['end_time'])
            && \Carbon\Carbon::parse($occDate.' '.$slot['end_time'], $clubTz)->isPast();
        $classStarted = empty($slot['start_time'])
            ? true   // unknown start → don't block marking
            : \Carbon\Carbon::parse($occDate.' '.$slot['start_time'], $clubTz)->isPast();
        $subRow = \App\Models\ClassSubstitution::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->whereDate('date', $occDate)
            ->with('substitute:id,name,full_name,profile_picture,updated_at')->first();
        $substitute = $subRow ? [
            'name' => $subRow->substitute ? ($subRow->substitute->full_name ?: $subRow->substitute->name) : 'Substitute',
            'date' => $occDate,
        ] : null;
        if ($substitute) {
            $s['coach'] = $substitute['name'];   // reflect the cover on the detail
        }

        // Coach tile link + photo: substitute → their public profile; regular coach
        // → the instructor page. Resolve the displayed coach name to a real user.
        $coachLink = null;
        $coachAvatar = null;
        if ($substitute && $subRow->substitute) {
            $coachLink = ['url' => route('trainer.show.public', $subRow->substitute->id), 'type' => 'profile'];
            $coachAvatar = $this->avatarUrl($subRow->substitute);
        } elseif (! empty($s['coach'])) {
            $coachUser = null;
            $iu = $pa->instructor?->user;
            if ($iu && ($iu->full_name ?: $iu->name) === $s['coach']) {
                $coachUser = $iu;
            } else {
                $ci = \App\Models\ClubInstructor::where('tenant_id', $tenant?->id)
                    ->with('user:id,name,full_name,profile_picture,updated_at')->get()
                    ->first(fn ($i) => $i->user && ($i->user->full_name ?: $i->user->name) === $s['coach']);
                $coachUser = $ci?->user;
            }
            if ($coachUser) {
                $coachLink = ['url' => route('trainer.show', $coachUser->id), 'type' => 'instructor'];
                $coachAvatar = $this->avatarUrl($coachUser);
            }
        }

        $substitute_search_url = route('me.schedule.substitute.search', ['token' => $token]);
        $substitute_assign_url = route('me.schedule.substitute.assign', ['token' => $token]);
        $substitute_remove_url = route('me.schedule.substitute.remove', ['token' => $token]);

        // Everyone involved with this class — the trainer (or the assigned
        // substitute taking their place) plus the enrolled members with a CURRENT
        // subscription. Visible to every viewer (members see classmates read-only);
        // attendance checkboxes stay staff-only (canMarkAttendance).
        $roster = [];
        if (true) {
            // Who's already marked present for the shown occurrence.
            $attendedIds = \App\Models\ClassAttendance::where('package_activity_id', $pa->id)
                ->where('slot_day', $day)->where('slot_start', (string) $start)
                ->whereDate('date', $occDate)
                ->pluck('user_id')->map(fn ($i) => (int) $i)->all();

            $entry = fn ($u, string $role, ?string $note = null) => [
                'id' => $u->id,
                'name' => $u->full_name ?: $u->name,
                'initials' => $this->initialsFor($u->full_name ?: ($u->name ?: 'U')),
                'avatar' => $this->avatarUrl($u),
                'role' => $role,                    // trainer | member
                'note' => $note,
                'attended' => in_array((int) $u->id, $attendedIds, true),
            ];

            $rows = collect();

            // Trainer slot: when a substitute covers the shown occurrence, THEY are
            // the trainer for it; otherwise the regular instructor.
            if ($subRow && $subRow->substitute) {
                $rows->push($entry($subRow->substitute, 'trainer', 'Substitute · '.$occurrence->format('D, M j')));
            } elseif ($pa->instructor?->user) {
                $rows->push($entry($pa->instructor->user, 'trainer'));
            }

            // Members with an active/pending subscription to this package. Whether each
            // one belongs on the SHOWN occurrence is decided per-date below (an enrolment
            // must cover that date) so expired enrolments drop off until a re-enrolment.
            $memberSubs = ClubMemberSubscription::where('package_id', $pa->package_id)
                ->whereIn('status', ['active', 'pending'])
                ->with('user:id,name,full_name,profile_picture,updated_at')
                ->get();
            // Effective end of a subscription's term: explicit end_date, else
            // start_date + the package's duration; null = genuinely open-ended.
            $pkgMonths = max(0, (int) ($pa->package?->duration_months ?? 0));
            $effEnd = function ($sub) use ($pkgMonths) {
                if ($sub->end_date) {
                    return \Carbon\Carbon::parse($sub->end_date)->toDateString();
                }
                if ($pkgMonths > 0 && $sub->start_date) {
                    return \Carbon\Carbon::parse($sub->start_date)->addMonths($pkgMonths)->toDateString();
                }

                return null;
            };
            $subsByUser = $memberSubs->filter(fn ($s) => $s->user)->groupBy(fn ($s) => $s->user->id);

            // ---- Package-wide attendance: ALL the package's weekly class days,
            // counted from enrolment, capped at the package's total classes. ----
            $now2 = \Carbon\Carbon::now();
            $weekdaysL = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $pkgPas = $pa->package?->packageActivities ?? collect();
            $packagePaIds = $pkgPas->pluck('id')->all();
            $weeklySlots = [];   // every weekly class day across the package
            foreach ($pkgPas as $ppa) {
                $sch = is_array($ppa->schedule) ? $ppa->schedule : (json_decode($ppa->schedule ?? '[]', true) ?: []);
                foreach ($sch as $sl) {
                    $dd = strtolower($sl['day'] ?? '');
                    if (in_array($dd, $weekdaysL, true)) {
                        $weeklySlots[] = ['pa_id' => $ppa->id, 'day' => $dd, 'start' => (string) ($sl['start_time'] ?? '')];
                    }
                }
            }
            // Total classes the enrolment includes: the package's session_count, or
            // (class-days/week × 4 weeks × duration_months) — e.g. 3/week × 1 month = 12.
            $totalCap = (int) ($pa->package?->session_count ?? 0);
            if ($totalCap <= 0) {
                $months = max(1, (int) ($pa->package?->duration_months ?? 1));
                $totalCap = count($weeklySlots) * 4 * $months;
            }

            // Cancellations + attendance across the WHOLE package, keyed pa|day|start|date.
            $cancelSet = [];
            foreach (\App\Models\ClassCancellation::whereIn('package_activity_id', $packagePaIds)->get() as $c) {
                $cancelSet[$c->package_activity_id.'|'.$c->slot_day.'|'.(string) $c->slot_start.'|'.$c->date->toDateString()] = true;
            }
            $attSet = [];
            foreach (\App\Models\ClassAttendance::whereIn('package_activity_id', $packagePaIds)->get() as $a) {
                $attSet[$a->user_id][$a->package_activity_id.'|'.$a->slot_day.'|'.(string) $a->slot_start.'|'.$a->date->toDateString()] = true;
            }
            // The occurrence key for THIS class's shown session (for live updates on mark).
            $curOccKey = $pa->id.'|'.$day.'|'.(string) $start.'|'.$occDate;

            $memberUsers = $memberSubs->map(fn ($sub) => $sub->user)->filter()
                ->unique('id')->sortBy(fn ($u) => $u->full_name ?: $u->name)->values();

            foreach ($memberUsers as $u) {
                // The member belongs on THIS occurrence only if one of their enrolments
                // covers its date: start_date <= occDate <= effective end. This drops
                // not-yet-enrolled members (joined later) AND expired ones (term ended)
                // until they re-enrol, while keeping them on occurrences they were valid for.
                $covering = ($subsByUser[$u->id] ?? collect())->first(function ($sub) use ($occDate, $effEnd) {
                    $start = $sub->start_date ? \Carbon\Carbon::parse($sub->start_date)->toDateString() : null;
                    if ($start && $start > $occDate) {
                        return false;
                    }     // not enrolled yet on that date
                    $end = $effEnd($sub);
                    if ($end && $end < $occDate) {
                        return false;
                    }         // enrolment had already ended

                    return true;
                });
                if (! $covering) {
                    continue;
                }

                $e = $entry($u, 'member');
                $from = $covering->start_date ? \Carbon\Carbon::parse($covering->start_date) : $now2->copy()->subWeeks(12);

                // Project every package class occurrence from enrolment to today.
                $occ = [];
                foreach ($weeklySlots as $sl) {
                    foreach ($this->pastOccurrences($sl['day'], $from, $now2) as $d) {
                        $key = $sl['pa_id'].'|'.$sl['day'].'|'.$sl['start'].'|'.$d;
                        if (isset($cancelSet[$key])) {
                            continue;
                        }           // cancelled classes don't count
                        $occ[] = ['date' => $d, 'start' => $sl['start'], 'key' => $key];
                    }
                }
                // Chronological (1st class … nth class), then cap at the enrolment's total.
                usort($occ, fn ($x, $y) => ($x['date'].$x['start']) <=> ($y['date'].$y['start']));
                if ($totalCap > 0) {
                    $occ = array_slice($occ, 0, $totalCap);
                }

                $att = $attSet[$u->id] ?? [];
                $e['attended_count'] = count(array_filter($occ, fn ($o) => isset($att[$o['key']])));
                $e['total_count'] = count($occ);
                $e['breakdown'] = array_map(fn ($o) => [
                    'date' => $o['date'],
                    'key' => $o['key'],
                    'label' => \Carbon\Carbon::parse($o['date'])->format('D, M j'),
                    'attended' => isset($att[$o['key']]),
                ], array_reverse($occ));      // recent-first for display
                $rows->push($e);
            }

            // Dedupe by user id, keeping the higher role (trainer > member).
            $roster = $rows->unique('id')->values()->all();
        }

        // Is the shown occurrence cancelled?
        $cancelRow = \App\Models\ClassCancellation::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->whereDate('date', $occDate)->first();
        $cancelled = (bool) $cancelRow;
        $cancelReason = $cancelRow?->reason;
        $cancelCreditable = $cancelRow?->creditable ?? false;

        // Make-up credits the VIEWER is owed for this class (member-facing).
        $myCredits = \App\Models\ClassMakeupCredit::where('package_activity_id', $paId)
            ->where('user_id', Auth::id())->where('status', 'open')->count();

        // ----- Trainee fun: emoji reaction + trainer rating (enrolled members) -----
        $reactRows = \App\Models\ClassReaction::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->whereDate('date', $occDate)->get();
        $reactions = $reactRows->groupBy('emoji')->map->count();           // {emoji: count}
        $myReaction = optional($reactRows->firstWhere('user_id', Auth::id()))->emoji;

        // Who gets rated = whoever actually taught the shown occurrence (the
        // substitute if covered, else the regular instructor) + my existing rating.
        $trainerUid = $this->trainerUserId($pa, $day, (string) $start, $occDate);
        $trainerUser = $trainerUid ? \App\Models\User::find($trainerUid) : null;
        $rateCi = $this->trainerInstructor($pa, $trainerUid, false);   // no create on display
        $rateInstructorId = $trainerUid;                                    // show the rating UI when there's a trainer
        $coachRatingName = $trainerUser ? ($trainerUser->full_name ?: $trainerUser->name) : ($s['coach'] ?? null);
        $coachRatingAvg = $rateCi ? round((float) $rateCi->reviews()->avg('rating'), 1) : null;
        $myReview = $rateCi
            ? \App\Models\InstructorReview::where('instructor_id', $rateCi->id)->where('reviewer_user_id', Auth::id())->first()
            : null;
        $myRating = (int) ($myReview->rating ?? 0);
        $myComment = $myReview->comment ?? null;
        $canEngage = $enrolled;     // reactions + rating are a trainee thing

        // ----- Class rating (about the CLASS itself, not the trainer) + its comments -----
        $classRows = \App\Models\ClassRating::with('user:id,name,full_name,profile_picture,updated_at')
            ->where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->orderByDesc('updated_at')->get();
        $myClassRow = $classRows->firstWhere('user_id', Auth::id());
        $myClassRating = (int) ($myClassRow->rating ?? 0);
        $myClassComment = $myClassRow->comment ?? null;
        $classRatingAvg = $classRows->count() ? round((float) $classRows->avg('rating'), 1) : null;
        $classRatingCount = $classRows->count();
        $classComments = $this->classCommentPayload($classRows);
        $classDistribution = $this->classRatingDistribution($classRows);

        // ----- Who may review: enrolled + attended a session of this class that has already started -----
        // (Attendance can be marked before a class starts, but you can't review until it has — and only
        //  if you attended. One review per class, editable; creating a new one is what's gated.)
        $iAttendedAny = \App\Models\ClassAttendance::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->where('user_id', Auth::id())->exists();
        $canReview = $enrolled && $this->attendedStartedClass($paId, $day, (string) $start);

        // Structured location for the detail (map + Google link), resolving a facility.
        $locType = $slot['location_type'] ?? (! empty($slot['facility_id']) ? 'facility' : (isset($slot['location_lat']) ? 'map' : 'text'));
        $location = $this->locationView($locType, [
            'facility_id' => $slot['facility_id'] ?? null,
            'facility_name' => $slot['facility_name'] ?? null,
            'lat' => $slot['location_lat'] ?? null,
            'lng' => $slot['location_lng'] ?? null,
            'address' => $slot['location_address'] ?? null,
            'text' => $slot['location_text'] ?? ($slot['facility_name'] ?? null),
        ]);

        // Facilities for the edit form's dropdown (club classes only).
        $clubFacilities = $tenant
            ? \App\Models\ClubFacility::where('tenant_id', $tenant->id)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->values()
            : collect();

        // Instructors of the club for the Coach dropdown (club classes only).
        $clubInstructors = $tenant
            ? \App\Models\ClubInstructor::where('tenant_id', $tenant->id)
                ->with('user:id,name,full_name,profile_picture,updated_at')->get()
                ->map(fn ($i) => $i->user)->filter()
                ->unique('id')
                ->map(fn ($u) => [
                    'name' => $u->full_name ?: $u->name,
                    'avatar' => $u->profile_picture ? asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp : null,
                    'initials' => $this->initialsFor($u->full_name ?: ($u->name ?: 'U')),
                ])
                ->sortBy('name')->values()
            : collect();

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'personal.mobile.schedule-show' : 'personal.desktop.schedule-show', [
            's' => $s, 'member' => $member, 'isOwner' => false, 'synced' => true,
            'canEditClub' => $canEditClub, 'editSlot' => $editSlot, 'updateUrl' => $updateUrl,
            'occurrenceDate' => $occDate, 'occurrenceLabel' => $occurrence->format('D, M j'),
            'substitute' => $substitute,
            'substituteSearchUrl' => $substitute_search_url,
            'substituteAssignUrl' => $substitute_assign_url,
            'substituteRemoveUrl' => $substitute_remove_url,
            'roster' => $roster,
            'canMarkAttendance' => ($canEditClub || $isSubstitute) && ! $cancelled,
            'classEnded' => $classEnded,
            'classStarted' => $classStarted,
            'slotStart' => $start,
            'slotEnd' => $slot['end_time'] ?? null,
            'attendanceUrl' => route('me.schedule.attendance.toggle', ['token' => $token]),
            'curOccKey' => $curOccKey ?? null,
            'cancelled' => $cancelled,
            'cancelReason' => $cancelReason,
            'cancelCreditable' => $cancelCreditable,
            'cancelUrl' => route('me.schedule.cancel', ['token' => $token]),
            'uncancelUrl' => route('me.schedule.uncancel', ['token' => $token]),
            'programOverridden' => $programOverridden,
            'programOverrideBy' => $programOverrideBy,
            'programResetUrl' => route('me.schedule.program.reset', ['token' => $token]),
            'myCredits' => $myCredits,
            'location' => $location,
            'clubFacilities' => $clubFacilities,
            'clubInstructors' => $clubInstructors,
            'coachLink' => $coachLink,
            'coachAvatar' => $coachAvatar,
            'canEngage' => $canEngage,
            'emojiChoices' => self::REACTION_EMOJIS,
            'reactions' => $reactions, 'myReaction' => $myReaction,
            'reactUrl' => route('me.schedule.react', ['token' => $token]),
            'rateInstructorId' => $rateInstructorId,
            'rateUrl' => route('me.schedule.rate', ['token' => $token]),
            'myRating' => $myRating, 'myComment' => $myComment, 'coachRatingAvg' => $coachRatingAvg, 'coachRatingName' => $coachRatingName,
            'rateClassUrl' => route('me.schedule.rate.class', ['token' => $token]),
            'rateClassDeleteUrl' => route('me.schedule.rate.class.destroy', ['token' => $token]),
            'myClassRating' => $myClassRating, 'myClassComment' => $myClassComment,
            'classRatingAvg' => $classRatingAvg, 'classRatingCount' => $classRatingCount, 'classComments' => $classComments,
            'classDistribution' => $classDistribution,
            'canReview' => $canReview, 'iAttendedAny' => $iAttendedAny,
        ]);
    }

    /** Has the user attended ≥1 occurrence of this class slot that has already started? Gate for reviewing. */
    private function attendedStartedClass(int $paId, string $day, string $start, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        $dates = \App\Models\ClassAttendance::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->where('user_id', $userId)->pluck('date');
        foreach ($dates as $d) {
            if (\Carbon\Carbon::parse(\Carbon\Carbon::parse($d)->toDateString().' '.$start)->isPast()) {
                return true;
            }
        }

        return false;
    }

    /** Star distribution [5..1] => count for a set of class-rating rows. */
    private function classRatingDistribution($rows): array
    {
        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($rows as $r) {
            $n = (int) $r->rating;
            if ($n >= 1 && $n <= 5) {
                $dist[$n]++;
            }
        }

        return $dist;
    }

    /** Map class-rating rows → a view/JSON payload (reviewer name, avatar, stars, comment, when). */
    private function classCommentPayload($rows): array
    {
        return $rows->filter(fn ($r) => filled($r->comment))->map(function ($r) {
            $u = $r->user;
            $name = $u ? ($u->full_name ?: $u->name) : 'Member';

            return [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'name' => $name,
                'avatar' => $u && $u->profile_picture
                    ? asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp
                    : null,
                'initials' => $this->initialsFor($name),
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'when' => optional($r->updated_at)->diffForHumans(),
            ];
        })->values()->all();
    }

    /** Trainee rates the CLASS itself (1–5 ★, optional comment) → ClassRating + visible to the whole class. */
    public function rateClass(Request $request, string $token): JsonResponse
    {
        $pa = $this->classFromToken($token);
        if (! $this->isEnrolledIn($pa)) {
            return response()->json(['success' => false, 'message' => 'Only enrolled members can rate.'], 403);
        }
        [$paId, $day, $start] = $this->decodeSyncedToken($token);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        // One review per member per class (the unique key below guarantees it). You may edit
        // an existing review freely, but a NEW one requires you to have attended a session of
        // this class that has already started.
        $existing = \App\Models\ClassRating::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->where('user_id', Auth::id())->first();
        if (! $existing && ! $this->attendedStartedClass($paId, $day, (string) $start)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only review a class you attended, once it has started.',
            ], 422);
        }

        \App\Models\ClassRating::updateOrCreate(
            ['package_activity_id' => $paId, 'slot_day' => $day, 'slot_start' => (string) $start, 'user_id' => Auth::id()],
            ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null],
        );

        $rows = \App\Models\ClassRating::with('user:id,name,full_name,profile_picture,updated_at')
            ->where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->orderByDesc('updated_at')->get();

        return response()->json([
            'success' => true,
            'message' => 'Thanks — your class rating was saved.',
            'rating' => $data['rating'],
            'average' => $rows->count() ? round((float) $rows->avg('rating'), 1) : null,
            'count' => $rows->count(),
            'comments' => $this->classCommentPayload($rows),
            'distribution' => $this->classRatingDistribution($rows),
        ]);
    }

    /** Trainee deletes their own CLASS review (rating + comment). */
    public function rateClassDestroy(string $token): JsonResponse
    {
        $pa = $this->classFromToken($token);
        if (! $this->isEnrolledIn($pa)) {
            return response()->json(['success' => false, 'message' => 'Only enrolled members can manage reviews.'], 403);
        }
        [$paId, $day, $start] = $this->decodeSyncedToken($token);

        \App\Models\ClassRating::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->where('user_id', Auth::id())
            ->delete();

        $rows = \App\Models\ClassRating::with('user:id,name,full_name,profile_picture,updated_at')
            ->where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->orderByDesc('updated_at')->get();

        return response()->json([
            'success' => true,
            'message' => 'Your review was deleted.',
            'average' => $rows->count() ? round((float) $rows->avg('rating'), 1) : null,
            'count' => $rows->count(),
            'comments' => $this->classCommentPayload($rows),
            'distribution' => $this->classRatingDistribution($rows),
        ]);
    }

    /** End time ("HH:MM[:SS]") of a class slot, or null. */
    private function slotEndTime(?ClubPackageActivity $pa, string $day, string $start): ?string
    {
        if (! $pa) {
            return null;
        }
        $sched = is_array($pa->schedule) ? $pa->schedule : (json_decode($pa->schedule ?? '[]', true) ?: []);
        $slot = collect($sched)->first(fn ($s) => strtolower($s['day'] ?? '') === $day && (string) ($s['start_time'] ?? '') === $start);

        return $slot['end_time'] ?? null;
    }

    /** May the current user run this class (mark attendance) — coach/manager OR the assigned substitute. */
    private function canRunClass(?ClubPackageActivity $pa): bool
    {
        if (! $pa) {
            return false;
        }
        if ($this->canEditClass($pa)) {
            return true;
        }

        return \App\Models\ClassSubstitution::where('package_activity_id', $pa->id)
            ->where('substitute_user_id', Auth::id())
            ->whereDate('date', '>=', \Carbon\Carbon::now()->toDateString())
            ->exists();
    }

    /** Toggle a person's attendance for one dated occurrence of a club class. */
    public function attendanceToggle(Request $request, string $token): JsonResponse
    {
        $pa = $this->classFromToken($token);
        if (! $this->canRunClass($pa)) {
            return response()->json(['success' => false, 'message' => 'You can’t mark attendance for this class.'], 403);
        }
        [$paId, $day, $start] = $this->decodeSyncedToken($token);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
        ]);

        // Attendance can only be marked DURING the class — not before it starts, not
        // after it ends. Interpret the wall-clock times in the club's timezone so the
        // window is correct regardless of server TZ.
        $tz = $pa->package?->tenant?->timezone ?: config('app.timezone');
        if (! empty($start) && \Carbon\Carbon::parse($data['date'].' '.$start, $tz)->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'This class hasn’t started yet — attendance opens when it begins.',
            ], 422);
        }
        $end = $this->slotEndTime($pa, $day, (string) $start);
        if ($end && \Carbon\Carbon::parse($data['date'].' '.$end, $tz)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'This class is over — attendance can no longer be changed.',
            ], 422);
        }

        // NB: the `date` column stores a datetime — match it with whereDate, never
        // an exact string compare (that misses the row and re-inserts → dup error).
        $base = \App\Models\ClassAttendance::where('package_activity_id', $paId)
            ->where('slot_day', $day)
            ->where('slot_start', (string) $start)
            ->where('user_id', $data['user_id'])
            ->whereDate('date', $data['date']);

        $existing = (clone $base)->first();
        if ($existing) {
            $existing->delete();
            $attended = false;
        } else {
            \App\Models\ClassAttendance::create([
                'package_activity_id' => $paId,
                'slot_day' => $day,
                'slot_start' => (string) $start,
                'date' => $data['date'],
                'user_id' => $data['user_id'],
                'marked_by' => Auth::id(),
            ]);
            $attended = true;
        }

        return response()->json(['success' => true, 'attended' => $attended, 'user_id' => $data['user_id']]);
    }

    /** Emojis a trainee can drop on a class they enjoyed (WhatsApp-style set). */
    public const REACTION_EMOJIS = ['👍', '❤️', '🔥', '💪', '👏', '🙌', '😄', '😍', '🎉', '🤩', '😮', '🙏'];

    /** True if the current user is enrolled in this class's package (a trainee). */
    private function isEnrolledIn(?ClubPackageActivity $pa): bool
    {
        if (! $pa) {
            return false;
        }
        [, $subjectKeys] = $this->scheduleMembers();

        return ClubMemberSubscription::whereIn('user_id', array_keys($subjectKeys))
            ->where('package_id', $pa->package_id)
            ->whereIn('status', ['active', 'pending'])->exists();
    }

    /** User id of whoever TAUGHT the shown occurrence: the substitute if covered, else the regular instructor. */
    private function trainerUserId(?ClubPackageActivity $pa, string $day, string $start, string $date): ?int
    {
        if (! $pa) {
            return null;
        }
        $sub = \App\Models\ClassSubstitution::where('package_activity_id', $pa->id)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->whereDate('date', $date)->first();
        if ($sub) {
            return (int) $sub->substitute_user_id;
        }

        return $pa->instructor?->user_id;
    }

    /**
     * The ClubInstructor record the rating attaches to (so it shows on that
     * trainer's profile). For the regular coach this is pa->instructor. For a
     * substitute it's their instructor record in the club — created on demand
     * ($create) so a covering substitute becomes a rateable trainer there.
     */
    private function trainerInstructor(?ClubPackageActivity $pa, ?int $userId, bool $create = false): ?\App\Models\ClubInstructor
    {
        if (! $pa || ! $userId) {
            return null;
        }
        if ($pa->instructor && (int) $pa->instructor->user_id === $userId) {
            return $pa->instructor;                       // regular coach — use the exact record
        }
        $tenantId = $pa->package?->tenant_id;
        $ci = \App\Models\ClubInstructor::where('tenant_id', $tenantId)->where('user_id', $userId)->first();
        if (! $ci && $create) {
            $ci = \App\Models\ClubInstructor::create([
                'tenant_id' => $tenantId, 'user_id' => $userId, 'role' => 'Substitute', 'rating' => 0,
            ]);
        }

        return $ci;
    }

    /** Trainee drops / changes / clears an emoji reaction on a class occurrence. */
    public function reactClass(Request $request, string $token): JsonResponse
    {
        $pa = $this->classFromToken($token);
        if (! $this->isEnrolledIn($pa)) {
            return response()->json(['success' => false, 'message' => 'Only enrolled members can react.'], 403);
        }
        [$paId, $day, $start] = $this->decodeSyncedToken($token);
        $data = $request->validate([
            'emoji' => ['required', 'string', Rule::in(self::REACTION_EMOJIS)],
            'date' => ['required', 'date'],
        ]);

        $row = \App\Models\ClassReaction::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->whereDate('date', $data['date'])->where('user_id', Auth::id())->first();

        if ($row && $row->emoji === $data['emoji']) {
            $row->delete();               // tapping the same emoji clears it
            $mine = null;
        } elseif ($row) {
            $row->update(['emoji' => $data['emoji']]);
            $mine = $data['emoji'];
        } else {
            \App\Models\ClassReaction::create([
                'package_activity_id' => $paId, 'slot_day' => $day, 'slot_start' => (string) $start,
                'date' => $data['date'], 'user_id' => Auth::id(), 'emoji' => $data['emoji'],
            ]);
            $mine = $data['emoji'];
        }

        $counts = \App\Models\ClassReaction::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->whereDate('date', $data['date'])->get()->groupBy('emoji')->map->count();

        return response()->json(['success' => true, 'mine' => $mine, 'counts' => $counts]);
    }

    /** Trainee rates the class trainer (1–5 ★, optional comment) → InstructorReview + profile. */
    public function rateClassTrainer(Request $request, string $token): JsonResponse
    {
        $pa = $this->classFromToken($token);
        if (! $this->isEnrolledIn($pa)) {
            return response()->json(['success' => false, 'message' => 'Only enrolled members can rate.'], 403);
        }
        [$paId, $day, $start] = $this->decodeSyncedToken($token);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
            'date' => ['nullable', 'date'],
        ]);

        $date = $data['date'] ?? \Carbon\Carbon::now()->toDateString();
        $uid = $this->trainerUserId($pa, $day, (string) $start, $date);
        $ci = $this->trainerInstructor($pa, $uid, true);   // create the trainer's record so it lands on THEIR profile
        if (! $ci) {
            return response()->json(['success' => false, 'message' => 'No trainer to rate for this class.'], 422);
        }

        \App\Models\InstructorReview::updateOrCreate(
            ['instructor_id' => $ci->id, 'reviewer_user_id' => Auth::id()],
            ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null, 'reviewed_at' => now()],
        );

        // Keep the denormalised rating column in sync (shown on the club list).
        $avg = round((float) $ci->reviews()->avg('rating'), 2);
        $ci->update(['rating' => $avg]);

        return response()->json([
            'success' => true,
            'message' => 'Thanks — your rating was saved.',
            'rating' => $data['rating'],
            'average' => round($avg, 1),
        ]);
    }

    /** Weekly recurrence — days credited per cancelled occurrence. */
    private int $slotIntervalDays = 7;

    /**
     * Cancel this class for a single date or a date range. For each cancelled
     * occurrence: drop any substitute, credit every enrolled member a make-up
     * (and nudge their subscription end_date so the credit survives expiry),
     * then notify + live-refresh.
     */
    public function classCancel(Request $request, string $token): JsonResponse
    {
        $pa = $this->classFromToken($token);
        if (! $this->canRunClass($pa)) {
            return response()->json(['success' => false, 'message' => 'You can’t cancel this class.'], 403);
        }
        [$paId, $day, $start] = $this->decodeSyncedToken($token);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'reason' => ['nullable', 'string', 'max:300'],
            'credit' => ['nullable', 'boolean'],
        ]);
        $creditable = $data['credit'] ?? true;          // default: members are credited

        $today = \Carbon\Carbon::now()->startOfDay();
        $from = \Carbon\Carbon::parse($data['from'])->startOfDay();
        if ($from->lt($today)) {
            $from = $today->copy();
        }          // can't cancel the past
        $to = isset($data['to']) ? \Carbon\Carbon::parse($data['to'])->startOfDay() : $from->copy();

        // Occurrences of THIS slot's weekday within the span.
        $dates = [];
        $cursor = $from->copy();
        $guard = 0;
        while ($cursor->lte($to) && $guard < 400) {
            $guard++;
            if (strtolower($cursor->format('l')) === $day) {
                $dates[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }
        if (empty($dates)) {
            return response()->json(['success' => false, 'message' => 'No sessions of this class fall in that range.'], 422);
        }

        $affected = [];
        foreach ($dates as $d) {
            // Skip dates already cancelled (whereDate — the column stores a datetime).
            $already = \App\Models\ClassCancellation::where('package_activity_id', $paId)
                ->where('slot_day', $day)->where('slot_start', (string) $start)
                ->whereDate('date', $d)->exists();
            if ($already) {
                continue;
            }

            \App\Models\ClassCancellation::create([
                'package_activity_id' => $paId,
                'slot_day' => $day,
                'slot_start' => (string) $start,
                'date' => $d,
                'reason' => $data['reason'] ?? null,
                'creditable' => $creditable,
                'cancelled_by' => Auth::id(),
            ]);
            // A cancelled class can't also have a substitute.
            \App\Models\ClassSubstitution::where('package_activity_id', $paId)
                ->where('slot_day', $day)->where('slot_start', (string) $start)
                ->whereDate('date', $d)->delete();

            // Only credit members when the coach marked the cancellation creditable.
            if ($creditable) {
                $affected = array_merge($affected, $this->creditMembersForCancellation($pa, $d));
            }
        }
        $affected = array_values(array_unique($affected));

        $className = $pa->activity->name ?? 'your class';
        $label = count($dates) > 1
            ? (count($dates).' sessions')
            : \Carbon\Carbon::parse($dates[0])->format('D, M j');
        $this->notifyClassRecipients(
            $pa,
            $className.' cancelled',
            $creditable
                ? ('The '.$label.' was cancelled — a make-up credit has been added to your account.')
                : ('The '.$label.' was cancelled.'),
            $token,
            $affected,
        );
        $this->pushScheduleRefresh($this->classAudience($pa, array_merge($affected, [Auth::id()])));

        $msg = 'Class cancelled for '.count($dates).' session'.(count($dates) === 1 ? '' : 's').'. '
            .($creditable ? (count($affected).' member(s) credited.') : 'No make-up credit given.');

        return response()->json([
            'success' => true,
            'message' => $msg,
            'redirect' => route('me.schedule'),
        ]);
    }

    /** Restore a previously cancelled occurrence and reverse the make-up credits. */
    public function classUncancel(Request $request, string $token): JsonResponse
    {
        $pa = $this->classFromToken($token);
        if (! $this->canRunClass($pa)) {
            return response()->json(['success' => false, 'message' => 'You can’t change this class.'], 403);
        }
        [$paId, $day, $start] = $this->decodeSyncedToken($token);
        $data = $request->validate(['date' => ['required', 'date']]);

        \App\Models\ClassCancellation::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->whereDate('date', $data['date'])->delete();

        // Reverse OPEN credits from this date (pull back the end_date nudge). Used
        // credits are left as history — the member already took that make-up.
        $credits = \App\Models\ClassMakeupCredit::where('package_activity_id', $paId)
            ->whereDate('source_date', $data['date'])->get();
        $affected = [];
        foreach ($credits as $cr) {
            $affected[] = $cr->user_id;
            if ($cr->status === 'open') {
                if ($cr->credit_days > 0 && $cr->subscription_id) {
                    $sub = ClubMemberSubscription::find($cr->subscription_id);
                    if ($sub && $sub->end_date) {
                        $sub->end_date = \Carbon\Carbon::parse($sub->end_date)->subDays($cr->credit_days);
                        $sub->save();
                    }
                }
                $cr->delete();
            }
        }
        $affected = array_values(array_unique($affected));

        $className = $pa->activity->name ?? 'your class';
        $this->notifyClassRecipients(
            $pa,
            $className.' is back on',
            'The '.\Carbon\Carbon::parse($data['date'])->format('D, M j').' class is no longer cancelled.',
            $token,
            $affected,
        );
        $this->pushScheduleRefresh($this->classAudience($pa, array_merge($affected, [Auth::id()])));

        return response()->json(['success' => true, 'message' => 'Class restored.', 'redirect' => route('me.schedule')]);
    }

    /**
     * Clear a one-off training-program override for a single dated occurrence
     * of a club class, reverting the display back to the recurring plan
     * stored on the package activity's schedule JSON (which this never touched).
     */
    public function programReset(Request $request, string $token): JsonResponse
    {
        $pa = $this->classFromToken($token);
        if (! $this->canEditClass($pa)) {
            return response()->json(['success' => false, 'message' => 'You can’t change this class.'], 403);
        }
        [$paId, $day, $start] = $this->decodeSyncedToken($token);
        $data = $request->validate(['date' => ['required', 'date']]);

        \App\Models\ClassProgramOverride::where('package_activity_id', $paId)
            ->where('slot_day', $day)->where('slot_start', (string) $start)
            ->whereDate('date', $data['date'])->delete();

        $className = $pa->activity->name ?? 'your class';
        $this->notifyClassRecipients(
            $pa,
            $className.' program reset',
            'The '.\Carbon\Carbon::parse($data['date'])->format('D, M j').' class now follows the recurring plan again.',
            $token,
        );
        $this->pushScheduleRefresh($this->classAudience($pa, [Auth::id()]));

        return response()->json(['success' => true, 'message' => 'Program reset to the recurring plan.']);
    }

    /**
     * Credit each enrolled member a make-up for one cancelled date: a tracked
     * credit + a subscription end_date extension (so it survives expiry).
     * Returns affected user ids. Idempotent per (class, member, date).
     */
    private function creditMembersForCancellation(ClubPackageActivity $pa, string $date): array
    {
        $today = \Carbon\Carbon::now()->toDateString();
        $subs = ClubMemberSubscription::where('package_id', $pa->package_id)
            ->whereIn('status', ['active', 'pending'])
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->get();

        $ids = [];
        foreach ($subs as $sub) {
            $ids[] = $sub->user_id;
            $exists = \App\Models\ClassMakeupCredit::where('package_activity_id', $pa->id)
                ->where('user_id', $sub->user_id)
                ->whereDate('source_date', $date)->exists();
            if ($exists) {
                continue;
            }                       // already credited for this date

            $days = 0;
            if ($sub->end_date) {                        // only time-bounded subs need the nudge
                $days = $this->slotIntervalDays;
                $sub->end_date = \Carbon\Carbon::parse($sub->end_date)->addDays($days);
                $sub->save();
            }

            \App\Models\ClassMakeupCredit::create([
                'package_activity_id' => $pa->id,
                'user_id' => $sub->user_id,
                'subscription_id' => $sub->id,
                'source_date' => $date,
                'credit_days' => $days,
                'status' => 'open',
                'created_by' => Auth::id(),
            ]);
        }

        return $ids;
    }

    /** Decode a club-class token into [paId, day, start] (paId null when malformed). */
    private function decodeSyncedToken(string $token): array
    {
        return \App\Support\SyncedClassToken::decode($token);
    }

    /**
     * Club owner/admin OR the assigned coach edits a club-class slot from the
     * schedule detail — the FULL session (schedule + rich content: intensity,
     * focus, notes, the workout breakdown, icon, colour…), exactly like a
     * personal session. Persists into the package activity schedule JSON, so it
     * changes for every member enrolled in that package.
     */
    public function scheduleSyncedUpdate(Request $request, string $token): JsonResponse
    {
        [$paId, $origDay, $origStart] = $this->decodeSyncedToken($token);
        if ($paId === null) {
            return response()->json(['success' => false, 'message' => 'Invalid session.'], 404);
        }

        $pa = ClubPackageActivity::with(['package.tenant', 'instructor'])->find($paId);
        if (! $pa) {
            return response()->json(['success' => false, 'message' => 'Class not found.'], 404);
        }

        // The assigned coach OR a manager of the club may edit the class.
        $tenant = $pa->package?->tenant;
        $manages = $tenant && $this->canManageClub($tenant);
        $teaches = $pa->instructor && $pa->instructor->user_id === Auth::id();
        if (! $manages && ! $teaches) {
            return response()->json(['success' => false, 'message' => 'You can’t edit this class.'], 403);
        }

        // Same field set as a personal session, minus the family "subject", plus a
        // scope choice: 'recurring' changes the permanent weekly plan (default,
        // existing behaviour); 'once' saves the rich-content fields as a one-time
        // variation for a single dated occurrence, leaving the recurring plan untouched.
        $data = $request->validate([
            'scope' => ['nullable', 'in:recurring,once'],
            'date' => ['required_if:scope,once', 'date_format:Y-m-d'],
            'day' => ['required', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'start_time' => ['nullable', 'string', 'max:12'],
            'end_time' => ['nullable', 'string', 'max:12'],
            'title' => ['nullable', 'string', 'max:120'],
            'discipline' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'coach' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:160'],     // text-mode value
            'location_type' => ['nullable', 'in:facility,map,text'],
            'facility_id' => ['nullable', 'integer'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'location_address' => ['nullable', 'string', 'max:200'],
            'intensity' => ['nullable', 'in:Low,Moderate,High'],
            'focus' => ['nullable', 'array', 'max:12'],
            'focus.*' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'workout' => ['nullable', 'array'],
            'workout.warmup' => ['nullable', 'array', 'max:30'],
            'workout.warmup.*' => ['nullable', 'string', 'max:200'],
            'workout.cooldown' => ['nullable', 'array', 'max:30'],
            'workout.cooldown.*' => ['nullable', 'string', 'max:200'],
            'workout.main' => ['nullable', 'array', 'max:30'],
            'workout.main.*.name' => ['nullable', 'string', 'max:120'],
            'workout.main.*.sets' => ['nullable', 'string', 'max:20'],
            'workout.main.*.reps' => ['nullable', 'string', 'max:40'],
            'workout.main.*.note' => ['nullable', 'string', 'max:120'],
        ]);

        $sched = is_array($pa->schedule) ? $pa->schedule : (json_decode($pa->schedule ?? '[]', true) ?: []);
        $idx = null;
        foreach ($sched as $i => $sl) {
            if (strtolower($sl['day'] ?? '') === $origDay && (string) ($sl['start_time'] ?? '') === $origStart) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return response()->json(['success' => false, 'message' => 'This class slot no longer exists.'], 404);
        }

        $slot = $sched[$idx];

        // ----- Scheduling -----
        $slot['day'] = $data['day'];
        $slot['start_time'] = $data['start_time'] ?? '';
        $slot['end_time'] = $data['end_time'] ?? '';

        // ----- Location (facility | map | text) -----
        $locType = $data['location_type'] ?? 'text';
        // reset all location keys, then set the chosen mode's
        $slot['location_type'] = $locType;
        $slot['facility_id'] = null;
        $slot['facility_name'] = null;
        $slot['location_lat'] = null;
        $slot['location_lng'] = null;
        $slot['location_address'] = null;
        $slot['location_text'] = null;
        if ($locType === 'facility' && ! empty($data['facility_id'])) {
            $f = \App\Models\ClubFacility::where('tenant_id', $tenant?->id)->find($data['facility_id']);
            if ($f) {
                $slot['facility_id'] = $f->id;
                $slot['facility_name'] = $f->name;
            }
        } elseif ($locType === 'map' && isset($data['location_lat'], $data['location_lng'])) {
            $slot['location_lat'] = (float) $data['location_lat'];
            $slot['location_lng'] = (float) $data['location_lng'];
            $slot['location_address'] = $data['location_address'] ?? null;
        } else {
            $slot['location_type'] = 'text';
            $slot['location_text'] = ($data['location'] ?? null) ?: null;
        }

        // ----- Rich content (clean the workout lists, like a personal session) -----
        $focus = collect($data['focus'] ?? [])->map(fn ($f) => trim((string) $f))->filter()->values()->all();
        $w = $data['workout'] ?? [];
        $warmup = collect($w['warmup'] ?? [])->map(fn ($x) => trim((string) $x))->filter()->values()->all();
        $cooldown = collect($w['cooldown'] ?? [])->map(fn ($x) => trim((string) $x))->filter()->values()->all();
        $main = collect($w['main'] ?? [])
            ->map(fn ($ex) => [
                'name' => trim((string) ($ex['name'] ?? '')),
                'sets' => trim((string) ($ex['sets'] ?? '')),
                'reps' => trim((string) ($ex['reps'] ?? '')),
                'note' => trim((string) ($ex['note'] ?? '')),
            ])
            ->filter(fn ($ex) => $ex['name'] !== '')
            ->values()->all();

        $slot['title'] = ($data['title'] ?? null) ?: null;
        $slot['discipline'] = ($data['discipline'] ?? null) ?: null;
        $slot['icon'] = ($data['icon'] ?? null) ?: null;
        $slot['color'] = ($data['color'] ?? null) ?: null;
        $slot['coach'] = ($data['coach'] ?? null) ?: null;

        // Rich content (intensity/focus/notes/workout) only overwrites the RECURRING
        // plan when scope=recurring. For scope=once it's saved as a dated override
        // below instead — $slot keeps whatever it already had, so the base JSON
        // (and therefore every other week) is left completely untouched.
        $scope = $data['scope'] ?? 'recurring';
        if ($scope === 'recurring') {
            $slot['intensity'] = ($data['intensity'] ?? null) ?: null;
            $slot['focus'] = $focus;
            $slot['notes'] = ($data['notes'] ?? null) ?: null;
            $slot['workout'] = ['warmup' => $warmup, 'main' => $main, 'cooldown' => $cooldown];
        }

        $sched[$idx] = $slot;

        $pa->schedule = json_encode(array_values($sched));
        $pa->save();

        if ($scope === 'once') {
            // NOTE: deliberately not updateOrCreate() — its naive attribute-match lookup
            // compares the raw 'date' string against the date-cast column and misses an
            // existing row (the cast serializes with a time part), throwing a unique-
            // constraint violation on the second save instead of updating. Look the row
            // up with whereDate() (as everywhere else in this file) and save explicitly.
            $override = \App\Models\ClassProgramOverride::where('package_activity_id', $paId)
                ->where('slot_day', $origDay)->where('slot_start', $origStart)
                ->whereDate('date', $data['date'])
                ->first() ?? new \App\Models\ClassProgramOverride([
                    'package_activity_id' => $paId, 'slot_day' => $origDay, 'slot_start' => $origStart, 'date' => $data['date'],
                ]);
            $override->intensity = ($data['intensity'] ?? null) ?: null;
            $override->focus = $focus;
            $override->notes = ($data['notes'] ?? null) ?: null;
            $override->workout = ['warmup' => $warmup, 'main' => $main, 'cooldown' => $cooldown];
            $override->set_by = Auth::id();
            $override->save();
        }

        // Notify everyone enrolled + the coach + any substitutes, and deep-link
        // them to the (possibly rescheduled) class. Token reflects the new slot.
        $newToken = $this->syncedToken($pa->id, $slot['day'], (string) ($slot['start_time'] ?? ''));
        $subIds = $this->slotSubstituteUserIds($paId, $origDay, $origStart);
        $className = $slot['title'] ?: ($pa->activity->name ?? 'Your class');
        $this->notifyClassRecipients(
            $pa,
            $scope === 'once' ? $className.' program updated' : $className.' was updated',
            $scope === 'once'
                ? 'The '.\Carbon\Carbon::parse($data['date'])->format('D, M j').' class has a one-time program change. Tap to see what’s new.'
                : 'The class schedule or details changed. Tap to see what’s new.',
            $newToken,
            $subIds,
        );

        // Live-refresh everyone's open schedule (members + coach + subs + actor).
        $this->pushScheduleRefresh($this->classAudience($pa, array_merge($subIds, [Auth::id()])));

        return response()->json([
            'success' => true,
            'message' => $scope === 'once' ? 'Program saved for this date.' : 'Class schedule updated.',
            'redirect' => route('me.schedule'),
            'once' => $scope === 'once',
        ]);
    }

    /** Store a new personal session. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateSession($request);
        $member = $this->resolveSubject($data['subject']);
        if (! $member) {
            return response()->json(['success' => false, 'message' => 'Unknown family member.'], 422);
        }

        $model = UserScheduleSession::create($this->sessionAttributes($data, $member['user_id']));

        $card = $model->toCardArray($member['key']);
        $card = $this->stampStatus($card);

        $this->pushSchedule($member['user_id'], 'created', $card);

        return response()->json(['success' => true, 'message' => 'Session added.', 'session' => $card]);
    }

    /** Update an existing personal session (owner only). */
    public function update(Request $request, int $session): JsonResponse
    {
        $model = UserScheduleSession::where('id', $session)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $data = $this->validateSession($request);
        $member = $this->resolveSubject($data['subject']);
        if (! $member) {
            return response()->json(['success' => false, 'message' => 'Unknown family member.'], 422);
        }

        $model->update($this->sessionAttributes($data, $member['user_id']));

        $card = $this->stampStatus($model->fresh()->toCardArray($member['key']));
        $this->pushSchedule(Auth::id(), 'updated', $card);

        return response()->json(['success' => true, 'message' => 'Session updated.', 'session' => $card]);
    }

    /** Delete a personal session (owner only). */
    public function destroy(int $session): JsonResponse
    {
        $model = UserScheduleSession::where('id', $session)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $model->delete();
        $this->pushSchedule(Auth::id(), 'deleted', ['id' => $session]);

        return response()->json(['success' => true, 'message' => 'Session removed.', 'id' => $session]);
    }

    /** Shared validation for create/update. */
    private function validateSession(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:40'],
            'day' => ['required', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'start_time' => ['nullable', 'string', 'max:12'],
            'end_time' => ['nullable', 'string', 'max:12'],
            'title' => ['required', 'string', 'max:120'],
            'discipline' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'coach' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:160'],
            'location_type' => ['nullable', 'in:map,text'],          // personal: no facility
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'location_address' => ['nullable', 'string', 'max:200'],
            'intensity' => ['nullable', 'in:Low,Moderate,High'],
            'focus' => ['nullable', 'array', 'max:12'],
            'focus.*' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'workout' => ['nullable', 'array'],
            'workout.warmup' => ['nullable', 'array', 'max:30'],
            'workout.warmup.*' => ['nullable', 'string', 'max:200'],
            'workout.cooldown' => ['nullable', 'array', 'max:30'],
            'workout.cooldown.*' => ['nullable', 'string', 'max:200'],
            'workout.main' => ['nullable', 'array', 'max:30'],
            'workout.main.*.name' => ['nullable', 'string', 'max:120'],
            'workout.main.*.sets' => ['nullable', 'string', 'max:20'],
            'workout.main.*.reps' => ['nullable', 'string', 'max:40'],
            'workout.main.*.note' => ['nullable', 'string', 'max:120'],
        ]);
    }

    /** Map validated input → model attributes (cleans the workout lists). */
    private function sessionAttributes(array $data, int $subjectUserId): array
    {
        $focus = collect($data['focus'] ?? [])->map(fn ($f) => trim((string) $f))->filter()->values()->all();

        $w = $data['workout'] ?? [];
        $warmup = collect($w['warmup'] ?? [])->map(fn ($x) => trim((string) $x))->filter()->values()->all();
        $cooldown = collect($w['cooldown'] ?? [])->map(fn ($x) => trim((string) $x))->filter()->values()->all();
        $main = collect($w['main'] ?? [])
            ->map(fn ($ex) => [
                'name' => trim((string) ($ex['name'] ?? '')),
                'sets' => trim((string) ($ex['sets'] ?? '')),
                'reps' => trim((string) ($ex['reps'] ?? '')),
                'note' => trim((string) ($ex['note'] ?? '')),
            ])
            ->filter(fn ($ex) => $ex['name'] !== '')
            ->values()->all();

        return [
            'user_id' => Auth::id(),
            'subject_user_id' => $subjectUserId,
            'day' => $data['day'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'title' => $data['title'],
            'discipline' => $data['discipline'] ?? null,
            'icon' => ($data['icon'] ?? null) ?: 'bi-calendar-check',
            'color' => ($data['color'] ?? null) ?: '#7c3aed',
            'coach' => $data['coach'] ?? null,
            'location' => $this->personalLocationLabel($data),
            'location_meta' => $this->personalLocationMeta($data),
            'intensity' => $data['intensity'] ?? null,
            'focus' => $focus,
            'notes' => $data['notes'] ?? null,
            'workout' => ['warmup' => $warmup, 'main' => $main, 'cooldown' => $cooldown],
        ];
    }

    /** Personal session: human label for the location (address for map, else text). */
    private function personalLocationLabel(array $data): ?string
    {
        $type = $data['location_type'] ?? 'text';
        if ($type === 'map') {
            return ($data['location_address'] ?? null) ?: 'Pinned location';
        }

        return ($data['location'] ?? null) ?: null;
    }

    /** Personal session: structured {type, lat, lng, address} for the map view. */
    private function personalLocationMeta(array $data): ?array
    {
        $type = $data['location_type'] ?? 'text';
        if ($type === 'map' && isset($data['location_lat'], $data['location_lng'])
            && $data['location_lat'] !== null && $data['location_lng'] !== null) {
            return [
                'type' => 'map',
                'lat' => (float) $data['location_lat'],
                'lng' => (float) $data['location_lng'],
                'address' => $data['location_address'] ?? null,
            ];
        }

        return ['type' => 'text'];
    }

    /** Resolve a subject key ("me" / "u{id}") to a roster member the user owns. */
    private function resolveSubject(string $key): ?array
    {
        [$members] = $this->scheduleMembers();

        return $members[$key] ?? null;
    }

    /** Tag a card with done/today/upcoming for the current week. */
    private function stampStatus(array $card): array
    {
        $order = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        $cmp = ($order[$card['day']] ?? 0) <=> $order[strtolower(\Carbon\Carbon::now()->format('l'))];
        $card['status'] = $cmp < 0 ? 'done' : ($cmp === 0 ? 'today' : 'upcoming');

        return $card;
    }

    /** Best-effort MQTT push so the member's other open devices update in place. */
    private function pushSchedule(int $userId, string $action, array $payload): void
    {
        if (! (function_exists('Realtime') && Realtime()->enabled())) {
            return;
        }
        try {
            Realtime()->publishToUser($userId, 'schedule', ['action' => $action, 'session' => $payload]);
        } catch (\Throwable) {
            // realtime is best-effort; the DB is the source of truth.
        }
    }

    /**
     * Tell a set of users to re-pull their schedule live (each user's cards have
     * different ids/content for the same class, so a generic "refresh" is the
     * reliable way to update everyone in place — no manual reload).
     */
    private function pushScheduleRefresh(iterable $userIds): void
    {
        if (! (function_exists('Realtime') && Realtime()->enabled())) {
            return;
        }
        $ids = collect($userIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        try {
            $batch = $ids->map(fn ($uid) => [
                'topic' => Realtime()->userTopic($uid, 'schedule'),
                'payload' => ['action' => 'refresh'],
            ])->all();
            Realtime()->publishMany($batch);
        } catch (\Throwable) {
            // best-effort
        }
    }

    /** Everyone who should live-refresh when a class changes: enrolled members + coach + substitutes. */
    private function classAudience(ClubPackageActivity $pa, array $extraUserIds = []): \Illuminate\Support\Collection
    {
        $memberIds = ClubMemberSubscription::where('package_id', $pa->package_id)
            ->whereIn('status', ['active', 'pending'])
            ->distinct()->pluck('user_id');

        return collect($memberIds)
            ->push($pa->instructor?->user_id)
            ->merge($extraUserIds)
            ->filter()->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function initialsFor(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $a = mb_substr($parts[0] ?? '', 0, 1);
        $b = mb_substr($parts[1] ?? '', 0, 1);

        return mb_strtoupper(($a.$b) ?: 'U');
    }

    /** Public storage URL for a user's profile picture (cache-busted), or null. */
    private function avatarUrl(?\App\Models\User $u): ?string
    {
        if (! $u || ! $u->profile_picture) {
            return null;
        }

        return asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp;
    }

    /** "18:00" → "6:00 PM"; passes already-formatted strings through. */
    private function fmtTime(?string $t): ?string
    {
        if (! $t) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($t)->format('g:i A');
        } catch (\Throwable) {
            return $t;
        }
    }

    private function slotDuration(?string $start, ?string $end): string
    {
        if (! $start || ! $end) {
            return '—';
        }
        try {
            $mins = \Carbon\Carbon::parse($start)->diffInMinutes(\Carbon\Carbon::parse($end));

            return $mins > 0 ? $mins.' min' : '—';
        } catch (\Throwable) {
            return '—';
        }
    }

    /** Curated icon options for the personal-session create form. */
    private function scheduleIconChoices(): array
    {
        return [
            'bi-trophy', 'bi-heart-pulse-fill', 'bi-lightning-charge-fill', 'bi-activity',
            'bi-water', 'bi-stars', 'bi-dribbble', 'bi-bicycle', 'bi-person-arms-up',
            'bi-calendar-check', 'bi-fire', 'bi-bullseye',
        ];
    }

    /** Curated colour swatches for the personal-session create form. */
    private function scheduleColorChoices(): array
    {
        return ['#7c3aed', '#ec4899', '#0ea5e9', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#14b8a6'];
    }

    /** Dedicated page: the member's club affiliations — active now + history of clubs left. */
    public function affiliations(): View
    {
        $affiliations = Auth::user()->clubAffiliations()
            ->with(['skillAcquisitions', 'tenant:id,slug,country'])
            ->get();

        $active = $affiliations->whereNull('end_date')->sortByDesc('start_date')->values();
        $left = $affiliations->whereNotNull('end_date')->sortByDesc('end_date')->values();

        // The mobile shell labels its header from $shellTitle for routes that
        // aren't in its own nav list.
        return view('personal.affiliations', compact('active', 'left'))
            ->with('shellTitle', __('nav.affiliations'));
    }

    /**
     * The "Profile" tab — renders the member's full rich profile inside the
     * mobile shell (keeps the top bar + bottom tabs) via the $inShell flag.
     * Mirrors the data MemberController@show builds for one's own profile.
     */
    public function profile(): \Illuminate\Http\RedirectResponse
    {
        // The standalone "my profile" page is redundant with the full member
        // profile — send members straight to /member/{uuid}, the single source
        // of truth for a person's profile.
        return redirect()->route('member.show', Auth::user()->uuid);
    }

    /** @deprecated superseded by member.show — kept for reference. */
    private function profileLegacy(): View
    {
        $user = Auth::user();

        $relationship = (object) [
            'dependent' => $user,
            'relationship_type' => 'self',
            'guardian_user_id' => $user->id,
            'dependent_user_id' => $user->id,
        ];

        $latestHealthRecord = $user->healthRecords()->latest('recorded_at')->first();
        $healthRecords = $user->healthRecords()->orderBy('recorded_at', 'desc')->paginate(10);
        $comparisonRecords = $user->healthRecords()->orderBy('recorded_at', 'desc')->take(2)->get();

        $invoices = \App\Models\Invoice::where('student_user_id', $user->id)
            ->orWhere('payer_user_id', $user->id)
            ->with(['student', 'tenant'])->get();

        // Unified payment history: explicit invoices PLUS club-package subscriptions
        // (what self-registration / "join club" creates). Same normalised shape the
        // desktop profile uses so the billing tab renders both in one list.
        $payments = $this->buildMemberPayments($user, $invoices);

        $tournamentEvents = $user->tournamentEvents()
            ->with(['performanceResults', 'notesMedia', 'clubAffiliation'])
            ->orderBy('date', 'desc')->get();

        $awardCounts = [
            'special' => $tournamentEvents->flatMap->performanceResults->where('medal_type', 'special')->count(),
            '1st' => $tournamentEvents->flatMap->performanceResults->where('medal_type', '1st')->count(),
            '2nd' => $tournamentEvents->flatMap->performanceResults->where('medal_type', '2nd')->count(),
            '3rd' => $tournamentEvents->flatMap->performanceResults->where('medal_type', '3rd')->count(),
        ];
        $sports = $tournamentEvents->pluck('sport')->unique()->sort()->values();

        // Club achievements this member is featured in (linked as an athlete).
        $memberClubIds = $user->memberClubs()->pluck('tenants.id');
        $awardedAchievements = $memberClubIds->isEmpty()
            ? collect()
            : \App\Models\ClubAchievement::whereIn('tenant_id', $memberClubIds)
                ->where('status', 'active')
                ->orderByDesc('achievement_date')
                ->with('tenant:id,club_name,slug,translations')
                ->get()
                ->map(function ($a) use ($user) {
                    $athletes = is_array($a->athletes) ? $a->athletes : [];
                    $mine = collect($athletes)->first(fn ($x) => is_array($x) && (int) ($x['user_id'] ?? 0) === (int) $user->id);
                    $a->member_award = $mine['role'] ?? null;

                    return $mine ? $a : null;
                })
                ->filter()
                ->values();

        $goals = $user->goals()->orderBy('created_at', 'desc')->get();
        $activeGoalsCount = $goals->where('status', 'active')->count();
        $completedGoalsCount = $goals->where('status', 'completed')->count();
        $successRate = $goals->count() > 0 ? round(($completedGoalsCount / $goals->count()) * 100) : 0;

        $attendanceRecords = $user->attendanceRecords()->orderBy('session_datetime', 'desc')->get();
        $sessionsCompleted = $attendanceRecords->where('status', 'completed')->count();
        $noShows = $attendanceRecords->where('status', 'no_show')->count();
        $totalSessions = $attendanceRecords->count();
        $attendanceRate = $totalSessions > 0 ? round(($sessionsCompleted / $totalSessions) * 100, 1) : 0;

        $clubAffiliations = $user->clubAffiliations()
            ->with([
                'skillAcquisitions.package', 'skillAcquisitions.activity', 'skillAcquisitions.instructor.user',
                'affiliationMedia',
                'subscriptions.package.activities', 'subscriptions.package.packageActivities.activity',
                'subscriptions.package.packageActivities.instructor.user',
            ])
            ->orderBy('start_date', 'desc')->get();
        $clubAffiliations->each(fn ($a) => $a->affiliationMedia->each(fn ($m) => $m->icon_class = $m->icon_class));

        $totalAffiliations = $clubAffiliations->count();
        $distinctSkills = $clubAffiliations->flatMap->skillAcquisitions->pluck('skill_name')->unique()->count();
        $totalMembershipDuration = $clubAffiliations->sum('duration_in_months');
        $allSkills = $clubAffiliations->flatMap(fn ($a) => $a->skillAcquisitions->pluck('skill_name'))->unique()->sort()->values();
        $totalInstructors = $clubAffiliations->flatMap(fn ($a) => $a->skillAcquisitions->pluck('instructor'))->filter()->unique('id')->count();

        $joinedEventRegistrations = \App\Models\ClubEventRegistration::where('user_id', $user->id)
            ->with(['event.tenant'])->orderBy('registered_at', 'desc')->get();

        return view('components-templates.member.mobile.show', [
            'inShell' => true,
            'relationship' => $relationship,
            'user' => $user,
            // Own profile: may edit basic info; not club staff over themselves.
            'canEditBasic' => true,
            'canManageMember' => false,
            // Own profile — the member can always see their own sensitive sections.
            'canViewSensitive' => true,
            'latestHealthRecord' => $latestHealthRecord,
            'healthRecords' => $healthRecords,
            'comparisonRecords' => $comparisonRecords,
            'invoices' => $invoices,
            'payments' => $payments,
            'tournamentEvents' => $tournamentEvents,
            'awardCounts' => $awardCounts,
            'sports' => $sports,
            'awardedAchievements' => $awardedAchievements,
            'goals' => $goals,
            'activeGoalsCount' => $activeGoalsCount,
            'completedGoalsCount' => $completedGoalsCount,
            'successRate' => $successRate,
            'attendanceRecords' => $attendanceRecords,
            'sessionsCompleted' => $sessionsCompleted,
            'noShows' => $noShows,
            'attendanceRate' => $attendanceRate,
            'clubAffiliations' => $clubAffiliations,
            'totalAffiliations' => $totalAffiliations,
            'distinctSkills' => $distinctSkills,
            'totalMembershipDuration' => $totalMembershipDuration,
            'allSkills' => $allSkills,
            'totalInstructors' => $totalInstructors,
            'joinedEventRegistrations' => $joinedEventRegistrations,
            'allClubs' => \App\Models\Tenant::orderBy('club_name')->get(['id', 'club_name', 'address', 'logo']),
            'canResetPassword' => true,
        ]);
    }

    public function packages(Request $request): View
    {
        $subscriptions = ClubMemberSubscription::where('user_id', Auth::id())
            ->with([
                'package:id,name,cover_image,price,translations',
                'package.packageActivities:id,package_id,activity_id,instructor_id,schedule',
                'package.packageActivities.activity:id,name,translations',
                'package.packageActivities.instructor:id,user_id',
                'package.packageActivities.instructor.user:id,full_name,profile_picture,updated_at',
                'tenant:id,club_name,logo,currency,translations',
            ])
            ->latest()
            ->get();

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'personal.mobile.packages' : 'personal.desktop.packages', compact('subscriptions'));
    }

    public function progress(): View
    {
        $goals = Goal::where('user_id', Auth::id())->latest()->get();
        $goalStats = [
            'completed' => $goals->where('status', 'completed')->count(),
            'in_progress' => $goals->where('status', 'in_progress')->count(),
            'pending' => $goals->whereNotIn('status', ['completed', 'in_progress'])->count(),
        ];

        return view('personal.progress', compact('goals', 'goalStats'));
    }

    public function payments(): View
    {
        $subscriptions = ClubMemberSubscription::where('user_id', Auth::id())
            ->with(['package:id,name,translations', 'tenant:id,club_name,currency,translations'])
            ->latest()
            ->get();

        $totalPaid = (float) $subscriptions->sum('amount_paid');
        $totalDue = (float) $subscriptions->whereIn('payment_status', ['unpaid', 'pending_approval'])->sum('amount_due');

        return view('personal.payments', compact('subscriptions', 'totalPaid', 'totalDue'));
    }

    /**
     * Settle an outstanding bill: the member uploads proof of payment, which
     * moves the subscription to "pending approval" and notifies the club owner
     * to review it. The owner's approval (SubscriptionService::approvePayment)
     * marks it paid and stamps settled_at.
     */
    public function settlePayment(Request $request, \App\Models\ClubMemberSubscription $subscription): JsonResponse
    {
        // A bill may be settled by the member themselves, their guardian, or a
        // super-admin (the same people who can see the member's billing tab).
        $actor = Auth::user();
        $isSelf = $subscription->user_id === $actor->id;
        $isSuper = $actor->hasRole('super-admin');
        $isGuardian = ! $isSelf && \App\Models\UserRelationship::where('guardian_user_id', $actor->id)
            ->where('dependent_user_id', $subscription->user_id)
            ->exists();
        abort_unless($isSelf || $isSuper || $isGuardian, 403);

        if (! in_array($subscription->payment_status, ['unpaid', 'pending_approval'], true)) {
            return response()->json(['success' => false, 'message' => __('This bill cannot be settled.')], 422);
        }

        $validated = $request->validate([
            'payment_proof_base64' => ['required', 'string'],
        ]);

        $path = $this->storeBase64Image(
            $validated['payment_proof_base64'], 'payment-proofs', 'sub_'.$subscription->id.'_'.time(), 'local'
        );
        if (! $path) {
            return response()->json(['success' => false, 'message' => __('Please upload a valid image (JPG or PNG).')], 422);
        }

        // Replace any previous proof file so we don't orphan it in storage.
        if ($subscription->proof_of_payment && $subscription->proof_of_payment !== $path) {
            rescue(fn () => \Illuminate\Support\Facades\Storage::disk('local')->delete($subscription->proof_of_payment), null, false);
        }

        $subscription->update([
            'proof_of_payment' => $path,
            'payment_status' => 'pending_approval',
        ]);

        // Notify the club owner to review the payment.
        if ($ownerId = $subscription->tenant?->owner_user_id) {
            UserNotification::notifyUser(
                $ownerId,
                'payment_pending',
                __(':name submitted a payment to review.', ['name' => $subscription->user?->full_name ?? $actor->full_name]),
                [
                    'actor_id' => Auth::id(),
                    'tenant_id' => $subscription->tenant_id,
                    'icon' => 'bi-cash-coin',
                    'body' => $subscription->package?->tr('name') ?? __('Membership payment'),
                    'action_url' => route('admin.club.members', $subscription->tenant->slug ?? $subscription->tenant_id),
                ],
            );
        }

        return response()->json([
            'success' => true,
            'message' => __('Payment sent for review.'),
            'payment_status' => 'pending_approval',
            'subscription_id' => $subscription->id,
        ]);
    }

    public function events(): View
    {
        // DUMMY: curated sample events for design preview. Swap for the real
        // ClubEvent query (commented below) when the feature is wired in.
        $demo = $this->demoEvents();

        return view('personal.events', compact('demo'));
    }

    /**
     * DUMMY event detail page. Renders a curated sample event so the detail
     * view can be designed/previewed before the real ClubEvent feed is wired in.
     * Swap $events lookup for a real ClubEvent query when ready.
     */
    public function eventShow(int $event): View
    {
        $events = $this->demoEvents();
        $e = $events[$event] ?? $events[array_key_first($events)];

        return view('personal.event-show', ['e' => $e]);
    }

    /** DUMMY tournament brackets page (weight categories, draws, matches, podiums). */
    public function eventBracket(int $event): View
    {
        $events = $this->demoEvents();
        $e = $events[$event] ?? $events[array_key_first($events)];

        return view('personal.event-bracket', ['e' => $e, 'categories' => $e['categories'] ?? []]);
    }

    /** Shared curated dummy events (keyed by id) for the events list + detail. */
    public function demoEvents(): array
    {
        // Schema notes:
        //   type            human label (Class / Race / Belt Test / Tournament / Championship …)
        //   participant_fee what it costs to take part ('Free' or 'BHD x')
        //   spectator       null, or ['fee' => 'BHD x' | 'Free', 'count' => int] — pay-to-watch
        //   participants    people already signed up (name + meta like belt/seed)
        //   prize / divisions / requirements   extra blocks for comps & belt tests
        return [
            1 => [
                'id' => 1, 'day' => '24', 'mon' => 'Jun', 'wday' => 'Sat',
                'title' => 'Summer Sprint Cup', 'club' => 'Eta Athletics Club',
                'location' => 'Main Stadium · Manama', 'address' => 'Isa Town Sports City, Manama, Bahrain',
                'time' => '4:00 PM', 'end' => '7:30 PM', 'level' => 'Open', 'tag' => 'Race', 'type' => 'Competition',
                'icon' => 'bi-lightning-charge-fill', 'color' => '#7c3aed', 'going' => 48, 'cap' => 60,
                'participant_fee' => 'Free', 'spectator' => null, 'duration' => '3h 30m',
                'prize' => 'Medals · Top 3 each category',
                'about' => 'A high-energy 100m & 200m sprint competition open to all club members. Heats run through the afternoon with a finals showdown under the lights. Medals for the top three in each category, plus refreshments and a post-race social.',
                'agenda' => [
                    ['t' => '4:00 PM', 'd' => 'Check-in & warm-up'],
                    ['t' => '4:45 PM', 'd' => 'Qualifying heats'],
                    ['t' => '6:00 PM', 'd' => 'Semi-finals'],
                    ['t' => '6:45 PM', 'd' => 'Finals & medal ceremony'],
                ],
                'tags' => ['Sprint', 'Outdoor', 'Competitive', 'All ages'],
                'participants' => [
                    ['name' => 'Layla Ahmad', 'meta' => '100m · 200m'],
                    ['name' => 'Omar Khalid', 'meta' => '100m'],
                    ['name' => 'Sara Mansour', 'meta' => '200m'],
                    ['name' => 'Yousef Hadi', 'meta' => '100m'],
                    ['name' => 'Noor Salem', 'meta' => '200m'],
                ],
            ],
            2 => [
                'id' => 2, 'day' => '28', 'mon' => 'Jun', 'wday' => 'Wed',
                'title' => 'Karate Belt Grading', 'club' => 'Eta Martial Arts',
                'location' => 'Dojo · Block 412', 'address' => 'Eta Athletics Club, Block 412, Manama',
                'time' => '5:00 PM', 'end' => '7:00 PM', 'level' => 'White → Brown', 'tag' => 'Belt Test', 'type' => 'Belt Test',
                'icon' => 'bi-patch-check-fill', 'color' => '#f59e0b', 'going' => 18, 'cap' => 24,
                'participant_fee' => 'BHD 10', 'spectator' => ['fee' => 'Free', 'count' => 40], 'duration' => '2h',
                'about' => 'Official belt examination graded by Sensei Tariq. Demonstrate your kata, kihon and kumite to advance to the next belt. The grading fee covers your assessment, new belt and certificate. Family and friends are welcome to watch for free.',
                'requirements' => [
                    'Minimum 3 months at your current belt',
                    'Clean gi and current belt',
                    'Know your full kata sequence',
                    'Grading fee paid before exam day',
                ],
                'agenda' => [
                    ['t' => '5:00 PM', 'd' => 'Line-up & warm-up'],
                    ['t' => '5:20 PM', 'd' => 'Kihon (basics) assessment'],
                    ['t' => '6:00 PM', 'd' => 'Kata demonstration'],
                    ['t' => '6:30 PM', 'd' => 'Kumite & belt ceremony'],
                ],
                'tags' => ['Grading', 'Indoor', 'Official'],
                'participants' => [
                    ['name' => 'Omar Khalid', 'meta' => 'Green → Blue'],
                    ['name' => 'Maya Tariq', 'meta' => 'White → Yellow'],
                    ['name' => 'Ali Faris', 'meta' => 'Blue → Brown'],
                    ['name' => 'Sara Mansour', 'meta' => 'Yellow → Orange'],
                ],
            ],
            3 => [
                'id' => 3, 'day' => '03', 'mon' => 'Jul', 'wday' => 'Mon',
                'title' => 'Strength & Conditioning', 'club' => 'Eta Athletics Club',
                'location' => 'Gym Floor 1', 'address' => 'Eta Athletics Club, Block 412, Manama',
                'time' => '7:00 AM', 'end' => '8:00 AM', 'level' => 'All', 'tag' => 'Class', 'type' => 'Class',
                'icon' => 'bi-heart-pulse-fill', 'color' => '#10b981', 'going' => 15, 'cap' => 25,
                'participant_fee' => 'Free', 'spectator' => null, 'duration' => '1h',
                'about' => 'An early-morning full-body conditioning session to build power and endurance. Suitable for all levels — scale up or down with the coach.',
                'agenda' => [
                    ['t' => '7:00 AM', 'd' => 'Mobility & activation'],
                    ['t' => '7:20 AM', 'd' => 'Strength circuit'],
                    ['t' => '7:45 AM', 'd' => 'Conditioning finisher'],
                ],
                'tags' => ['Fitness', 'Indoor', 'Morning'],
                'participants' => [
                    ['name' => 'Dana Wael', 'meta' => 'Member'],
                    ['name' => 'Khalid Bader', 'meta' => 'Member'],
                ],
            ],
            4 => [
                'id' => 4, 'day' => '12', 'mon' => 'Jul', 'wday' => 'Wed',
                'title' => 'Club Boxing Championship', 'club' => 'Eta Boxing',
                'location' => 'Main Arena · Manama', 'address' => 'Eta Arena, Seef District, Manama, Bahrain',
                'time' => '6:00 PM', 'end' => '10:00 PM', 'level' => 'Amateur', 'tag' => 'Championship', 'type' => 'Championship',
                'icon' => 'bi-trophy-fill', 'color' => '#ef4444', 'going' => 32, 'cap' => 48,
                'participant_fee' => 'BHD 15', 'spectator' => ['fee' => 'BHD 5', 'count' => 210], 'duration' => '4h',
                'prize' => 'BHD 500 + Championship Belt',
                'about' => 'The annual club boxing championship — three weight divisions, full amateur rules, ringside doctor and certified referees. Fighters pay an entry fee that covers medicals, gloves and wraps. Spectators can buy a ticket to watch all the bouts ringside. Concessions available all night.',
                'divisions' => ['Lightweight (−60 kg)', 'Welterweight (−69 kg)', 'Heavyweight (+81 kg)'],
                'agenda' => [
                    ['t' => '6:00 PM', 'd' => 'Doors open · weigh-in check'],
                    ['t' => '6:45 PM', 'd' => 'Lightweight bouts'],
                    ['t' => '7:45 PM', 'd' => 'Welterweight bouts'],
                    ['t' => '8:45 PM', 'd' => 'Heavyweight bouts'],
                    ['t' => '9:30 PM', 'd' => 'Finals & belt ceremony'],
                ],
                'tags' => ['Boxing', 'Ticketed', 'Competitive', 'Prize'],
                'participants' => [
                    ['name' => 'Omar Khalid', 'meta' => 'Welterweight · #2 seed'],
                    ['name' => 'Hassan Tariq', 'meta' => 'Heavyweight · #1 seed'],
                    ['name' => 'Ali Faris', 'meta' => 'Lightweight'],
                    ['name' => 'Saad Mubarak', 'meta' => 'Welterweight'],
                    ['name' => 'Khalid Bader', 'meta' => 'Heavyweight'],
                ],
            ],
            5 => [
                'id' => 5, 'day' => '19', 'mon' => 'Jul', 'wday' => 'Sat',
                'title' => 'Open Padel Tournament', 'club' => 'Eta Racquet Club',
                'location' => 'Padel Courts 1–4', 'address' => 'Eta Racquet Club, Janabiyah, Bahrain',
                'time' => '9:00 AM', 'end' => '6:00 PM', 'level' => 'Open · Doubles', 'tag' => 'Tournament', 'type' => 'Tournament',
                'icon' => 'bi-trophy', 'color' => '#0ea5e9', 'going' => 28, 'cap' => 32,
                'participant_fee' => 'BHD 20 / team', 'spectator' => ['fee' => 'Free', 'count' => 75], 'duration' => 'Full day',
                'prize' => 'BHD 300 + trophies',
                'about' => 'A full-day open doubles padel tournament with group stages and knockout rounds. Register as a team of two — the fee covers court time, balls, referees and lunch. Open to all clubs and the public. Spectators watch free all day.',
                'divisions' => ['Mixed Doubles', 'Men’s Doubles', 'Women’s Doubles'],
                'agenda' => [
                    ['t' => '9:00 AM', 'd' => 'Group stage begins'],
                    ['t' => '12:30 PM', 'd' => 'Lunch break'],
                    ['t' => '1:30 PM', 'd' => 'Knockout rounds'],
                    ['t' => '5:00 PM', 'd' => 'Finals & prize giving'],
                ],
                'tags' => ['Padel', 'Doubles', 'Open', 'Prize'],
                'participants' => [
                    ['name' => 'Reem & Lina', 'meta' => 'Women’s · #1 seed'],
                    ['name' => 'Fahad & Saad', 'meta' => 'Men’s'],
                    ['name' => 'Omar & Yousef', 'meta' => 'Men’s · #2 seed'],
                    ['name' => 'Dana & Maya', 'meta' => 'Mixed'],
                ],
            ],
            6 => [
                'id' => 6, 'day' => '26', 'mon' => 'Jul', 'wday' => 'Sat',
                'title' => 'Grand Championship — Finals Night', 'club' => 'Eta Athletics Club',
                'location' => 'Grand Hall · Manama', 'address' => 'Eta Arena, Seef District, Manama, Bahrain',
                'time' => '7:00 PM', 'end' => '10:30 PM', 'level' => 'Elite', 'tag' => 'Championship', 'type' => 'Championship',
                'icon' => 'bi-award-fill', 'color' => '#7c3aed', 'going' => 16, 'cap' => 16,
                'participant_fee' => 'Qualified finalists', 'spectator' => ['fee' => 'BHD 8', 'count' => 430], 'duration' => '3h 30m',
                'prize' => 'BHD 1,000 + Grand Trophy',
                'about' => 'The season finale — only the 16 qualified finalists compete, so participation is by qualification, not sign-up. This is a marquee spectator night: buy a ticket to watch the finals across all disciplines, with a live DJ, awards gala and after-party. Tickets are limited.',
                'agenda' => [
                    ['t' => '7:00 PM', 'd' => 'Doors & red carpet'],
                    ['t' => '7:45 PM', 'd' => 'Finals — round 1'],
                    ['t' => '9:00 PM', 'd' => 'Championship finals'],
                    ['t' => '10:00 PM', 'd' => 'Awards gala & after-party'],
                ],
                'tags' => ['Elite', 'Ticketed', 'Gala', 'Limited'],
                'participants' => [
                    ['name' => 'Layla Ahmad', 'meta' => 'Sprint finalist'],
                    ['name' => 'Hassan Tariq', 'meta' => 'Boxing finalist'],
                    ['name' => 'Reem Al Najjar', 'meta' => 'Swim finalist'],
                ],
            ],
            7 => [
                'id' => 7, 'day' => '24', 'mon' => 'Jul', 'wday' => 'Thu',
                'title' => 'World Taekwondo Championship', 'club' => 'World Taekwondo Federation',
                'location' => 'National Arena · Manama', 'address' => 'Khalifa Sports City, Manama, Bahrain',
                'time' => '9:00 AM', 'end' => '9:00 PM', 'level' => 'International', 'tag' => 'World Championship', 'type' => 'World Championship',
                'icon' => 'bi-trophy-fill', 'color' => '#6d28d9', 'going' => 96, 'cap' => 128,
                'participant_fee' => 'BHD 25', 'spectator' => ['fee' => 'BHD 10', 'count' => 1240], 'duration' => '3 days',
                'prize' => '$10,000 + World Title',
                'about' => 'The official World Taekwondo Championship — Olympic-style sparring across six weight categories for both men and women. Athletes compete in single-elimination brackets seeded after the official weigh-in. Entry fee covers registration, medicals and equipment check. Spectators can buy a 3-day pass to watch every bout across all mats.',
                'divisions' => ['Men −58kg', 'Men −68kg', 'Men −80kg', 'Women −49kg', 'Women −57kg', 'Women −67kg'],
                'requirements' => [
                    'Valid national federation license',
                    'Make weight at the official weigh-in (Jul 22)',
                    'WT-approved protective gear (hogu, helmet, guards)',
                    'Entry fee paid before enrollment closes (Jul 20)',
                ],
                // Event lifecycle: enrollment → weigh-in → draw → competition → finals.
                'phases' => [
                    ['label' => 'Enrollment opens',      'date' => 'Jul 1',     'icon' => 'bi-megaphone',     'status' => 'done',     'note' => 'Registration & fee payment'],
                    ['label' => 'Enrollment closes',     'date' => 'Jul 20',    'icon' => 'bi-door-closed',   'status' => 'active',   'note' => 'Last day to sign up'],
                    ['label' => 'Official weigh-in',     'date' => 'Jul 22',    'icon' => 'bi-speedometer2',  'status' => 'upcoming', 'note' => 'Make your category weight'],
                    ['label' => 'Draw & brackets',       'date' => 'Jul 22',    'icon' => 'bi-diagram-3',     'status' => 'upcoming', 'note' => 'Seeding & bracket creation'],
                    ['label' => 'Competition days',      'date' => 'Jul 24–25', 'icon' => 'bi-trophy',        'status' => 'upcoming', 'note' => 'Prelims through semi-finals'],
                    ['label' => 'Finals & medals',       'date' => 'Jul 26',    'icon' => 'bi-award-fill',    'status' => 'upcoming', 'note' => 'Finals, podium & prizes'],
                ],
                'agenda' => [
                    ['t' => 'Jul 24', 'd' => 'Preliminary rounds — all categories'],
                    ['t' => 'Jul 25', 'd' => 'Quarter-finals & semi-finals'],
                    ['t' => 'Jul 26', 'd' => 'Finals, medal ceremony & prizes'],
                ],
                'tags' => ['Taekwondo', 'International', 'Ticketed', 'Olympic-style'],
                'participants' => [
                    ['name' => 'Omar Khalid', 'meta' => 'Men −58kg · #1 seed · BHR'],
                    ['name' => 'Ahmed Saleh', 'meta' => 'Men −58kg · #2 seed · EGY'],
                    ['name' => 'Min-jun Park', 'meta' => 'Men −58kg · #4 seed · KOR'],
                    ['name' => 'Jin-ho Lee', 'meta' => 'Men −68kg · #1 seed · KOR'],
                    ['name' => 'Sara Mansour', 'meta' => 'Women −49kg · BHR'],
                ],
                // Weight categories — each with its own enrollment + bracket state.
                'categories' => $this->demoTkdCategories(),
            ],
        ];
    }

    /** DUMMY taekwondo weight categories with brackets / rosters / podiums. */
    public function demoTkdCategories(): array
    {
        return [
            'm58' => [
                'key' => 'm58', 'name' => 'Men −58 kg', 'class' => 'Fin weight',
                'cap' => 8, 'joined' => 8, 'open' => 0, 'status' => 'live',
                'note' => 'Semi-finals in progress on Mat 1',
                'rounds' => [
                    ['name' => 'Quarter-finals', 'matches' => [
                        ['court' => 'Mat 1', 'time' => '10:00', 'status' => 'done', 'winner' => 'a',
                            'a' => ['name' => 'Omar Khalid',  'country' => 'BHR', 'seed' => 1, 'score' => '2'],
                            'b' => ['name' => 'Diego Santos',  'country' => 'BRA', 'seed' => 8, 'score' => '0']],
                        ['court' => 'Mat 1', 'time' => '10:30', 'status' => 'done', 'winner' => 'b',
                            'a' => ['name' => 'Min-jun Park',  'country' => 'KOR', 'seed' => 4, 'score' => '1'],
                            'b' => ['name' => 'Ivan Petrov',   'country' => 'RUS', 'seed' => 5, 'score' => '2']],
                        ['court' => 'Mat 2', 'time' => '11:00', 'status' => 'done', 'winner' => 'a',
                            'a' => ['name' => 'Carlos Ruiz',   'country' => 'ESP', 'seed' => 3, 'score' => '2'],
                            'b' => ['name' => 'Yuki Tanaka',   'country' => 'JPN', 'seed' => 6, 'score' => '1']],
                        ['court' => 'Mat 2', 'time' => '11:30', 'status' => 'done', 'winner' => 'a',
                            'a' => ['name' => 'Ahmed Saleh',   'country' => 'EGY', 'seed' => 2, 'score' => '2'],
                            'b' => ['name' => 'Sami Haddad',   'country' => 'LBN', 'seed' => 7, 'score' => '0']],
                    ]],
                    ['name' => 'Semi-finals', 'matches' => [
                        ['court' => 'Mat 1', 'time' => '14:00', 'status' => 'live', 'winner' => null,
                            'a' => ['name' => 'Omar Khalid', 'country' => 'BHR', 'seed' => 1, 'score' => '1'],
                            'b' => ['name' => 'Ivan Petrov', 'country' => 'RUS', 'seed' => 5, 'score' => '1']],
                        ['court' => 'Mat 1', 'time' => '14:45', 'status' => 'upcoming', 'winner' => null,
                            'a' => ['name' => 'Carlos Ruiz', 'country' => 'ESP', 'seed' => 3, 'score' => '–'],
                            'b' => ['name' => 'Ahmed Saleh', 'country' => 'EGY', 'seed' => 2, 'score' => '–']],
                    ]],
                    ['name' => 'Final', 'matches' => [
                        ['court' => 'Mat 1', 'time' => '17:00', 'status' => 'upcoming', 'winner' => null,
                            'a' => ['name' => 'Winner SF1', 'country' => '', 'seed' => null, 'score' => '–'],
                            'b' => ['name' => 'Winner SF2', 'country' => '', 'seed' => null, 'score' => '–']],
                    ]],
                ],
            ],
            'm68' => [
                'key' => 'm68', 'name' => 'Men −68 kg', 'class' => 'Feather weight',
                'cap' => 8, 'joined' => 8, 'open' => 0, 'status' => 'completed',
                'note' => 'Completed — Jin-ho Lee takes gold',
                'podium' => [
                    ['place' => 1, 'name' => 'Jin-ho Lee',   'country' => 'KOR', 'prize' => '$10,000 + Gold'],
                    ['place' => 2, 'name' => 'Mehdi Karimi',  'country' => 'IRI', 'prize' => '$5,000 + Silver'],
                    ['place' => 3, 'name' => 'Tariq Bin Saad', 'country' => 'KSA', 'prize' => '$2,500 + Bronze'],
                ],
                'rounds' => [
                    ['name' => 'Semi-finals', 'matches' => [
                        ['court' => 'Mat 2', 'time' => '15:00', 'status' => 'done', 'winner' => 'a',
                            'a' => ['name' => 'Jin-ho Lee',     'country' => 'KOR', 'seed' => 1, 'score' => '2'],
                            'b' => ['name' => 'Tariq Bin Saad', 'country' => 'KSA', 'seed' => 4, 'score' => '1']],
                        ['court' => 'Mat 2', 'time' => '15:45', 'status' => 'done', 'winner' => 'b',
                            'a' => ['name' => 'Luca Moretti',   'country' => 'ITA', 'seed' => 3, 'score' => '0'],
                            'b' => ['name' => 'Mehdi Karimi',   'country' => 'IRI', 'seed' => 2, 'score' => '2']],
                    ]],
                    ['name' => 'Final', 'matches' => [
                        ['court' => 'Mat 1', 'time' => '18:00', 'status' => 'done', 'winner' => 'a',
                            'a' => ['name' => 'Jin-ho Lee',   'country' => 'KOR', 'seed' => 1, 'score' => '2'],
                            'b' => ['name' => 'Mehdi Karimi', 'country' => 'IRI', 'seed' => 2, 'score' => '1']],
                    ]],
                ],
            ],
            'w49' => [
                'key' => 'w49', 'name' => 'Women −49 kg', 'class' => 'Fin weight',
                'cap' => 8, 'joined' => 5, 'open' => 3, 'status' => 'enrolling',
                'note' => 'Bracket is drawn after weigh-in (Jul 22)',
                'roster' => [
                    ['name' => 'Sara Mansour',  'country' => 'BHR'],
                    ['name' => 'Hana Suzuki',   'country' => 'JPN'],
                    ['name' => 'Elena Volkova',  'country' => 'RUS'],
                    ['name' => 'Mariam Sayed',   'country' => 'EGY'],
                    ['name' => 'Noor Salem',     'country' => 'BHR'],
                ],
            ],
        ];
    }

    /** DEMO news-feed: curated club posts (All) + member posts (Following/Mine). */
    public function demoFeed(?\App\Models\User $me = null): array
    {
        $now = \Carbon\Carbon::now();
        $myName = $me?->full_name ?? 'You';
        $myAvatar = $me && $me->profile_picture
            ? asset('storage/'.$me->profile_picture).'?v='.optional($me->updated_at)->timestamp
            : null;

        // Member-shaped post factory (matches UserPost::toFeedArray + post-card).
        $mem = function ($id, $hours, $name, $isMe, $body, $likes, $comments, $avatar = null) use ($now, $myAvatar) {
            return [
                'id' => $id,
                'demo' => true,
                'type' => 'member',
                'author' => [
                    'id' => $id,
                    'slug' => 'demo-'.$id,
                    'name' => $name,
                    'avatar' => $isMe ? $myAvatar : $avatar,
                    'url' => '#',
                    'isMe' => $isMe,
                ],
                'time' => $now->copy()->subHours($hours)->diffForHumans(),
                'edited' => false,
                'body' => $body,
                'editing' => false,
                'draft' => '',
                'images' => [],
                'likes' => $likes,
                'liked' => false,
                'comments' => $comments,
                'showComments' => false,
                'commentDraft' => '',
                'url' => '#',
            ];
        };

        $following = [
            $mem(95001, 2, 'Coach Adam', false, "Form check 📹 — keep your core braced and drive through the heels on every squat. Small fix, big difference. Who's training legs today? 🦵", 41, [
                ['id' => 1, 'name' => 'Sara M.', 'avatar' => null, 'body' => 'Needed this reminder, thanks Coach!'],
                ['id' => 2, 'name' => 'Omar K.', 'avatar' => null, 'body' => 'Legs today 🔥'],
            ]),
            $mem(95002, 6, 'Layla Ahmad', false, "New 5K PB this morning — 22:48! ☀️🏃‍♀️ Six months ago I couldn't run 1K without stopping. Consistency really does pay off.", 88, [
                ['id' => 3, 'name' => 'Noor S.', 'avatar' => null, 'body' => 'Amazing progress 👏'],
            ]),
            $mem(95003, 14, 'Yousef Hadi', false, 'Anyone up for a doubles padel match this weekend? Looking for 2 more players 🎾', 17, []),
        ];

        $mine = [
            $mem(96001, 3, $myName, true, 'Hit a new bench press PR today — 80kg for 3 reps 💪 Slow and steady. Next stop: 85.', 32, [
                ['id' => 9, 'name' => 'Coach Adam', 'avatar' => null, 'body' => "That's the way — proud of you!"],
            ]),
            $mem(96002, 26, $myName, true, 'Rest day done right: mobility, a long walk and an early night. 😴 Recovery is part of the program.', 14, []),
        ];

        $mk = function ($id, $hours, $club, $category, $body, $likes, $comments, $cover = null, $commentList = []) use ($now) {
            return [
                'type' => 'club',
                'id' => $id,
                'ts' => $now->copy()->subHours($hours)->timestamp,
                'club' => ['name' => $club, 'logo' => null],
                'category' => $category,
                'time' => $now->copy()->subHours($hours)->diffForHumans(),
                'body' => $body,
                'image' => null,
                'cover' => $cover,
                'likes' => $likes,
                'comments' => $comments,
                'commentList' => $commentList,
            ];
        };

        $posts = [
            $mk(90001, 1, 'Eta Athletics Club', 'Match Day', "🏆 What a finish! Our sprint squad swept the podium at today's Summer Cup — 3 golds and a new club record in the 100m. Proud of every single athlete. #EtaPride", 142, 23,
                ['color' => '#7c3aed', 'icon' => 'bi-trophy-fill', 'label' => 'Summer Cup · 3 Golds'],
                [
                    ['id' => 101, 'name' => 'Sara Mansour', 'avatar' => null, 'body' => 'So proud of the team! 🥇'],
                    ['id' => 102, 'name' => 'Omar Khalid', 'avatar' => null, 'body' => 'That 100m record was insane 🔥'],
                ]),
            $mk(90002, 4, 'Coach Adam', 'Tip of the day', "Recovery is where the gains happen. Aim for 7–9 hours of sleep, hydrate well, and don't skip your mobility work. Your body will thank you tomorrow. 💪", 64, 8, null,
                [['id' => 103, 'name' => 'Layla Ahmad', 'avatar' => null, 'body' => 'Saving this 🙌']]),
            $mk(90003, 7, 'Eta Athletics Club', 'Event', '📣 New this week: Sunrise Yoga every Sunday at 8 AM on the rooftop. Limited to 15 spots — book through the app. Namaste 🧘', 38, 5,
                ['color' => '#10b981', 'icon' => 'bi-flower1', 'label' => 'Sunrise Yoga · Sundays']),
            $mk(90004, 11, 'Boxing Team', 'Milestone', "Big shoutout to Omar K. for landing his 10th win this season! 🥊 The grind never stops. Who's next in the ring?", 97, 19, null,
                [['id' => 104, 'name' => 'Yousef Hadi', 'avatar' => null, 'body' => 'Legend 👏']]),
            $mk(90005, 20, 'FuelLab', 'Community', 'Fuel feature 🥤 — your favourite post-workout shake just got a new strawberry flavour. Drop a 💗 if you want us to stock it at the club café!', 51, 12,
                ['color' => '#ec4899', 'icon' => 'bi-cup-straw', 'label' => 'New Flavour Drop']),
            $mk(90006, 28, 'Eta Athletics Club', 'Throwback', "#ThrowbackThursday to last month's regional friendly. The energy in the stands was unreal — thank you to everyone who came out to support. 🙌", 73, 9, null),
        ];

        return compact('posts', 'following', 'mine');
    }

    public function market(Request $request): View
    {
        // Real products from the clubs this member belongs to.
        $clubIds = $this->clubIds();

        $items = \App\Models\ClubProduct::whereIn('tenant_id', $clubIds)
            ->where('status', 'published')
            ->orderByDesc('featured')->latest()
            ->get();

        $products = $items->mapWithKeys(fn ($p) => [$p->id => $p->toCardArray()])->all();

        // Categories: 'All' + the categories the club(s) defined, limited to those
        // that actually have products.
        $usedCats = $items->pluck('category')->unique();
        $defined = \App\Models\ClubProductCategory::whereIn('tenant_id', $clubIds)
            ->whereIn('key', $usedCats)
            ->orderBy('sort')->get()->unique('key');

        $categories = collect([['key' => 'all', 'label' => __('market.cat_all'), 'icon' => 'bi-grid-1x2']])
            ->concat($defined->map(fn ($c) => ['key' => $c->key, 'label' => $c->label, 'icon' => $c->icon]))
            ->values()->all();

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'personal.mobile.market' : 'personal.desktop.market', compact('products', 'categories'));
    }

    public function marketShow(int $product, Request $request): View
    {
        $model = \App\Models\ClubProduct::where('status', 'published')->findOrFail($product);
        $p = $model->toCardArray();

        $related = \App\Models\ClubProduct::where('tenant_id', $model->tenant_id)
            ->where('status', 'published')
            ->where('category', $model->category)
            ->where('id', '!=', $model->id)
            ->limit(4)->get()
            ->map(fn ($r) => $r->toCardArray())->all();

        // Real reviews: each product rating, with the buyer's name + their order
        // comment (the comment lives on the order's seller review).
        $rows = \App\Models\ProductReview::where('club_product_id', $model->id)
            ->with('user:id,full_name,profile_picture,updated_at')
            ->latest()->limit(30)->get();

        $comments = \App\Models\OrderReview::whereIn('order_id', $rows->pluck('order_id')->filter()->unique())
            ->get()->keyBy(fn ($r) => $r->order_id.'-'.$r->user_id);

        $reviews = $rows->map(function ($r) use ($comments) {
            $u = $r->user;
            $name = $u?->full_name ?? __('admin.fin_member');

            return [
                'name' => $name,
                'initials' => mb_strtoupper(mb_substr(strtok($name, ' '), 0, 1).mb_substr(strstr($name, ' ') ?: '', 1, 1)),
                'avatar' => $u && $u->profile_picture
                    ? asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp : null,
                'rating' => (int) $r->rating,
                'comment' => optional($comments->get($r->order_id.'-'.$r->user_id))->comment,
                'time' => $r->created_at->diffForHumans(),
            ];
        })->values()->all();

        // Star distribution (5→1) as percentages of total ratings.
        $dist = \App\Models\ProductReview::where('club_product_id', $model->id)
            ->selectRaw('rating, COUNT(*) as c')->groupBy('rating')->pluck('c', 'rating');
        $total = max(1, (int) $model->rating_count);
        $breakdown = [];
        for ($s = 5; $s >= 1; $s--) {
            $breakdown[$s] = (int) round((($dist[$s] ?? 0) / $total) * 100);
        }

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'personal.mobile.market-show' : 'personal.desktop.market-show', compact('p', 'related', 'reviews', 'breakdown'));
    }

    /** Shared curated dummy marketplace — categories + products (keyed by id). */
    public function demoMarket(): array
    {
        $categories = [
            ['key' => 'all',       'label' => 'All',       'icon' => 'bi-grid-1x2'],
            ['key' => 'gear',      'label' => 'Gear',      'icon' => 'bi-bag'],
            ['key' => 'equipment', 'label' => 'Equipment', 'icon' => 'bi-bicycle'],
            ['key' => 'nutrition', 'label' => 'Nutrition', 'icon' => 'bi-cup-hot'],
            ['key' => 'passes',    'label' => 'Passes',    'icon' => 'bi-ticket-perforated'],
            ['key' => 'apparel',   'label' => 'Apparel',   'icon' => 'bi-person-arms-up'],
        ];

        $products = [
            1 => [
                'id' => 1, 'cat' => 'gear', 'name' => 'Pro Boxing Gloves', 'brand' => 'TAKEONE Sport',
                'price' => 28.0, 'old' => 38.0, 'rating' => 4.8, 'reviews' => 124, 'icon' => 'bi-trophy',
                'color' => '#7c3aed', 'badge' => 'Sale', 'stock' => 'In stock', 'featured' => true,
                'desc' => 'Premium 12oz sparring gloves with multi-layer foam, moisture-wicking lining and a secure wrist strap. Built for daily training.',
                'specs' => [['Weight', '12 oz'], ['Material', 'Vegan leather'], ['Closure', 'Velcro strap'], ['Warranty', '1 year']],
                'colors' => ['#7c3aed', '#ef4444', '#111827'],
            ],
            2 => [
                'id' => 2, 'cat' => 'equipment', 'name' => 'Adjustable Dumbbell 24kg', 'brand' => 'IronCore',
                'price' => 95.0, 'old' => null, 'rating' => 4.9, 'reviews' => 88, 'icon' => 'bi-bicycle',
                'color' => '#0ea5e9', 'badge' => null, 'stock' => 'In stock', 'featured' => true,
                'desc' => 'One dumbbell, fifteen weights. Dial from 2.5kg to 24kg in seconds — perfect for a compact home gym.',
                'specs' => [['Range', '2.5–24 kg'], ['Increments', '2.5 kg'], ['Handle', 'Knurled steel'], ['Warranty', '2 years']],
                'colors' => ['#111827'],
            ],
            3 => [
                'id' => 3, 'cat' => 'nutrition', 'name' => 'Whey Protein · 1kg', 'brand' => 'FuelLab',
                'price' => 22.0, 'old' => 26.0, 'rating' => 4.7, 'reviews' => 256, 'icon' => 'bi-cup-hot',
                'color' => '#f59e0b', 'badge' => 'Sale', 'stock' => 'In stock', 'featured' => false,
                'desc' => '24g of protein per scoop, low sugar, mixes smooth. Chocolate, vanilla and strawberry.',
                'specs' => [['Protein', '24 g / scoop'], ['Servings', '33'], ['Flavour', 'Chocolate'], ['Vegetarian', 'Yes']],
                'colors' => ['#7c3aed', '#f59e0b', '#ec4899'],
            ],
            4 => [
                'id' => 4, 'cat' => 'passes', 'name' => '10-Session Class Pass', 'brand' => 'Eta Athletics',
                'price' => 45.0, 'old' => 60.0, 'rating' => 5.0, 'reviews' => 42, 'icon' => 'bi-ticket-perforated',
                'color' => '#10b981', 'badge' => 'Best value', 'stock' => 'Digital', 'featured' => true,
                'desc' => 'Ten drop-in sessions to use across any group class. Valid for 3 months — share with family.',
                'specs' => [['Sessions', '10'], ['Valid', '3 months'], ['Transferable', 'Family'], ['Delivery', 'Instant']],
                'colors' => ['#10b981'],
            ],
            5 => [
                'id' => 5, 'cat' => 'apparel', 'name' => 'Performance Training Tee', 'brand' => 'TAKEONE Sport',
                'price' => 16.0, 'old' => null, 'rating' => 4.6, 'reviews' => 73, 'icon' => 'bi-person-arms-up',
                'color' => '#ec4899', 'badge' => 'New', 'stock' => 'In stock', 'featured' => false,
                'desc' => 'Breathable quick-dry tee with a relaxed athletic cut. Stays light through the toughest sessions.',
                'specs' => [['Fabric', 'Quick-dry poly'], ['Fit', 'Athletic'], ['Sizes', 'XS–XXL'], ['Care', 'Machine wash']],
                'colors' => ['#111827', '#ec4899', '#0ea5e9', '#10b981'],
            ],
            6 => [
                'id' => 6, 'cat' => 'gear', 'name' => 'Speed Jump Rope', 'brand' => 'IronCore',
                'price' => 9.0, 'old' => 14.0, 'rating' => 4.5, 'reviews' => 310, 'icon' => 'bi-lightning-charge-fill',
                'color' => '#ef4444', 'badge' => 'Sale', 'stock' => 'In stock', 'featured' => false,
                'desc' => 'Ball-bearing speed rope with adjustable length and anti-slip handles. Built for double-unders.',
                'specs' => [['Length', 'Adjustable'], ['Bearing', 'Dual ball'], ['Cable', 'Coated steel'], ['Handles', 'Anti-slip']],
                'colors' => ['#ef4444', '#111827'],
            ],
            7 => [
                'id' => 7, 'cat' => 'equipment', 'name' => 'Yoga & Recovery Mat', 'brand' => 'FlowState',
                'price' => 19.0, 'old' => null, 'rating' => 4.8, 'reviews' => 145, 'icon' => 'bi-grid',
                'color' => '#10b981', 'badge' => null, 'stock' => 'In stock', 'featured' => false,
                'desc' => 'Extra-thick 6mm non-slip mat with alignment lines. Cushions joints for floor work and stretching.',
                'specs' => [['Thickness', '6 mm'], ['Size', '183 × 61 cm'], ['Surface', 'Non-slip'], ['Strap', 'Included']],
                'colors' => ['#10b981', '#7c3aed', '#111827'],
            ],
            8 => [
                'id' => 8, 'cat' => 'nutrition', 'name' => 'Electrolyte Hydration Mix', 'brand' => 'FuelLab',
                'price' => 12.0, 'old' => 15.0, 'rating' => 4.4, 'reviews' => 67, 'icon' => 'bi-droplet-half',
                'color' => '#0ea5e9', 'badge' => 'Sale', 'stock' => 'In stock', 'featured' => false,
                'desc' => 'Sugar-free electrolyte sachets to keep you hydrated through long sessions. 20 sticks per box.',
                'specs' => [['Sticks', '20'], ['Sugar', '0 g'], ['Flavour', 'Citrus'], ['Caffeine', 'None']],
                'colors' => ['#0ea5e9', '#f59e0b'],
            ],
        ];

        return compact('categories', 'products');
    }

    public function challenge(): View
    {
        // DUMMY: curated sample challenges + 1v1 duels for design preview.
        $challenges = $this->demoChallenges();
        $duels = $this->demoDuels();

        return view('personal.challenge', compact('challenges', 'duels'));
    }

    /**
     * DUMMY challenge detail page. Renders a curated sample challenge so the
     * detail view can be designed/previewed before a real Challenge model exists.
     */
    public function challengeShow(int $challenge): View
    {
        $challenges = $this->demoChallenges();
        $c = $challenges[$challenge] ?? $challenges[array_key_first($challenges)];

        return view('personal.challenge-show', ['c' => $c]);
    }

    /** Shared curated dummy challenges (keyed by id) for the list + detail. */
    public function demoChallenges(): array
    {
        return [
            1 => [
                'id' => 1, 'status' => 'active', 'title' => '10K Steps a Day',
                'club' => 'Eta Athletics Club', 'tag' => 'Cardio', 'icon' => 'bi-activity',
                'color' => '#7c3aed', 'progress' => 64, 'metric' => 'steps',
                'current' => 6400, 'goal' => 10000, 'unit' => '',
                'days_left' => 4, 'points' => 250, 'participants' => 128, 'rank' => 12,
                'streak' => 9, 'joined' => true,
                'about' => 'Hit 10,000 steps every day for a full week. Sync from any tracker or log manually. Keep your streak alive to earn bonus points and a badge.',
                'rules' => [
                    'Log at least 10,000 steps each day',
                    'A missed day breaks your streak (not your entry)',
                    'Manual or auto-synced steps both count',
                ],
                'rewards' => [
                    ['icon' => 'bi-award-fill', 'label' => 'Strider badge', 'sub' => 'On completion'],
                    ['icon' => 'bi-star-fill',  'label' => '250 points',    'sub' => 'Added to profile'],
                    ['icon' => 'bi-fire',       'label' => 'Streak bonus',  'sub' => '+10 / day'],
                ],
                'leaders' => [
                    ['name' => 'Layla A.', 'val' => '70,200', 'pts' => 690],
                    ['name' => 'Omar K.',  'val' => '68,900', 'pts' => 670],
                    ['name' => 'Sara M.',  'val' => '64,100', 'pts' => 640],
                    ['name' => 'You',      'val' => '44,800', 'pts' => 250, 'me' => true],
                ],
            ],
            2 => [
                'id' => 2, 'status' => 'active', 'title' => 'Weekend Warrior',
                'club' => 'Eta Athletics Club', 'tag' => 'Attendance', 'icon' => 'bi-calendar2-check',
                'color' => '#0ea5e9', 'progress' => 33, 'metric' => 'sessions',
                'current' => 1, 'goal' => 3, 'unit' => '',
                'days_left' => 2, 'points' => 150, 'participants' => 64, 'rank' => 21,
                'streak' => 2, 'joined' => true,
                'about' => 'Attend 3 weekend training sessions this month. Check in at the club to log attendance automatically.',
                'rules' => [
                    'Attend any 3 Saturday or Sunday sessions',
                    'Check-in at reception counts your session',
                    'Sessions must be in the same calendar month',
                ],
                'rewards' => [
                    ['icon' => 'bi-award-fill', 'label' => 'Warrior badge', 'sub' => 'On completion'],
                    ['icon' => 'bi-star-fill',  'label' => '150 points',    'sub' => 'Added to profile'],
                ],
                'leaders' => [
                    ['name' => 'Yousef H.', 'val' => '3/3', 'pts' => 150],
                    ['name' => 'Noor S.',   'val' => '2/3', 'pts' => 100],
                    ['name' => 'You',       'val' => '1/3', 'pts' => 50, 'me' => true],
                ],
            ],
            3 => [
                'id' => 3, 'status' => 'upcoming', 'title' => 'Summer Plank Off',
                'club' => 'Eta Athletics Club', 'tag' => 'Strength', 'icon' => 'bi-stopwatch',
                'color' => '#f59e0b', 'progress' => 0, 'metric' => 'seconds',
                'current' => 0, 'goal' => 300, 'unit' => 's',
                'days_left' => 6, 'points' => 200, 'participants' => 41, 'rank' => null,
                'streak' => 0, 'joined' => false, 'starts_in' => 'Starts in 6 days',
                'about' => 'Build up to a 5-minute plank hold over two weeks. Log your best hold each day and watch your endurance climb.',
                'rules' => [
                    'Log your longest plank hold daily',
                    'Best single hold of the challenge counts',
                    'Form check videos optional but encouraged',
                ],
                'rewards' => [
                    ['icon' => 'bi-award-fill', 'label' => 'Iron Core badge', 'sub' => 'Reach 5:00'],
                    ['icon' => 'bi-star-fill',  'label' => '200 points',      'sub' => 'Added to profile'],
                ],
                'leaders' => [],
            ],
            4 => [
                'id' => 4, 'status' => 'completed', 'title' => 'Spring 5K Streak',
                'club' => 'Eta Athletics Club', 'tag' => 'Running', 'icon' => 'bi-trophy',
                'color' => '#10b981', 'progress' => 100, 'metric' => 'runs',
                'current' => 10, 'goal' => 10, 'unit' => '',
                'days_left' => 0, 'points' => 300, 'participants' => 96, 'rank' => 3,
                'streak' => 10, 'joined' => true, 'completed' => true,
                'about' => 'Completed! You ran 10 × 5K sessions through spring and finished 3rd overall. Your badge and points have been added to your profile.',
                'rules' => [
                    'Run 10 separate 5K sessions',
                    'GPS or treadmill both count',
                    'One 5K per day maximum',
                ],
                'rewards' => [
                    ['icon' => 'bi-award-fill', 'label' => 'Spring Runner badge', 'sub' => 'Earned'],
                    ['icon' => 'bi-star-fill',  'label' => '300 points',          'sub' => 'Earned'],
                    ['icon' => 'bi-trophy-fill', 'label' => 'Podium · 3rd',        'sub' => 'Earned'],
                ],
                'leaders' => [
                    ['name' => 'Fahad N.', 'val' => '10/10', 'pts' => 360],
                    ['name' => 'Reem A.',  'val' => '10/10', 'pts' => 340],
                    ['name' => 'You',      'val' => '10/10', 'pts' => 300, 'me' => true],
                ],
            ],
        ];
    }

    /** DUMMY 1v1 / head-to-head challenge detail (versus). */
    public function duelShow(int $duel): View
    {
        $duels = $this->demoDuels();
        $d = $duels[$duel] ?? $duels[array_key_first($duels)];

        return view('personal.duel-show', ['d' => $d]);
    }

    /** DUMMY "create a challenge & invite a challenger" form. */
    public function challengeCreate(): View
    {
        // Sample club members you could challenge.
        $opponents = [
            ['name' => 'Omar Khalid',   'initials' => 'OK', 'record' => '9W · 2L', 'tag' => 'Boxing',   'club' => 'Eta Athletics', 'city' => 'Manama'],
            ['name' => 'Sara Mansour',  'initials' => 'SM', 'record' => '5W · 1L', 'tag' => 'Sprint',   'club' => 'Eta Athletics', 'city' => 'Manama'],
            ['name' => 'Yousef Hadi',   'initials' => 'YH', 'record' => '4W · 4L', 'tag' => 'MMA',      'club' => 'Eta Athletics', 'city' => 'Manama'],
            ['name' => 'Noor Salem',    'initials' => 'NS', 'record' => '6W · 0L', 'tag' => 'Running',  'club' => 'Eta Athletics', 'city' => 'Manama'],
            ['name' => 'Fahad Nasser',  'initials' => 'FN', 'record' => '3W · 5L', 'tag' => 'Cycling',  'club' => 'Eta Athletics', 'city' => 'Manama'],
            ['name' => 'Layla Ahmad',   'initials' => 'LA', 'record' => '8W · 1L', 'tag' => 'Swimming', 'club' => 'Eta Athletics', 'city' => 'Manama'],
        ];

        // Platform-wide athletes from OTHER clubs / cities you can also challenge.
        $athletes = [
            ['name' => 'Ali Rahimi',     'initials' => 'AR', 'record' => '12W · 3L', 'tag' => 'Boxing',     'club' => 'Riffa Fight Club',   'city' => 'Riffa',     'verified' => true],
            ['name' => 'Mariam Sayed',   'initials' => 'MS', 'record' => '7W · 2L',  'tag' => 'Sprint',     'club' => 'Gulf Track Academy', 'city' => 'Dubai',     'verified' => true],
            ['name' => 'Hassan Tariq',   'initials' => 'HT', 'record' => '15W · 5L', 'tag' => 'MMA',        'club' => 'Desert MMA',         'city' => 'Riyadh',    'verified' => false],
            ['name' => 'Dana Wael',      'initials' => 'DW', 'record' => '9W · 1L',  'tag' => 'CrossFit',   'club' => 'Pulse Strength',     'city' => 'Doha',      'verified' => true],
            ['name' => 'Karim Mostafa',  'initials' => 'KM', 'record' => '6W · 6L',  'tag' => 'Cycling',    'club' => 'Cairo Riders',       'city' => 'Cairo',     'verified' => false],
            ['name' => 'Reem Al Najjar', 'initials' => 'RN', 'record' => '11W · 0L', 'tag' => 'Swimming',   'club' => 'Aqua Elite',         'city' => 'Kuwait City', 'verified' => true],
            ['name' => 'Tariq Bin Saad', 'initials' => 'TS', 'record' => '4W · 2L',  'tag' => 'Powerlift',  'club' => 'Iron House',         'city' => 'Jeddah',    'verified' => false],
        ];

        return view('personal.challenge-create', compact('opponents', 'athletes'));
    }

    /** DUMMY challenge results & history (solo + versus, completed). */
    public function challengeHistory(): View
    {
        $duels = collect($this->demoDuels())->where('status', 'completed')->values()->all();
        $solo = collect($this->demoChallenges())->where('status', 'completed')->values()->all();

        return view('personal.challenge-history', compact('duels', 'solo'));
    }

    /** Shared curated dummy 1v1 duels (keyed by id) for hub, detail & history. */
    public function demoDuels(): array
    {
        $me = ['name' => 'You', 'initials' => 'YO', 'record' => '7W · 3L'];

        return [
            1 => [
                'id' => 1, 'kind' => 'versus', 'type' => 'fight', 'status' => 'invite_incoming',
                'discipline' => 'Boxing Spar — 3 Rounds', 'icon' => 'bi-trophy', 'color' => '#ef4444',
                'me' => $me,
                'opponent' => ['name' => 'Omar Khalid', 'initials' => 'OK', 'record' => '9W · 2L', 'rank' => '#4'],
                'metric' => 'Best of 3 rounds', 'stake' => '200 pts + bragging rights',
                'deadline' => 'Sat, Jun 26 · 6:00 PM', 'location' => 'Ring A · Eta Athletics',
                'message' => 'Think you can last 3 rounds with me? Let’s settle this Saturday. 🥊',
                'when' => '2h ago',
            ],
            2 => [
                'id' => 2, 'kind' => 'versus', 'type' => 'athletic', 'status' => 'active',
                'discipline' => '100m Sprint Duel', 'icon' => 'bi-lightning-charge-fill', 'color' => '#7c3aed',
                'me' => $me + ['score' => '12.84s', 'pct' => 72],
                'opponent' => ['name' => 'Sara Mansour', 'initials' => 'SM', 'record' => '5W · 1L', 'rank' => '#8', 'score' => '13.02s', 'pct' => 64],
                'metric' => 'Best of 3 timed runs', 'stake' => '150 pts',
                'deadline' => '2 days left', 'location' => 'Main Track',
                'message' => 'Two runs each logged. One to go — bring it. 🏃',
                'leading' => 'me', 'when' => 'Started 3 days ago',
            ],
            3 => [
                'id' => 3, 'kind' => 'versus', 'type' => 'athletic', 'status' => 'invite_sent',
                'discipline' => '500m Row Challenge', 'icon' => 'bi-water', 'color' => '#0ea5e9',
                'me' => $me,
                'opponent' => ['name' => 'Yousef Hadi', 'initials' => 'YH', 'record' => '4W · 4L', 'rank' => '#11'],
                'metric' => 'Fastest 500m row', 'stake' => '100 pts',
                'deadline' => 'Expires in 3 days', 'location' => 'Rowing Studio',
                'message' => 'Sent them a 500m row duel — waiting on a reply.',
                'when' => 'Sent 5h ago',
            ],
            4 => [
                'id' => 4, 'kind' => 'versus', 'type' => 'fight', 'status' => 'completed',
                'discipline' => 'Grappling Match', 'icon' => 'bi-trophy', 'color' => '#10b981',
                'me' => $me + ['score' => '2', 'pct' => 100],
                'opponent' => ['name' => 'Khalid Bader', 'initials' => 'KB', 'record' => '6W · 6L', 'rank' => '#9', 'score' => '1', 'pct' => 50],
                'metric' => 'Best of 3 submissions', 'stake' => '180 pts',
                'result' => 'win', 'final' => '2 — 1', 'points_earned' => 180,
                'date' => 'Jun 12', 'location' => 'Mat Room',
                'message' => 'Won 2–1. Tight one in the final round.',
                'when' => 'Jun 12',
            ],
            5 => [
                'id' => 5, 'kind' => 'versus', 'type' => 'athletic', 'status' => 'completed',
                'discipline' => '5K Time Trial', 'icon' => 'bi-stopwatch', 'color' => '#f59e0b',
                'me' => $me + ['score' => '24:10', 'pct' => 88],
                'opponent' => ['name' => 'Noor Salem', 'initials' => 'NS', 'record' => '6W · 0L', 'rank' => '#2', 'score' => '22:48', 'pct' => 100],
                'metric' => 'Fastest 5K', 'stake' => '120 pts',
                'result' => 'loss', 'final' => '24:10 vs 22:48', 'points_earned' => 0,
                'date' => 'Jun 5', 'location' => 'Outdoor Loop',
                'message' => 'Lost by 82s — rematch incoming.',
                'when' => 'Jun 5',
            ],
        ];
    }

    public function settings(Request $request): View
    {
        $user = Auth::user();

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'personal.mobile.settings' : 'personal.desktop.settings', compact('user'));
    }

    /** Mark an app section / feed-tab as seen (clears its unseen indicator). */
    public function markSectionSeen(Request $request, \App\Support\SectionActivity $activity): JsonResponse
    {
        $activity->markSeen(Auth::user(), (string) $request->input('section', ''));

        return response()->json(['success' => true]);
    }

    /**
     * Toggle the current user's people-discovery visibility (search + cold DMs).
     * Opt-out: default is discoverable.
     */
    public function updateDiscoverable(Request $request): JsonResponse
    {
        $data = $request->validate(['is_discoverable' => 'required|boolean']);

        $user = Auth::user();
        $user->is_discoverable = $data['is_discoverable'];
        $user->save();

        return response()->json([
            'success' => true,
            'is_discoverable' => $user->is_discoverable,
            'message' => $user->is_discoverable
                ? __('personal.discoverable_on')
                : __('personal.discoverable_off'),
        ]);
    }
}
