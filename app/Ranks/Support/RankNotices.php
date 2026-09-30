<?php

namespace App\Ranks\Support;

use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Members\Models\UserRelationship;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Models\Grading;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The notices ranks & grading send — each exactly once, in the reader's own
 * language, through UserNotification::notifyUser (which also pushes it live).
 *
 * To the member (and, for a child, their guardians): promoted, invited to a
 * grading, grading tomorrow, ready for grading, a few classes to go.
 * To the club's admins and the activity's coaches: members became ready,
 * grading tomorrow, results not recorded.
 *
 * Best-effort: a notice that fails never fails the action that caused it.
 */
final class RankNotices
{
    public const CLOSE_CLASSES = 3;

    public function __construct(private MemberRanks $ranks, private Eligibility $eligibility) {}

    /** After a promotion. */
    public function promoted(ActivityPackage $link, User $member, string $label, ?User $by): void
    {
        $this->toMember($link, $member, 'promoted', 'rank-'.now()->format('YmdHis').'-'.$member->id, 'notice_promoted_title', 'notice_promoted_body',
            ['belt' => $label, 'activity' => $link->activity?->name], $by, 'bi-award-fill', allowRepeat: true);
    }

    /** When a grading is planned — each candidate is told. */
    public function invited(ActivityPackage $link, Grading $g, ?User $by): void
    {
        foreach ($g->candidates()->with('user')->get() as $c) {
            if ($c->user) {
                $this->toMember($link, $c->user, 'invited', $g->uuid, 'notice_invited_title', 'notice_invited_body',
                    ['date' => $g->held_on->toDateString(), 'activity' => $link->activity?->name, 'title' => $g->title], $by, 'bi-trophy');
            }
        }
    }

    /**
     * The daily pass (ranks:reminders). Returns how many notices went out.
     */
    public function daily(): int
    {
        $sent = 0;
        foreach (ActivityPackage::where('sport', '<>', '')->with('activity.tenant')->get() as $link) {
            if (! $link->activity || ! $this->ranks->book($link)?->hasRanks()) {
                continue;
            }
            $ladder = $this->ranks->ladder($link);
            $rows = $this->eligibility->annotate($link, $ladder, $this->ranks->roster($link));
            $users = User::whereIn('uuid', collect($rows)->pluck('uuid'))->get()->keyBy('uuid');
            $newlyReady = 0;

            foreach ($rows as $r) {
                $u = $users[$r['uuid']] ?? null;
                if (! $u || ! $r['next']) {
                    continue;
                }
                if ($r['status'] === 'eligible') {
                    if ($this->toMember($link, $u, 'eligible', $r['next']['belt'], 'notice_eligible_title', 'notice_eligible_body',
                        ['belt' => $r['next']['label'], 'activity' => $link->activity->name], null, 'bi-stars')) {
                        $newlyReady++;
                        $sent++;
                    }
                } elseif ($r['status'] === 'nearly' && isset($r['progress']['classes'])) {
                    $left = $r['progress']['classes'][1] - $r['progress']['classes'][0];
                    if ($left > 0 && $left <= self::CLOSE_CLASSES
                        && $this->toMember($link, $u, 'close', $r['next']['belt'], 'notice_close_title', 'notice_close_body',
                            ['n' => $left, 'belt' => $r['next']['label'], 'activity' => $link->activity->name], null, 'bi-graph-up-arrow')) {
                        $sent++;
                    }
                }
            }

            if ($newlyReady > 0) {
                $sent += $this->toStaff($link, 'ready', now()->toDateString(), 'notice_ready_staff_title', 'notice_ready_staff_body',
                    ['n' => $newlyReady, 'activity' => $link->activity->name], 'bi-stars');
            }

            foreach (Grading::where('activity_id', $link->activity_id)->where('status', 'planned')->get() as $g) {
                if ($g->held_on->isTomorrow()) {
                    foreach ($g->candidates()->with('user')->get() as $c) {
                        if ($c->user && $this->toMember($link, $c->user, 'eve', $g->uuid, 'notice_eve_title', 'notice_eve_body',
                            ['title' => $g->title, 'activity' => $link->activity->name], null, 'bi-trophy')) {
                            $sent++;
                        }
                    }
                    $sent += $this->toStaff($link, 'eve', $g->uuid, 'notice_eve_staff_title', 'notice_eve_staff_body',
                        ['title' => $g->title, 'n' => $g->candidates()->count()], 'bi-trophy');
                } elseif ($g->held_on->lt(now()->startOfDay())) {
                    $sent += $this->toStaff($link, 'unrecorded', $g->uuid, 'notice_unrecorded_title', 'notice_unrecorded_body',
                        ['title' => $g->title], 'bi-exclamation-circle');
                }
            }
        }

        return $sent;
    }

