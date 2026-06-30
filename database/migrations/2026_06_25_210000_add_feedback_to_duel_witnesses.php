<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duel_witnesses', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('name');   // 1..5 stars
            $table->string('comment', 500)->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('duel_witnesses', function (Blueprint $table) {
            $table->dropColumn(['rating', 'comment']);
        });
    }
};
