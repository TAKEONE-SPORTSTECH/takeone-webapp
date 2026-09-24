<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->string('before_proof')->nullable()->after('icon_type');
            $table->string('after_proof')->nullable()->after('before_proof');
            $table->timestamp('completed_at')->nullable()->after('after_proof');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn(['before_proof', 'after_proof', 'completed_at']);
        });
    }
};
