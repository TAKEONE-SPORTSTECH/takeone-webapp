<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Does a public entry need the organiser to say yes?
 *
 * Phase C of Documentation/EVENTS-PUBLIC-ENTRY.md, decision 2. A public link
 * means anybody can enter, so the default is that somebody looks: entries land
 * pending and the organiser accepts them. An open competition that wants no
 * gatekeeping turns this on, per event.
 *
 * Default FALSE, so publishing an event never quietly opens an unreviewed door.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->boolean('public_entry_auto_accept')->default(false)->after('entry_mode');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('public_entry_auto_accept');
        });
    }
};
