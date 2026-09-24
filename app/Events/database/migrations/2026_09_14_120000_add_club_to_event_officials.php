<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which club an official came with — or nobody's.
 *
 * An officiating sheet names a referee, and the next question anyone in the
 * hall asks is "whose referee is that?". A federation sends its own, a visiting
 * club brings a coach who takes a corner job, and plenty of people turn up on
 * their own account. Until now the sheet could not say which, so a competitor
 * reading it had no way to see that the man refereeing their bout wears the
 * same badge as their opponent.
 *
 * Two nullable columns, exactly as an ENTRY records the same fact:
 *  - `representing_tenant_id` — a club with an account on this platform,
 *  - `event_club_id` — a club on THIS event's own list that has no account
 *    (a visiting team somebody wrote down).
 * Both null means independent, which is a real answer and the default.
 *
 * Additive: every existing appointment reads as independent, which is what the
 * sheet already showed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->unsignedBigInteger('representing_tenant_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('event_club_id')->nullable()->after('representing_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->dropColumn(['representing_tenant_id', 'event_club_id']);
        });
    }
};
