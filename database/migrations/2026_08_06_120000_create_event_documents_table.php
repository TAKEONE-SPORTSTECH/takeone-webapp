<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents attached to an event — rulebooks, entry forms, schedules.
 *
 * Files live on the PRIVATE disk and are served through a controller that
 * re-checks visibility on every request; nothing here is web-reachable by path.
 * `path` is app-generated (see EventDocumentController::store) and `title` is
 * the only thing a human typed — the original filename is never used for
 * storage, per the Upload Storage Structure rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                 // public key — never the id
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->string('title');                        // display name, from its own input
            $table->string('path');                         // generated storage path (private disk)
            $table->string('mime', 100);                    // sniffed from the bytes, not the client
            $table->string('extension', 10);                // assigned server-side from the whitelist
            $table->unsignedBigInteger('size');             // bytes
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_documents');
    }
};
