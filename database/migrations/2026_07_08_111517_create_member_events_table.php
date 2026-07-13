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
        // Free-form, member-owned log of events a person took part in
        // (non-competitive participation history). Distinct from club-event
        // registrations (system-managed) and tournaments (tournament_events).
        Schema::create('member_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->date('event_date');
            $table->string('location', 150)->nullable();
            $table->string('role', 80)->nullable();     // e.g. Participant, Volunteer, Organizer
            $table->string('result', 150)->nullable();  // e.g. Finished, 3rd place, Completed 10km
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_events');
    }
};
