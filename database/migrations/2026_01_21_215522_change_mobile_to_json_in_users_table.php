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
        // First make mobile nullable
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile')->nullable()->change();
        });

        // Then change to json
        Schema::table('users', function (Blueprint $table) {
            $table->json('mobile')->change();
        });

        // Convert existing mobile data to JSON format
        DB::statement("UPDATE users SET mobile = JSON_OBJECT('number', REPLACE(mobile, '+', '')) WHERE mobile IS NOT NULL AND mobile != ''");

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile')->change();
        });
    }
};
