<?php

namespace App\Shop;

use App\Clubs\Models\Tenant;
use App\Support\Modules\AbstractModule;
use App\Support\Modules\Surfaces\ContributesClubAdminNav;

/**
 * Commerce: what a club sells besides training.
 *
 * Owns products and the variants, categories and stock behind them, the orders
 * members place and their reviews, and the perks members redeem.
 *
 * Its own top-level vertical rather than a corner of the club, because it is a
 * different business with different rules — pricing, stock, fulfilment,
 * refunds — that happens to be operated from the club's workspace. The three
 * entries it adds to that workspace are declared here, not in the club.
 */
class Shop extends AbstractModule implements ContributesClubAdminNav
{
    public function key(): string
    {
        return 'shop';
    }

    public function clubAdminNav(Tenant $club, string $surface): array
    {
        return match ($surface) {
            'mobile' => [
                ['group' => 'store', 'order' => 10, 'route' => 'admin.club.shop', 'icon' => 'bi-shop', 'label' => __('admin.nav_shop')],
                ['group' => 'store', 'order' => 20, 'route' => 'admin.club.orders', 'icon' => 'bi-bag-check', 'label' => __('admin.nav_orders')],

                // Perks sit under Content on mobile — they read as something the
                // club offers its members, not as a till.
                ['group' => 'content', 'order' => 30, 'route' => 'admin.club.perks', 'icon' => 'bi-gift', 'label' => __('admin.nav_perks')],
            ],
            default => [
                ['group' => 'storefront', 'order' => 10, 'route' => 'admin.club.shop', 'icon' => 'bi-shop', 'label' => __('nav.layouts_admin_club_nav_shop')],
                ['group' => 'storefront', 'order' => 20, 'route' => 'admin.club.orders', 'icon' => 'bi-bag-check', 'label' => __('nav.layouts_admin_club_nav_orders')],
                ['group' => 'storefront', 'order' => 30, 'route' => 'admin.club.perks', 'icon' => 'bi-gift', 'label' => __('nav.layouts_admin_club_nav_perks')],
            ],
        };
    }
}
