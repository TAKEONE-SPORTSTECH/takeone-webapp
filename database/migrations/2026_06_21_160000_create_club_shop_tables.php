<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The club Shop: products a club sells, plus its own product categories.
 * A product is either held in stock (quantity tracked) or dropshipped (no
 * stock — an order is placed with the supplier and shipped to the buyer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('icon', 40)->default('bi-grid-1x2');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('club_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('category')->default('gear');       // category key
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('old_price', 10, 2)->nullable();    // compare-at
            $table->string('badge', 40)->nullable();            // Sale | New | Best value | …
            $table->string('availability', 40)->default('In stock');
            $table->boolean('featured')->default(false);
            $table->string('color', 16)->default('#7c3aed');
            $table->string('icon', 40)->default('bi-bag');
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->json('colors')->nullable();                 // colour swatches
            $table->json('specs')->nullable();                  // [[label, value], …]

            // Fulfilment
            $table->string('fulfillment', 16)->default('stock');// stock | dropship
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('low_stock_alert')->nullable();
            $table->string('supplier')->nullable();
            $table->string('supplier_url')->nullable();
            $table->string('ships_in', 60)->nullable();

            $table->string('status', 16)->default('published'); // published | draft
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_products');
        Schema::dropIfExists('club_product_categories');
    }
};
