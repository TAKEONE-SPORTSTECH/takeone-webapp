<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member posts can carry the same decorative gradient "cover" banner that club
 * posts use ({ color, icon, label }) — so a member-created "highlight" renders
 * with the animated picture-area, not as a plain text record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_posts', function (Blueprint $table) {
            $table->json('cover')->nullable()->after('poll');
        });
    }

    public function down(): void
    {
        Schema::table('user_posts', function (Blueprint $table) {
            $table->dropColumn('cover');
        });
    }
};
