<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('club_social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('platform'); // e.g., "Instagram", "TikTok", "Snapchat", "WhatsApp", "Phone", "Email"
            $table->string('url'); // Full URL or contact info
            $table->string('icon')->nullable(); // Icon class or path
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_social_links');
    }
};
