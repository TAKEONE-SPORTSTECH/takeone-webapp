<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop orders. A member's cart checkout creates one order per club (tenant),
 * each with its line items. No payment gateway — orders start 'pending' and a
 * club admin advances them (confirmed → fulfilled) under the manual
 * proof-of-payment workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();              // human ref, e.g. TK-AB12CD
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // buyer
            $table->string('status', 24)->default('pending');       // pending|confirmed|fulfilled|cancelled
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 8)->default('BHD');
            $table->boolean('has_dropship')->default(false);
            $table->text('note')->nullable();                        // buyer note
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('club_product_id')->nullable()->constrained('club_products')->nullOnDelete();
            $table->string('name');                                  // snapshot
            $table->string('brand')->nullable();
            $table->string('image_path')->nullable();
            $table->string('color', 16)->nullable();                 // chosen colour
            $table->string('fulfillment', 16)->default('stock');     // stock|dropship
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->timestamps();
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
