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
        Schema::create('members_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('users')->onDelete('cascade');
            $table->string('session_type'); // e.g. Personal Training, Group Fitness, Yoga, Pilates
            $table->string('trainer_name');
            $table->datetime('session_datetime');
            $table->enum('status', ['completed', 'no_show']);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members_attendance');
    }
};
