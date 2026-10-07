<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventEntryInvoice;
use App\Events\Support\ClubEntry\PlatformClubEntry;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Approve or reject a club payment')]
#[Description('For an event where you verify payments: approve a club\'s payment proof (every athlete it covers is marked paid) or reject it with a reason the club will read. Only a proof still waiting for review can be decided. Look at the proof in the event console first.')]
class DecideEventClubPaymentTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'payment' => $schema->string()->required()->description('The payment\'s uuid (from list_event_club_payments).'),
            'decision' => $schema->string()->enum(['approve', 'reject'])->required(),
            'reason' => $schema->string()->description('Why it is not approved — shown to the club. For reject.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        $v = $request->validate(['event' => 'required|uuid', 'payment' => 'required|uuid', 'decision' => 'required|in:approve,reject', 'reason' => 'nullable|string|max:500']);
        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();
        if (! $event || ! app(EventAccess::class)->canVerifyPayments($event, $user)) {
            return Response::error('Event not found.');
        }
        $row = EventEntryInvoice::where('event_id', $event->id)->where('uuid', $v['payment'])->first();
        if (! $row) {
            return Response::error('Payment not found.');
        }
        if ($row->state !== EventEntryInvoice::SUBMITTED) {
            return Response::error('That payment has already been decided.');
        }
        PlatformClubEntry::decide($event, $row, $v['decision'] === 'approve', $v['reason'] ?? null, $user);

        return Response::json(['payment' => $row->uuid, 'state' => $row->fresh()->state]);
    }
}
