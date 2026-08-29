<?php

namespace App\Media;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\DuelMedia;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\EventRecording;
use App\Models\MediaFile;
use App\Support\BoutStage;
use App\Models\User;
use Illuminate\Support\Collection;
use App\Models\MediaFileSubject;

/**
 * Everything filmed, arranged for a person or for an event.
 *
 * Two readers, two shapes, one substrate:
 *
 *   • A MEMBER asks "where is my footage?" and gets it grouped by what kind of
 *     thing it is — bouts they fought, duels they took, clips they shot.
 *   • An EVENT asks "what was filmed here?" and gets it grouped by DIVISION,
 *     because that is how a competition is read: nobody looks for bout 34, they
 *     look for the -61 kg final.
 *
 * Both are read-only projections over `event_recordings` → `media_files`. No new
 * join table: the bout already knows its category and the recording already knows
 * its bout, so grouping is a walk, not a schema.
 */
class VideoLibrary
{
    public function __construct(
        private BoutFilm $film,
        private BoutArena $arena,
    ) {}

    /* ──────────────────────────────────────────────────────────────────────
     | An event's gallery, grouped by division
     ────────────────────────────────────────────────────────────────────── */

    /**
     * Every playable bout of an event, in the divisions the draw already uses.
     *
     * Division order is the organiser's own (`sort_order`, then id) so the
     * gallery reads in the same sequence as the bracket page and the running
     * order. A division with nothing filmed is omitted entirely rather than
     * shown empty — an empty shelf is noise on a page whose whole job is to
     * show what exists.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forEvent(ClubEvent $event): array
    {
        $recordings = EventRecording::with('mediaFile')
            ->where('event_id', $event->id)
            ->where('status', EventRecording::STATUS_LINKED)
            ->whereNotNull('media_file_id')
            ->whereNotNull('match_id')
            ->get()
            ->groupBy('match_id');

        if ($recordings->isEmpty()) {
            return [];
        }

        $matches = EventMatch::with('category')
            ->whereIn('id', $recordings->keys())
            ->orderBy('match_no')
            ->orderBy('id')
            ->get();

        $categories = EventCategory::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $groups = [];

        foreach ($matches as $match) {
            $angles = $this->film->fromRecordings($recordings->get($match->id));

            if ($angles === []) {
                continue;
            }

            // A bout with no category still has to land somewhere; an event can
            // be filmed before its divisions are drawn.
            $key = $match->category_id ?? 0;
            $category = $match->category_id ? $categories->get($match->category_id) : null;

            $groups[$key] ??= [
                'id' => $match->category_id,
                // The weight class is the name people search for; the category
                // name is the fallback, and a name is not a weight.
                'title' => $category?->weight_class ?: ($category?->name ?: __('events.bout_video_ungrouped')),
                'subtitle' => $category?->weight_class ? $category->name : null,
                'sort' => $category?->sort_order ?? 9999,
                'bouts' => [],
            ];

            $groups[$key]['bouts'][] = $this->bout($event, $match, $angles);
        }

        usort($groups, fn ($x, $y) => [$x['sort'], (string) $x['title']] <=> [$y['sort'], (string) $y['title']]);

        return array_values(array_map(function (array $group) {
            $group['count'] = count($group['bouts']);
            unset($group['sort']);

            return $group;
        }, $groups));
    }

    /**
     * One bout, as a gallery tile.
     *
     * The tile is a broadcast card, not a filename: the two fighters, their
     * clubs and countries, the stage and the mat. `arena` is what draws it and
     * `preview` is the ladder the card plays on hover — never the original,
     * which for a long bout is a gigabyte.
     */
    private function bout(ClubEvent $event, EventMatch $match, array $angles): array
    {
        $first = $angles[0];

        return [
            'arena' => $this->arena->for($event, $match, $angles),
            'preview' => $first['hls'],
            'match_no' => $match->match_no,
            // The phase as a person says it. Stored values are machine keys
            // ('open_mat', 'quarterfinals'), and a chip reading "OPEN_MAT" is
            // the database showing through the page.
            'round' => BoutStage::label($match),
            // A machine key for the stage, so the gallery can offer a tab per
            // stage without matching on a display string.
            'stage' => BoutStage::key($match),
            'court' => $match->court,
            'a' => $match->a_name,
            'b' => $match->b_name,
            'score' => filled($match->a_score) || filled($match->b_score)
                ? trim(($match->a_score ?? '0').' – '.($match->b_score ?? '0'))
                : null,
            'winner' => $match->winner,
            'angles' => count($angles),
            'duration' => $first['duration'],
            'poster' => $first['poster'],
            'url' => route('me.events.bout.video', [
                'event' => $event->uuid,
                'matchNo' => $match->match_no,
            ]),
        ];
    }

