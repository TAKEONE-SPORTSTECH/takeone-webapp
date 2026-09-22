<?php

namespace App\Platform;

use App\Support\Modules\AbstractModule;

/**
 * The platform as its own subject: the super-admin's room, and the operations
 * that keep the box running.
 *
 * Every other module is a vertical the PRODUCT has — clubs, members, events,
 * the shop. This one is the vertical the OPERATOR has: who owns which club,
 * what the whole platform is doing, the global activity directory every club
 * picks from, the settings that apply everywhere, and the commands that back
 * things up, reset a baseline or seed a demo.
 *
 * ⚠️ NOT to be confused with App\Http\Controllers\PlatformController, which
 * despite the name is the PUBLIC club browser — /explore, /{country}/clubs/…,
 * joining a club, collecting a perk, liking a timeline post. That one belongs
 * with Clubs and has not moved; see Documentation/HOUSE-CLEANING.md. The
 * super-admin's own screens are Admin\PlatformController, and they are here.
 *
 * The activity catalogue sits here rather than in Clubs because it is
 * explicitly GLOBAL and not tenant-scoped: it is the list every club chooses
 * from, maintained centrally, and a club's own activities reference it.
 */
class Platform extends AbstractModule
{
    public function key(): string
    {
        return 'platform';
    }
}
