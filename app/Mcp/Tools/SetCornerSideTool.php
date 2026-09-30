<?php

namespace App\Mcp\Tools;

use App\Sports\Combat\CornerSide;
use App\Sports\Combat\SportRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Set a hall-screen corner side')]
#[Description('Super-admin only. Put a combat sport\'s red corner on the left or right of every hall screen and scoring table of that sport, or `default` to return to the sport\'s own convention. Same as Platform Settings → Hall screens. Screens pick it up on their next reload.')]
class SetCornerSideTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'sport' => $schema->string()->required()->description('Sport key, e.g. karate, taekwondo, bjj.'),
            'side' => $schema->string()->enum(['left', 'right', 'default'])->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if (! $user->hasRole('super-admin')) {
            return Response::error('Only a super-admin can change platform settings.');
        }

        $v = $request->validate([
            'sport' => ['required', 'string', Rule::in(array_keys(app(SportRegistry::class)->all()))],
            'side' => 'required|in:left,right,default',
        ]);

        CornerSide::setOverride($v['sport'], $v['side'] === 'default' ? null : $v['side']);
        Log::info('Corner side changed (MCP)', ['by' => $user->id, 'sport' => $v['sport'], 'side' => $v['side']]);

        return Response::json(['corner' => collect(CornerSide::all())->firstWhere('sport', $v['sport'])]);
    }
}
