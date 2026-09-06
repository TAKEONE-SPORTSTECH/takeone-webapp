<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is IN a video.
 *
 * A file has one owner — the thing that produced it, on `media_files` — but it
 * has many subjects. Two competitors are in a bout; so is the coach who appears
 * in the corner and the official who steps into frame.
 *
 * Today "my videos" is inferred by walking the draw:
 * ClubEventRegistration -> EventMatch.a_competitor_id / b_competitor_id. That
 * works for competitors and only for competitors, and it is derived from a draw
 * that can be regenerated — so the answer changes underneath a member who had
 * already been shown their own footage.
 *
 * Recording it makes three things possible that inference cannot:
 *   · coaches and officials who appear on camera but are in no draw,
 *   · a record that survives a re-cut draw,
 *   · one place to answer "remove me" for a member who asks to be erased.
 *
 * Deliberately NOT a folder. A member cannot be a path segment: a video has
 * several of them, and putting one in the path would mean copying gigabytes per
 * person, or moving files whenever a substitution is corrected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_file_subjects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // competitor | coach | official | uploader — why this person is in
            // the frame. A string rather than an enum: SQLite ignores enums, and
            // a new role should not need a migration.
            $table->string('role', 32)->default('competitor');

            // Where the claim came from, so a derived row can be recomputed and
            // a hand-made one is never quietly overwritten.
            $table->string('source', 32)->default('draw');   // draw | manual | detection

            $table->timestamps();

            // One row per person per role per file.
            $table->unique(['media_file_id', 'user_id', 'role']);

            // "Every video this member is in" — the query the library runs.
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_file_subjects');
    }
};
