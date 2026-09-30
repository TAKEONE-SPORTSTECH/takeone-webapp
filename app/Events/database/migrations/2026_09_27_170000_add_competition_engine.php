<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The generic competition engine (steps 4–6 of the generic-sports plan,
 * 2026-09-27). ADDITIVE ONLY (RULE #1): new nullable columns and one new
 * table; nothing existing is altered, and every existing row reads exactly as
 * before (the new columns are empty for it).
 *
 *   club_events.competition     the chosen format and settings of an event run
 *                               by App\Events\Championship — its MARKER: only an
 *                               event that carries one is run by that type, so
 *                               no existing event changes hands.
 *   event_matches.a/b_side_id   a head-to-head contest between two pairs/teams
 *   event_matches.detail        what a score cannot hold: sets and games, extra
 *                               time, penalties
 *   event_matches.group_no      which group a group-stage contest belongs to
 *   event_performances          field, measured and judged contests — a heat's
 *                               times, a jump's attempts, a routine's marks
 *
 * No foreign-key constraints on the columns added to existing tables: SQLite
 * cannot add one with ALTER TABLE. They are indexed, and the code scopes every
 * lookup by event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->text('competition')->nullable();
        });

        Schema::table('event_matches', function (Blueprint $table) {
            $table->unsignedBigInteger('a_side_id')->nullable()->index();
            $table->unsignedBigInteger('b_side_id')->nullable()->index();
            $table->text('detail')->nullable();
            $table->unsignedSmallInteger('group_no')->nullable();
        });

        Schema::create('event_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('event_categories')->nullOnDelete();
            $table->string('round', 20)->default('final');   // heats | semi | final | qualification
            $table->unsignedSmallInteger('heat')->default(1);
            $table->unsignedSmallInteger('lane')->nullable();
            $table->foreignId('registration_id')->nullable()->constrained('club_event_registrations')->cascadeOnDelete();
            $table->foreignId('side_id')->nullable()->constrained('event_sides')->cascadeOnDelete();
            $table->text('attempts')->nullable();          // JSON: each attempt / each judge's mark
            $table->decimal('value', 12, 3)->nullable();   // the result that ranks: seconds, metres, kg, marks
            $table->string('status', 8)->default('ok');    // ok | dns | dnf | dq
            $table->unsignedSmallInteger('rank')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'category_id', 'round', 'heat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_performances');

        Schema::table('event_matches', function (Blueprint $table) {
            $table->dropColumn(['a_side_id', 'b_side_id', 'detail', 'group_no']);
        });

        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('competition');
        });
    }
};
