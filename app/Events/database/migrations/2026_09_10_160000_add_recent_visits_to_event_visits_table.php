<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last few visits a person actually made, so "when did they come" has an
 * answer beyond a first and a last date.
 *
 * A CAPPED JSON list on the visitor's own row rather than a second table, and
 * that is the point: a per-hit table on a public page grows without limit and
 * becomes a browsing history of people who only opened a poster. This holds the
 * most recent handful and drops the rest as it goes, so the shape of the data
 * is bounded by construction instead of by a cleanup job somebody has to
 * remember to write.
 *
 * Each entry is {at, device, locale, from} — the same four fields already on
 * the row, nothing new about anybody.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_visits', function (Blueprint $table) {
            $table->json('recent_visits')->nullable()->after('visits');
        });
    }

    public function down(): void
    {
        Schema::table('event_visits', function (Blueprint $table) {
            $table->dropColumn('recent_visits');
        });
    }
};
