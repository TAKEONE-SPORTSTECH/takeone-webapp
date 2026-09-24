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
        Schema::create('club_gallery_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('image_path');
            $table->string('caption')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_gallery_images');
    }
};
