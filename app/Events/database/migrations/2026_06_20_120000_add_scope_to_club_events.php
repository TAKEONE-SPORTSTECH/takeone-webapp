<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Event reach/eligibility. Decides who (beyond the host club's own members)
     * may see and self-register for an event:
     *   internal    — host club members only
     *   inter_club  — open to members of any club on the platform
     *   nationwide  — members of clubs in the same country as the host club
     *   regional    — members of clubs in the same region (currently ≈ nationwide)
     *   worldwide   — any platform member
     */
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->string('scope', 20)->default('internal')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
