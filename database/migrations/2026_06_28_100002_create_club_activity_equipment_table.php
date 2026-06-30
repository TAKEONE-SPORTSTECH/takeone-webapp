<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Equipment catalog, tied to a specific activity/sport. A member registering
     * for an activity is offered only that activity's gear. Prices change over
     * time; the live price lives here, the charged price is snapshotted onto
     * member_equipment at registration.
     */
    public function up(): void
    {
        Schema::create('club_activity_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
            $table->string('name'); // e.g. "Dobok (uniform)", "Belt", "Boxing gloves"
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_required')->default(true); // pre-ticked at registration
            $table->boolean('is_active')->default(true);   // hidden from new registrations when false
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('activity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_activity_equipment');
    }
};
