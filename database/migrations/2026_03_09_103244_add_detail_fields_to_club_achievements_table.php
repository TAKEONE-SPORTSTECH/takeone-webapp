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
        Schema::table('club_achievements', function (Blueprint $table) {
            $table->string('short_title')->nullable()->after('title');
            $table->string('type_icon')->nullable()->after('short_title'); // emoji e.g. 🏆
            $table->string('date_label')->nullable()->after('achievement_date'); // free text e.g. "Nov 2023"
            $table->unsignedSmallInteger('medals_gold')->default(0)->after('date_label');
            $table->unsignedSmallInteger('medals_silver')->default(0)->after('medals_gold');
            $table->unsignedSmallInteger('medals_bronze')->default(0)->after('medals_silver');
            $table->unsignedSmallInteger('bouts_count')->default(0)->after('medals_bronze');
            $table->unsignedSmallInteger('wins_count')->default(0)->after('bouts_count');
            $table->string('category')->nullable()->after('wins_count');
            $table->json('chips')->nullable()->after('category');
            $table->json('athletes')->nullable()->after('chips');
        });
    }

    public function down(): void
    {
        Schema::table('club_achievements', function (Blueprint $table) {
            $table->dropColumn([
                'short_title', 'type_icon', 'date_label',
                'medals_gold', 'medals_silver', 'medals_bronze',
                'bouts_count', 'wins_count', 'category', 'chips', 'athletes',
            ]);
        });
    }
};
