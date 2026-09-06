<?php

namespace App\Support\Modules;

/**
 * One vertical of the platform.
 *
 * The whole application is a set of modules — events, clubs, members,
 * challenges, the shop, personal training, media — each owning a top-level
 * folder under app/ that holds everything that vertical needs: its models, its
 * controllers, its services, its routes, its views, its translations.
 *
 * WHY: at ~106k lines the alternative is a codebase organised by TECHNICAL KIND
 * — every model in one folder, every controller in another — where the shop, the
 * roster and the books sit side by side as neighbours with nothing to do with
 * each other. Reading or changing one vertical then means opening files that
 * belong to five others, and nothing tells you where a vertical stops. A module
 * boundary answers that: everything about the shop is app/Shop/, and a change
 * there cannot reach the club's books without crossing a line you can see.
 *
 * A module is NOT a plugin to be swapped for another implementation — unlike an
 * event type, which is a substitutable variant behind one contract. A module
 * exists to be BOUNDED, so it can be read, reviewed, changed or deleted as a
 * unit.
 *
 * Test: could this vertical be removed by deleting its directory, its tables and
 * its one line in config/modules.php — with the rest of the platform still
 * working?
 *
 * A module contributes to a SHELL by implementing that shell's surface
 * interface (see Surfaces/). A module that has nothing to say to a shell simply
 * does not implement it, which is how the club admin sidebar can be composed
 * from whichever modules have something to put in it — the shop is its own
 * top-level module, and still owns the three entries it adds to the club
 * workspace.
 */
interface Module
{
    /**
     * Stable slug. Names the module's view and translation namespaces
     * (`<key>::…`), so it must not change once shipped.
     */
    public function key(): string;
}
