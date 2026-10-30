<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voice notes and short videos join pictures as things a note can hold.
 * Additive: new nullable/defaulted columns on devboard_assets; every existing
 * row reads as what it always was — a ready picture.
 *
 *   kind         image | audio | video
 *   status       ready | processing | failed   (a video is prepared on the media queue)
 *   mime         what the stored file is served as
 *   duration_ms  length of a recording
 *   meta         small JSON: a voice note's waveform peaks
 *   source_path  the upload as received, kept only until it has been prepared
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devboard_assets', function (Blueprint $t) {
            if (! Schema::hasColumn('devboard_assets', 'kind')) {
                $t->string('kind', 8)->default('image');
            }
            if (! Schema::hasColumn('devboard_assets', 'status')) {
                $t->string('status', 12)->default('ready');
            }
            if (! Schema::hasColumn('devboard_assets', 'mime')) {
                $t->string('mime', 40)->nullable();
            }
            if (! Schema::hasColumn('devboard_assets', 'duration_ms')) {
                $t->unsignedInteger('duration_ms')->nullable();
            }
            if (! Schema::hasColumn('devboard_assets', 'meta')) {
                $t->json('meta')->nullable();
            }
            if (! Schema::hasColumn('devboard_assets', 'source_path')) {
                $t->string('source_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('devboard_assets', function (Blueprint $t) {
            foreach (['kind', 'status', 'mime', 'duration_ms', 'meta', 'source_path'] as $c) {
                if (Schema::hasColumn('devboard_assets', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
