<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The belt an athlete presented at the weigh-in.
 *
 * Rank is announced on the arena screen ("Black Belt · 2nd Dan"), and the system
 * already knows it for most athletes: a certification names it, and a skill
 * record carries a proficiency level. But those are claims made in a profile at
 * some earlier date, and a competitor can turn up having graded since — or with
 * nothing recorded at all.
 *
 * The weigh-in is where that gets settled. It is already the moment an official
 * physically checks the athlete and signs off a number, so it is the natural
 * place to also record the belt they presented: same desk, same official, same
 * signature (weighed_in_by), one more field. This column is therefore the
 * AUTHORITATIVE rank for this event — App\Sports\Combat\BeltRank prefers it over
 * anything in the profile, because an official looked at it today.
 *
 * Two columns rather than one string, because they answer different questions:
 * colour is what the audience sees, grade is the degree within it (dan/kyu/gup).
 * A white belt has a colour and no grade; a 2nd dan has both. Free text on
 * purpose — belt ladders differ per sport and per federation, and the sport
 * package is what knows the vocabulary, not this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->string('belt_colour', 40)->nullable()->after('weight');
            $table->string('belt_grade', 40)->nullable()->after('belt_colour');
        });
    }

    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropColumn(['belt_colour', 'belt_grade']);
        });
    }
};
