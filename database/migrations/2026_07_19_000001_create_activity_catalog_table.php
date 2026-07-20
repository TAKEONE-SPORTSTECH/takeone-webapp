<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global activity directory — a canonical, platform-wide catalog of activities
 * (Taekwondo, Boxing, Yoga, Swimming, …) that any club can reuse instead of
 * re-typing the same activity + description for every club. A club may still
 * create its own exclusive activity; when it does, the newcomer is contributed
 * back to this directory so it becomes reusable.
 *
 * This table is intentionally NOT tenant-scoped (no tenant_id / TenantScope):
 * it is shared by every club. `source_tenant_id` only records provenance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_catalog', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                 // non-predictable public id
            $table->string('name');                          // default/fallback (English)
            $table->string('slug')->unique();                // canonical key, dedupes across clubs
            $table->text('description')->nullable();         // rich HTML (sanitized)
            $table->json('translations')->nullable();        // { name:{ar:…}, description:{ar:…} }
            $table->string('picture_url')->nullable();       // public-disk path
            $table->string('icon')->nullable();              // optional bootstrap-icon name
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedBigInteger('source_tenant_id')->nullable(); // who first contributed it
            $table->timestamps();

            $table->index('is_active');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_catalog');
    }
};
