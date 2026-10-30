<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventMatch;
use App\Events\Support\EventAccess;
use App\Media\BoutStats;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * One bout's statistics (App\Media\BoutStats) — the same sheet the bout page
 * offers as a PDF and a sent video closes on, as data.
 *
 * Same doors as the web: whoever may WATCH the bout (the event's audience,
 * plus the two athletes who fought it). Read-only.
 */
#[Title('Bout statistics')]
#[Description('One bout\'s statistics, read from what the mat logged: final score and winner, who scored first and when, lead changes, whether it was a comeback, when it was decided, each corner\'s points (by value), penalties, time in the lead, biggest lead and longest run, the bout in thirds, stoppages, and every scoring action with its bout-clock time. Also returns the PDF download URL. Returns "not found" unless the acting user can see the event or fought in the bout.')]
class BoutStatsTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid (from list_events).'),
            'match_no' => $schema->integer()->required()
                ->description('The bout number within the event (from list_event_videos or the draw).'),
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

        $match = $event ? EventMatch::with(['event', 'category'])
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

        $stats = app(BoutStats::class)->for($match);

        if ($stats === null) {
            return Response::error('Nothing was logged on the mat for this bout, so it has no statistics.');
        }

        return Response::json([
            'event' => $event->uuid,
            'match_no' => $match->match_no,
            'division' => $match->category?->name,
            'pdf_url' => route('me.events.bout.stats.pdf', ['event' => $event->uuid, 'matchNo' => $match->match_no]),
        ] + \Illuminate\Support\Arr::except($stats, ['steps']));
    }
}
