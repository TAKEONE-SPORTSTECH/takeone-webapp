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
        Schema::table('club_transactions', function (Blueprint $table) {
            $table->foreignId('instructor_id')->nullable()->after('subscription_id')
                ->constrained('club_instructors')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instructor_id');
        });
    }
};
