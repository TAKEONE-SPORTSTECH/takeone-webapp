<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('club_instructors', function (Blueprint $table) {
            $table->string('staff_type')->default('instructor')->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_instructors', function (Blueprint $table) {
            $table->dropColumn('staff_type');
        });
    }
};
