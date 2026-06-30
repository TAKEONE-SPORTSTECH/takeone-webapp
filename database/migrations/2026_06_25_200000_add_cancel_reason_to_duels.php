<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duels', function (Blueprint $table) {
            $table->string('cancel_reason', 300)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('duels', function (Blueprint $table) {
            $table->dropColumn('cancel_reason');
        });
    }
};
