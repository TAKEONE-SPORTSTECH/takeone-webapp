<?php

namespace App\Services;

use App\Models\AchievementVouch;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserRelationship;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for the verification status of any member-authored,
 * club-attestable record — a self-claimed tournament medal OR an acquired skill.
 * Every `$model` passed in uses App\Traits\HasVerificationState (providing the
 * status columns, `attestingTenant()`, `attestationOwnerId()`, `attestationLabel()`
 * and the polymorphic `vouches()`). Controllers, MCP tools and jobs MUST route
 * every status change through this service — they never write verification_* directly.
 *
 * Trust model: a record is created `self_reported`. It becomes `verified` only when
 * a trusted authority attests to it — the named club confirms (`club_confirm`), or
 * credible coach/teammate vouches pass threshold (`vouch`).
 */
class AchievementVerificationService
{
    /** Weighted-vouch score at/above which a claim auto-verifies. */
    public const VOUCH_THRESHOLD = 3.0;

    /** Credibility weights per voucher relationship to the claim's club. */
    private const WEIGHT_CLUB_STAFF = 3.0;   // admin/owner/instructor of the named club
    private const WEIGHT_OFFICIAL = 2.0;     // self-declared official, not club staff
    private const WEIGHT_COACH = 1.5;        // self-declared coach, not club staff
    private const WEIGHT_TEAMMATE = 1.0;     // plain member vouch

    /**
     * Member (or their guardian) asks for a record to be verified. Moves it to
     * `pending` and notifies the named club's admins when it maps to a real club.
     */
    public function requestVerification(Model $model, User $actor): Model
    {
        // A club decision is final; don't let a re-request reopen it.
        if (in_array($model->verification_status, [$model::STATUS_VERIFIED, $model::STATUS_REJECTED], true)
            && $model->verification_method === 'club_confirm') {
            return $model;
        }

        $model->forceFill(['verification_status' => $model::STATUS_PENDING])->save();

        $tenant = $model->attestingTenant();
        if ($tenant) {
            $owner = $this->owner($model);
            foreach ($this->clubAdminIds((int) $tenant->id) as $adminId) {
                UserNotification::notifyUser(
                    $adminId,
                    'verification:requested',
                    __(':name asked you to verify a record', ['name' => $owner?->full_name ?? __('A member')]),
                    [
                        'actor_id' => $actor->id,
                        'tenant_id' => (int) $tenant->id,
                        'subject_type' => $model::class,
                        'subject_id' => $model->getKey(),
                        'body' => $model->attestationLabel(),
                        'icon' => 'bi-patch-check',
                        'action_url' => route('admin.club.achievements.verifications', $tenant->slug ?? $tenant->id),
                    ],
                );
            }
        }

        $this->pushStatus($model);

        return $model;
    }

    /** A club admin confirms a record naming their club. Highest-trust attestation. */
    public function clubConfirm(Model $model, User $admin): Model
    {
        $tenant = $model->attestingTenant();
        if (! $tenant || ! $this->canAdminTenant($admin, (int) $tenant->id)) {
            throw new AuthorizationException('Not authorized to verify this record.');
        }

        $model->forceFill([
            'verification_status' => $model::STATUS_VERIFIED,
            'verification_method' => 'club_confirm',
            'verified_by_tenant_id' => $tenant->id,
            'verified_by_user_id' => $admin->id,
            'verified_at' => now(),
            'verification_note' => null,
        ])->save();

        $this->audit('confirmed', $model, $admin);
        $this->notifyMember($model, $admin, 'verification:approved',
            __('Your submission was verified by :club', ['club' => $tenant->tr('club_name') ?? $tenant->club_name]),
            'bi-patch-check-fill');
        $this->pushStatus($model);

        return $model;
    }

    /** A club admin rejects a record naming their club (with a reason). */
    public function clubReject(Model $model, User $admin, ?string $note = null): Model
    {
        $tenant = $model->attestingTenant();
        if (! $tenant || ! $this->canAdminTenant($admin, (int) $tenant->id)) {
            throw new AuthorizationException('Not authorized to review this record.');
        }

        $model->forceFill([
            'verification_status' => $model::STATUS_REJECTED,
            'verification_method' => 'club_confirm',
            'verified_by_tenant_id' => $tenant->id,
            'verified_by_user_id' => $admin->id,
            'verified_at' => null,
            'verification_note' => $note,
        ])->save();

        $this->audit('rejected', $model, $admin);
        $this->notifyMember($model, $admin, 'verification:rejected',
            __('A submission could not be verified'), 'bi-patch-exclamation');
        $this->pushStatus($model);

        return $model;
    }

