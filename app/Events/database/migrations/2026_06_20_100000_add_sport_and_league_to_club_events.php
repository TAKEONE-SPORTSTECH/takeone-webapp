<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make events sport-aware and support the new "League" type:
     *  - `sport`  : drives which structures the form shows (combat → weight
     *               categories, team → standings/fixtures, racquet → draws…).
     *  - `league` : JSON for round-robin leagues — { teams: [...], fixtures: [...] }.
     * Also renames the old `competition` event type to `league`.
     */
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->string('sport')->nullable()->after('event_type');
            $table->json('league')->nullable()->after('results');
        });

        DB::table('club_events')->where('event_type', 'competition')->update(['event_type' => 'league']);
    }

    public function down(): void
    {
        DB::table('club_events')->where('event_type', 'league')->update(['event_type' => 'competition']);

        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn(['sport', 'league']);
        });
    }
};
