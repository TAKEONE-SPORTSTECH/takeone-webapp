<?php

namespace App\Scoreboard\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventMatch;
use App\Events\Support\EntryEditor;
use App\Members\Models\User;
use App\Scoreboard\Models\PhotoVsFrame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The console's Players tab, for every sport — find an entrant at the table
 * and correct them: name, club, nationality, date of birth or age, gender,
 * weight, belt, photo, and where the photo sits on the VS screen.
 *
 * Sport-neutral on purpose (CLAUDE.md → Shared Stays Shared): nothing here
 * knows a point, a belt ladder or a mat design. Every write goes through
 * App\Events\Support\EntryEditor, the same editor the event's People page
 * uses, so the platform's rules apply — a real member's date of birth stays
 * theirs unless the actor holds authority over them, a name is re-stamped
 * across the draw, the photo lands in the person's one home — and the editor
 * refreshes every sport's mats through Fleet. The only things handled here are
 * a stated AGE for a person with no date of birth, which the editor has no
 * field for, and the VS framing (PhotoVsFrame).
 *
 * Lifted from the Karate console (ScoreboardController::players*), which keeps
 * its own copy until it is moved onto this one as a separate step.
 *
 * Callers authorise first: ORGANISERS only (EventAccess::canManage), never a
 * scoring official — see ConsolePlayersController.
 */
class ConsolePlayers
{
    public function __construct(private EntryEditor $editor, private VsCorner $vs) {}

    /**
     * The tab's addresses for one console — what scoreboard::shared.players
     * .runtime is handed as `$players` — or NULL when this person may not
     * manage the event, and then the console renders no tab at all.
     *
     * `$token` is the paired table's (its organiser is `$actor`); without one
     * the doors are the signed-in organiser's, which alone get the platform's
     * weight-class doors (they are session routes). Every door re-authorises.
     */
    public static function console(ClubEvent $event, ?User $actor, string $court, ?string $token = null, ?string $drawUrl = null): ?array
    {
        $sport = (string) $event->sport;

        if (! $actor || ! app(\App\Events\Support\EventAccess::class)->canManage($event, $actor)
            || ! \Illuminate\Support\Facades\Route::has("{$sport}-scoreboard.players")) {
            return null;
        }

        if ($token !== null) {
            return [
                'list' => route("{$sport}-scoreboard.token-players", $token, false),
                'vsPreview' => route("{$sport}-scoreboard.token-players-vs-preview", $token, false),
                'geo' => route("{$sport}-scoreboard.token-players-geo", $token, false),
                'classDoors' => null,
                'drawUrl' => $drawUrl,
                'mat' => $court,
            ];
        }

        return [
            'list' => route("{$sport}-scoreboard.players", $event->uuid, false),
            'vsPreview' => route("{$sport}-scoreboard.players-vs-preview", $event->uuid, false),
            'geo' => route("{$sport}-scoreboard.players-geo", $event->uuid, false),
            'classDoors' => [
                'person' => route('me.events.person.divisions', [$event->uuid, '00000000-0000-4000-8000-000000000000'], false),
                'members' => route('me.events.divisions.members', [$event->uuid, 987654321], false),
                'move' => \App\Events\Support\Tournament\LiveMove::enabled()
                    ? route('me.events.divisions.live-move', $event->uuid, false) : null,
            ],
            'drawUrl' => $drawUrl,
            'mat' => $court,
        ];
    }

