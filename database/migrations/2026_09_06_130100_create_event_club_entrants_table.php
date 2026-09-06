<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which entrants a club brought.
 *
 * The join between `event_clubs` and the entry list. It is what makes the
 * promotion rule answerable after the event — a temporary club that brought at
 * least one athlete who competed is a real club; one that brought nobody is a
 * name somebody typed while they were on the phone.
 *
 * Deliberately NOT `club_event_registrations.representing_tenant_id`, which
 * already exists and answers a different question: that column names a club the
 * platform KNOWS, claimed by the athlete themselves. This names a club standing
 * behind THIS event, which may not exist anywhere else yet, and is recorded by
 * the organiser at the desk. An athlete can have both, and they can disagree —
 * which is the honest state of a hall on the day.
 *
 * `unique(registration_id)`: one athlete represents one club at one competition.
 * Both sides cascade, so deleting an event or an entry takes its rows with it
 * and never leaves a club claiming somebody who is no longer entered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_club_entrants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_club_id')
                ->constrained('event_clubs')->cascadeOnDelete();

            $table->foreignId('registration_id')
                ->constrained('club_event_registrations')->cascadeOnDelete();

            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();

            $table->unique('registration_id');
            $table->index('event_club_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_club_entrants');
    }
};
