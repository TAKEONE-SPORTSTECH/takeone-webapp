<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-variation fulfillment: a variation (e.g. Kwon · M) can be held in stock
     * (its own quantity) or dropshipped, carrying its own supplier + ships-in
     * estimate. Lets one product mix in-stock and dropshipped brands.
     */
    public function up(): void
    {
        Schema::table('club_product_variants', function (Blueprint $table) {
            $table->string('fulfillment', 20)->default('stock')->after('quantity'); // stock | dropship
            $table->string('supplier', 120)->nullable()->after('fulfillment');
            $table->string('ships_in', 60)->nullable()->after('supplier');
        });
    }

    public function down(): void
    {
        Schema::table('club_product_variants', function (Blueprint $table) {
            $table->dropColumn(['fulfillment', 'supplier', 'ships_in']);
        });
    }
};
