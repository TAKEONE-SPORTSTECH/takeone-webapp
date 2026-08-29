<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SportsMatch;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The competition truth for one match video, as pushed by takeone.
 *
 * takeone owns who fought, in which division and round, for which clubs, in which
 * corner, and how it ended (Match Sync Contract). This platform owns the media and
 * the timeline. So everything here is DERIVED: it is written by the service client
 * and never typed by a person on this side.
 *
 * Deliberately narrow, per RULE #3:
 *   - match-type videos only. A music or generic video is not addressable here.
 *   - it writes `sports_matches` and nothing else. It never touches match_rounds,
 *     match_points or coach_reviews, which are this platform's own.
 *   - it never deletes. Deletion does not cross between the platforms.
 */
class MatchController extends Controller
{
    public function update(Request $request, string $video): JsonResponse
    {
        /*
         * The key in a video URL is an ENCODED id (Video::encodeId), not the share
         * token and not the primary key — so it is decoded the same way the web
         * routes do. Resolved by hand rather than through route binding because
         * that path also issues merge redirects, which have no meaning to an API
         * client.
         */
        $id = Video::decodeId($video);
        $model = ($id === null || $id <= 0) ? null : Video::find($id);

        if (! $model) {
            return response()->json(['ok' => false, 'error' => 'video_not_found'], 404);
        }

        // A match video, or nothing. This endpoint has no business with the music
        // library, and saying so here means no later change can widen it by accident.
        if ($model->type !== 'match') {
            return response()->json(['ok' => false, 'error' => 'not_a_match_video'], 422);
        }

        $data = $request->validate([
            'revision'          => ['required', 'integer', 'min:1'],
            'title'             => ['nullable', 'string', 'max:255'],
            'event_name'        => ['nullable', 'string', 'max:255'],
            'sport'             => ['nullable', 'string', 'max:80'],
            'match_type'        => ['nullable', 'string', 'max:80'],
            'match_date'        => ['nullable', 'date'],
            'match_time'        => ['nullable', 'date_format:H:i'],
            'venue_name'        => ['nullable', 'string', 'max:255'],
            'participant1_name' => ['nullable', 'string', 'max:255'],
            'participant2_name' => ['nullable', 'string', 'max:255'],
            'referee_name'      => ['nullable', 'string', 'max:255'],
            // Free-form groups, bounded so a crafted push cannot store unbounded
            // JSON. Their shapes are the contract's, not this platform's.
            'participants'      => ['nullable', 'array'],
            'result'            => ['nullable', 'array'],
            'competition'       => ['nullable', 'array'],
            /*
             * The deep links back into takeone (VIDEO-INTEGRATION.md 6.6).
             *
             * Declared explicitly because validate() returns only what it was told
             * about — an undeclared nested key arrives on the request and is then
             * dropped, which is exactly how the "view match details" link went
             * missing without any error.
             *
             * URLs are validated as URLs: they are rendered as hrefs on a public
             * page, so a non-URL must never reach the template.
             */
            'competition.event'    => ['nullable', 'array'],
            'competition.bout'     => ['nullable', 'array'],
            'competition.draw'     => ['nullable', 'array'],
            'competition.category' => ['nullable', 'array'],
            'competition.event.url' => ['nullable', 'url', 'max:2048'],
            'competition.bout.url'  => ['nullable', 'url', 'max:2048'],
            'competition.draw.url'  => ['nullable', 'url', 'max:2048'],
            'venue'             => ['nullable', 'array'],
            'officials'         => ['nullable', 'array', 'max:24'],
            'officials.*.role'  => ['nullable', 'string', 'max:80'],
            // The sport's own word for the position — karate says "Referee
            // (Shushin)". Sent rather than derived, because the vocabulary belongs
            // to the sport and this platform does not model sports.
            'officials.*.label'   => ['nullable', 'string', 'max:120'],
            'officials.*.name'    => ['nullable', 'string', 'max:255'],
            'officials.*.uuid'    => ['nullable', 'string', 'max:64'],
            // An official's flag is their nationality. Two letters or nothing:
            // this value becomes a CSS class.
            'officials.*.country' => ['nullable', 'string', 'size:2', 'alpha'],
            'officials.*.profile' => ['nullable', 'url', 'max:2048'],
            'officials.*.photo'   => ['nullable', 'url', 'max:2048'],
        ]);

        $match = SportsMatch::firstOrNew(['video_id' => $model->id]);

        /*
         * Refuse a stale or replayed push rather than applying it.
         *
         * Reported as a success with `stale: true`, not an error: the caller did
         * nothing wrong, its work was simply already superseded, and making that
         * an error would have it retry forever.
         */
        if ($match->exists && $match->revision !== null && (int) $data['revision'] <= (int) $match->revision) {
            return response()->json([
                'ok'       => true,
                'stale'    => true,
                'revision' => (int) $match->revision,
            ]);
        }

        // A record created by this push belongs to the video's owner, so it behaves
        // like any other match record on the platform.
        if (! $match->exists) {
            $match->user_id = $model->user_id;
            $match->status  = 'published';
        }

        foreach ([
            'title', 'event_name', 'sport', 'match_type', 'match_date', 'match_time',
            'venue_name', 'participant1_name', 'participant2_name', 'referee_name',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $match->{$field} = $data[$field];
            }
        }

        /*
         * The JSON groups are MERGED, not replaced.
         *
         * `participants` also carries values a human set on this side — the
         * referee photo path, image captions — and a push that owns only the
         * competition fields must not wipe them. takeone sends what it owns; what
         * it does not send is left as it was.
         */
        foreach (['participants', 'result', 'competition', 'venue'] as $group) {
            if (array_key_exists($group, $data) && is_array($data[$group])) {
                $match->{$group} = array_merge((array) ($match->{$group} ?? []), $data[$group]);
            }
        }

        /*
         * The faces and the crests, fetched onto this platform.
         *
         * takeone sends URLs to its own copies; this platform renders from its
         * own storage, the way the manual annotation flow always has. Only what
         * changed is downloaded, and a failure leaves the bout without a picture
         * rather than without a bout — see MatchMediaImporter.
         *
         * After `$match->save()` would be too late for a new record (there is no
         * id to file the image under yet), so the record is saved first when it
         * is new and the media applied immediately after.
         */
        $importable = array_key_exists('participants', $data) && is_array($data['participants'])
            ? $data['participants']
            : null;

        // Officials ARE replaced: they are a list, and a merge would leave a
        // removed official behind for ever.
        if (array_key_exists('officials', $data)) {
            $match->officials = $data['officials'] ?: null;
        }

        $match->revision = (int) $data['revision'];
        $match->save();

        if ($importable !== null) {
            $match->media = app(\App\Services\MatchMediaImporter::class)->apply($match, $importable);
            $match->save();
        }

        return response()->json([
            'ok'       => true,
            'stale'    => false,
            'match_id' => $match->id,
            'revision' => (int) $match->revision,
        ]);
    }
}
