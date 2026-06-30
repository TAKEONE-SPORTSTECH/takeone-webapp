<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Member-owned equipment. Double duty:
     *   1. Frozen accounting line — name + price snapshotted at registration.
     *   2. Ownership memory — drives smart default ticks next time (auto-skip
     *      gear the member already owns; still overridable when damaged/outgrown).
     *
     * equipment_id / activity_id are nullOnDelete so history survives catalog edits.
     */
    public function up(): void
    {
        Schema::create('member_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('club_activities')->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('club_activity_equipment')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('club_member_subscriptions')->nullOnDelete();
            $table->string('name');                 // snapshot of catalog name
            $table->decimal('price', 10, 2)->default(0); // snapshot of price charged
            // pending  = awaiting payment approval (wizard); counts as owned for default-skip
            // owned    = paid/active
            // refunded = enrollment rejected/cancelled; frees the item to be re-offered
            $table->enum('status', ['pending', 'owned', 'refunded'])->default('owned');
            $table->timestamp('acquired_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['user_id', 'activity_id']);
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_equipment');
    }
};
