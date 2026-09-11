<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which languages an event's poster OFFERS.
 *
 * Additive and nullable, and NULL is the whole of the backward compatibility:
 * every event that exists keeps offering all ~68 content languages, exactly as
 * it does today, until somebody ticks a shorter list. See
 * App\Translation\Contracts\LimitsOfferedLocales for why an empty list means
 * the same as null.
 *
 * ⚠️ It limits what is OFFERED, never what is stored. Nothing here can delete a
 * translation, so hiding a language keeps every hand correction an organiser
 * typed into it — which is the difference between this and removing one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->json('offered_locales')->nullable()->after('source_locale');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('offered_locales');
        });
    }
};
