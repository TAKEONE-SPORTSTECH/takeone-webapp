<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Federations, step 3: a federation's own events (a competition the
 * federation runs, entered by clubs through the invitation link), their
 * documents, and uploads staged while an event form is still open.
 *
 * Core, queried columns are columns; the rest of the designed form (schedule,
 * fees, requirements, competition types…) is `details` JSON, exactly as the
 * form groups it. Purely additive — three new tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('sport', 40);
            $table->string('status', 30)->default('Planning');   // Planning | Registration open | Live | Completed
            $table->unsignedInteger('capacity')->nullable();      // null = unlimited
            $table->string('starts_on', 16)->nullable();          // as the form holds it: YYYY-MM-DD[THH:MM]
            $table->string('ends_on', 16)->nullable();
            $table->json('details')->nullable();
            $table->string('logo')->nullable();
            $table->string('logo_shape', 10)->default('circle');
            $table->string('art_portrait')->nullable();
            $table->string('art_landscape')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['federation_id', 'sport']);
        });

        Schema::create('federation_event_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('federation_event_id')->constrained('federation_events')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('filename', 150)->nullable();   // the uploader's name for it — display only, never a path
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // A file picked in an event form before the event is saved. Attached
        // (moved) on save; anything left here is disposable.
        Schema::create('federation_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('filename', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_uploads');
        Schema::dropIfExists('federation_event_documents');
        Schema::dropIfExists('federation_events');
    }
};
