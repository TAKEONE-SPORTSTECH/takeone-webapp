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
        Schema::table('club_facilities', function (Blueprint $table) {
            $table->json('images')->nullable()->after('photo');
        });
    }

    public function down(): void
    {
        Schema::table('club_facilities', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
