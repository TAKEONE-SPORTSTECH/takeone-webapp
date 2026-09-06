<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an event has a page anybody may open.
 *
 * Phase B of Documentation/EVENTS-PUBLIC-ENTRY.md. `members` is what every
 * event does today — visible to whoever the event's scope reaches, and nobody
 * else — so the default changes nothing. An organiser opts a SPECIFIC event
 * into `public`, which is the feature flag RULE #1 asks for: off unless somebody
 * deliberately turned it on, one event at a time.
 *
 * `club_only` is Phase D and is accepted here so the vocabulary does not have to
 * change again later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            // club_only | members | public
            $table->string('entry_mode', 16)->default('members')->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('entry_mode');
        });
    }
};
