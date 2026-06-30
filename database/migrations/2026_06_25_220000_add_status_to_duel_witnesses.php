<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duel_witnesses', function (Blueprint $table) {
            // Attendance: invited (default) | accepted | declined
            $table->string('status', 12)->default('invited')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('duel_witnesses', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
