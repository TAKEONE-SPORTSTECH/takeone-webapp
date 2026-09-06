<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A competitor's picture FOR THIS EVENT.
 *
 * Deliberately on the registration and not on the user. A photo taken at the
 * scoring table is a different thing from the one a member chose for their
 * account: it is shot at the desk to fill an introduction screen, it belongs to
 * one competition, and the official adding it has no business overwriting
 * somebody's profile. An organiser is authorised to run THIS event, so what
 * they add is scoped to THIS event and leaves with it.
 *
 * It also sidesteps `profile_picture_is_public` cleanly. A member's own picture
 * is only projected on a wall when they have published it; a picture supplied
 * by the organiser for the event's own screens was supplied precisely to be
 * shown there, so it needs no separate permission and grants none elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            // A storage path on the public disk, written by StoresBase64Images
            // with a server-generated name — never anything the client sent.
            $table->string('photo')->nullable()->after('belt_grade');
        });
    }

    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
