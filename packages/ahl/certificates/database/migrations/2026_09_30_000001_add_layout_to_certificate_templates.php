<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.2 — additive. Where each element of the certificate sits, how big it is and what
 * colour it prints in, as the organiser arranged it in the editor (Support\Layout).
 * NULL = the default arrangement, which is what every template before this had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->json('layout')->nullable()->after('print_offset_y');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
