<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutes_per_match')->default(8)->after('end_time'); // bout + changeover gap
            $table->unsignedSmallInteger('break_minutes')->default(60)->after('minutes_per_match'); // daily break (lunch etc.)
            $table->json('day_courts')->nullable()->after('break_minutes'); // owner override of courts per day {"1":4,"2":2}
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn(['minutes_per_match', 'break_minutes', 'day_courts']);
        });
    }
};