    /**
     * Record a peer/coach vouch (or dispute), then recompute the record's status.
     * Eligibility is enforced here as defence-in-depth on top of the FormRequest.
     */
    public function addVouch(Model $model, User $voucher, string $stance, string $relationship, ?string $note = null): AchievementVouch
    {
        if (! $this->canVouch($voucher, $model)) {
            throw new AuthorizationException('You are not eligible to vouch for this record.');
        }

        $weight = $this->credibilityWeight($voucher, $model);

        $vouch = AchievementVouch::updateOrCreate(
            ['vouchable_type' => $model::class, 'vouchable_id' => $model->getKey(), 'voucher_user_id' => $voucher->id],
            [
                'stance' => $stance === AchievementVouch::STANCE_DISPUTE ? AchievementVouch::STANCE_DISPUTE : AchievementVouch::STANCE_VOUCH,
                'relationship' => in_array($relationship, ['coach', 'official', 'teammate', 'other'], true) ? $relationship : 'other',
                'note' => $note,
            ],
        );
        // weight is server-derived, never mass-assigned.
        $vouch->forceFill(['weight' => $weight])->save();

        $this->recompute($model);

        return $vouch;
    }

    /**
     * Recompute a record's status from (club decision, weighted vouches).
     * A club decision always wins and is never downgraded here.
     */
    public function recompute(Model $model): Model
    {
        if ($model->verification_method === 'club_confirm') {
            return $model; // club decision is final; vouches can't override it
        }

        $vouches = $model->vouches()->get();
        $support = $vouches->where('stance', AchievementVouch::STANCE_VOUCH)->sum(fn ($v) => (float) $v->weight);
        $dispute = $vouches->where('stance', AchievementVouch::STANCE_DISPUTE)->sum(fn ($v) => (float) $v->weight);
        $hasCredible = $vouches
            ->where('stance', AchievementVouch::STANCE_VOUCH)
            ->contains(fn ($v) => (float) $v->weight >= self::WEIGHT_COACH);

        $prev = $model->verification_status;

        if ($dispute >= $support && $dispute > 0) {
            $status = $model::STATUS_REJECTED;
        } elseif ($support >= self::VOUCH_THRESHOLD && $hasCredible) {
            $status = $model::STATUS_VERIFIED;
        } elseif ($support > 0) {
            $status = $model::STATUS_PENDING;
        } else {
            $status = $model::STATUS_SELF_REPORTED;
        }

        $model->forceFill([
            'verification_status' => $status,
            'verification_method' => $status === $model::STATUS_VERIFIED ? 'vouch' : $model->verification_method,
            'verified_at' => $status === $model::STATUS_VERIFIED ? now() : null,
        ])->save();

        if ($status !== $prev) {
            if ($status === $model::STATUS_VERIFIED) {
                $this->notifyMember($model, null, 'verification:approved',
                    __('Your submission was verified by your peers'), 'bi-patch-check-fill');
            }
            $this->pushStatus($model);
        }

        return $model;
    }

    /** Credibility weight of a voucher for this record. 0 = excluded (self / family). */
    public function credibilityWeight(User $voucher, Model $model): float
    {
        $ownerId = (int) $model->attestationOwnerId();
        if ($voucher->id === $ownerId || $this->isFamilyLinked($voucher->id, $ownerId)) {
            return 0.0;
        }

        $tenant = $model->attestingTenant();
        if ($tenant && ($this->canAdminTenant($voucher, (int) $tenant->id) || $voucher->isInstructor((int) $tenant->id))) {
            return self::WEIGHT_CLUB_STAFF;
        }

        $vouch = $model->vouches()->where('voucher_user_id', $voucher->id)->first();
        return match ($vouch?->relationship) {
            'official' => self::WEIGHT_OFFICIAL,
            'coach' => self::WEIGHT_COACH,
            default => self::WEIGHT_TEAMMATE,
        };
    }

