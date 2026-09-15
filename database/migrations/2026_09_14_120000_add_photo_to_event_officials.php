<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The face on the officiating sheet.
 *
 * An APPOINTMENT's photo, exactly as `club_event_registrations.photo` is an
 * ENTRY's: taken at the desk, for this competition, by the organiser who
 * appointed them. It is deliberately not the person's profile picture —
 *
 *   · a referee the federation sent has no account and therefore no profile;
 *   · a member who DOES have one chose what the world sees of their face
 *     (`users.profile_picture_is_public`), and a desk photographing them for a
 *     team sheet is not consent to change that.
 *
 * So the photo belongs to the row that says "this person is refereeing this
 * event", and it disappears with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
