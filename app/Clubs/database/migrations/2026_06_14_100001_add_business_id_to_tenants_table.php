<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links a club (tenant) to the business/chain it belongs to. Nullable so
     * standalone clubs (no chain) keep working exactly as before.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'business_id')) {
                $table->foreignId('business_id')
                    ->nullable()
                    ->after('owner_user_id')
                    ->constrained('businesses')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'business_id')) {
                $table->dropForeign(['business_id']);
                $table->dropColumn('business_id');
            }
        });
    }
};