    /**
     * May this user vouch for this record? Never for their own, never a family member,
     * and (as anti-ring hygiene) reciprocal vouches are blocked.
     */
    public function canVouch(User $voucher, Model $model): bool
    {
        $ownerId = (int) $model->attestationOwnerId();
        if ($voucher->id === $ownerId || $this->isFamilyLinked($voucher->id, $ownerId)) {
            return false;
        }
        if ($this->isReciprocalRing($voucher->id, $ownerId)) {
            Log::warning('Reciprocal attestation vouch blocked', [
                'voucher_user_id' => $voucher->id,
                'claim_owner_id' => $ownerId,
                'vouchable' => $model::class.'#'.$model->getKey(),
            ]);

            return false;
        }

        return true;
    }

    // ---- helpers -------------------------------------------------------------

    private function owner(Model $model): ?User
    {
        $id = $model->attestationOwnerId();

        return $id ? User::find($id) : null;
    }

    private function canAdminTenant(User $user, int $tenantId): bool
    {
        return $user->isSuperAdmin() || $user->isClubAdmin($tenantId)
            || DB::table('tenants')->where('id', $tenantId)->where('owner_user_id', $user->id)->exists();
    }

    /** Owner + club-admins of a tenant. */
    private function clubAdminIds(int $tenantId): array
    {
        $ownerId = DB::table('tenants')->where('id', $tenantId)->value('owner_user_id');

        $adminIds = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.tenant_id', $tenantId)
            ->where('roles.slug', 'club-admin')
            ->pluck('user_roles.user_id')
            ->all();

        return collect($adminIds)->push($ownerId)->filter()->unique()->values()->all();
    }

    private function isFamilyLinked(int $a, int $b): bool
    {
        return UserRelationship::where(fn ($q) => $q->where('guardian_user_id', $a)->where('dependent_user_id', $b))
            ->orWhere(fn ($q) => $q->where('guardian_user_id', $b)->where('dependent_user_id', $a))
            ->exists();
    }

    /** True if the claim owner has themselves vouched for any record owned by this voucher. */
    private function isReciprocalRing(int $voucherId, int $ownerId): bool
    {
        return AchievementVouch::where('voucher_user_id', $ownerId)
            ->with('vouchable')
            ->get()
            ->contains(fn ($v) => $v->vouchable && (int) ($v->vouchable->attestationOwnerId() ?? 0) === $voucherId);
    }

    private function notifyMember(Model $model, ?User $actor, string $type, string $title, string $icon): void
    {
        $ownerId = $model->attestationOwnerId();
        if (! $ownerId) {
            return;
        }
        $owner = $this->owner($model);

        UserNotification::notifyUser((int) $ownerId, $type, $title, [
            'actor_id' => $actor?->id,
            'tenant_id' => $model->verified_by_tenant_id,
            'subject_type' => $model::class,
            'subject_id' => $model->getKey(),
            'body' => $model->attestationLabel(),
            'icon' => $icon,
            'action_url' => route('member.show', $owner?->uuid ?? $ownerId),
        ]);
    }

    /**
     * Best-effort MQTT patch to the member + the attesting club's admins.
     * Same payload for every recipient (status is not viewer-specific).
     */
    private function pushStatus(Model $model): void
    {
        if (! function_exists('Realtime') || ! Realtime()->enabled()) {
            return;
        }

        $payload = [
            'action' => 'status',
            'event_uuid' => $model->uuid,
            'status' => $model->verification_status,
            'method' => $model->verification_method,
            'verified_club' => $model->verifiedByTenant?->tr('club_name') ?? $model->verifiedByTenant?->club_name,
        ];

        $recipients = [(int) $model->attestationOwnerId()];
        if ($tenant = $model->attestingTenant()) {
            $recipients = array_merge($recipients, $this->clubAdminIds((int) $tenant->id));
        }

        foreach (array_unique(array_filter($recipients)) as $uid) {
            Realtime()->publishToUser($uid, 'verification', $payload);
        }
    }

    private function audit(string $action, Model $model, User $admin): void
    {
        Log::info('Verification '.$action, [
            'vouchable' => $model::class.'#'.$model->getKey(),
            'claim_owner_id' => $model->attestationOwnerId(),
            'admin_id' => $admin->id,
            'tenant_id' => $model->verified_by_tenant_id,
        ]);
    }
}
