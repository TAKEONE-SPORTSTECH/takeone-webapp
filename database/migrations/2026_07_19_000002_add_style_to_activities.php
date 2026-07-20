<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity styles / federations (e.g. Taekwondo → WTF/ITF, Karate → Shotokan).
 * Style-as-attribute model: one canonical directory entry per discipline that
 * carries a curated list of suggested styles; each club activity optionally
 * records the specific style it teaches.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Curated list of suggested styles on the shared directory entry.
        Schema::table('activity_catalog', function (Blueprint $table) {
            $table->json('variants')->nullable()->after('translations');
        });

        // The specific style a club teaches (free text; may be picked from the
        // directory's variants or typed). Arabic goes in the translations JSON.
        Schema::table('club_activities', function (Blueprint $table) {
            $table->string('style', 100)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('activity_catalog', function (Blueprint $table) {
            $table->dropColumn('variants');
        });
        Schema::table('club_activities', function (Blueprint $table) {
            $table->dropColumn('style');
        });
    }
};
