<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventMatch;
use App\Events\Support\EventAccess;
use App\Media\BoutFilm;
use App\Media\BoutScoreboard;
use App\Media\BoutShare;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * A bout as a file to send (App\Media\BoutShare) — the whole bout or its
 * highlights, score bar burned in, points as chapters — for an integration
 * that wants to hand somebody a video rather than a page.
 *
 * Same doors as the web: whoever may WATCH the bout (the event's audience,
 * plus the two athletes who fought it). Asking is idempotent: a file already
 * made or being made is reported as it stands; only an idle one is queued.
 * The download URL is the web door, which re-checks the session — it is useless
 * to anyone the acting user could not already hand the page to.
 */
#[Title('Share bout video')]
#[Description('Make (or check on) a bout video for sending to WhatsApp and other apps: the score bar is burned into the picture and every point is a chapter. kind = "full" (whole bout) or "highlights" (scoring moments only). Returns the state (working, ready, failed), the download URL once ready, and the points written out as a message. Rendering takes about as long as the bout; call again to check. Returns "not found" unless the acting user can see the event or fought in the bout.')]
class ShareBoutVideoTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid (from list_events).'),
            'match_no' => $schema->integer()->required()
                ->description('The bout number within the event (from list_event_videos).'),
            'kind' => $schema->string()->enum(BoutShare::KINDS)->required()
                ->description('"full" for the whole bout, "highlights" for the scoring moments only.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $share = app(BoutShare::class);

        if (! $share->enabled()) {
            return Response::error('Sending bout videos is not switched on.');
        }

        $kind = (string) $request->get('kind', '');

        if (! in_array($kind, BoutShare::KINDS, true)) {
            return Response::error('kind must be "full" or "highlights".');
        }

        $uuid = trim((string) $request->get('event', ''));
        $event = $uuid !== '' ? ClubEvent::where('uuid', $uuid)->first() : null;

        $match = $event ? EventMatch::with(['category', 'competitorA', 'competitorB'])
            ->where('event_id', $event->id)
            ->where('match_no', (int) $request->get('match_no'))
            ->first() : null;

        if (! $event || ! $match) {
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
        $recording = $film->recordings($match)->first();
        $media = $recording?->mediaFile;

        if (! $media) {
            return Response::error('This bout has no footage to send.');
        }

        $msb = app(BoutScoreboard::class)->for($event, $match, $recording);
        $duration = (int) ($film->angles($match)[0]['duration'] ?? $media->duration_seconds ?? 0);

        if (! $share->possible($kind, $msb, $duration)) {
            return Response::error('This bout has no scoring moments, so it has no highlights. Ask for kind "full".');
        }

        $title = $share->title($msb, (string) $event->title);

        $status = $share->request($media, $kind, $msb, $title, [
            'event' => $event->uuid,
            'match_no' => $match->match_no,
            'user_id' => $user->id,
        ]);

        rescue(fn () => activity()
            ->performedOn($event)
            ->causedBy($user)
            ->withProperties(['match_no' => $match->match_no, 'kind' => $kind, 'via' => 'mcp'])
            ->log('bout_video_shared'), null, false);

        $params = ['event' => $event->uuid, 'matchNo' => $match->match_no, 'kind' => $kind];

        return Response::json([
            'kind' => $kind,
            'state' => $status['state'],
            'bytes' => $status['bytes'],
            'download_url' => $status['state'] === 'ready' ? route('me.events.bout.video.share.file', $params) : null,
            'filename' => $share->filename($msb, $kind, $match->match_no),
            'message' => $share->message(
                $msb,
                $title,
                $duration,
                route('me.events.bout.video', ['event' => $event->uuid, 'matchNo' => $match->match_no]),
            ),
        ]);
    }
}
