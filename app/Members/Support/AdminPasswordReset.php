<?php

namespace App\Members\Support;

use App\Clubs\Models\Tenant;
use App\Members\Mail\PasswordChangedByAdminEmail;
use App\Members\Models\Membership;
use App\Members\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Who may set somebody else's password, and what happens when they do.
 *
 * A club admin may set the password of a member of their club — the desk helping
 * somebody who forgot it. They may NOT for anyone who holds power over other
 * people's data (User::holdsPrivilegedAccess(): super-admin, any owner, any club
 * admin, federation staff, event officials): an admin can enroll anyone into
 * their club without consent, so "is a member here" alone was a route to taking
 * over those accounts. Those people reset by email, or through a super-admin.
 *
 * Every password set by somebody else is logged and the member is emailed, so it
 * can never happen silently. See docs/platform/account-recovery.md.
 */
final class AdminPasswordReset
{
    /**
     * The club through which $actor, as a club admin, may set $member's password,
     * or null when they may not. Super-admins and the member themselves are
     * decided by the caller, not here.
     */
    public static function viaClub(User $actor, User $member): ?Tenant
    {
        if (! $actor->isClubAdmin()) {
            return null;
        }

        $tenantId = Membership::where('user_id', $member->id)
            ->pluck('tenant_id')
            ->first(fn ($id) => $actor->isClubAdmin((int) $id));

        if ($tenantId === null || $member->holdsPrivilegedAccess()) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Record that $actor set $member's password, and tell the member.
     * The mail is best-effort: the desk has already set the password.
     */
    public static function record(User $actor, User $member, ?Tenant $club): void
    {
        activity()->causedBy($actor)->performedOn($member)
            ->withProperties(['club' => $club?->slug])
            ->log('member.password_set_by_admin');

        if (empty($member->email)) {
            return;
        }

        try {
            Mail::to($member)->send(new PasswordChangedByAdminEmail($member, $club?->club_name));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