    // ─── Delivery ───────────────────────────────────────────────────────────

    /** The member, and their guardians. Returns true when it was sent now (not before). */
    private function toMember(ActivityPackage $link, User $member, string $kind, string $ref, string $title, string $body, array $vars, ?User $by, string $icon, bool $allowRepeat = false): bool
    {
        if (! $allowRepeat && ! $this->claim($member->id, $link->activity_id, $kind, $ref)) {
            return false;
        }
        $recipients = collect([$member->id])
            ->merge(UserRelationship::where('dependent_user_id', $member->id)->pluck('guardian_user_id'))
            ->unique();
        foreach (User::whereIn('id', $recipients)->get() as $to) {
            $this->send($to, $link, 'rank_'.$kind, $title, $body, $vars + ['name' => $member->full_name ?: $member->name],
                route('member.show', $member->uuid), $by, $icon);
        }

        return true;
    }

    /** The club's owner and admins, and the activity's coaches. Returns how many were sent. */
    private function toStaff(ActivityPackage $link, string $kind, string $ref, string $title, string $body, array $vars, string $icon): int
    {
        $club = $link->activity->tenant;
        $ids = collect([$club?->owner_user_id])
            ->merge(DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('roles.slug', 'club-admin')->where('user_roles.tenant_id', $link->activity->tenant_id)->pluck('user_roles.user_id'))
            ->merge(DB::table('club_activity_instructor as ai')->join('club_instructors as ci', 'ci.id', '=', 'ai.instructor_id')
                ->where('ai.activity_id', $link->activity_id)->pluck('ci.user_id'))
            ->filter()->unique();
        $n = 0;
        foreach (User::whereIn('id', $ids)->get() as $to) {
            if ($this->claim($to->id, $link->activity_id, 'staff_'.$kind, $ref)) {
                $this->send($to, $link, 'rank_staff_'.$kind, $title, $body, $vars, $club ? route('admin.club.ranks', $club->slug) : null, null, $icon);
                $n++;
            }
        }

        return $n;
    }

    /** Record that a notice goes out; false if it already has. */
    private function claim(int $userId, int $activityId, string $kind, string $ref): bool
    {
        return DB::table('rank_notices')->insertOrIgnore([
            'user_id' => $userId, 'activity_id' => $activityId, 'kind' => $kind, 'ref' => mb_substr($ref, 0, 80), 'created_at' => now(),
        ]) === 1;
    }

    private function send(User $to, ActivityPackage $link, string $type, string $title, string $body, array $vars, ?string $url, ?User $by, string $icon): void
    {
        try {
            $locale = in_array($to->locale, ['en', 'ar'], true) ? $to->locale : 'en';
            UserNotification::notifyUser($to->id, $type, __('ranks::messages.'.$title, $vars, $locale), [
                'body' => __('ranks::messages.'.$body, $vars, $locale),
                'action_url' => $url,
                'icon' => $icon,
                'tenant_id' => $link->activity?->tenant_id,
                'actor_id' => $by?->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Rank notice failed', ['to' => $to->id, 'type' => $type, 'error' => $e->getMessage()]);
        }
    }
}
