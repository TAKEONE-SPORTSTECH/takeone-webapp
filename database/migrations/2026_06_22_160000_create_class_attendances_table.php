<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance marks for a single dated occurrence of a club class slot. A row's
 * presence = that person attended that session. Taken by whoever runs the class
 * (coach / manager / substitute).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_activity_id')->constrained('club_package_activities')->cascadeOnDelete();
            $table->string('slot_day', 12);
            $table->string('slot_start', 12)->nullable();
            $table->date('date');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();      // the attendee
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['package_activity_id', 'slot_day', 'slot_start', 'date', 'user_id'], 'class_att_unique');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_attendances');
    }
};
