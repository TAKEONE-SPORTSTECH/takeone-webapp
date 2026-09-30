<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The club invitation reads in the languages its ORGANISER offers (2026-09-28),
 * the same way a club event's poster does (App\Translation\Contracts\
 * LimitsOfferedLocales).
 *
 *   offered_locales — the languages a club may read the invitation in; NULL =
 *                     the two it has always spoken, English and Arabic.
 *   source_locale   — the language the organiser WROTE the event in, so the
 *                     machine translation starts from the right one.
 *
 * Purely additive — two nullable columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('federation_events', function (Blueprint $t) {
            $t->json('offered_locales')->nullable();
            $t->string('source_locale', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('federation_events', function (Blueprint $t) {
            $t->dropColumn(['offered_locales', 'source_locale']);
        });
    }
};
