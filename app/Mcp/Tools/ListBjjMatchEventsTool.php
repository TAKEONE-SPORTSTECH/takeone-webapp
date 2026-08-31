<?php

namespace App\Mcp\Tools;

use App\Events\Sports\BrazilianJiuJitsu\Tournament\Scoreboard\Ledger;
use App\Events\Sports\BrazilianJiuJitsu\Tournament\Scoreboard\MatState;
use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Read a Brazilian jiu-jitsu officiating log')]
#[Description('Read the officiating record of one Brazilian jiu-jitsu match: every scoring action in order, which corner it went to, what it was worth and the position it came from, plus penalties, advantages and any correction. The score is DERIVED from this log by replaying it, so this is the authoritative record of how a result was reached. Corrections appear as reversals that name the entry they undo and carry a reason — nothing is ever erased, so a reversed entry is still listed and flagged. Read-only, and restricted to people entitled to score or manage the event, because each entry names the official who made it.')]
class ListBjjMatchEventsTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid (the unpredictable public id used in its URL).'),
            'court' => $schema->string()->required()
                ->description('The mat the match is on.'),
            'match_id' => $schema->integer()
                ->description('Optional bout id. Omit for the match currently loaded on that mat.'),
            'limit' => $schema->integer()
                ->description('How many entries to return, newest first. Default 60, maximum 200.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $validated = $request->validate([
            'event' => 'required|string',
            'court' => 'required|string|max:40',
            'match_id' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $event = ClubEvent::where('uuid', $validated['event'])->with('tenant')->first();
        $access = app(EventAccess::class);

        // One answer for "no such event", "not yours" and "you may look at this
        // event but not at its officiating log". Anyone who can merely SEE the
        // event is refused here, because every entry names the official who made
        // it — that is the operator's record, not the hall's.
        if (! $event
            || $event->sport !== 'bjj'
            || ! $access->visible($event, $user)
            || ! ($access->canScore($event, $user) || $access->canManage($event, $user))) {
            return Response::error('Match log not found.');
        }

        $mat = MatState::query()
            ->where('event_id', $event->id)
            ->where('court', $validated['court'])
            ->first();

        if (! $mat) {
            return Response::error('No such mat on this event.');
        }

        $matchId = $validated['match_id'] ?? $mat->match_id;

        if (! $matchId) {
            return Response::json([
                'event' => ['uuid' => $event->uuid, 'title' => $event->title],
                'court' => $mat->court,
                'match_id' => null,
                'entries' => [],
                'message' => 'No match is loaded on that mat.',
            ]);
        }

        $ledger = app(Ledger::class);

        return Response::json([
            'event' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'club' => $event->tenant?->club_name,
            ],
            'court' => $mat->court,
            'match_id' => (int) $matchId,
            'division' => $mat->division,

            // The same replay the screens read, so a caller comparing the log
            // against the score can never be told two different stories.
            'score' => $ledger->tally($event, $mat->court, (int) $matchId)->toArray(),

            'entries' => $ledger->timeline(
                $event,
                $mat->court,
                (int) $matchId,
                $validated['limit'] ?? 60,
            ),
        ]);
    }
}
