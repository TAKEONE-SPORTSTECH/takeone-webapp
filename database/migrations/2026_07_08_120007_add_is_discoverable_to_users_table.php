<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Opt-OUT people discovery: members are findable + contactable by
            // default; they can hide themselves from search/DMs in settings.
            $table->boolean('is_discoverable')->default(true)->after('is_personal_trainer');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_discoverable');
        });
    }
};
