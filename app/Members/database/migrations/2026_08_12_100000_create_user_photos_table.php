<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The pictures on a member's profile.
 *
 * A profile holds several: one of them is the avatar (mirrored into
 * `users.profile_picture`, which stays the single source for every avatar the
 * platform already renders), the rest sit behind it in the photo sheet.
 *
 * `path` is app-generated (see UserPhotoController::store) under
 * people/{user uuid}/photos/ with a random filename, per the Upload Storage
 * Structure rule — the client never names a file or a folder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                  // public key — never the id
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');                          // generated storage path (public disk)
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_photos');
    }
};