    /**
     * The stages present in a set of divisions, in competition order.
     *
     * Derived from the bouts that were actually filmed rather than from a fixed
     * list: an event with no semi-finals should not offer a Semi-finals tab that
     * opens on nothing.
     *
     * @param  array<int, array<string, mixed>>  $divisions
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function stagesIn(array $divisions): array
    {
        // Roughly how a competition runs, so the tabs read in that order.
        // Anything unrecognised sorts after, alphabetically by label.
        $order = [
            'group' => 10, 'round-robin' => 11, 'preliminary' => 12, 'prelims' => 13,
            'round-of-16' => 20, 'last-16' => 21,
            'quarterfinal' => 30, 'quarter-final' => 30, 'quarterfinals' => 30, 'quarter-finals' => 30,
            'semifinal' => 40, 'semi-final' => 40, 'semifinals' => 40, 'semi-finals' => 40,
            'bronze' => 50, 'third-place' => 51,
            'final' => 60, 'finals' => 60, 'knockout' => 61,
        ];

        return collect($divisions)
            ->flatMap(fn (array $d) => $d['bouts'])
            ->filter(fn (array $b) => filled($b['stage']))
            ->groupBy('stage')
            ->map(fn ($rows, $key) => [
                'key' => (string) $key,
                'label' => $rows->first()['round'],
                'count' => $rows->count(),
            ])
            ->sortBy(fn (array $s) => [$order[$s['key']] ?? 99, $s['label']])
            ->values()
            ->all();
    }

    /**
     * How many bouts of this event have footage — for the door on the event page.
     *
     * A count, not a list: the event page should not pay for the gallery it is
     * only offering a link to.
     */
    public function eventClipCount(ClubEvent $event): int
    {
        return EventRecording::where('event_id', $event->id)
            ->where('status', EventRecording::STATUS_LINKED)
            ->whereNotNull('media_file_id')
            ->whereNotNull('match_id')
            ->distinct()
            ->count('match_id');
    }

    /* ──────────────────────────────────────────────────────────────────────
     | A member's own gallery
     ────────────────────────────────────────────────────────────────────── */

    /**
     * Everything filmed of, or by, one person — grouped by what it is.
     *
     * Bouts come first because they are what a competitor came for, then duels,
     * then anything they shot themselves. Each group is omitted when empty, so
     * a member who has only ever fought sees one clean shelf rather than three
     * with two apologies.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        return array_values(array_filter([
            $this->boutShelf($user),
            $this->duelShelf($user),
            $this->uploadShelf($user),
        ]));
    }

    /** Their competition footage, newest event first. */
    private function boutShelf(User $user): ?array
    {
        // Two ways in, and the recorded one is authoritative.
        //
        // media_file_subjects says who is actually IN a file — including a coach
        // or official who is in no draw at all — and it survives the draw being
        // re-cut. The draw walk below stays as the fallback, because every file
        // ingested before subjects existed has no rows yet, and a member should
        // not lose footage they could already see.
        $subjectMatchIds = MediaFileSubject::where('user_id', $user->id)
            ->join('event_recordings', 'event_recordings.media_file_id', '=', 'media_file_subjects.media_file_id')
            ->pluck('event_recordings.match_id')
            ->filter()
            ->unique();

        $entries = ClubEventRegistration::where('user_id', $user->id)->pluck('id');

        if ($entries->isEmpty() && $subjectMatchIds->isEmpty()) {
            return null;
        }

        $matches = EventMatch::with(['category', 'event'])
            ->where(fn ($q) => $q->whereIn('a_competitor_id', $entries)
                ->orWhereIn('b_competitor_id', $entries)
                ->orWhereIn('id', $subjectMatchIds))
            ->get();

        if ($matches->isEmpty()) {
            return null;
        }

        $recordings = EventRecording::with('mediaFile')
            ->whereIn('match_id', $matches->pluck('id'))
            ->where('status', EventRecording::STATUS_LINKED)
            ->whereNotNull('media_file_id')
            ->get()
            ->groupBy('match_id');

        if ($recordings->isEmpty()) {
            return null;
        }

        $items = [];

        foreach ($matches as $match) {
            if (! $recordings->has($match->id) || $match->event === null) {
                continue;
            }

            $angles = $this->film->fromRecordings($recordings->get($match->id));

            if ($angles === []) {
                continue;
            }

            $mine = in_array($match->a_competitor_id, $entries->all(), true) ? 'a' : 'b';
            $theirs = $mine === 'a' ? 'b' : 'a';

            $items[] = [
                'kind' => 'bout',
                // The same broadcast card the event gallery draws.
                'arena' => $this->arena->for($match->event, $match, $angles),
                'preview' => $angles[0]['hls'],
                'title' => $match->{$theirs.'_name'}
                    ? __('events.bout_video_vs', ['name' => $match->{$theirs.'_name'}])
                    : __('events.bout_video_bout_n', ['n' => $match->match_no]),
                'subtitle' => $match->event->title,
                'meta' => $match->category?->weight_class ?: $match->category?->name,
                'date' => $match->event->date,
                'won' => $match->winner === $mine,
                'decided' => filled($match->winner),
                'duration' => $angles[0]['duration'],
                'poster' => $angles[0]['poster'],
                'angles' => count($angles),
                'url' => route('me.events.bout.video', [
                    'event' => $match->event->uuid,
                    'matchNo' => $match->match_no,
                ]),
            ];
        }

        if ($items === []) {
            return null;
        }

        usort($items, fn ($x, $y) => (string) $y['date'] <=> (string) $x['date']);

        return [
            'key' => 'bouts',
            'title' => __('personal.videos_group_bouts'),
            'subtitle' => __('personal.videos_group_bouts_sub'),
            'icon' => 'bi-trophy-fill',
            'items' => $items,
            'count' => count($items),
        ];
    }

