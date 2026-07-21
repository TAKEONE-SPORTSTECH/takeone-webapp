<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curated video clips for a directory activity — an ordered JSON list of
 * YouTube references ({id, title, title_ar?, source}). Index 0 is the featured
 * clip; the rest form the supporting rail. Additive + nullable, so existing
 * rows are untouched and activities without videos simply render no video block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_catalog', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_catalog', 'videos')) {
                $table->json('videos')->nullable()->after('variants');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_catalog', function (Blueprint $table) {
            if (Schema::hasColumn('activity_catalog', 'videos')) {
                $table->dropColumn('videos');
            }
        });
    }
};
