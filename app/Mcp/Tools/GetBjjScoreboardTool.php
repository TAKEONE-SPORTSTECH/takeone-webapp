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

#[Title('Read a Brazilian jiu-jitsu scoreboard')]
#[Description('Read the live state of a Brazilian jiu-jitsu competition mat: who is on it, the running clock, and the score — points, advantages and penalties counted separately for the blue and white corners, plus who currently leads and on which of the three counters the lead was decided. Also reports the match state (live, paused, referee review, medical, overtime, finished) and, once a match is over, how it was won. Read-only, one mat or every mat of an event, and limited to events the acting user can already see in the app.')]
class GetBjjScoreboardTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid (the unpredictable public id used in its URL).'),
            'court' => $schema->string()
                ->description('Optional mat identifier to return on its own. Omit for every mat of the event.'),
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
            'court' => 'nullable|string|max:40',
        ]);

        $event = ClubEvent::where('uuid', $validated['event'])->with('tenant')->first();

        // The same answer whether the event does not exist or is merely out of
        // reach, so this cannot be used to discover which uuids are real.
        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        if ($event->sport !== 'bjj') {
            return Response::error('This event is not a Brazilian jiu-jitsu event.');
        }

        $query = MatState::query()->where('event_id', $event->id);

        if (! empty($validated['court'])) {
            $query->where('court', $validated['court']);
        }

        $ledger = app(Ledger::class);

        $mats = $query->orderBy('court')->get()->map(function (MatState $mat) use ($event, $ledger) {
            $tally = $ledger->tally($event, $mat->court, $mat->match_id);

            return [
                'court' => $mat->court,
                'court_label' => $mat->court_label,
                'mode' => $mat->mode,
                'status' => $mat->status,
                'division' => $mat->division,
                'stage' => $mat->stage,
                'match_no' => $mat->match_no,
                'referee' => $mat->referee,

                // Blue and white — never red. A BJJ mat has no red corner, and
                // calling one red here would be the first place that leaks back
                // into a screen.
                'blue' => $this->corner($mat->blue),
                'white' => $this->corner($mat->white),

                'score' => $tally->toArray(),

                // The clock is stored as a remaining figure plus whether it is
                // running and when it was last touched; the wall screen derives
                // the live number from those. Reported the same way rather than
                // as a number that is already stale by the time it is read.
                'clock' => [
                    'remaining_seconds' => $mat->remaining,
                    'duration_seconds' => $mat->duration,
                    'running' => (bool) $mat->running,
                    'as_of' => $mat->clock_at?->toIso8601String(),
                ],

                'result' => $mat->winner === null ? null : [
                    'winner' => $mat->winner,
                    'method' => $mat->win_method,
                    'note' => $mat->win_note,
                ],
                'awaiting_referee_decision' => (bool) $mat->awaiting_decision,
            ];
        })->all();

        if ($mats === [] && ! empty($validated['court'])) {
            return Response::error('No such mat on this event.');
        }

        return Response::json([
            'event' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'club' => $event->tenant?->club_name,
                'date' => $event->date?->toDateString(),
            ],
            'mats' => $mats,
        ]);
    }

    /**
     * A corner, reduced to what a scoreboard shows.
     *
     * Deliberately not the whole stored blob: that carries a photo path and
     * whatever else the board needs to draw, and a reader asking for the score
     * has no business receiving a competitor's file.
     *
     * @param  array<string, mixed>|null  $corner
     * @return array<string, mixed>|null
     */
    private function corner(?array $corner): ?array
    {
        if (! $corner) {
            return null;
        }

        return [
            'name' => $corner['name'] ?? null,
            'club' => $corner['club'] ?? null,
            'country' => $corner['country'] ?? null,
        ];
    }
}
