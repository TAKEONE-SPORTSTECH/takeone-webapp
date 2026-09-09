<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A heading in the list of divisions — a title, and nothing else.
 *
 * An event with ten divisions reads as ten equal rows, and the reader has to
 * infer from the names that five of them are Gi and five No-Gi. A heading is
 * the organiser writing that down: a row that says "GI" and then the divisions
 * that follow it, until the next heading.
 *
 * It is deliberately a FLAG on a category rather than a new table. A heading
 * lives in the same ordered list, is created and renamed and deleted through
 * the same editor, and is dragged into position among the divisions it labels —
 * a separate table would need its own ordering, its own editor and its own
 * merge into the list, to describe the same one thing.
 *
 * What a heading is NOT: it holds no entrants, it is never a draw, it takes no
 * weight or age range, it is not a division anybody is entered in. Every place
 * that would treat it as one refuses it explicitly rather than relying on it
 * simply having no rows.
 *
 * Additive and false by default, so every existing category is untouched and
 * every existing query keeps its meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->boolean('is_heading')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropColumn('is_heading');
        });
    }
};
