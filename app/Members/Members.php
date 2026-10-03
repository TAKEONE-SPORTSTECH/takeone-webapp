<?php

namespace App\Members;

use App\Clubs\Models\Tenant;
use App\Support\Modules\AbstractModule;
use App\Support\Modules\Surfaces\ContributesClubAdminNav;

/**
 * The member: the person the platform exists for.
 *
 * Owns who they are and what is recorded about them — the profile, family and
 * guardianship, health records, goals, attendance, certifications and work
 * history, skills and their provenance — plus their membership of a club and the
 * roles and per-member permissions that decide what they may do inside it. It
 * also owns how one member reaches another: discovery, the safe public profile,
 * the social graph, direct messages and the personal feed.
 *
 * Its surfaces are /me (home, schedule, packages, payments, progress,
 * affiliations, settings), /member/{uuid}, /family/*, /people/{uuid}, /messages
 * and /bills.
 *
 * Does NOT own: the club (App\Clubs), competition (App\Events), what a club
 * sells (App\Shop), member-vs-member duels (App\Challenges) or footage
 * (App\Media). Several of those have screens under /me — a member-facing URL is
 * not the same thing as a member-owned vertical, and their routes stay with
 * their own module.
 *
 * Still outside the folder, deliberately, and listed so it is not mistaken for
 * finished work:
 *   • FamilyService, KinshipService, PeopleRecommendationService and
 *     AchievementVerificationService are still in app/Services. The first three
 *     are Members' alone but have their own unit tests, which ModuleBoundaryTest
 *     counts as outside callers of a private layer; the fourth is genuinely
 *     shared with the club achievements admin and the MCP.
 *   • The lang files (lang/{en,ar}/personal.php, family.php, people.php,
 *     messenger.php) stay platform-level, as the club module's do.
 *   • resources/views/personal/ still holds the Events, Challenges, Shop and
 *     Media screens that share the /me shell. They leave with their own modules.
 */
class Members extends AbstractModule implements ContributesClubAdminNav, \App\Support\Modules\Surfaces\HostsEvents
{
    public function key(): string
    {
        return 'members';
    }

    public function clubAdminNav(Tenant $club, string $surface): array
    {
        return match ($surface) {
            'mobile' => [
                ['group' => 'people', 'order' => 10, 'route' => 'admin.club.members', 'icon' => 'bi-people', 'label' => __('admin.nav_members')],
                ['group' => 'people', 'order' => 30, 'route' => 'admin.club.roles', 'icon' => 'bi-person-lock', 'label' => __('admin.nav_roles')],
            ],
            default => [
                ['group' => 'people', 'order' => 10, 'route' => 'admin.club.members', 'icon' => 'bi-person-plus', 'label' => __('nav.layouts_admin_club_nav_members')],
                ['group' => 'people', 'order' => 30, 'route' => 'admin.club.roles', 'icon' => 'bi-person-lock', 'label' => __('nav.layouts_admin_club_nav_roles')],
            ],
        };
    }

    // ------------------------------------------------------------------
    // A PERSON hosting their own event (owner, 2026-10-03): no club behind it,
    // the organiser is the host (App\Events\Support\EventHost, kind 'person').
    // Nothing here grants power over anybody else's event: it answers only for
    // an event whose host IS this person.
    // ------------------------------------------------------------------

    public function eventHostTypes(): array
    {
        return ['person'];
    }

    public function eventHost(string $type, int $id): ?array
    {
        $u = $type === 'person' ? \App\Members\Models\User::find($id) : null;
        if (! $u) {
            return null;
        }

        return [
            'name' => (string) ($u->full_name ?: $u->name),
            // A person is not a brand: no logo stands in for them, and their
            // face is theirs to show (profile_picture_is_public) — so none.
            'logo' => null,
            'country' => $u->nationality && strlen((string) $u->nationality) <= 3 ? strtoupper((string) $u->nationality) : null,
            'currency' => null,      // the event's own fee_currency, chosen in the editor
            'timezone' => null,      // the event's own timezone
            'lat' => null,
            'lng' => null,
            'color' => null,
            'uuid' => $u->uuid,
        ];
    }

    public function managesEventsOf(string $type, int $id, \App\Members\Models\User $user): bool
    {
        return $type === 'person' && (int) $id === (int) $user->id;
    }

    public function eventHostManagers(string $type, int $id): array
    {
        return $type === 'person' ? [$id] : [];
    }

    public function hostedEventChanged(\App\Events\Models\ClubEvent $event): void {}

    public function hostedEntryDecided(\App\Events\Models\ClubEvent $event, \App\Events\Models\EventPublicEntry $entry): void {}

    /** People enter a person's event themselves — no other door. */
    public function hostedEntryDoor(\App\Events\Models\ClubEvent $event, \App\Members\Models\User $user): ?array
    {
        return null;
    }
}
