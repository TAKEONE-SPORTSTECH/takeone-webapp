<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a public entrant ticked, held until an organiser accepts them.
 *
 * Door C is the only entry that does not become a registration in the same
 * breath: a public request waits in `event_public_entries` and is priced when
 * somebody accepts it, which may be days later. Without somewhere to put the
 * selection, an auto-accepted entrant was charged for their extras and a
 * REVIEWED one silently was not — the same form, the same competitor, a
 * different bill depending on a setting they never saw.
 *
 * A JSON list of option UUIDs rather than prices or labels. Two reasons:
 *
 *  · The price must be read at the moment of acceptance, from the event's own
 *    rows, exactly as every other door reads it. Storing an amount here would
 *    create a second place money lives and a second thing to keep in step.
 *  · UUIDs are already what the browser sends and what `EventFee::quote()`
 *    validates, so an option deactivated between request and acceptance simply
 *    drops out — which is the correct answer, and one nobody has to code.
 *
 * Nullable, so every request already in the table stays exactly as it is and is
 * priced at the base fee the way it always would have been.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_public_entries', function (Blueprint $table) {
            $table->json('fee_options')->nullable()->after('belt_colour');
        });
    }

    public function down(): void
    {
        Schema::table('event_public_entries', function (Blueprint $table) {
            $table->dropColumn('fee_options');
        });
    }
};
