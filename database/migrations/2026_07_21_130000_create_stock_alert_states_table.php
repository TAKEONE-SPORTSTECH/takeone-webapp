<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-product low-stock alert state so an item that stays low keeps nagging the
     * owner (every 24h via the daily re-check) until restocked, and can be muted
     * indefinitely per item. One row per (tenant, product).
     */
    public function up(): void
    {
        Schema::create('stock_alert_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_notified_at')->nullable();   // when the owner was last alerted for this item
            $table->timestamp('muted_at')->nullable();           // set = never alert for this item again
            $table->timestamps();

            $table->unique(['tenant_id', 'club_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alert_states');
    }
};
