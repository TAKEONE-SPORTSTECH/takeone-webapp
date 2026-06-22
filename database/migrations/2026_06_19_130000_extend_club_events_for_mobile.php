<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend club_events with the fields the mobile Events experience needs:
     * an explicit event type, participation + spectator fees, prize, lifecycle
     * phases, requirements, agenda and an icon. Existing columns (level, tags,
     * color, location, max_capacity, spots_taken, description) are reused.
     */
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->string('event_type')->default('class')->after('level');      // class|race|belt_test|tournament|championship|competition
            $table->string('icon')->nullable()->after('event_type');
            $table->string('participant_fee')->nullable()->after('icon');         // 'BHD 10' | null = free | 'Qualified finalists'
            $table->boolean('spectator_enabled')->default(false)->after('participant_fee');
            $table->string('spectator_fee')->nullable()->after('spectator_enabled');
            $table->string('prize')->nullable()->after('spectator_fee');
            $table->json('requirements')->nullable()->after('prize');
            $table->json('phases')->nullable()->after('requirements');            // lifecycle timeline
            $table->json('agenda')->nullable()->after('phases');                  // schedule rows
            $table->foreignId('created_by')->nullable()->after('agenda')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'event_type', 'icon', 'participant_fee', 'spectator_enabled',
                'spectator_fee', 'prize', 'requirements', 'phases', 'agenda',
            ]);
        });
    }
};