    /** Every entrant, one row per person, with the divisions they are in. */
    public function list(ClubEvent $event): array
    {
        $regs = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->with(['user:id,uuid,full_name,name,gender,birthdate,profile_picture,profile_picture_is_public',
                'user.memberClubs:id,club_name,country',
                'representingTenant:id,club_name,country',
                'category:id,name,weight_class'])
            ->orderBy('id')
            ->get();

        $written = \App\Events\Support\EntryClub::forMany($event, $regs->pluck('id')->all());

        return [
            'players' => $regs->groupBy(fn ($r) => $r->user_id ? 'u'.$r->user_id : 'r'.$r->id)
                ->map(function ($rows) use ($written) {
                    $r = $rows->first();

                    return $this->row($r, $written[$r->id]['name'] ?? null, $written[$r->id]['country'] ?? null)
                        + ['divisions' => $rows->map(fn ($x) => $x->category?->weight_class ?: $x->category?->name)
                            ->filter()->unique()->values()->all(),
                            'classes' => $rows->filter(fn ($x) => $x->category)->unique('category_id')
                                ->map(fn ($x) => ['id' => (int) $x->category->id, 'name' => (string) $x->category->name,
                                    'weight' => $this->weightLabel($x->category->weight_class, $x->category->name),
                                    'registration' => (int) $x->id])
                                ->values()->all(),
                            // The person's public key, for the enrolment sheet's door.
                            'uuid' => $r->user?->uuid];
                })
                ->sortBy(fn ($p) => mb_strtolower((string) $p['name']))
                ->values()->all(),
        ];
    }

    /** One entrant, with what this organiser may change on them and why not. */
    public function show(ClubEvent $event, int $registration, ?User $actor): JsonResponse
    {
        $reg = $this->entry($event, $registration);
        $present = $this->editor->present($event, $reg);
        $perm = $this->editor->permissions($event, $reg, $actor);
        $vsPath = $this->vsPath($reg);

        return response()->json([
            // array_merge, not `+`: the editor's own club name must win over the row's.
            'player' => array_merge($this->row($reg, null), [
                'declared_age' => $reg->declared_age ?: null,
                'nationality' => $reg->user?->nationality ? strtoupper((string) $reg->user->nationality) : null,
                'club_key' => $present['club']['slug'] ?? null,
                'vs_photo' => $vsPath ? file_url($vsPath) : null,
                'vs_frame' => PhotoVsFrame::payloadFor($vsPath),
                'club' => $present['club']['name'] ?? '',
            ]),
            'clubs' => $present['clubs'] ?? [],
            // Each rung of THIS event's belt system, drawn with the shared belt.
            'belts' => collect($present['belts'] ?? [])->map(fn ($r) => $r + [
                'icon' => \App\Sports\Combat\BeltIcon::payload($r['value'])['style'] ?? null,
            ])->values()->all(),
            'fields' => collect($perm['fields'])->only(['name', 'club', 'birthdate', 'gender', 'nationality', 'weight', 'belt_colour', 'belt_grade', 'photo'])
                ->map(fn ($f) => ['editable' => (bool) $f['editable'], 'reason' => $f['reason']])->all(),
            'age_editable' => (bool) ($perm['fields']['birthdate']['editable'] ?? false),
        ]);
    }

    /** Save what changed — absent is never blank. */
    public function save(Request $request, ClubEvent $event, int $registration, ?User $actor): JsonResponse
    {
        $reg = $this->entry($event, $registration);

        // The same vocabulary the event's People page accepts.
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'club' => ['sometimes', 'nullable', 'string', 'max:120'],
            'birthdate' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', \Illuminate\Validation\Rule::in(['Male', 'Female'])],
            'nationality' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:15', 'max:250'],
            'belt_colour' => ['sometimes', 'nullable', 'string', \Illuminate\Validation\Rule::in(\App\Sports\Combat\BeltRank::accepted($event->sport))],
            'belt_grade' => ['sometimes', 'nullable', 'string', 'max:32'],
            // Real bytes are checked by the editor (StoresBase64Images).
            'photo' => ['sometimes', 'nullable', 'string', 'starts_with:data:image/'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:99'],
        ]);

        $data = array_intersect_key($validated, array_flip(['name', 'club', 'birthdate', 'gender', 'nationality', 'weight', 'belt_colour', 'belt_grade', 'photo']));
        if (! empty($data['nationality'])) {
            $data['nationality'] = strtoupper($data['nationality']);
        }

        // A stated age only stands in for a date of birth nobody has — and only
        // where this organiser may edit the person's date of birth at all.
        if (array_key_exists('age', $validated)) {
            $perm = $this->editor->permissions($event, $reg, $actor);
            $hasDob = array_key_exists('birthdate', $data)
                ? ! empty($data['birthdate'])
                : $reg->user?->birthdate !== null;

            if ($hasDob || ! ($perm['fields']['birthdate']['editable'] ?? false)) {
                return response()->json([
                    'success' => false, 'field' => 'age',
                    'message' => __('scoreboard::players.pl_age_locked'),
                ], 422);
            }
        }

