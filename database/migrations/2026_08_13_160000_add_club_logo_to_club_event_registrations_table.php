<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The crest the screens show beside a competitor's club, FOR THIS EVENT.
 *
 * On the registration for the same reason the competitor's picture is (see
 * `add_photo_to_club_event_registrations_table`), and here the reason is even
 * stronger: `tenants.logo` is the club's identity across the whole platform —
 * its public page, its admin panel, every member's card. An official appointed
 * to run one competition has no business rewriting that, and very often is not
 * an admin of the visiting club at all.
 *
 * So what the desk supplies is scoped to this competition and leaves with it.
 * A club that has never uploaded a crest gets one on the wall for the weekend;
 * their account is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            // A storage path on the public disk, written by StoresBase64Images
            // with a server-generated name. PNG in practice — a crest is a
            // transparent mark and must not be flattened onto a box.
            $table->string('club_logo')->nullable()->after('photo');
        });
    }

    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropColumn('club_logo');
        });
    }
};
