<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->json('images')->nullable();   // array of storage paths
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('user_post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_post_id')->constrained('user_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index('user_post_id');
        });

        Schema::create('user_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_post_id')->constrained('user_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_post_likes');
        Schema::dropIfExists('user_post_comments');
        Schema::dropIfExists('user_posts');
    }
};
