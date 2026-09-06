<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which athletes a TEMPORARY club brought — `app/EventLab/`.
 *
 * A club that is already on the platform needs none of this: its athletes name
 * it through `club_event_registrations.representing_tenant_id`, which is a real
 * foreign key to a real club. A temporary club has no such row to point at —
 * that is what makes it temporary — so the link lives here instead.
 *
 * It is not bookkeeping. It is the whole test for what happens after the
 * competition: a temporary club that actually brought athletes to the mat is a
 * real club that simply has not registered yet, and it is promoted to one. A
 * temporary club that brought nobody was a name typed into a form, and it
 * expires with the event. Without this table there is no way to tell those two
 * apart, and every name anyone ever typed would become a club.
 *
 * Sandbox-only. It cascades from both sides, so removing a twin, or a club, or
 * an entry takes the link with it and never leaves a half-answer behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sandbox_event_club_entrants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sandbox_event_club_id')
                ->constrained('sandbox_event_clubs')->cascadeOnDelete();

            $table->foreignId('registration_id')
                ->constrained('club_event_registrations')->cascadeOnDelete();

            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();

            // One athlete represents one club at one competition.
            $table->unique('registration_id');
            $table->index('sandbox_event_club_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_event_club_entrants');
    }
};
