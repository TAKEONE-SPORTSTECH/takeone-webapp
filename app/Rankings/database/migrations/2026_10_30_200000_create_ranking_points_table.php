<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rankings ledger: what one athlete earned at one event in one category,
 * and every component of it, so a total can always be explained.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            // The entry it was earned with — for the face and the club, read live.
            $table->foreignId('registration_id')->nullable()->constrained('club_event_registrations')->nullOnDelete();

            $table->string('scheme', 32);                 // wkf
            $table->string('category_key', 190);          // shotokan:under-10-years-kumite-male-under-25-kg
            $table->string('category_name');
            $table->unsignedInteger('category_order')->default(0);
            $table->string('discipline', 16);             // kumite | kata

            $table->unsignedSmallInteger('participation')->default(0);
            $table->unsignedSmallInteger('wins')->default(0);   // includes byes that count
            $table->unsignedTinyInteger('place')->nullable();   // 1..4 from the podium
            $table->unsignedSmallInteger('place_points')->default(0);
            $table->unsignedSmallInteger('medal_round')->default(0);
            $table->unsignedSmallInteger('factor');
            $table->unsignedInteger('points');            // (sum of the above) × factor

            $table->integer('score_for')->default(0);     // tie-breaks
            $table->integer('score_against')->default(0);

            $table->string('club_name')->nullable();
            $table->string('country', 8)->nullable();
            $table->date('earned_on');
            $table->timestamps();

            $table->unique(['event_id', 'user_id', 'category_key']);
            $table->index(['federation_id', 'category_key', 'earned_on']);
            $table->index(['user_id', 'earned_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_points');
    }
};
