<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a division is decided: a knockout bracket, or everyone playing everyone.
 *
 * Only one format has ever existed here. `DrawEngine` builds a single-elimination
 * bracket and nothing else — the words "round robin" appear in this codebase
 * exactly once, in `Scheduler`, and there they describe how MATS are handed out,
 * not how a division is run.
 *
 * So this column is deliberately arriving BEFORE the engine that reads it. It
 * defaults to `knockout`, which is what every division in the database already
 * is, so nothing changes for anybody today. What it buys is a place for the
 * answer to live, chosen per division by the organiser who knows, instead of
 * being assumed by the engine.
 *
 * ⚠️ Until the round-robin engine exists, the UI offers `round_robin` as
 * VISIBLY unavailable rather than selectable. That is the whole point of doing
 * it this way round: `bronzeRule()` already accepts `'repechage'` and
 * `Advancement.php` quietly runs it as something else, so the configuration
 * promises a convention the engine cannot keep. One of those in a codebase is
 * enough. A format nobody can select cannot lie about what will happen on the
 * mat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            // Not an enum: SQLite ignores them, and a sport package may
            // legitimately add a format this table has never heard of (pools
            // feeding a cup, best-of-three). The vocabulary is enforced in
            // App\Models\EventCategory and at the write paths.
            $table->string('format', 24)->default('knockout')->after('weight_class');
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
