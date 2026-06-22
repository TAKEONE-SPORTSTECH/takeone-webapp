<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A one-off substitute trainer covering a single dated occurrence of a club
 * class (a weekly schedule slot on a package activity). Lets a coach who can't
 * make it assign someone else — from inside or outside the club — just for that
 * date, without changing the permanent instructor assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_activity_id')->constrained('club_package_activities')->cascadeOnDelete();
            $table->string('slot_day', 12);        // the recurring slot this covers
            $table->string('slot_start', 12)->nullable();
            $table->date('date');                  // the specific occurrence being covered

            $table->foreignId('original_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('substitute_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 300)->nullable();

            $table->timestamps();

            $table->unique(['package_activity_id', 'slot_day', 'slot_start', 'date'], 'class_sub_unique');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_substitutions');
    }
};
