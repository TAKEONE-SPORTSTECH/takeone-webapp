<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use App\Scoreboard\Corners\MatSides;
use App\Sports\Combat\CornerSide;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * The scoring console's Corners → "Swap corner colours on this mat", for every
 * combat scoreboard (App\Scoreboard\Corners\MatSides).
 */
#[Title('Set a mat\'s corner sides')]
#[Description('Put the red corner of one mat of a karate, taekwondo or BJJ event on the left or right of that mat\'s hall screens and scoring consoles — the PHYSICAL layout of the mat, never who fights in which corner. `default` returns the mat to the sport\'s convention. For karate, `screen` (control, board, upcoming) sets just that screen. Requires the right to score the event. Every screen and console on the mat redraws at once.')]
class SetMatCornerSideTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event uuid.'),
            'mat' => $schema->string()->required()->description('The mat, e.g. "Mat 1".'),
            'red_side' => $schema->string()->enum(['left', 'right', 'default'])->required(),
            // One screen only (karate): the scoring console, the scoreboard and
            // VS, or the upcoming board. Omit it for the whole mat.
            'screen' => $schema->string()->enum(MatSides::SCREENS)->description('Karate only: set just this screen of the mat. Omit for every screen.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }

        $v = $request->validate([
            'event' => 'required|string',
            'mat' => 'required|string|max:40',
            'red_side' => 'required|in:left,right,default',
            'screen' => 'nullable|in:'.implode(',', MatSides::SCREENS),
        ]);

        $event = ClubEvent::where('uuid', $v['event'])->first();
        $sport = (string) $event?->sport;

        // One answer for missing, out-of-reach and unsupported, so uuids cannot be probed.
        if (! $event || ! CornerTools::supports($sport) || ! app(EventAccess::class)->canScore($event, $user)) {
            return Response::error('Event not found.');
        }

        if (! $event->matches()->where('court', $v['mat'])->exists()) {
            return Response::error('This event runs no such mat.');
        }

        $redRight = $v['red_side'] === 'default' ? CornerSide::redRight($sport) : $v['red_side'] === 'right';
        $screen = $v['screen'] ?? null;

        // Only karate's screens read a side of their own; elsewhere it would be
        // a setting nothing draws.
        if ($screen !== null && $sport !== 'karate') {
            return Response::error('Setting one screen is for karate mats; set the whole mat instead.');
        }

        if ($screen !== null) {
            if (MatSides::redRight($event, $v['mat'], $sport, $screen) !== $redRight) {
                MatSides::flip($event, $v['mat'], [$screen], $sport);
            }
        } else {
            MatSides::set($event, $v['mat'], $redRight, $sport);
        }
        CornerTools::push($event, $v['mat']);
        Log::info('corners.side (MCP)', ['event' => $event->uuid, 'mat' => $v['mat'], 'red' => $redRight ? 'right' : 'left', 'by' => $user->id]);

        return Response::json([
            'mat' => $v['mat'],
            'red_side' => MatSides::redRight($event->fresh(), $v['mat'], $sport) ? 'right' : 'left',
            'screens' => array_map(fn (bool $r) => $r ? 'right' : 'left', MatSides::screens($event->fresh(), $v['mat'], $sport)),
            'sport_default' => CornerSide::red($sport),
        ]);
    }
}
