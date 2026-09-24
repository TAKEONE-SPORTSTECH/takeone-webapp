<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A camera's feed is a thing that can be switched off.
 *
 * Until now it could not. A phone claimed onto a mat went live the moment it was
 * paired and stayed live until somebody unpaired it (TV/lab-app: "THE FEED IS
 * NOT A BUTTON ANY MORE"). That was right about WHERE the decision belongs — not
 * with whoever is holding the phone — and wrong that it therefore belongs to
 * nobody. It belongs to the console.
 *
 * `broadcasting` defaults to TRUE precisely so nothing changes for a camera
 * already in a hall: pairing still means filming and still means live. What is
 * new is only that an organiser can now say otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_cameras', function (Blueprint $table) {
            $table->boolean('broadcasting')->default(true)->after('recording');

            // Which stream this camera's feed IS. Written when the camera asks
            // for a publish credential, so the console can show what is on air
            // and stop it without matching titles back to devices.
            $table->unsignedBigInteger('live_stream_id')->nullable()->after('broadcasting');
        });
    }

    public function down(): void
    {
        Schema::table('event_cameras', function (Blueprint $table) {
            $table->dropColumn(['broadcasting', 'live_stream_id']);
        });
    }
};
