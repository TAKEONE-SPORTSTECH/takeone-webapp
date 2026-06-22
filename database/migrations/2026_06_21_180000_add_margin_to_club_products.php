<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Margin-based pricing for shop products: the club records what a unit costs
 * (club_products.cost, already present) and a profit margin — either a fixed
 * amount (BHD) or a percentage of cost. The selling price is then derived as
 * cost + margin and locked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_products', function (Blueprint $table) {
            $table->string('margin_type', 10)->nullable()->after('cost');  // fixed | percent
            $table->decimal('margin_value', 10, 2)->nullable()->after('margin_type');
        });
    }

    public function down(): void
    {
        Schema::table('club_products', function (Blueprint $table) {
            $table->dropColumn(['margin_type', 'margin_value']);
        });
    }
};
