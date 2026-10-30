<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A note put on the board stays a note too (owner, 27 Oct 2026: "not all notes
 * are for executions … some are taken to action plan, but have to remain as a
 * note too"). One row, seen in both places: in_notebook says the notebook
 * lists it whatever its kind. Additive: every note today is in the notebook,
 * and so is every card that was converted from one; a card made on the board
 * is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('devboard_cards', 'in_notebook')) {
            Schema::table('devboard_cards', function (Blueprint $t) {
                $t->boolean('in_notebook')->default(false)->after('kind');
            });
        }

        DB::table('devboard_cards')->where('kind', 'note')->update(['in_notebook' => true]);
        $converted = DB::table('devboard_activity')->where('action', 'converted')->pluck('card_id')->unique()->all();
        if ($converted) {
            DB::table('devboard_cards')->whereIn('id', $converted)->update(['in_notebook' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devboard_cards', 'in_notebook')) {
            Schema::table('devboard_cards', function (Blueprint $t) {
                $t->dropColumn('in_notebook');
            });
        }
    }
};
