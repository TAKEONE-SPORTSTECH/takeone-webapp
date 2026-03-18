<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perk_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perk_id')->constrained('club_perks')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('collected_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('collected_for_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // One collection per perk per beneficiary
            $table->unique(['perk_id', 'collected_for_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perk_collections');
    }
};
