<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Club timeline posts can carry a decorative gradient "cover" banner
 * ({ color, icon, label }) shown in the feed when there's no uploaded image —
 * the animated picture-area the personal feed already renders for club cards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_timeline_posts', function (Blueprint $table) {
            $table->json('cover')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('club_timeline_posts', function (Blueprint $table) {
            $table->dropColumn('cover');
        });
    }
};
