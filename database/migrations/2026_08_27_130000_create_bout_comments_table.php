<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What people say to each other under a bout video.
 *
 * Distinct from `bout_coach_notes`, which are timed instruction pinned to the
 * footage and written only by the organiser or the athletes. This is the public
 * conversation: anyone who may watch the bout may join it.
 *
 * One level of replies, deliberately. A thread that can nest without limit turns
 * into a structure nobody can follow on a phone, and every platform that allows
 * it ends up capping the display anyway.
 *
 * `stamp_seconds` is the moment being talked about, when the writer named one —
 * it renders as a chip that seeks the player. Nullable because most comments are
 * about the fight, not about a second of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bout_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('event_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // A reply belongs to a top-level comment. Deleting the parent takes
            // its replies with it — an orphaned reply answers nothing.
            $table->foreignId('parent_id')->nullable()->constrained('bout_comments')->cascadeOnDelete();

            $table->text('body');
            $table->decimal('stamp_seconds', 10, 3)->nullable();

            $table->timestamps();

            $table->index(['match_id', 'created_at']);
            $table->index('parent_id');
        });

        // Likes as rows, not a counter: a counter cannot tell whether THIS
        // reader already liked it, so the heart could never show its own state.
        Schema::create('bout_comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('bout_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bout_comment_likes');
        Schema::dropIfExists('bout_comments');
    }
};
