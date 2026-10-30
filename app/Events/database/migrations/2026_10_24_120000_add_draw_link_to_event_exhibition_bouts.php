<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An exhibition can be LINKED to the draw bout it really was (owner,
 * 2026-10-24): the mat could not reach the draw on the day, so the bout was
 * fought as an exhibition, and afterwards the organiser says which bout of the
 * bracket it was. Its result is then filed into that bout through the
 * package's own recordOutcome(), and the exhibition row keeps only the pointer
 * — status `linked` — so the hall's record of what was fought survives.
 *
 * Additive: three nullable columns, nothing altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event_exhibition_bouts', 'linked_match_id')) {
            return;
        }

        // Plain columns, no foreign keys: on SQLite adding a constraint to an
        // existing table means rebuilding it, and a live table is not rebuilt
        // for a pointer. A bout deleted later (a re-cut draw) leaves the
        // pointer dangling, which ExhibitionLink reads as "linked, bout gone".
        Schema::table('event_exhibition_bouts', function (Blueprint $table) {
            $table->unsignedBigInteger('linked_match_id')->nullable()->index();
            $table->unsignedBigInteger('linked_by')->nullable();
            $table->timestamp('linked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_exhibition_bouts', function (Blueprint $table) {
            $table->dropIndex(['linked_match_id']);
            $table->dropColumn(['linked_match_id', 'linked_by', 'linked_at']);
        });
    }
};
