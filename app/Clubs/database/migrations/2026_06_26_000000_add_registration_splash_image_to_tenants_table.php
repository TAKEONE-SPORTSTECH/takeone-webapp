<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Dedicated portrait image for the self-registration wizard splash
            // screen. Separate from cover_image (the wide profile banner).
            $table->string('registration_splash_image')->nullable()->after('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('registration_splash_image');
        });
    }
};
