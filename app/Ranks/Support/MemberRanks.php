<?php

namespace App\Ranks\Support;

use App\Members\Models\User;
use App\Members\Support\Avatar;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Models\MemberRank;
use App\Ranks\Models\RankPromotion;
use App\Sports\Package\RuleBook;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Members' ranks in one ranked activity — who trains in it, where each of them
 * stands on the sport's ladder, and the history of how they got there.
 *
 * The ladder is the activity's rule book's (App\Sports\Package); nothing here
 * knows a belt colour of its own. Every change writes a RankPromotion first,
 * so the history is never lost to an edit.
 */
final class MemberRanks
{
    public function __construct(private ActivityPackages $links) {}

    /** The rule book of a linked activity, or null. */
    public function book(ActivityPackage $link): ?RuleBook
    {
        return $link->sport !== '' ? app(\App\Sports\Package\SportPackages::class)->style($link->sport, $link->style) : null;
    }

    /** The ladder as a client may read it: value, label, colours, grades. */
    public function ladder(ActivityPackage $link): array
    {
        return array_values(array_map(fn ($b) => [
            'value' => $b['value'], 'label' => $b['label'], 'bg' => $b['bg'] ?? null, 'fg' => $b['fg'] ?? null,
            'accent' => $b['accent'] ?? null, 'group' => $b['group'] ?? null, 'grades' => array_values($b['grades'] ?? []),
            // The shared belt drawing's colours (<x-belt-icon-defs>), computed here so JS never redraws a belt.
            'icon' => \App\Sports\Combat\BeltIcon::payload($b['value'])['style'] ?? null,
        ], $this->book($link)?->belts?->ladder ?? []));
    }

    /**
     * Everyone who trains in the activity — an active or pending subscription to
     * a package that includes it — plus anyone already holding a rank in it.
     *
     * @return Collection<int, int> user ids
     */
    public function memberIds(ActivityPackage $link): Collection
    {
        $subscribed = DB::table('club_member_subscriptions as s')
            ->join('club_package_activities as pa', 'pa.package_id', '=', 's.package_id')
            ->where('pa.activity_id', $link->activity_id)
            ->whereIn('s.status', ['active', 'pending'])
            ->pluck('s.user_id');

        return $subscribed->merge(MemberRank::where('activity_id', $link->activity_id)->pluck('user_id'))->unique()->values();
    }

    /** One row per member, ready to draw. Only what a club screen needs. */
    public function roster(ActivityPackage $link): array
    {
        $ids = $this->memberIds($link);
        $ranks = MemberRank::where('activity_id', $link->activity_id)->whereIn('user_id', $ids)->get()->keyBy('user_id');
        $users = User::whereIn('id', $ids)->get(['id', 'uuid', 'full_name', 'name', 'gender', 'birthdate', 'profile_picture', 'updated_at']);

        return $users->map(fn (User $u) => $this->row($link, $u, $ranks[$u->id] ?? null))
            ->sortBy(fn ($r) => mb_strtolower($r['name']))->values()->all();
    }

    public function row(ActivityPackage $link, User $u, ?MemberRank $rank): array
    {
        $ladder = collect($this->ladder($link))->keyBy('value');

        return [
            'uuid' => $u->uuid,
            'name' => $u->full_name ?: $u->name,
            'gender' => $u->gender,
            'age' => $u->birthdate ? Carbon::parse($u->birthdate)->age : null,
            'photo' => $u->profile_picture ? file_url($u->profile_picture) : Avatar::placeholder($u->gender),
            'rank' => $rank ? [
                'belt' => $rank->belt,
                'grade' => $rank->grade,
                'label' => ($ladder[$rank->belt]['label'] ?? $rank->belt).($rank->grade ? ' · '.$rank->grade : ''),
                'bg' => $ladder[$rank->belt]['bg'] ?? '#9ca3af',
                'accent' => $ladder[$rank->belt]['accent'] ?? null,
                'icon' => \App\Sports\Combat\BeltIcon::payload($rank->belt, $rank->grade)['style'] ?? ($ladder[$rank->belt]['icon'] ?? null),
                'since' => $rank->since?->toDateString(),
            ] : null,
        ];
    }

