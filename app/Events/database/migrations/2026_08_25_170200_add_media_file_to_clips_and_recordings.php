<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "This bout's video is OURS now."
 *
 * Both tables already have somewhere to record a video on TAKEONE Play, and
 * those columns are left exactly as they are — a clip uploaded there still reads
 * correctly, and the Play path still works if it is ever wanted again. This adds
 * a second, parallel pointer for media this platform holds itself.
 *
 * Additive and nullable on purpose: a row with neither pointer has no video, a
 * row with the Play pointer has one there, and a row with this one has one here.
 * Nothing has to be migrated for the new path to start working, and nothing
 * breaks if it is switched back off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_camera_clips', function (Blueprint $table) {
            $table->foreignId('media_file_id')->nullable()->after('local_ref')
                ->constrained('media_files')->nullOnDelete();
        });

        Schema::table('event_recordings', function (Blueprint $table) {
            $table->foreignId('media_file_id')->nullable()->after('play_url')
                ->constrained('media_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_camera_clips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_file_id');
        });

        Schema::table('event_recordings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_file_id');
        });
    }
};
