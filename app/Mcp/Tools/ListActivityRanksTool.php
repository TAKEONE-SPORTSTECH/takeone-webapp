<?php

namespace App\Mcp\Tools;

use App\Clubs\Models\Tenant;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Support\ActivityPackages;
use App\Ranks\Support\Eligibility;
use App\Ranks\Support\MemberRanks;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List a club\'s ranks')]
#[Description('Ranks & grading for a club: each activity whose sport has belts (Taekwondo, Karate, BJJ…), its ladder, and every member\'s current belt, since when, and readiness for the next belt (eligible · nearly · not_yet · no_rules · unranked · top). Club admins and the activity\'s coaches. Only while ranks are switched on (RANKS_ENABLED).')]
class ListActivityRanksTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['club' => $schema->string()->required()->description('The club\'s slug.')];
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
        $v = $request->validate(['club' => 'required|string|max:120']);
        $club = Tenant::where('slug', $v['club'])->first();
        if (! $club) {
            return Response::error('Club not found.');
        }

        $ranks = app(MemberRanks::class);
        $admin = $this->canAdminClub($user, $club);
        $out = [];
        foreach (app(ActivityPackages::class)->rankedActivities($club) as $row) {
            $link = ActivityPackage::where('activity_id', $row['activity']->id)->first();
            if (! $link || (! $admin && ! $ranks->canManage($user, $link))) {
                continue;
            }
            $ladder = $ranks->ladder($link);
            $out[] = [
                'activity' => $link->uuid,
                'name' => $row['activity']->name,
                'package' => $row['book']->styleLabel,
                'ladder' => array_map(fn ($b) => ['value' => $b['value'], 'label' => $b['label'], 'grades' => $b['grades']], $ladder),
                'members' => array_map(fn ($m) => array_intersect_key($m, array_flip(['uuid', 'name', 'rank', 'status', 'next', 'progress'])),
                    app(Eligibility::class)->annotate($link, $ladder, $ranks->roster($link))),
            ];
        }
        if ($out === [] && ! $admin) {
            return Response::error('Club not found.');
        }

        return Response::json(['activities' => $out]);
    }
}
