<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enrollment window: when joining opens and the last day to enrol.
     * Chronology: enrollment_starts ≤ enrollment_ends ≤ weigh_in ≤ date ≤ end_date.
     */
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->date('enrollment_starts_at')->nullable()->after('weigh_in_at');
            $table->date('enrollment_ends_at')->nullable()->after('enrollment_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn(['enrollment_starts_at', 'enrollment_ends_at']);
        });
    }
};
