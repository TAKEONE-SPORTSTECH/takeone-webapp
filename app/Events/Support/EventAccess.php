<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\EventOfficial;
use App\Models\User;

/**
 * Who may see an event, and who may run it.
 *
 * One place, because this rule now has two callers — the web screens and the
 * MCP server — and an authorization rule that exists twice is an authorization
 * rule that will eventually disagree with itself. The MCP must never expose
 * more than the acting user could reach in the UI, so both ask this.
 */
class EventAccess
{
    /** Visible to anyone the event's scope reaches (host-club members + wider). */
    public function visible(ClubEvent $event, User $user): bool
    {
        if ($event->is_archived) {
            return false;
        }

        return $this->eligible($event, $user) || $this->canManage($event, $user);
    }

    /** Only the event's creator may manage it (super-admin kept as a platform override). */
    public function canManage(ClubEvent $event, User $user): bool
    {
        return $event->created_by === $user->id || $user->isSuperAdmin();
    }

    /**
     * Appointed to officiate THIS event — the jury.
     *
     * Deliberately not folded into canManage(): that gates editing, deleting,
     * results and financials, and a jury has no business there. It is checked
     * on its own by canArrangeDraw().
     */
    public function isOfficial(ClubEvent $event, User $user, string $role = 'jury'): bool
    {
        return $event->officials()
            ->where('user_id', $user->id)
            ->where('role', $role)
            ->exists();
    }

    /**
     * Who may hand-arrange a draw: the organiser who created the event, the
     * jury appointed to it, and platform staff.
     *
     * This answers "who", never "when" — the event-started gate lives in the
     * package's availableActions() and in Arrangement itself, so a draw is
     * final from the first bout no matter who is asking.
     */
    public function canArrange(ClubEvent $event, User $user): bool
    {
        return $this->canManage($event, $user) || $this->isOfficial($event, $user);
    }

    /**
     * Record and verify official weights.
     *
     * The organiser is included because someone has to be able to run a
     * weigh-in when the appointed official does not turn up — but the row still
     * records WHO signed it off, so an unverified weight can never reach the
     * final draw just because nobody was appointed.
     */
    public function canVerifyWeighIn(ClubEvent $event, User $user): bool
    {
        return $this->canManage($event, $user)
            || $this->isOfficial($event, $user, EventOfficial::ROLE_WEIGH_IN);
    }

    /** Check proof of payment against the club account and approve it. */
    public function canVerifyPayments(ClubEvent $event, User $user): bool
    {
        return $this->canManage($event, $user)
            || $this->isOfficial($event, $user, EventOfficial::ROLE_PAYMENTS);
    }

    /** Any officiating job at all — used to decide who sees the console. */
    public function canOfficiate(ClubEvent $event, User $user): bool
    {
        return $this->canVerifyWeighIn($event, $user) || $this->canVerifyPayments($event, $user);
    }

    public function eligible(ClubEvent $event, User $user): bool
    {
        if ($user->memberClubs()->whereKey($event->tenant_id)->exists()) {
            return true;
        }

        return match ($event->scope ?? 'internal') {
            'inter_club', 'worldwide' => true,
            // regional currently mirrors nationwide until a region taxonomy exists.
            'nationwide', 'regional' => $this->shareCountry($event, $user),
            default => false, // internal
        };
    }

    /** True when the member belongs to a club in the host club's country. */
    private function shareCountry(ClubEvent $event, User $user): bool
    {
        $hostCountry = $event->tenant?->country ?? $event->tenant()->value('country');

        return $hostCountry
            && $user->memberClubs()->where('tenants.country', $hostCountry)->exists();
    }
}
