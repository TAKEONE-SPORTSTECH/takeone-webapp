<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photographs of an event — the gallery's pictures, beside its bout videos.
 *
 * A new table, nothing altered (RULE #1). The files live on the private disk
 * under events/{uuid}/photos and are only ever reached through
 * EventPhotoController, which authorises every request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('bytes')->default(0);
            $table->string('caption', 200)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_photos');
    }
};
