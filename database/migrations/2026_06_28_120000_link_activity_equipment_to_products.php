<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Equipment is no longer a standalone item — it LINKS a shop product
     * (club_products) to an activity, plus the registration-specific "required"
     * flag. The product (name, price, image, stock) is the single source of
     * truth in the shop; the price is still snapshotted onto member_equipment
     * at registration for accounting.
     */
    public function up(): void
    {
        Schema::table('club_activity_equipment', function (Blueprint $table) {
            $table->unsignedBigInteger('club_product_id')->nullable()->after('activity_id');
            $table->index('club_product_id');
        });

        // Drop the now-redundant duplicated item fields (table is freshly added,
        // no real data to preserve).
        Schema::table('club_activity_equipment', function (Blueprint $table) {
            $table->dropColumn(['name', 'price']);
        });

        // Member-owned gear references the underlying product too, so ownership
        // ("already owns a Dobok") is keyed on the product, not the activity link.
        Schema::table('member_equipment', function (Blueprint $table) {
            $table->unsignedBigInteger('club_product_id')->nullable()->after('equipment_id');
            $table->index('club_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('club_activity_equipment', function (Blueprint $table) {
            $table->dropIndex(['club_product_id']);
            $table->dropColumn('club_product_id');
            $table->string('name')->default('');
            $table->decimal('price', 10, 2)->default(0);
        });

        Schema::table('member_equipment', function (Blueprint $table) {
            $table->dropIndex(['club_product_id']);
            $table->dropColumn('club_product_id');
        });
    }
};
