<?php

namespace App\Support\Modules\Surfaces;

use App\Clubs\Models\Tenant;

/**
 * A module that puts entries in the club admin workspace's navigation.
 *
 * Implemented by any module with something for a club owner to manage —
 * including modules that are not "the club": the shop is its own top-level
 * vertical and still owns the three entries it adds here.
 *
 * A module with nothing to say to this shell does not implement the interface,
 * rather than implementing it and returning nothing.
 */
interface ContributesClubAdminNav
{
    /**
     * Nav entries this module contributes, in no particular order.
     *
     * Each entry:
     *   'group' => 'people'        which sidebar group it appears under
     *   'order' => 20              its position within that group
     *   'route' => 'admin.club.instructors'
     *   'icon'  => 'bi-people'
     *   'label' => __('…')
     *   'badge' => int             optional live count
     *
     * `group` is deliberately separate from which module owns the code, because
     * the two do not line up and pretending they do would either scramble a
     * sidebar people know or force code into the wrong folder. Instructors are
     * club programme code that belongs under "People"; the shop's perks read as
     * something the club offers rather than a till. A module says where its
     * entry BELONGS TO THE READER, and keeps its code where it belongs to the
     * codebase.
     *
     * $surface is 'desktop' or 'mobile'. The two shells are deliberately
     * different navigations, not one navigation at two widths — the mobile
     * drawer groups differently, uses its own icons and labels, and surfaces
     * entries the desktop rail keeps elsewhere. A module therefore answers per
     * surface, and returning [] for one is normal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function clubAdminNav(Tenant $club, string $surface): array;
}
