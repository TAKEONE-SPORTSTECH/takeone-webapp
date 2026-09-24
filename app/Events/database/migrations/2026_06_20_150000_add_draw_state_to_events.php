<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-division draw lifecycle for the auto-draw engine.
        Schema::table('event_categories', function (Blueprint $table) {
            // null = no auto-draw | provisional = pre-start (includes unpaid) | final = locked after start (paid only)
            $table->string('draw_state', 16)->nullable()->after('status');
            $table->unsignedSmallInteger('draw_count')->nullable()->after('draw_state'); // entrants the draw was built from
        });

        // Mark a bracket slot as provisional (an unpaid entrant — "imaginary" until paid/removed).
        Schema::table('event_matches', function (Blueprint $table) {
            $table->boolean('a_provisional')->default(false)->after('a_score');
            $table->boolean('b_provisional')->default(false)->after('b_score');
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropColumn(['draw_state', 'draw_count']);
        });
        Schema::table('event_matches', function (Blueprint $table) {
            $table->dropColumn(['a_provisional', 'b_provisional']);
        });
    }
};
