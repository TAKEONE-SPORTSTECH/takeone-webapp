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
class Members extends AbstractModule implements ContributesClubAdminNav
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
}
