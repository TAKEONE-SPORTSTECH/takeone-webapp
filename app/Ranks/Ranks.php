<?php

namespace App\Ranks;

use App\Clubs\Models\Tenant;
use App\Ranks\Support\ActivityPackages;
use App\Support\Modules\AbstractModule;
use App\Support\Modules\Surfaces\ContributesClubAdminNav;

/**
 * Ranks & grading — belts, promotions, grading eligibility and grading sessions.
 *
 * NOT a club feature. It arrives with an ACTIVITY: a club only has any of this
 * because it teaches an activity whose sport package declares ranks (Taekwondo,
 * Karate, BJJ…), and every record here hangs off that activity. A club teaching
 * only football never sees it; a club that removes its last ranked activity
 * loses the menu entry with it. (Owner's decision, 2026-09-30.)
 *
 * The rules — the belt ladder, what makes someone eligible — come from the
 * activity's sport package (App\Sports\Package), never from here.
 *
 * Switched by config('ranks.enabled') — OFF by default.
 */
class Ranks extends AbstractModule implements ContributesClubAdminNav
{
    public function key(): string
    {
        return 'ranks';
    }

    public function clubAdminNav(Tenant $club, string $surface): array
    {
        if (! config('ranks.enabled') || ! app(ActivityPackages::class)->clubHasRanks($club)) {
            return [];
        }

        $entry = [
            'group' => 'people',
            'order' => 35,
            'route' => 'admin.club.ranks',
            'icon' => 'bi-award',
            'label' => $this->line('nav'),
        ];
        if ($eligible = self::eligibleCount($club)) {
            $entry['badge'] = $eligible;
        }

        return [$entry];
    }

    /** Members ready for their next belt, across the club's ranked activities. Cached briefly. */
    public static function eligibleCount(Tenant $club): int
    {
        return (int) \Illuminate\Support\Facades\Cache::remember('ranks.eligible.'.$club->id, 300, function () use ($club) {
            $ranks = app(\App\Ranks\Support\MemberRanks::class);
            $eligibility = app(\App\Ranks\Support\Eligibility::class);
            $n = 0;
            foreach (\App\Ranks\Models\ActivityPackage::whereIn('activity_id', \App\Clubs\Models\ClubActivity::where('tenant_id', $club->id)->select('id'))->where('sport', '<>', '')->get() as $link) {
                if ($ranks->book($link)?->hasRanks()) {
                    $n += collect($eligibility->annotate($link, $ranks->ladder($link), $ranks->roster($link)))->where('status', 'eligible')->count();
                }
            }

            return $n;
        });
    }

    public static function forgetBadge(Tenant $club): void
    {
        \Illuminate\Support\Facades\Cache::forget('ranks.eligible.'.$club->id);
    }
}
