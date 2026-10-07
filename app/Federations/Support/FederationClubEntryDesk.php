<?php

namespace App\Federations\Support;

use App\Events\Support\ClubEntry\ClubEntryDesk;
use App\Federations\Controllers\FederationAppController;
use App\Federations\Controllers\InvitePageController;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationInvitation;
use Illuminate\Http\Request;

/**
 * The federation's back office behind the shared club-entry screens.
 *
 * Nothing new: the same boot, store and doors the federation's own invitation
 * page has always run on (FederationAppController::inviteBoot,
 * InvitePageController::viewData), so a club entering through
 * /me/events/{uuid}/club writes through the very same
 * `federations.invite.*` doors — and the mirror into platform registrations
 * (SyncPlatformEntries) keeps running, because it is keyed on those doors.
 */
final class FederationClubEntryDesk implements ClubEntryDesk
{
    public function __construct(private FederationEvent $event) {}

    public function prepare(Request $request): void
    {
        $invitation = app(FederationInvitation::class);
        if ($request->query('club')) {
            $invitation->bindToken($this->event, (string) $request->query('club'));
        }
        $invitation->bindOwner($this->event, $request->user());
    }

    public function subject(Request $request): ?array
    {
        $invitation = app(FederationInvitation::class);
        $club = $invitation->sessionClub($this->event);
        if (! $club) {
            return null;
        }
        $name = $club instanceof \App\Federations\Models\FederationClub ? $club->club?->club_name : $club->name;

        return ['key' => app(\App\Federations\Services\FederationRegistry::class)->clubKey($club), 'name' => $name];
    }

    public function invitationUrl(): ?string
    {
        return route('federations.app.invitation').'?event='.rawurlencode($this->event->uuid);
    }

    public function page(Request $request): array
    {
        $boot = app(FederationAppController::class)->inviteBoot($this->event);

        return app(InvitePageController::class)->viewData($request, $this->event, $boot);
    }
}
