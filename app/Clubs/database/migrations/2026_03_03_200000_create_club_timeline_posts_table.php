<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_timeline_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->text('body');
            $table->string('category')->default('Announcement'); // Announcement|Highlight|Community|Update
            $table->string('image_path')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('status')->default('published'); // published|draft
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_timeline_posts');
    }
};
