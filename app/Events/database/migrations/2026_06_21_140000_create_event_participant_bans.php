<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moderation bans set by an event owner.
     *  - scope = 'event'  → barred from this one event (event_id set)
     *  - scope = 'club'   → blacklisted across all the club/chain's events (event_id null)
     * A ban bars BOTH competing and spectating.
     */
    public function up(): void
    {
        Schema::create('event_participant_bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope', 12)->default('event'); // event | club
            $table->string('reason', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'tenant_id', 'scope']);
            $table->index(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participant_bans');
    }
};
