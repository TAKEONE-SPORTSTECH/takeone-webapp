<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A TakeNote folder shared with another person (Support\Notebooks): they work in
 * the owner's notebook, fenced to that folder and the folders inside it. Until
 * the person has TakeNote (or confirms the email it was shared to) `user_id` is
 * null and `email` is what binds it. Removing someone stamps `revoked_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devboard_folder_shares', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('folder_id')->constrained('devboard_folders')->cascadeOnDelete();
            $t->foreignId('notebook_id')->constrained('devboard_notebooks')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('email', 190);
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->unique(['folder_id', 'email']);
            $t->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devboard_folder_shares');
    }
};
