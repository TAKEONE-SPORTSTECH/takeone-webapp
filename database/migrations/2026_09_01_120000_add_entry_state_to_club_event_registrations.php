<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An entry can exist before it is complete.
 *
 * Phase A of Documentation/EVENTS-PUBLIC-ENTRY.md: a coach commits an entry by
 * NAME, and the athlete supplies the details a coach could only have guessed at.
 * Everything already on file keeps today's meaning — the default is `complete`,
 * so every existing row is exactly what it was before this ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            // complete | incomplete | pending_review | declined
            $table->string('entry_state', 16)->default('complete')->after('entry_channel');
            $table->index(['event_id', 'entry_state'], 'cer_event_entry_state_idx');
        });
    }

    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropIndex('cer_event_entry_state_idx');
            $table->dropColumn('entry_state');
        });
    }
};
