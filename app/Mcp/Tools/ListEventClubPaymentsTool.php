<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\ClubEntry\PlatformClubEntry;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List club payments for an event')]
#[Description('For an event where you verify payments: the payment proofs clubs sent for their athletes ("Register your club") — club, amount, number of athletes covered, state (submitted / approved / rejected) and the rejection reason. Waiting ones first. The proof image itself is viewed in the event console.')]
class ListEventClubPaymentsTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['event' => $schema->string()->required()->description('The event\'s uuid.')];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        $v = $request->validate(['event' => 'required|uuid']);
        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();
        if (! $event || ! app(EventAccess::class)->canVerifyPayments($event, $user)) {
            return Response::error('Event not found.');
        }
        $list = array_map(fn ($p) => array_diff_key($p, ['proof' => true]), (new PlatformClubEntry($event))->organiserList());

        return Response::json(['event' => $event->uuid, 'payments' => $list]);
    }
}
