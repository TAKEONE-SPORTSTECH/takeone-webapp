<?php

namespace App\Mcp\Tools;

use App\Sports\Combat\CornerSide;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List hall-screen corner sides')]
#[Description('Super-admin only. For each combat sport: which side of the hall screens and scoring tables the red corner stands on — the sport\'s own default, any platform override, and the side in effect.')]
class ListCornerSidesTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if (! $user->hasRole('super-admin')) {
            return Response::error('Only a super-admin can read platform settings.');
        }

        return Response::json(['sports' => CornerSide::all()]);
    }
}
