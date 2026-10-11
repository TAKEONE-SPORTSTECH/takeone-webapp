<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.4 — additive. The organiser's own text elements ({key, text per locale, style}; where
 * each sits is in `layout` / `layout_portrait` under the same key), and how uploaded
 * artwork meets the page (fill | fit | stretch | tile | center). NULL fit = the artwork
 * as it was baked at upload, which is what every template before this had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->json('elements')->nullable()->after('layout_portrait');
            $table->string('artwork_fit', 10)->nullable()->after('elements');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn(['elements', 'artwork_fit']);
        });
    }
};
