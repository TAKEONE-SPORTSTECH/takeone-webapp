<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserRelationship;
use App\Traits\HandlesClubAuthorization;
use App\Traits\StoresBase64Images;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ══════════════════════════════════════════════════════════════════════════
 * TAKEONE Play — linked match lookup API
 *
 * Read-only lookups that let the match-video form on TAKEONE Play fill itself
 * from real TAKEONE data instead of being retyped by hand: pick an athlete and
 * their club, club logo, photo, country and profile link come with them.
 *
 * IDENTITY — the whole point of this design (see Documentation/VIDEO-INTEGRATION.md §3.1):
 * every request is authenticated as a REAL TAKEONE user via their own Sanctum
 * token, so this controller answers exactly what that person could already see
 * in the web UI, and Play never gets to decide who may see whom. Play stores
 * the token and renders the results; it holds no authorisation model of its own.
 *
 * SCOPE — a club admin/owner searches the members of the clubs THEY administer.
 * A super-admin searches every discoverable person. Nobody searches across a
 * tenant boundary, so this can never become an enumeration surface for the wider
 * member base (CLAUDE.md — Anti-Enumeration and Anti-Extraction).
 *
 * WHAT IT NEVER RETURNS — email, phone, birthdate, health, billing, documents,
 * family links, or anything else the SAFE public profile withholds. The response
 * is deliberately the same shape of data as `people.show`: a name, a photo, a
 * club and a link back.
 * ══════════════════════════════════════════════════════════════════════════
 */
class PlayIntegrationController extends Controller
{
    use HandlesClubAuthorization;
    use StoresBase64Images;

    /** Hard ceiling on a single search response — abuse resistance, not paging. */
    private const MAX_RESULTS = 20;

