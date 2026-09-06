<?php

namespace App\Mcp\Tools;

use App\Events\Support\EventAccess;
use App\Media\BoutFilm;
use App\Media\BoutTimeline;
use App\Models\BoutCoachNote;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * One bout's footage: its camera angles, its derived highlights, its notes.
 *
 * The highlights are not stored annotations — they are the officiating log
 * replayed against the recording's anchor, so an integration reading this gets
 * exactly what the review page draws and can never drift from the score sheet.
 *
 * Access mirrors the web page and the media routes: anyone the event reaches,
 * plus the two athletes who fought this bout whatever the event's scope and
 * after it is archived.
 */
#[Title('Get bout video')]
#[Description('Get the footage of one bout: every camera angle, the scoring timeline derived from the officiating log (timestamps are seconds into the video), and any coach notes. Returns "not found" unless the acting user can see the event or fought in the bout.')]
class GetBoutVideoTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid (from list_events).'),
            'match_no' => $schema->integer()->required()
                ->description('The bout number within the event (from list_event_videos or get_event_bracket).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $uuid = trim((string) $request->get('event', ''));
        $event = $uuid !== '' ? ClubEvent::where('uuid', $uuid)->first() : null;

        if (! $event) {
            return Response::error('Bout not found.');
        }

        $match = EventMatch::with('category')
            ->where('event_id', $event->id)
            ->where('match_no', (int) $request->get('match_no'))
            ->first();

        if (! $match) {
            return Response::error('Bout not found.');
        }

        // The same two doors the web page opens, and no others.
        $competed = ClubEventRegistration::where('user_id', $user->id)
            ->whereIn('id', array_filter([$match->a_competitor_id, $match->b_competitor_id]))
            ->exists();

        if (! app(EventAccess::class)->visible($event, $user) && ! $competed) {
            return Response::error('Bout not found.');
        }

        $film = app(BoutFilm::class);
        $angles = $film->angles($match);
        $recording = $film->recordings($match)->first();

        $timeline = $recording
            ? app(BoutTimeline::class)->for($match, $recording)
            : ['anchored' => false, 'rounds' => [], 'moments' => []];

        return Response::json([
            'event' => ['uuid' => $event->uuid, 'title' => $event->title],
            'bout' => [
                'match_no' => $match->match_no,
                'division' => $match->category?->weight_class ?: $match->category?->name,
                'court' => $match->court,
                'red' => $match->a_corner === 'blue' ? $match->b_name : $match->a_name,
                'blue' => $match->a_corner === 'blue' ? $match->a_name : $match->b_name,
                'winner' => $match->winner,
            ],
            'watch_url' => route('me.events.bout.video', [
                'event' => $event->uuid, 'matchNo' => $match->match_no,
            ]),
            // Playback URLs are authorised per request and per segment; they are
            // useless to anyone without a session, and listed so an integration
            // can hand a person the link.
            'angles' => collect($angles)->map(fn (array $a) => [
                'angle' => $a['angle'],
                'label' => $a['label'],
                'duration_seconds' => $a['duration'],
                'resolution' => $a['width'] && $a['height'] ? $a['width'].'x'.$a['height'] : null,
                'streamable' => ! $a['transcoding'],
                'hls_url' => $a['hls'],
            ])->values(),
            'timeline' => [
                // False when the recording has no anchor: markers cannot be
                // placed against the video, and none are invented.
                'anchored' => $timeline['anchored'],
                'rounds' => $timeline['rounds'],
                'moments' => collect($timeline['moments'])->map(fn (array $m) => [
                    'at_seconds' => $m['t'],
                    'clock' => $m['clock'],
                    'round' => $m['round'],
                    'what' => $m['label'],
                    'who' => $m['who'],
                    'side' => $m['side'],
                    'score_red' => $m['score_red'],
                    'score_blue' => $m['score_blue'],
                ])->values(),
            ],
            'coach_notes' => BoutCoachNote::where('match_id', $match->id)
                ->orderBy('start_seconds')
                ->get()
                ->map(fn (BoutCoachNote $n) => [
                    'uuid' => $n->uuid,
                    'from_seconds' => (float) $n->start_seconds,
                    'to_seconds' => $n->end_seconds !== null ? (float) $n->end_seconds : null,
                    'emoji' => $n->emoji,
                    'note' => $n->note,
                    'coach' => $n->coach_name,
                ])->values(),
        ]);
    }
}
