<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A job this competition needs that the platform has no word for.
 *
 * Every sport supplies its own mat roles — referee, tatami manager, judge — and
 * the platform supplies the four that grant ACCESS (jury, weigh-in, payments,
 * organiser). Between them they miss the ones a real hall still needs: a medic,
 * a photographer, an announcer, a mat sweeper.
 *
 * ⚠️ Why a LABEL and not just a free-text `role`.
 *
 * `role` is not decoration. `App\Events\Support\EventAccess` reads it, and
 * `isMatRole()` decides from it whether an appointee must be a member of the
 * host club. Letting an organiser type into that column means typing `jury`
 * grants the power to arrange a draw by hand, and typing `payments` grants the
 * power to approve money — an access grant by spelling.
 *
 * So the vocabulary stays closed. A custom appointment is stored as
 * `role = 'other'` with the organiser's wording in `role_label`, and `other` is
 * treated exactly as a mat role is: it grants what appointing ANY official
 * already grants on this platform (seeing the draw, entering athletes) and
 * nothing that the four access roles grant.
 *
 * Nullable, and every existing row keeps its role untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->string('role_label', 60)->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->dropColumn('role_label');
        });
    }
};
