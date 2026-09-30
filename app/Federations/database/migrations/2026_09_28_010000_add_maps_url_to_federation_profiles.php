<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The HQ's Google Maps link, as the organiser pasted it. The pin itself stays
 * in `lat`/`lng` (the map follows the link when it is pasted); the link is
 * kept so visitors can open the place exactly as the federation chose it.
 * Additive and nullable — no existing row changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('federation_profiles', function (Blueprint $table) {
            $table->string('maps_url', 500)->nullable()->after('lng');
        });
    }

    public function down(): void
    {
        Schema::table('federation_profiles', function (Blueprint $table) {
            $table->dropColumn('maps_url');
        });
    }
};
