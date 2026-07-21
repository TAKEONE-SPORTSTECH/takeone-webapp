<?php

namespace App\Mcp\Tools;

use App\Models\ClubAchievement;
use App\Models\TournamentEvent;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Get member')]
#[Description('Fetch a single member profile by uuid or numeric id. Authorization mirrors the app: allowed only for super-admins, the member themselves, a confirmed guardian, or a club-admin of a club the member belongs to.')]
class GetMemberTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'member' => $schema->string()->required()
                ->description('Member uuid (preferred) or numeric id.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $ref = (string) $request->get('member');

        $member = User::query()
            ->where('uuid', $ref)
            ->orWhere('id', is_numeric($ref) ? (int) $ref : 0)
            ->first();

        if (! $member) {
            return Response::error('Member not found.');
        }

        if (! $this->canViewMember($user, $member)) {
            return Response::error('You are not authorized to view this member.');
        }

        return Response::json([
            'id' => $member->id,
            'uuid' => $member->uuid,
            'slug' => $member->slug,
            'name' => $member->full_name ?? $member->name,
            'email' => $member->email,
            'phone' => $member->phone,
            'gender' => $member->gender,
            'birthdate' => optional($member->birthdate)->toDateString(),
            'age' => $member->age,
            'blood_type' => $member->blood_type,
            'marital_status' => $member->marital_status,
            'motto' => $member->motto,
            'is_personal_trainer' => (bool) $member->is_personal_trainer,
            'clubs' => $member->memberClubs()->get(['tenants.id', 'club_name', 'slug'])
                ->map(fn ($c) => [
                    'club_id' => $c->id,
                    'club_name' => $c->club_name,
                    'slug' => $c->slug,
                    'status' => $c->pivot->status ?? null,
                ])->values(),
            'active_subscriptions' => $member->subscriptions()->where('status', 'active')->count(),
            // Only AUTHENTIC medals: club-awarded achievements + tournament results a
            // club has verified. Self-reported / pending claims are never exposed here.
            'medals' => $this->authenticMedals($member),
            // Only VERIFIED skills (provenance-backed): activity + club + since + proficiency.
            'skills' => $this->verifiedSkills($member),
            // Self-managed certifications / qualifications the member holds.
            'certifications' => $member->certifications()
                ->orderByRaw('issue_date IS NULL, issue_date DESC')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'title' => $c->title,
                    'issuer' => $c->issuer,
                    'issue_date' => optional($c->issue_date)->toDateString(),
                    'expiry_date' => optional($c->expiry_date)->toDateString(),
                    'expired' => $c->isExpired(),
                    'credential_id' => $c->credential_id,
                    'credential_url' => $c->credential_url,
                ])->values(),
            // Self-managed work / coaching history (current roles first).
            'work_history' => $member->workHistory()
                ->orderByRaw('end_date IS NULL DESC')
                ->orderBy('start_date', 'desc')
                ->get()
                ->map(fn ($w) => [
                    'id' => $w->id,
                    'title' => $w->title,
                    'organization' => $w->organization,
                    'employment_type' => $w->employment_type,
                    'location' => $w->location,
                    'start_date' => optional($w->start_date)->toDateString(),
                    'end_date' => optional($w->end_date)->toDateString(),
                    'current' => $w->isCurrent(),
                ])->values(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function verifiedSkills(User $member): array
    {
        return \App\Models\SkillAcquisition::where('user_id', $member->id)
            ->verified()
            ->with(['activity:id,name,translations', 'verifiedByTenant:id,club_name'])
            ->get()
            ->map(fn ($s) => [
                'skill' => $s->skill_name,
                'activity' => $s->activity?->tr('name') ?? $s->activity_name,
                'club' => $s->verifiedByTenant?->club_name,
                'since' => $s->start_date ? $s->start_date->format('Y-m') : null,
                'proficiency' => $s->proficiency_level,
                'verification_status' => 'verified',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function authenticMedals(User $member): array
    {
        $medals = [];

        // Club-awarded achievements the member is linked to (authentic by construction).
        $clubIds = $member->memberClubs()->pluck('tenants.id');
        if ($clubIds->isNotEmpty()) {
            ClubAchievement::whereIn('tenant_id', $clubIds)
                ->where('status', 'active')
                ->with('tenant:id,club_name,slug')
                ->get()
                ->each(function ($a) use ($member, &$medals) {
                    $athletes = is_array($a->athletes) ? $a->athletes : [];
                    $mine = collect($athletes)->first(fn ($x) => is_array($x) && (int) ($x['user_id'] ?? 0) === (int) $member->id);
                    if ($mine) {
                        $medals[] = [
                            'source' => 'club_award',
                            'title' => $a->title,
                            'award' => $mine['role'] ?? null,
                            'club' => $a->tenant?->club_name,
                            'verification_status' => 'verified',
                        ];
                    }
                });
        }

        // Member self-recorded tournament medals that a club has VERIFIED.
        TournamentEvent::where('user_id', $member->id)
            ->verified()
            ->with(['performanceResults:id,tournament_event_id,medal_type,points', 'verifiedByTenant:id,club_name'])
            ->get()
            ->each(function ($t) use (&$medals) {
                foreach ($t->performanceResults as $r) {
                    $medals[] = [
                        'source' => 'tournament',
                        'title' => $t->title,
                        'award' => $r->medal_type,
                        'club' => $t->verifiedByTenant?->club_name,
                        'verification_status' => 'verified',
                    ];
                }
            });

        return $medals;
    }
}
