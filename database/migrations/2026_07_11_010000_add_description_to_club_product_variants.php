<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional per-variation description. A variant (e.g. Adidas · Kirugi · M) may
     * carry its own blurb; when empty the storefront falls back to the product's
     * description (or the first variation that has one).
     */
    public function up(): void
    {
        Schema::table('club_product_variants', function (Blueprint $table) {
            $table->text('description')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('club_product_variants', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
