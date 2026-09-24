<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The language the organiser actually WROTE this event in.
 *
 * Without it the translator has to assume, and the assumption would be English
 * — which is wrong for the case this whole feature exists to serve: a club in
 * Manama announcing a championship in Arabic. Translating Arabic while
 * believing it is English does not fail loudly; it produces fluent, confident
 * nonsense.
 *
 * Nullable, and null keeps the old behaviour exactly (fall back to the app's
 * default locale), so every event that already exists is unaffected until
 * somebody says otherwise. New events record the locale their author was
 * writing the site in at the time, which is the best available guess and one
 * the organiser can correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->string('source_locale', 12)->nullable()->after('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('source_locale');
        });
    }
};
