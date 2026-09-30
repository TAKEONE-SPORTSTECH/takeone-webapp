<?php

namespace App\Mcp\Tools;

use App\Members\Models\User;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Support\MemberRanks;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Set a member\'s rank')]
#[Description('Set or correct a member\'s belt in one ranked activity (the `activity` uuid from list_activity_ranks). The belt must be on that sport\'s ladder and the grade one of that belt\'s grades; the change is kept in the member\'s history and they are notified. Club admins and the activity\'s coaches.')]
class SetMemberRankTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'activity' => $schema->string()->required()->description('Ranked activity uuid.'),
            'member' => $schema->string()->required()->description('Member uuid.'),
            'belt' => $schema->string()->required()->description('A ladder value, e.g. blue.'),
            'grade' => $schema->string()->description('One of the belt\'s grades, e.g. "2 stripes".'),
            'since' => $schema->string()->description('YYYY-MM-DD, not in the future. Defaults to today.'),
            'note' => $schema->string(),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if (! config('ranks.enabled')) {
            return Response::error('Ranks & grading are switched off.');
        }
        $v = $request->validate([
            'activity' => 'required|uuid', 'member' => 'required|uuid',
            'belt' => 'required|string|max:40', 'grade' => 'nullable|string|max:40',
            'since' => 'nullable|date|before_or_equal:today', 'note' => 'nullable|string|max:500',
        ]);
        $ranks = app(MemberRanks::class);
        $link = ActivityPackage::where('uuid', $v['activity'])->with('activity.tenant')->first();
        $member = User::where('uuid', $v['member'])->first();
        // One answer for every miss: not found.
        if (! $link || ! $link->activity || ! $ranks->book($link)?->hasRanks() || ! $member
            || ! ($this->canAdminClub($user, $link->activity->tenant) || $ranks->canManage($user, $link))
            || ! $ranks->memberIds($link)->contains($member->id)) {
            return Response::error('Not found.');
        }

        try {
            $row = $ranks->set($link, $member, $v['belt'], $v['grade'] ?? null, $v['since'] ?? now()->toDateString(), $user, $v['note'] ?? null);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return Response::error($e->getMessage() ?: 'Invalid rank.');
        }
        Log::info('Rank set (MCP)', ['by' => $user->id, 'activity' => $link->activity_id, 'member' => $member->id]);

        return Response::json(['member' => array_intersect_key($row, array_flip(['uuid', 'name', 'rank']))]);
    }
}
