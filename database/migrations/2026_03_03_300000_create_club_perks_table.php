<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_perks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('badge');                          // free text: "-20% OFF", "+500 PTS", etc.
            $table->string('image_path')->nullable();         // card background image
            $table->string('icon')->default('bi-gift');       // Bootstrap icon fallback
            $table->string('bg_from')->default('#f59e0b');    // gradient from color
            $table->string('bg_to')->default('#f97316');      // gradient to color
            $table->string('perk_type')->default('code');     // code | qr
            $table->text('perk_value')->nullable();           // promo code or QR content
            $table->string('status')->default('active');      // active | inactive
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_perks');
    }
};