    /**
     * Who the token belongs to, and what it may reach.
     *
     * Play calls this once when a user connects their account: it proves the
     * token is live, names the human behind it for the "Connected as …" line,
     * and returns the clubs whose members will be searchable so Play can say so
     * up front instead of the user discovering an empty result set.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $clubs = $this->administeredClubs($user)
            ->map(fn (Tenant $c) => [
                'slug'  => $c->slug,
                'name'  => $c->club_name,
                'logo'  => $this->publicUrl($c->logo),
                'url'   => $this->clubUrl($c),
            ])->values();

        return response()->json([
            'user' => [
                'uuid'  => $user->uuid,
                'name'  => $user->name,
                'photo' => $this->publicUrl($user->profile_picture),
            ],
            'is_super_admin' => $user->isSuperAdmin(),
            // A super-admin searches everyone, so the club list is informational
            // rather than the scope boundary it is for everybody else.
            'scope'          => $user->isSuperAdmin() ? 'platform' : 'administered_clubs',
            'clubs'          => $clubs,
        ]);
    }

    /**
     * Search people to put in a match — competitors or officials.
     *
     * `role=referee` does not change WHO is searchable (an official is a person
     * on the platform like anyone else); it only tells the caller which slot the
     * pick is destined for, and is accepted so the contract can tighten later
     * without Play having to change.
     */
    public function searchPeople(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Optional: an empty query lists the first page of people in scope,
            // so the picker opens already full and typing narrows it down rather
            // than making the organiser guess a name before seeing anything.
            'q'    => ['nullable', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'in:athlete,referee'],
        ]);

        $user = $request->user();
        $term = trim((string) ($data['q'] ?? ''));

        $query = User::query()->whereNull('deleted_at');

        if ($term !== '') {
            // Name, email or phone — the same three the platform's own people
            // search accepts, so an organiser who knows a competitor's number can
            // find them without guessing the spelling of their name. `mobile` is
            // a {"code","number"} JSON blob, so LIKE matches the digits inside it.
            // Wildcards are escaped: a query of "%" must not behave differently
            // from any other two characters.
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $query->where(fn ($w) => $w
                ->where('full_name', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('mobile', 'like', $like));
        }

        if ($user->isSuperAdmin()) {
            // Platform-wide reach still honours the member's own opt-out: a person
            // who turned off discovery is not findable here either.
            $query->where('is_discoverable', true);
        } else {
            $clubIds = $this->administeredClubs($user)->pluck('id');

            if ($clubIds->isEmpty()) {
                return response()->json(['results' => [], 'scope' => 'administered_clubs']);
            }

            // Members of the clubs this person administers. `is_discoverable` is
            // deliberately NOT applied here: their own club admin already sees
            // them in the members panel, and hiding them only from this one form
            // would be an inconsistency, not a privacy gain.
            $query->whereIn('id', DB::table('memberships')
                ->whereIn('tenant_id', $clubIds)
                ->pluck('user_id'));
        }

        $people = $query
            ->orderBy('full_name')
            ->limit(self::MAX_RESULTS)
            ->get(['id', 'uuid', 'name', 'full_name', 'profile_picture', 'birthdate', 'gender', 'updated_at']);

        return response()->json([
            'scope'   => $user->isSuperAdmin() ? 'platform' : 'administered_clubs',
            'results' => $people->map(fn (User $p) => $this->personRow($p))->values(),
        ]);
    }

    /**
     * The full fill-in payload for one person, by uuid.
     *
     * The search list stays lean; this is what the form actually consumes once a
     * pick is made. Re-authorises from scratch — a uuid from a previous search,
     * or guessed, is not a permit (CLAUDE.md: a random identifier does NOT
     * replace authorization).
     */
    public function person(Request $request, string $uuid): JsonResponse
    {
        $actor  = $request->user();
        $person = User::query()->where('uuid', $uuid)->first();

        if (! $person || ! $this->mayLookUp($actor, $person)) {
            // Same answer for "no such person" and "not yours" — never leak
            // whether a neighbouring identifier exists.
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['person' => $this->personDetail($person)]);
    }

    /**
     * The sports a match can be, and what each one calls its corners.
     *
     * TAKEONE owns this vocabulary — a karate bout has AKA and AO, a taekwondo
     * bout has Chong and Hong — so Play should render what it is told rather
     * than keep its own copy that drifts every time a sport is added.
     */
    public function sports(): JsonResponse
    {
        return response()->json(['sports' => $this->sportVocabulary()]);
    }

    /**
     * Set a person's profile picture from TAKEONE Play.
     *
     * The organiser filling in a match video is often looking at the only decent
     * headshot that person has, so it should land on their TAKEONE profile rather
     * than only on the video — the profile is the source of truth both platforms
     * read from.
     *
     * AUTHORISATION mirrors MemberController@uploadPicture EXACTLY — super-admin,
     * the person themselves, or a confirmed guardian. Being able to SEE someone
     * in the picker (a club admin can) is deliberately NOT enough to rewrite
     * their photo: this endpoint must not become a wider permission than the web
     * UI grants.
     */
    public function uploadPersonPhoto(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image/'],
        ]);

        $actor  = $request->user();
        $person = User::query()->where('uuid', $uuid)->first();

        // Same generic answer for "no such person" and "not visible to you".
        if (! $person || ! $this->mayLookUp($actor, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! $this->mayEditPhotoOf($actor, $person)) {
            return response()->json([
                'message' => 'You can see this person but may not change their profile picture on TAKEONE.',
            ], 403);
        }

        // Bytes are sniffed and the extension assigned server-side; SVG rejected.
        $path = $this->storeBase64Image(
            $data['image'],
            'people/'.$person->uuid.'/profile',
            \Illuminate\Support\Str::random(40),
        );

        if ($path === null) {
            return response()->json(['message' => 'Invalid or unsupported image.'], 422);
        }

        $old = $person->profile_picture;
        $person->profile_picture = $path;
        $person->save();

        // Only after a successful store, and only if the path actually changed.
        if ($old && $old !== $path && \Illuminate\Support\Facades\Storage::disk('public')->exists($old)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
        }

        return response()->json(['photo' => $this->publicUrl($path)]);
    }

    /**
     * Set a club's logo from TAKEONE Play.
     *
     * AUTHORISATION mirrors the club admin panel (HandlesClubAuthorization):
     * super-admin, the club's owner, a club admin, or the chain owner. Nobody
     * else can rewrite another club's crest, however visible it is in a picker.
     */
    public function uploadClubLogo(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image/'],
        ]);

        $club = Tenant::query()->where('slug', $slug)->first();

        if (! $club) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! $this->canManageClub($club)) {
            return response()->json([
                'message' => 'You may not change this club’s logo on TAKEONE.',
            ], 403);
        }

        // Same folder + naming the club-admin branding form writes to.
        $path = $this->storeBase64Image(
            $data['image'],
            'clubs/'.$club->id.'/branding',
            'logo_'.time(),
        );

        if ($path === null) {
            return response()->json(['message' => 'Invalid or unsupported image.'], 422);
        }

        $old = $club->logo;
        $club->logo = $path;
        $club->save();

        if ($old && $old !== $path && \Illuminate\Support\Facades\Storage::disk('public')->exists($old)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
        }

        return response()->json(['logo' => $this->publicUrl($path)]);
    }

    // ── internals ────────────────────────────────────────────────────────

    /**
     * May the actor replace this person's profile picture?
     * Deliberately narrower than mayLookUp(): seeing is not editing.
     */
    private function mayEditPhotoOf(User $actor, User $person): bool
    {
        if ($actor->isSuperAdmin() || $actor->id === $person->id) {
            return true;
        }

        return UserRelationship::query()
            ->where('guardian_user_id', $actor->id)
            ->where('dependent_user_id', $person->id)
            ->exists();
    }


    /** Clubs the user owns or holds an admin role in. Super-admin gets all. */
    private function administeredClubs(User $user)
    {
        if ($user->isSuperAdmin()) {
            return Tenant::query()->orderBy('club_name')->get(['id', 'slug', 'club_name', 'logo', 'country']);
        }

        $owned = $user->ownedClubs()->pluck('id');

        $roleScoped = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id')
            ->filter(fn ($id) => $user->isClubAdmin((int) $id));

        $ids = $owned->merge($roleScoped)->map(fn ($id) => (int) $id)->unique();

        return Tenant::query()->whereIn('id', $ids)
            ->orderBy('club_name')
            ->get(['id', 'slug', 'club_name', 'logo', 'country']);
    }

    /** May the actor look this person up at all? Mirrors the search scope exactly. */
    private function mayLookUp(User $actor, User $person): bool
    {
        if ($actor->isSuperAdmin()) {
            return (bool) $person->is_discoverable;
        }

        $clubIds = $this->administeredClubs($actor)->pluck('id');

        if ($clubIds->isEmpty()) {
            return false;
        }

        return DB::table('memberships')
            ->where('user_id', $person->id)
            ->whereIn('tenant_id', $clubIds)
            ->exists();
    }

    /**
     * Lean list row.
     *
     * Age and gender ride along because a name is NOT unique: a club can easily
     * hold four people called the same thing across its kids, cadet, junior and
     * senior squads, and picking the wrong one puts the wrong competitor on a
     * video. Age is the field that separates them, so the picker must show it.
     * (Age, not birthdate — the exact date is more than the caller needs.)
     */
    private function personRow(User $p): array
    {
        $club = $this->primaryClub($p);

        return [
            'uuid'   => $p->uuid,
            'name'   => $p->full_name ?: $p->name,
            'photo'  => $this->publicUrl($p->profile_picture),
            'club'   => $club ? $club->club_name : null,
            'age'    => $this->ageOf($p),
            'gender' => $p->gender ? ucfirst(strtolower($p->gender)) : null,
        ];
    }

    /** Whole years, or null when no birthdate is recorded. */
    private function ageOf(User $p): ?int
    {
        if (! $p->birthdate) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($p->birthdate)->age;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Everything the match form fills in from one pick. */
    private function personDetail(User $p): array
    {
        $club = $this->primaryClub($p);

        return [
            'uuid'        => $p->uuid,
            'name'        => $p->full_name ?: $p->name,
            'photo'       => $this->publicUrl($p->profile_picture),
            'age'         => $this->ageOf($p),
            'gender'      => $p->gender ? ucfirst(strtolower($p->gender)) : null,
            'profile_url' => url('/people/'.$p->uuid),
            // The country beside a competitor is the CLUB's country, never the
            // person's nationality — the rule the event side already follows.
            'country'     => $club?->country,
            'club'        => $club ? [
                'slug'    => $club->slug,
                'name'    => $club->club_name,
                'logo'    => $this->publicUrl($club->logo),
                'url'     => $this->clubUrl($club),
                'country' => $club->country,
            ] : null,
        ];
    }

    /** The club a person competes for: their most recent active membership. */
    private function primaryClub(User $p): ?Tenant
    {
        $tenantId = DB::table('memberships')
            ->where('user_id', $p->id)
            ->orderByDesc('id')
            ->value('tenant_id');

        return $tenantId ? Tenant::find($tenantId) : null;
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? url('/storage/'.ltrim($path, '/')) : null;
    }

    private function clubUrl(Tenant $club): ?string
    {
        $country = strtolower((string) $club->country);

        return ($country !== '' && $club->slug)
            ? url('/'.$country.'/clubs/'.$club->slug)
            : null;
    }

    /**
     * Corner naming and the officiating panel, per sport.
     *
     * Both come from the sport's own package (App\Events\Sports\<Sport>) via the
     * combat-sport registry, so a karate bout offers Shushin and four Fukushin
     * while a taekwondo bout offers a Center Referee and corner judges — and
     * adding a sport means adding a directory here, with zero deploys on the
     * video platform.
     */
    private function sportVocabulary(): array
    {
        $corners = [
            'karate'    => ['red' => 'Aka',  'blue' => 'Ao'],
            'taekwondo' => ['red' => 'Hong', 'blue' => 'Chong'],
        ];

        $sports = [];

        foreach (app(\App\Sports\Combat\SportRegistry::class)->all() as $key => $sport) {
            $sports[] = [
                'key'       => $key,
                'label'     => $sport->label(),
                'corners'   => $corners[$key] ?? ['red' => 'Red', 'blue' => 'Blue'],
                'officials' => $sport->officialRoles(),
            ];
        }

        // A match that is not one of the registered combat sports still needs to
        // be recordable, so the neutral vocabulary stays on the list.
        $sports[] = [
            'key'       => 'generic',
            'label'     => __('events.sport_generic'),
            'corners'   => ['red' => 'Red', 'blue' => 'Blue'],
            'officials' => [
                ['key' => 'referee',    'label' => __('events.official_referee')],
                ['key' => 'judge',      'label' => __('events.official_judge')],
                ['key' => 'timekeeper', 'label' => __('events.official_timekeeper')],
                ['key' => 'recorder',   'label' => __('events.official_recorder')],
            ],
        ];

        return $sports;
    }
}
