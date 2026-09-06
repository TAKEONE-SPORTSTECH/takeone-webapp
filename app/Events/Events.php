<?php

namespace App\Events;

use App\Clubs\Models\Tenant;
use App\Support\Modules\AbstractModule;
use App\Support\Modules\Surfaces\ContributesClubAdminNav;

/**
 * Competition: everything a club or the platform runs as an event.
 *
 * The one module that is itself made of packages. A sport owns a folder under
 * Sports/ and each kind of event that sport runs is a sub-folder implementing the
 * EventType contract — bound by App\Events\EventPackageServiceProvider, a level
 * below the platform module system. That nesting is deliberate: an event TYPE is
 * a substitutable variant behind one contract, which is a different relationship
 * from the one modules have to each other.
 *
 * Owns `club_events` as its shared core, the draw and bracket engines, entry and
 * billing, officials, the hall screens and the run-day flow.
 */
class Events extends AbstractModule implements ContributesClubAdminNav
{
    public function key(): string
    {
        return 'events';
    }

    public function clubAdminNav(Tenant $club, string $surface): array
    {
        return match ($surface) {
            'mobile' => [
                ['group' => 'offerings', 'order' => 30, 'route' => 'admin.club.events', 'icon' => 'bi-calendar-event', 'label' => __('admin.nav_events')],
                ['group' => 'offerings', 'order' => 40, 'route' => 'admin.club.sparring', 'icon' => 'bi-lightning-charge', 'label' => __('event-sparring::messages.label')],
            ],
            default => [
                ['group' => 'programs', 'order' => 30, 'route' => 'admin.club.events', 'icon' => 'bi-calendar-event', 'label' => __('nav.layouts_admin_club_nav_events')],
                ['group' => 'programs', 'order' => 40, 'route' => 'admin.club.sparring', 'icon' => 'bi-lightning-charge', 'label' => __('event-sparring::messages.label')],
            ],
        };
    }
}
