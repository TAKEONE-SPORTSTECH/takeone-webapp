<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product variants — a uniform comes in different sizes, colours and brands,
     * each its own sellable unit with its own price and stock. When a product has
     * variants, the variant is the source of truth for price/stock; when it has
     * none, the product's own price/quantity are used (fully backward-compatible).
     *
     * The chosen variant is referenced + snapshotted onto order_items (shop) and
     * member_equipment (registration) so history survives later catalog edits.
     */
    public function up(): void
    {
        Schema::create('club_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('club_product_id')->constrained('club_products')->cascadeOnDelete();

            // Variant dimensions — any combination, all optional.
            $table->string('size', 40)->nullable();
            $table->string('color', 60)->nullable();        // colour name, e.g. "Royal Blue"
            $table->string('color_hex', 16)->nullable();    // swatch
            $table->string('brand', 80)->nullable();
            $table->string('sku', 80)->nullable();

            // Per-variant pricing + stock (overrides the product's base values).
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('old_price', 10, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->unsignedInteger('quantity')->nullable();      // null = untracked / dropship
            $table->unsignedInteger('low_stock_alert')->nullable();

            $table->string('image_path')->nullable();             // optional per-variant image
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'club_product_id']);
            $table->index(['club_product_id', 'is_active']);
        });

        // Shop orders reference + snapshot the chosen variant.
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('club_product_variant_id')->nullable()->after('club_product_id');
            $table->string('size', 40)->nullable()->after('color');
            $table->string('variant_label')->nullable()->after('size'); // "Adidas · Royal Blue · M"
            $table->index('club_product_variant_id');
        });

        // Member-owned gear references + snapshots the chosen variant too, so
        // "already owns" is keyed on the exact variant (M is not L).
        Schema::table('member_equipment', function (Blueprint $table) {
            $table->unsignedBigInteger('club_product_variant_id')->nullable()->after('club_product_id');
            $table->string('variant_label')->nullable()->after('name');
            $table->index('club_product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['club_product_variant_id']);
            $table->dropColumn(['club_product_variant_id', 'size', 'variant_label']);
        });

        Schema::table('member_equipment', function (Blueprint $table) {
            $table->dropIndex(['club_product_variant_id']);
            $table->dropColumn(['club_product_variant_id', 'variant_label']);
        });

        Schema::dropIfExists('club_product_variants');
    }
};
