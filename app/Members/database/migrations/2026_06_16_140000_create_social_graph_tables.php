<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Social graph: follows (asymmetric), connection requests (symmetric once
 * accepted), and blocks. Powers wall visibility on the personal feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['follower_id', 'followee_id']);
            $table->index('followee_id');
        });

        Schema::create('user_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('addressee_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted'])->default('pending');
            $table->timestamps();
            $table->unique(['requester_id', 'addressee_id']);
            $table->index(['addressee_id', 'status']);
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('user_connections');
        Schema::dropIfExists('user_follows');
    }
};
