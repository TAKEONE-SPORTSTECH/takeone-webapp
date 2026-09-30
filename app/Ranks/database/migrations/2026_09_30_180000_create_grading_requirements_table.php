<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a member needs to be ready for a belt — set for ONE ranked activity.
 *
 * The club's own requirements for its activity (its custom edition of the
 * sport's grading rules); the official rule book is never changed by them.
 * Every column is optional: a requirement left empty is simply not asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('grading_requirements')) {
            return;
        }

        Schema::create('grading_requirements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
            $t->string('belt', 40);                          // the belt being worked TOWARDS
            $t->unsignedSmallInteger('min_classes')->nullable(); // since the last promotion
            $t->unsignedSmallInteger('min_months')->nullable();  // at the current rank
            $t->unsignedTinyInteger('min_age')->nullable();
            $t->timestamps();
            $t->unique(['activity_id', 'belt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_requirements');
    }
};
