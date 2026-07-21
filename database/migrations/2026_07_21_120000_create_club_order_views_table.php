<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks when an admin (typically the club owner) last opened a club's Orders
     * page, so the page can badge how many orders have arrived since — an unseen
     * COUNT, per (user, club). Mirrors user_section_views' "clear on open" pattern
     * but adds the tenant dimension that system lacks.
     */
    public function up(): void
    {
        Schema::create('club_order_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_order_views');
    }
};
