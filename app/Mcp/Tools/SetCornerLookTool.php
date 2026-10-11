<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * The scoring console's Corners → "Corner names & colours", for every combat
 * scoreboard (App\Scoreboard\Corners\CornerLook with the sport's own spec).
 */
#[Title('Set an event\'s corner names and colours')]
#[Description('Name and colour each corner of a karate, taekwondo or BJJ event. Every mat, wall screen, VS screen, winner celebration and upcoming board of the event uses them. `corners` is keyed by the sport\'s own corner ids — karate/taekwondo: aka, ao; BJJ: blue, white — each {label, colour}: label up to 12 characters, colour #rrggbb; null or omitted = the sport\'s own (e.g. AKA red / AO blue, SHIRO white for Kyokushin). Only the look changes, never who is in which corner. Requires the right to score the event. Returns the names and colours in effect.')]
class SetCornerLookTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event uuid.'),
            'corners' => $schema->object()->required()
                ->description('{"<corner id>": {"label": "HONG", "colour": "#1f8a3b"}, …} — both corners; null values return a corner to the default.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }

        $v = $request->validate(['event' => 'required|string', 'corners' => 'nullable|array']);
        $event = ClubEvent::where('uuid', $v['event'])->first();
        $sport = (string) $event?->sport;

        if (! $event || ! CornerTools::supports($sport) || ! app(EventAccess::class)->canScore($event, $user)) {
            return Response::error('Event not found.');
        }

        $look = CornerTools::look($sport);
        $keys = array_keys($look::for($event));
        $input = [];
        foreach ($keys as $k) {
            $c = (array) (($v['corners'] ?? [])[$k] ?? []);
            $input[$k] = ['label' => $c['label'] ?? null, 'colour' => $c['colour'] ?? null];
        }

        $check = validator($input, \App\Scoreboard\Corners\CornerLook::rules(['corners' => array_fill_keys($keys, [])]));
        if ($check->fails()) {
            return Response::error($check->errors()->first());
        }

        $look::set($event, $input);
        CornerTools::push($event, null);
        Log::info('corners.look (MCP)', ['event' => $event->uuid, 'by' => $user->id]);

        return Response::json(collect($look::for($event->fresh()))->map(fn ($c) => [
            'label' => $c['label'],
            'colour' => $c['colour'],
            'is_default' => $c['customLabel'] === null && $c['customColour'] === null,
        ])->all());
    }
}
