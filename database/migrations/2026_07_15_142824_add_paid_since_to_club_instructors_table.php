<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('club_instructors', function (Blueprint $table) {
            $table->timestamp('paid_since')->nullable()->after('wage_period');
        });

        // Backfill existing paid staff with their hire date, preserving today's
        // settlement behavior for them (only NEW volunteer→paid conversions from
        // here on stamp the real conversion moment instead of the hire date).
        DB::table('club_instructors')
            ->where('compensation_type', 'paid')
            ->update(['paid_since' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_instructors', function (Blueprint $table) {
            $table->dropColumn('paid_since');
        });
    }
};
