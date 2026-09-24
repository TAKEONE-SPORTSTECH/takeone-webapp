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
        Schema::table('club_activities', function (Blueprint $table) {
            $table->string('picture_url')->nullable()->after('description');
            $table->text('notes')->nullable()->after('picture_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_activities', function (Blueprint $table) {
            $table->dropColumn(['picture_url', 'notes']);
        });
    }
};
