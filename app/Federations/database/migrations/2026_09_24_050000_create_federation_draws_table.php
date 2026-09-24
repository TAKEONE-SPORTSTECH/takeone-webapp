<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Federations, step 5: the draw and its results, per division of an event.
 *
 * The dashboard builds a division's bracket from its entrants (its own fair
 * draw). The moment the first result is recorded the bracket is LOCKED: its
 * first-round order (`slots`, Takeone IDs, null = bye) is kept here, so a later
 * registration or reload can never reshuffle bouts that have been fought.
 * Clearing every result of a division unlocks it again (the row goes).
 *
 * `division` is the dashboard's own key for it: gender|age group|division.
 * Purely additive — one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_event_id')->constrained('federation_events')->cascadeOnDelete();
            $table->string('division', 191);
            $table->json('slots');                 // first round, in order: takeone_id | null
            $table->json('results');               // "round-match" => {winner: takeone_id, by}
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['federation_event_id', 'division']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_draws');
    }
};
