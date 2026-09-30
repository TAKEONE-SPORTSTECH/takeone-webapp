<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which sport package (and style) a club ACTIVITY is an instance of.
 *
 * Additive, and owned by the Ranks module rather than bolted onto
 * club_activities: an activity with no row behaves exactly as every activity
 * does today. `matched_by` is 'auto' for the platform's own match (redone if the
 * activity is renamed) and 'club' once a person has chosen — never overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_packages')) {
            return;
        }

        Schema::create('activity_packages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('activity_id')->unique()->constrained('club_activities')->cascadeOnDelete();
            $t->string('sport', 32);
            $t->string('style', 64);
            $t->string('matched_by', 8)->default('auto');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_packages');
    }
};
