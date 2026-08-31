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
 * roles and per-member permissions that decide what they may do inside it.
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
