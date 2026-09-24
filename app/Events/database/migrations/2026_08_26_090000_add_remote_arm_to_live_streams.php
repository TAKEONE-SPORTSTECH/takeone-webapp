<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who decides when a mat is on air.
 *
 * Until now the only switch was on the phone itself: somebody had to be
 * standing at the tripod to start a broadcast, and standing there again to end
 * it. That is the wrong place for the decision — the person who knows whether
 * this mat should be public is at the scoring table, not holding the camera.
 *
 * So the stream carries an INTENT alongside its state. `status` stays exactly
 * what it was: what the media server observes. `desired_state` is what the
 * console asked for, and the phone reconciles itself to it on its next beat.
 * Two separate facts, never conflated — a phone that has not obeyed yet is
 * something an organiser needs to be able to SEE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            // 'idle' | 'live'. Idle by default, so nothing that exists today
            // starts behaving differently: a stream nobody has armed is a
            // stream the phone is not told to publish.
            $table->string('desired_state', 12)->default('idle')->after('status');
            $table->timestamp('desired_at')->nullable()->after('desired_state');
            $table->unsignedBigInteger('desired_by')->nullable()->after('desired_at');

            // The phone's own beat, so the console can tell "no camera on this
            // mat" from "a camera is standing by, waiting to be told".
            $table->timestamp('camera_seen_at')->nullable()->after('desired_by');
        });

        // A mat that is on air RIGHT NOW was put there by somebody, and the
        // default must not read as "come off air" the moment its camera starts
        // asking for orders. Anything already publishing keeps publishing.
        DB::table('live_streams')->where('status', 'live')->update(['desired_state' => 'live']);
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn(['desired_state', 'desired_at', 'desired_by', 'camera_seen_at']);
        });
    }
};
