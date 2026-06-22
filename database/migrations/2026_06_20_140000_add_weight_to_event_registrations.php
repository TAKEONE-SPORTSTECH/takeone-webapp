<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Self-declared competition weight (kg), captured at registration. Used to
     * classify the member into the correct weight division (classifyTaekwondo).
     */
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
