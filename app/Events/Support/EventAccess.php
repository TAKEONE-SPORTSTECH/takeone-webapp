<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
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
