<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.3 — additive. Which way up the page is (landscape | portrait), and the organiser's
 * arrangement for a portrait page, kept apart from the landscape one so turning the
 * page and turning it back loses nothing. NULL orientation = landscape, which is what
 * every template before this was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->string('orientation', 10)->nullable()->after('layout');
            $table->json('layout_portrait')->nullable()->after('orientation');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn(['orientation', 'layout_portrait']);
        });
    }
};
