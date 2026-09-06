<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A clip's journey to TAKEONE Play, recorded on the clip itself.
 *
 * Additive columns, all nullable: a clip that is never uploaded is exactly the
 * row it was before this migration.
 *
 * The clip is the right place for this rather than `event_recordings`, because
 * the two answer different questions. `event_recordings` says "this bout has a
 * video on Play" — one per bout, the join the timeline sync uses. This says
 * "THIS phone's file, from THIS angle, got there" — one per camera per bout,
 * which is what an operator looking at a phone needs to know, and what stops a
 * second upload of a file that has already gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_camera_clips', function (Blueprint $table) {
            // queued → uploading → processing → ready, or failed. Null means the
            // clip has never been sent, which is the ordinary state.
            $table->string('play_status')->nullable();

            // Play's own key for the video (the one in its URL), once created.
            $table->string('play_video_key')->nullable();
            $table->unsignedBigInteger('play_video_id')->nullable();

            // How far a resumable upload has got, so a phone that lost the hall's
            // wifi mid-bout resumes rather than starting a gigabyte again.
            $table->unsignedBigInteger('uploaded_bytes')->nullable();
            $table->timestamp('upload_started_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();

            // Why it failed, in the operator's words rather than a stack trace.
            $table->string('upload_error')->nullable();

            $table->index('play_status');
        });
    }

    public function down(): void
    {
        Schema::table('event_camera_clips', function (Blueprint $table) {
            $table->dropIndex(['play_status']);
            $table->dropColumn([
                'play_status', 'play_video_key', 'play_video_id',
                'uploaded_bytes', 'upload_started_at', 'uploaded_at', 'upload_error',
            ]);
        });
    }
};
