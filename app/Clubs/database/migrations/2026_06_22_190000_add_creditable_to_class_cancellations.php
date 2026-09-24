<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_cancellations', function (Blueprint $table) {
            $table->boolean('creditable')->default(true)->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('class_cancellations', function (Blueprint $table) {
            $table->dropColumn('creditable');
        });
    }
};
