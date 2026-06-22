<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One emoji reaction per trainee per dated occurrence of a club class. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_activity_id')->constrained('club_package_activities')->cascadeOnDelete();
            $table->string('slot_day', 12);
            $table->string('slot_start', 12)->nullable();
            $table->date('date');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();

            $table->unique(['package_activity_id', 'slot_day', 'slot_start', 'date', 'user_id'], 'class_react_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('class_reactions'); }
};
