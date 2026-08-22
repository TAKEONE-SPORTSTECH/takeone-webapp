<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a bout was won, when the score is not the answer.
 *
 * A karate bout can end with the winner on fewer points — or on none at all:
 * hansoku (disqualification for a foul), shikkaku (disqualification for serious
 * misconduct), kiken (withdrawal), a medical retirement. The scoreboard could
 * already be told who won; it had nowhere to record WHY, which is exactly the
 * part somebody asks about afterwards, and the part that has to survive the
 * cache the mat state lives in.
 *
 * Two nullable columns, added beside the result rather than changing it: a code
 * from a known vocabulary, and the free-text line an official writes when the
 * code alone does not say enough. A bout won on points leaves both NULL, which
 * is what every existing row is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_matches', function (Blueprint $table) {
            $table->string('win_reason', 32)->nullable()->after('winner');
            $table->string('win_note', 200)->nullable()->after('win_reason');
        });
    }

    public function down(): void
    {
        Schema::table('event_matches', function (Blueprint $table) {
            $table->dropColumn(['win_reason', 'win_note']);
        });
    }
};
