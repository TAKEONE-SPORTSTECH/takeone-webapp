<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-division day scheduling: which event day each phase is played on.
        // e.g. {"preliminary":1,"quarterfinals":1,"finals":2}
        Schema::table('event_categories', function (Blueprint $table) {
            $table->json('schedule')->nullable()->after('draw_count');
        });

        // Phase grouping + continuous event-wide running order for the day.
        Schema::table('event_matches', function (Blueprint $table) {
            $table->string('phase', 20)->nullable()->after('round');     // preliminary | quarterfinals | finals
            $table->unsignedInteger('match_no')->nullable()->after('phase');
        });

        // Official jury weigh-in.
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->timestamp('weighed_in_at')->nullable()->after('weight');
            $table->unsignedBigInteger('weighed_in_by')->nullable()->after('weighed_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', fn (Blueprint $t) => $t->dropColumn('schedule'));
        Schema::table('event_matches', fn (Blueprint $t) => $t->dropColumn(['phase', 'match_no']));
        Schema::table('club_event_registrations', fn (Blueprint $t) => $t->dropColumn(['weighed_in_at', 'weighed_in_by']));
    }
};
