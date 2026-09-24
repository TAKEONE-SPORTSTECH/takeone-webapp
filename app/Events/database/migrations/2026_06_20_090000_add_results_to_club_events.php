<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Event results / winners — a podium the event manager fills in after the
     * event: [{place, name, prize, user_id?}]. Displayed on the event detail.
     */
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->json('results')->nullable()->after('prize');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('results');
        });
    }
};
