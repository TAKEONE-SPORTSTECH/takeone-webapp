<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('joined'); // joined | waitlisted
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_event_registrations');
    }
};
