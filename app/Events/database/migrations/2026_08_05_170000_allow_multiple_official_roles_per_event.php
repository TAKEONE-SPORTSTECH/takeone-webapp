<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One person, several jobs.
 *
 * The first version allowed a single row per (event, user), which quietly meant
 * one official could hold exactly one role. Small clubs run a championship with
 * three people: the same person weighs athletes in and checks the transfers.
 * The uniqueness that matters is (event, user, ROLE) — appoint someone twice to
 * the same job and it is still one appointment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'user_id']);
            $table->unique(['event_id', 'user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'user_id', 'role']);
            $table->unique(['event_id', 'user_id']);
        });
    }
};
