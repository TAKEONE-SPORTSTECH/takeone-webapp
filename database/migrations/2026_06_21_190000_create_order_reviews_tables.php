<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Once a buyer confirms they received an order, they can rate the seller (club)
 * and the products. Product ratings are aggregated onto club_products so the
 * market can show a real star rating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('payment_proof_path');
        });

        Schema::table('club_products', function (Blueprint $table) {
            $table->unsignedInteger('rating_count')->default(0)->after('specs');
            $table->unsignedInteger('rating_sum')->default(0)->after('rating_count');
        });

        // The buyer's rating of the seller (club) for this order.
        Schema::create('order_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');           // 1..5
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique('order_id');
        });

        // The buyer's rating of a product (one per product per order).
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_product_id')->constrained('club_products')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');           // 1..5
            $table->timestamps();
            $table->unique(['order_id', 'club_product_id']);
            $table->index('club_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('order_reviews');
        Schema::table('club_products', function (Blueprint $table) {
            $table->dropColumn(['rating_count', 'rating_sum']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });
    }
};
