<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventEntryConsent;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List event rule acceptances')]
#[Description('For an event you manage: who accepted its rules and conditions before registering — when, which statements, in which language, whether the rules have changed since (stale), and the club they went on to register. Newest first, up to 200.')]
class ListEventEntryConsentsTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }

        $v = $request->validate(['event' => 'required|uuid']);
        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();

        // One answer for missing and not-yours.
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        $latest = EventEntryConsent::where('event_id', $event->id)->latest('accepted_at')->value('rules_hash');

        $rows = EventEntryConsent::where('event_id', $event->id)
            ->with('user:id,uuid,full_name')
            ->latest('accepted_at')->limit(200)->get()
            ->map(fn (EventEntryConsent $c) => [
                'uuid' => $c->uuid,
                'accepted_at' => $c->accepted_at?->toIso8601String(),
                'channel' => $c->channel,
                'statements' => $c->statements,
                'locale' => $c->locale,
                'stale' => $latest !== null && $c->rules_hash !== $latest,
                'club' => $c->subject_name,
                'club_recorded_at' => $c->subject_at?->toIso8601String(),
                'person' => $c->user ? ['uuid' => $c->user->uuid, 'name' => $c->user->full_name] : null,
            ]);

        return Response::json(['event' => $event->uuid, 'count' => $rows->count(), 'consents' => $rows->values()]);
    }
}
