<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Capacity is optional — null means no cap on the weight class. */
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->unsignedInteger('capacity')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->unsignedInteger('capacity')->default(8)->change();
        });
    }
};
