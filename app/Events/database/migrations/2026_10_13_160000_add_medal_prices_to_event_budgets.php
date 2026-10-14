<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medals priced by type — a gold rarely costs what a bronze does. Each is
 * nullable and falls back to the single `medal_unit_cost`, so a budget that
 * only ever set one price reads exactly as before. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_budgets', function (Blueprint $table) {
            $table->decimal('medal_gold_cost', 10, 3)->nullable()->after('medal_unit_cost');
            $table->decimal('medal_silver_cost', 10, 3)->nullable()->after('medal_gold_cost');
            $table->decimal('medal_bronze_cost', 10, 3)->nullable()->after('medal_silver_cost');
        });
    }

    public function down(): void
    {
        Schema::table('event_budgets', function (Blueprint $table) {
            $table->dropColumn(['medal_gold_cost', 'medal_silver_cost', 'medal_bronze_cost']);
        });
    }
};
