<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What BELT a division is for.
 *
 * The one axis of a jiu-jitsu division that had no column. Organisers were
 * writing it into the division's NAME — "Group D (White Belt) (80+) [No-Gi]",
 * "Group E (Blue, Purple)" — which means nothing can read it: the entrant
 * picker cannot sort by it, the generated name cannot say it, and two people
 * spelling it differently make two different divisions.
 *
 * A LIST, not one value, because a division is regularly opened to several
 * grades at once ("Blue, Purple") — that is how a small entry list is made into
 * a runnable bracket.
 *
 * Nullable, and null means ANY belt — the same reading as every other column in
 * `App\Events\Support\DivisionRange`, which is a guide and never a gate.
 * Additive: nothing existing is touched, and every division that has one today
 * keeps its typed name exactly as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->json('belts')->nullable()->after('max_weight');
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropColumn('belts');
        });
    }
};
