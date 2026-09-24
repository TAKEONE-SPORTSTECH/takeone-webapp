<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks who has viewed each member post (one row per viewer per post), so we
 * can show a view count and let the post's owner see the list of viewers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_post_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_post_id')->constrained('user_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_post_id', 'user_id']);   // one view per member
            $table->index('user_post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_post_views');
    }
};
