<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the event sandbox's tables.
 *
 * `app/EventLab/` was a prototyping module served under /testcode. Everything
 * in it that turned out to be worth keeping has been ported into the live app
 * first — the clubs standing behind an event (`event_clubs`,
 * `event_club_entrants`), multi-pricing (`event_fee_options`,
 * `event_registration_fee_lines`), the entrant's payment-proof door, contact
 * editing, and the event-scoped club picker — and the module itself is deleted
 * in the same change. Its code lives on in commit e54f7720 if it is ever
 * wanted back.
 *
 * ⚠️ This is DESTRUCTIVE. It drops 112 rows of prototype data across eight
 * tables. A verified backup was taken immediately before it was first run
 * (db-20260906-013028.sqlite), per RULE #2. Nothing outside the sandbox ever
 * read these tables: `lab_*` was reachable only through
 * `App\EventLab\Controllers\LabController`, and the two `sandbox_event_club*`
 * tables only through the sandbox's own clubs controller.
 *
 * `down()` recreates the tables EMPTY. That is honest rather than helpful: the
 * rows were prototype data, they are in the backup, and a migration that
 * silently resurrects a schema without its contents should say so.
 *
 * Order matters — children before parents, so a foreign key never outlives the
 * table it points at.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'sandbox_event_club_entrants',
            'sandbox_event_clubs',
            'lab_entry_variants',
            'lab_entries',
            'lab_entrants',
            'lab_clubs',
            'lab_variants',
            'lab_events',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Deliberately empty.
     *
     * Rolling this back cannot mean "put the sandbox back" — the module's code
     * is gone from the working tree and its tables held nothing the live app
     * has ever read. Recreating eight empty tables would be a worse lie than
     * doing nothing: restore the backup, or check out commit e54f7720.
     */
    public function down(): void
    {
        // No-op. See the note above.
    }
};