        if (! $data && ! array_key_exists('age', $validated)) {
            return response()->json(['success' => false, 'message' => __('events::events.entry_edit_nothing')], 422);
        }

        if ($data) {
            $result = $this->editor->apply($event, $reg, $data, $actor);

            if (! $result['ok']) {
                return response()->json([
                    'success' => false, 'field' => $result['field'] ?? null, 'message' => $result['message'],
                ], 422);
            }
        }

        if (array_key_exists('age', $validated)) {
            $reg->forceFill(['declared_age' => $validated['age']])->save();
            // The editor was not involved, so the wall was not told.
            $this->refreshMats($event, $reg);
        } elseif (! empty($data['birthdate']) && $reg->declared_age !== null) {
            // A real date of birth supersedes a stated age.
            $reg->forceFill(['declared_age' => null])->save();
        }

        $reg->refresh()->load('user');
        $written = \App\Events\Support\EntryClub::forMany($event, [$reg->id]);

        return response()->json([
            'success' => true,
            'message' => __('scoreboard::players.pl_saved'),
            'player' => $this->row($reg, $written[$reg->id]['name'] ?? null, $written[$reg->id]['country'] ?? null),
        ]);
    }

    /**
     * Where this person's photo sits on the VS screen — zoom and focal point
     * over the ORIGINAL, which is never cut. Keyed by the photo the VS screen
     * actually shows (the entry's own, else a PUBLIC profile picture), so the
     * framing follows that picture onto every sport's wall. Whoever may not
     * change the photo may not reframe it either.
     */
    public function saveVsFrame(Request $request, ClubEvent $event, int $registration, ?User $actor): JsonResponse
    {
        $reg = $this->entry($event, $registration);

        $perm = $this->editor->permissions($event, $reg, $actor);
        abort_unless((bool) ($perm['fields']['photo']['editable'] ?? false), 403);

        $path = $this->vsPath($reg);
        if (! $path) {
            return response()->json(['success' => false, 'message' => __('scoreboard::players.vf_no_photo')], 422);
        }

        $data = $request->validate([
            'reset' => ['sometimes', 'boolean'],
            'zoom' => ['required_without:reset', 'numeric', 'between:1,3'],
            'x' => ['required_without:reset', 'numeric', 'between:0,100'],
            'y' => ['required_without:reset', 'numeric', 'between:0,100'],
        ]);

        if (! empty($data['reset'])) {
            PhotoVsFrame::where('path', $path)->delete();
        } else {
            PhotoVsFrame::updateOrCreate(['path' => $path], [
                'zoom' => round((float) $data['zoom'], 3), 'pos_x' => round((float) $data['x'], 3),
                'pos_y' => round((float) $data['y'], 3), 'updated_by' => $actor?->id,
            ]);
        }
        PhotoVsFrame::forget($path);

        // Any mat showing this person now moves the photo without a reload.
        $this->refreshMats($event, $reg);

        \Illuminate\Support\Facades\Log::info('scoreboard.vs-frame', ['event' => $event->uuid, 'registration' => $reg->id, 'by' => $actor?->id]);

        return response()->json([
            'success' => true,
            'message' => __('scoreboard::players.vf_saved'),
            'frame' => PhotoVsFrame::payloadFor($path),
        ]);
    }

    /**
     * The VS screen as a preview for the framing editor: THE shared screen
     * (scoreboard::vs.arena) in this event's corner look, this entrant in the
     * red corner and a stand-in opposite, frozen on the introduction. The
     * editor drives it over postMessage, so what it shows IS the wall's design.
     */
    public function vsPreview(Request $request, ClubEvent $event)
    {
        $reg = $request->filled('reg')
            ? ClubEventRegistration::where('event_id', $event->id)->find((int) $request->query('reg'))
            : null;

        $sample = fn (string $gender) => [
            'name' => __('scoreboard::players.vs_sample_name'),
            'club' => __('scoreboard::players.vs_sample_club'),
            'country' => __('scoreboard::players.vs_sample_country'),
            'flag' => 'bh', 'nationality' => 'bh', 'logo' => null, 'photo' => null, 'vs_frame' => null,
            'fallback' => \App\Members\Support\Avatar::placeholder($gender),
            'belt' => null, 'record' => '3W – 1L', 'age' => 18, 'height' => null, 'weight' => 60,
        ];

        $red = $sample('male');
        if ($reg) {
            $match = new EventMatch;
            $match->forceFill(['event_id' => $event->id, 'a_competitor_id' => $reg->id]);
            $red = VsCorner::announced($this->vs->corner($match, 'a', $this->vs->registrations($event, [$reg->id]),
                \App\Events\Support\EntryClub::forMany($event, [$reg->id])));
        }

        return response()->view('scoreboard::shared.players.vs-preview', $this->vs->look($event) + [
            'intro' => [
                'tournament' => (string) $event->title,
                'stage' => '', 'division' => '', 'matchNo' => null, 'referee' => null, 'court' => '',
                'aka' => $red, 'ao' => VsCorner::announced($sample('female')),
            ],
        ])->header('X-Frame-Options', 'SAMEORIGIN')->header('Cache-Control', 'no-store, private');
    }

    /** One answer for "no such entry" and "not this event's". */
    private function entry(ClubEvent $event, int $registration): ClubEventRegistration
    {
        $reg = ClubEventRegistration::where('event_id', $event->id)->with('user')->find($registration);
        abort_unless($reg, 404);

        return $reg;
    }

    /** The photo the VS screen shows, by the screens' own rule. */
    private function vsPath(ClubEventRegistration $reg): ?string
    {
        $user = $reg->user;

        return $reg->photo ?: (($user?->profile_picture && $user->profile_picture_is_public) ? $user->profile_picture : null);
    }

    private function refreshMats(ClubEvent $event, ClubEventRegistration $reg): void
    {
        rescue(fn () => app(\App\Scoreboard\Fleet::class)->entryChanged($event, (int) $reg->id), null, false);
    }

    /** A weight class as read aloud: a bare "-45" gains its unit. */
    private function weightLabel(?string $class, ?string $name = null): ?string
    {
        $class = trim((string) $class);
        if ($class === '') {
            return preg_match('/[+-]\s?\d+(?:\.\d+)?\s?kg/i', (string) $name, $m) ? $m[0] : null;
        }

        return preg_match('/^[+-]?\d+(\.\d+)?$/', $class) ? $class.' kg' : $class;
    }

    /** One person as the Players list and its editor read them. */
    private function row(ClubEventRegistration $r, ?string $writtenClub, ?string $writtenCountry = null): array
    {
        $user = $r->user;
        $club = $r->competingClub();
        // The club's country, then the entry's own — the one flag rule.
        $iso = collect([$club?->country, $writtenCountry, $r->countryCode()])
            ->first(fn ($c) => preg_match('/^[A-Za-z]{2}$/', (string) $c));

        return [
            'registration' => (int) $r->id,
            'name' => $user?->full_name ?: $user?->name,
            'club' => $club?->club_name ?: ($writtenClub ?: ''),
            'flag' => $iso ? strtolower((string) $iso) : null,
            'gender' => in_array($user?->gender, ['Male', 'Female'], true) ? $user->gender : null,
            'birthdate' => $user?->birthdate?->toDateString(),
            'age' => $user?->birthdate ? $user->birthdate->age : ($r->declared_age ?: null),
            'weight' => $r->weight !== null ? (float) $r->weight : null,
            'belt_colour' => $r->belt_colour,
            'belt_grade' => $r->belt_grade,
            'photo' => $r->photo
                ? file_url($r->photo)
                : (($user?->profile_picture && $user->profile_picture_is_public) ? file_url($user->profile_picture) : null),
            'fallback' => \App\Members\Support\Avatar::placeholder($user?->gender),
        ];
    }
}
