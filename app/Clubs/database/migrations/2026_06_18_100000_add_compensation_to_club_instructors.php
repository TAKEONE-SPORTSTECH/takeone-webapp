<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_instructors', function (Blueprint $table) {
            // 'volunteer' (no pay) or 'paid' (wage_amount + wage_period apply).
            $table->string('compensation_type')->default('volunteer')->after('role');
            $table->decimal('wage_amount', 10, 2)->nullable()->after('compensation_type');
            // 'monthly' | 'session' | 'hourly'
            $table->string('wage_period')->nullable()->after('wage_amount');
        });
    }

    public function down(): void
    {
        Schema::table('club_instructors', function (Blueprint $table) {
            $table->dropColumn(['compensation_type', 'wage_amount', 'wage_period']);
        });
    }
};
