<?php

namespace App\Clubs;

use App\Clubs\Models\Tenant;
use App\Support\Modules\AbstractModule;
use App\Support\Modules\Surfaces\ContributesClubAdminNav;

/**
 * The club: the tenant every other vertical hangs off.
 *
 * Owns the club's identity and settings, its presence (timeline, gallery,
 * awarded achievements), its programme (activities and their equipment,
 * packages, the class timetable, instructors, facilities), its books
 * (transactions, invoices, bank accounts, recurring expenses) and how it talks
 * to its members.
 *
 * Does NOT own: the roster (App\Members), what it sells besides training
 * (App\Shop), or its events (App\Events). Those are their own verticals that
 * happen to have something to show in the club's workspace — which is exactly
 * why nav contribution is an interface rather than a property of this module.
 */
class Clubs extends AbstractModule implements ContributesClubAdminNav
{
    public function key(): string
    {
        return 'clubs';
    }

    public function clubAdminNav(Tenant $club, string $surface): array
    {
        // Deciding a member's claimed medal is a People job to the reader, even
        // though the code that does it is the achievements admin.
        $verifications = [
            'group' => 'people',
            'order' => 40,
            'route' => 'admin.club.achievements.verifications',
            'icon' => 'bi-patch-check',
            'label' => __('nav.layouts_admin_club_nav_verifications'),
            'badge' => $this->pendingVerifications($club),
        ];

        return match ($surface) {
            'mobile' => [
                ['group' => 'overview', 'order' => 10, 'route' => 'admin.club.dashboard', 'icon' => 'bi-speedometer2', 'label' => __('admin.nav_dashboard')],
                ['group' => 'overview', 'order' => 20, 'route' => 'admin.club.analytics', 'icon' => 'bi-bar-chart', 'label' => __('admin.nav_analytics')],

                ['group' => 'people', 'order' => 20, 'route' => 'admin.club.instructors', 'icon' => 'bi-person-badge', 'label' => __('admin.nav_instructors')],
                $verifications,
                ['group' => 'people', 'order' => 50, 'route' => 'admin.club.messages', 'icon' => 'bi-chat-dots', 'label' => __('admin.nav_messages')],
                ['group' => 'people', 'order' => 60, 'route' => 'admin.club.notifications', 'icon' => 'bi-bell', 'label' => __('admin.nav_notifications')],

                ['group' => 'offerings', 'order' => 10, 'route' => 'admin.club.packages', 'icon' => 'bi-box', 'label' => __('admin.nav_packages')],
                ['group' => 'offerings', 'order' => 20, 'route' => 'admin.club.activities', 'icon' => 'bi-activity', 'label' => __('admin.nav_activities')],
                ['group' => 'offerings', 'order' => 50, 'route' => 'admin.club.facilities', 'icon' => 'bi-geo-alt', 'label' => __('admin.nav_facilities')],

                ['group' => 'content', 'order' => 10, 'route' => 'admin.club.gallery', 'icon' => 'bi-images', 'label' => __('admin.nav_gallery')],
                ['group' => 'content', 'order' => 20, 'route' => 'admin.club.timeline', 'icon' => 'bi-newspaper', 'label' => __('admin.nav_timeline')],
                ['group' => 'content', 'order' => 40, 'route' => 'admin.club.achievements', 'icon' => 'bi-trophy', 'label' => __('admin.nav_achievements')],

                ['group' => 'finance', 'order' => 10, 'route' => 'admin.club.financials', 'icon' => 'bi-currency-dollar', 'label' => __('admin.nav_financials')],

                ['group' => 'settings', 'order' => 10, 'route' => 'admin.club.details', 'icon' => 'bi-building', 'label' => __('admin.nav_details')],
            ],
            default => [
                ['group' => 'overview', 'order' => 10, 'route' => 'admin.club.dashboard', 'icon' => 'bi-speedometer2', 'label' => __('nav.layouts_admin_club_nav_dashboard')],
                ['group' => 'overview', 'order' => 20, 'route' => 'admin.club.analytics', 'icon' => 'bi-bar-chart', 'label' => __('nav.layouts_admin_club_nav_analytics')],
                ['group' => 'overview', 'order' => 30, 'route' => 'admin.club.financials', 'icon' => 'bi-currency-dollar', 'label' => __('nav.layouts_admin_club_nav_financials')],

                ['group' => 'people', 'order' => 20, 'route' => 'admin.club.instructors', 'icon' => 'bi-people', 'label' => __('nav.layouts_admin_club_nav_instructors')],
                $verifications,

                ['group' => 'programs', 'order' => 10, 'route' => 'admin.club.activities', 'icon' => 'bi-activity', 'label' => __('nav.layouts_admin_club_nav_activities')],
                ['group' => 'programs', 'order' => 20, 'route' => 'admin.club.packages', 'icon' => 'bi-box', 'label' => __('nav.layouts_admin_club_nav_packages')],
                ['group' => 'programs', 'order' => 50, 'route' => 'admin.club.facilities', 'icon' => 'bi-geo-alt', 'label' => __('nav.layouts_admin_club_nav_facilities')],

                ['group' => 'content', 'order' => 10, 'route' => 'admin.club.timeline', 'icon' => 'bi-newspaper', 'label' => __('nav.layouts_admin_club_nav_timeline')],
                ['group' => 'content', 'order' => 20, 'route' => 'admin.club.gallery', 'icon' => 'bi-images', 'label' => __('nav.layouts_admin_club_nav_gallery')],
                ['group' => 'content', 'order' => 30, 'route' => 'admin.club.achievements', 'icon' => 'bi-trophy', 'label' => __('nav.layouts_admin_club_nav_achievements')],
            ],
        };
    }

    /** Member-claimed medals and skills naming this club, awaiting a decision. */
    private function pendingVerifications(Tenant $club): int
    {
        $pending = fn (string $model) => $model::whereHas('clubAffiliation', fn ($q) => $q->where('tenant_id', $club->id))
            ->where('verification_status', 'pending')
            ->count();

        return $pending(\App\Members\Models\TournamentEvent::class) + $pending(\App\Members\Models\SkillAcquisition::class);
    }
}
