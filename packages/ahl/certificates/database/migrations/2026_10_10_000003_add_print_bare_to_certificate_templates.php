<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.5 — additive. "Print on my own paper": the PRINT copy leaves out the background,
 * frame, logos and seal (the paper already has them) and honours the print offset,
 * whatever the design's background is. The download is unaffected. It replaces the
 * old 'blank' background, which still reads the same way for templates that have it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->boolean('print_bare')->nullable()->after('artwork_fit');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn('print_bare');
        });
    }
};