    /**
     * Set a member's rank. Validated against the ladder; the change is written to
     * the history first. Returns the member's fresh row.
     */
    public function set(ActivityPackage $link, User $member, string $belt, ?string $grade, string $since, User $by, ?string $note = null, ?int $gradingId = null): array
    {
        $step = collect($this->ladder($link))->firstWhere('value', $belt);
        abort_unless($step, 422, __('ranks::messages.error_belt'));
        if ($grade !== null && $grade !== '') {
            abort_unless(in_array($grade, $step['grades'], true), 422, __('ranks::messages.error_grade'));
        } else {
            $grade = null;
        }
        $date = Carbon::parse($since)->startOfDay();
        abort_if($date->isFuture(), 422, __('ranks::messages.error_future'));

        $row = DB::transaction(function () use ($link, $member, $belt, $grade, $date, $by, $note, $gradingId) {
            $current = MemberRank::where('activity_id', $link->activity_id)->where('user_id', $member->id)->lockForUpdate()->first();

            RankPromotion::create([
                'activity_id' => $link->activity_id, 'user_id' => $member->id,
                'from_belt' => $current?->belt, 'from_grade' => $current?->grade,
                'to_belt' => $belt, 'to_grade' => $grade,
                'promoted_on' => $date->toDateString(), 'promoted_by' => $by->id,
                'grading_id' => $gradingId, 'note' => $note !== null ? mb_substr(strip_tags($note), 0, 500) : null,
            ]);

            $rank = MemberRank::updateOrCreate(
                ['activity_id' => $link->activity_id, 'user_id' => $member->id],
                ['belt' => $belt, 'grade' => $grade, 'since' => $date->toDateString(), 'awarded_by' => $by->id],
            );

            return $this->row($link, $member, $rank);
        });

        // Tell the member (and their guardians) — never blocks the promotion.
        app(RankNotices::class)->promoted($link, $member, $row['rank']['label'] ?? $belt, $by);

        return $row;
    }

    /** A member's history in the activity, newest first. */
    public function history(ActivityPackage $link, User $member): array
    {
        $ladder = collect($this->ladder($link))->keyBy('value');
        $name = fn ($b, $g) => $b ? ($ladder[$b]['label'] ?? $b).($g ? ' · '.$g : '') : null;

        return RankPromotion::where('activity_id', $link->activity_id)->where('user_id', $member->id)
            ->with('promoter:id,full_name,name')->orderByDesc('promoted_on')->orderByDesc('id')->get()
            ->map(fn (RankPromotion $p) => [
                'from' => $name($p->from_belt, $p->from_grade),
                'to' => $name($p->to_belt, $p->to_grade),
                'bg' => $ladder[$p->to_belt]['bg'] ?? '#9ca3af',
                'icon' => $ladder[$p->to_belt]['icon'] ?? null,
                'on' => $p->promoted_on->toDateString(),
                'by' => $p->promoter?->full_name ?: $p->promoter?->name,
                'note' => $p->note,
            ])->all();
    }

    /** Club admins, and the coaches of this activity. */
    public function canManage(User $user, ActivityPackage $link): bool
    {
        $activity = $link->activity;
        if (! $activity) {
            return false;
        }
        $club = $activity->tenant;
        if ($user->isSuperAdmin() || (int) $club?->owner_user_id === (int) $user->id
            || $user->hasRole('club-admin', $activity->tenant_id)) {
            return true;
        }

        return DB::table('club_activity_instructor as ai')
            ->join('club_instructors as ci', 'ci.id', '=', 'ai.instructor_id')
            ->where('ai.activity_id', $activity->id)
            ->where('ci.user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('ci.is_active')->orWhere('ci.is_active', true))
            ->exists();
    }

    /**
     * A member's standing in every ranked activity they train in or hold a rank
     * in — for their own profile. Each row is one activity (one club), with the
     * rank, and where they stand for the next belt.
     *
     * @return array<int, array>
     */
    public function forUser(User $user): array
    {
        $activityIds = DB::table('club_member_subscriptions as s')
            ->join('club_package_activities as pa', 'pa.package_id', '=', 's.package_id')
            ->where('s.user_id', $user->id)->whereIn('s.status', ['active', 'pending'])
            ->pluck('pa.activity_id')
            ->merge(MemberRank::where('user_id', $user->id)->pluck('activity_id'))
            ->unique();
        if ($activityIds->isEmpty()) {
            return [];
        }

        $eligibility = app(Eligibility::class);
        $out = [];
        foreach (ActivityPackage::whereIn('activity_id', $activityIds)->where('sport', '<>', '')->with('activity.tenant:id,club_name,slug,logo')->get() as $link) {
            if (! $link->activity || ! $this->book($link)?->hasRanks()) {
                continue;
            }
            $ladder = $this->ladder($link);
            $row = $this->row($link, $user, MemberRank::where('activity_id', $link->activity_id)->where('user_id', $user->id)->first());
            $row = $eligibility->annotate($link, $ladder, [$row])[0];
            $out[] = $row + [
                'activity' => $link->activity->name,
                'club' => $link->activity->tenant?->club_name,
                'package' => $this->book($link)->styleLabel,
            ];
        }

        return $out;
    }
}