    /** Challenge duels they posted video evidence to. */
    private function duelShelf(User $user): ?array
    {
        $rows = DuelMedia::with('duel')
            ->where('user_id', $user->id)
            ->where('type', 'video')
            ->latest('id')
            ->limit(60)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $items = $rows->map(fn (DuelMedia $m) => [
            'kind' => 'duel',
            'title' => $m->caption ?: __('personal.videos_duel_clip'),
            'subtitle' => __('personal.videos_group_duels'),
            'meta' => null,
            'date' => $m->created_at?->toDateString(),
            'duration' => 0,
            'poster' => null,
            'angles' => 1,
            // Duel evidence is a plain file, not a bout with a timeline — it
            // opens in the lightbox rather than on the review page.
            'src' => $m->full_url,
            'url' => null,
        ])->all();

        return [
            'key' => 'duels',
            'title' => __('personal.videos_group_duels'),
            'subtitle' => __('personal.videos_group_duels_sub'),
            'icon' => 'bi-lightning-charge-fill',
            'items' => $items,
            'count' => count($items),
        ];
    }

    /**
     * Footage they shot themselves that is not already one of their bouts —
     * a camera they ran on a mat, a session nobody drew a bracket for.
     */
    private function uploadShelf(User $user): ?array
    {
        $files = MediaFile::where('created_by', $user->id)
            ->whereIn('status', [MediaFile::STATUS_READY, MediaFile::STATUS_STORED])
            ->latest('id')
            ->limit(60)
            ->get();

        if ($files->isEmpty()) {
            return null;
        }

        // Anything already shown as one of their bouts is not shown twice.
        $claimed = EventRecording::whereIn('media_file_id', $files->pluck('id'))
            ->whereNotNull('match_id')
            ->pluck('media_file_id')
            ->all();

        $items = $files
            ->reject(fn (MediaFile $f) => in_array($f->id, $claimed, true))
            ->map(fn (MediaFile $f) => [
                'kind' => 'clip',
                'title' => $f->original_name ?: __('personal.videos_clip'),
                'subtitle' => __('personal.videos_group_clips'),
                'meta' => $f->human_size,
                'date' => $f->created_at?->toDateString(),
                'duration' => (int) ($f->duration_seconds ?? 0),
                'poster' => filled($f->hls_rel_path) ? route('media.poster', ['file' => $f->uuid]) : null,
                'angles' => 1,
                // Named to the playlist — see BoutFilm for why the bare
                // `/hls` form is a trap.
                'src' => filled($f->hls_rel_path)
                    ? route('media.hls', ['file' => $f->uuid, 'path' => 'playlist.m3u8'])
                    : route('media.original', ['file' => $f->uuid]),
                'url' => null,
            ])
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            'key' => 'clips',
            'title' => __('personal.videos_group_clips'),
            'subtitle' => __('personal.videos_group_clips_sub'),
            'icon' => 'bi-camera-reels-fill',
            'items' => $items,
            'count' => count($items),
        ];
    }

    /** Total across every shelf — the number the hero band shows. */
    public function countFor(array $shelves): int
    {
        return (int) collect($shelves)->sum('count');
    }
}
