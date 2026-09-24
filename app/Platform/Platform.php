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
 * There used to be a second class called PlatformController that meant the
 * opposite of this one — the PUBLIC club browser (/explore, /{country}/clubs/…,
 * joining a club, collecting a perk, liking a timeline post). It has moved into
 * Clubs as PublicClubController, so the name no longer means two things.
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
