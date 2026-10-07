<?php

namespace App\Events\Support\ClubEntry;

use App\Events\Models\ClubEvent;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\Surfaces\HostsClubEntry;

/**
 * Which back office takes CLUB entries for an event — asked of every module
 * that hosts club entries (HostsClubEntry), the same way EventHost finds the
 * module behind a host type. The Events module never names one.
 *
 * Null means the event takes no club entries through the shared screens (yet):
 * those addresses then answer 404, and nothing links to them.
 */
final class ClubEntryDesks
{
    public static function for(ClubEvent $event): ?ClubEntryDesk
    {
        foreach (app(ModuleRegistry::class)->contributingTo(HostsClubEntry::class) as $module) {
            $desk = $module->clubEntryDesk($event);
            if ($desk !== null) {
                return $desk;
            }
        }

        return null;
    }
}
