<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->timestamp('weigh_in_at')->nullable()->after('end_time'); // official weigh-in moment (combat)
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('weigh_in_at');
        });
    }
};
