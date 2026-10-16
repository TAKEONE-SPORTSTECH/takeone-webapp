<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\BoutNumbering;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Renumber an event\'s bouts')]
#[Description('Number every bout of a combat event again from its running order (the day order), mat by mat — the "Renumber bouts" button. Numbers the organiser set by hand are kept. Brackets, hall screens and the mats board refresh. Only the event\'s organiser may do it, and never once a bout has been fought.')]
class RenumberEventBoutsTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event uuid.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $validated = $request->validate(['event' => 'required|string']);

        $event = ClubEvent::where('uuid', $validated['event'])->first();

        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        if (! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Only the event\'s organiser can renumber its bouts.');
        }

        $result = app(BoutNumbering::class)->renumber($event);

        if (! $result['ok']) {
            return Response::error($result['message']);
        }

        return Response::json([
            'renumbered' => true,
            'numbered' => $result['numbered'],
            'kept_by_hand' => $result['kept'],
            'message' => $result['message'],
        ]);
    }
}
