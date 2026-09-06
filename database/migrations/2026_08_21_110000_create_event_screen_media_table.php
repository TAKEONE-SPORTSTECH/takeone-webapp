<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sound a competition makes: what a hall screen plays, and when.
 *
 * An introduction wants music under it, a winner wants a sting over the
 * confetti, and a point wants a noise the far side of the hall can hear. None of
 * that can be a file shipped with the app — every federation, club and
 * broadcaster has its own, and the ones we could ship we would not be licensed
 * to.
 *
 * One row per SLOT per event, so uploading again replaces rather than stacks
 * (see the unique index): a hall does not want two celebration tracks, it wants
 * the right one. Deliberately not on `club_events`: this is six nullable file
 * columns' worth of data that most events never set, and it is read by exactly
 * two surfaces.
 *
 * Files live on a PRIVATE disk and are served through a token-authorised route,
 * for the same reason the boards' fonts are: they belong to an event, not to the
 * open internet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_screen_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            // vs_music | winner_music | point_1 | point_2 | point_3 | foul
            $table->string('slot', 24);

            $table->string('disk', 20)->default('local');
            $table->string('path');
            // Untrusted metadata, kept only so the organiser recognises what they
            // uploaded. Never used to build a path — see the upload service.
            $table->string('original_name', 120)->nullable();
            $table->string('mime', 60)->nullable();
            $table->unsignedInteger('bytes')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_screen_media');
    }
};
