<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.4 — additive. The page's own size, chosen in the design room ("A5", "4 × 6 in",
 * or any width and height): {"long": mm, "short": mm}, the long side first, the way
 * up kept in `orientation`. NULL = the format's own (A4 for a certificate, A6 for a
 * badge) — exactly what every template before this was.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('certificate_templates', 'page_size')) {
            Schema::table('certificate_templates', function (Blueprint $table) {
                $table->json('page_size')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('certificate_templates', 'page_size')) {
            Schema::table('certificate_templates', fn (Blueprint $table) => $table->dropColumn('page_size'));
        }
    }
};
