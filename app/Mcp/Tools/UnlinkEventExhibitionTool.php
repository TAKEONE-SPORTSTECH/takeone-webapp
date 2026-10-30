<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventExhibitionBout;
use App\Events\Support\EventAccess;
use App\Events\Support\ExhibitionLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * Undo an exhibition's link to a draw bout — the console's Unlink
 * (App\Events\Support\ExhibitionLink::unlink). Organisers only.
 */
#[Title('Unlink an exhibition bout from the draw')]
#[Description('For an event you manage: undo a link_event_exhibition made by mistake. An attach-only link is simply removed; a replaced score is restored (the winner never moved); a filed result is cleared and any corner the link seated is taken out — refused when the winner has already fought the next bout. The exhibition, its footage and score log become an exhibition again. Others get "Event not found".')]
class UnlinkEventExhibitionTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event uuid (from list_events).'),
            'exhibition' => $schema->string()->required()->description('The linked exhibition uuid (from list_event_exhibitions).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $v = $request->validate(['event' => 'required|uuid', 'exhibition' => 'required|uuid']);

        $event = ClubEvent::where('uuid', $v['event'])->first();

        // One answer for "no such event" and "not yours".
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        $ex = EventExhibitionBout::where('event_id', $event->id)->where('uuid', $v['exhibition'])->first();

        if (! $ex) {
            return Response::error('Exhibition not found.');
        }

        try {
            $done = app(ExhibitionLink::class)->unlink($event, $ex, $user);
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json([
            'event' => $event->uuid,
            'exhibition' => ['uuid' => $ex->uuid, 'code' => $done['exhibition']['code'], 'status' => $done['exhibition']['status']],
            'message' => $done['message'],
        ]);
    }
}
