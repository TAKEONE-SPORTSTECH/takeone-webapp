<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the personal ("/me") feed fully DB-backed:
 *  • a member post can now be a poll  → user_posts.type + user_posts.poll,
 *    with one vote-per-user tracked in user_post_poll_votes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_posts', function (Blueprint $table) {
            // 'text' (default) | 'poll'. Photos remain a 'text' post with images.
            $table->string('type', 16)->default('text')->after('user_id');
            // Poll payload: { "question": "...", "options": ["A","B",...] }
            $table->json('poll')->nullable()->after('images');
        });

        Schema::create('user_post_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_post_id')->constrained('user_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('option');   // index into poll.options
            $table->timestamps();
            $table->unique(['user_post_id', 'user_id']);   // one vote per member
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_post_poll_votes');
        Schema::table('user_posts', function (Blueprint $table) {
            $table->dropColumn(['type', 'poll']);
        });
    }
};
