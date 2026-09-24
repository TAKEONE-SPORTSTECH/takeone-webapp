<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The Federations module (app/Federations): a governing body that clubs
 * affiliate WITH, rather than a chain that owns them.
 *
 * Purely additive — two new tables, nothing existing is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name', 20)->nullable();
            $table->string('country', 3)->nullable();   // ISO code, like tenants should
            $table->string('sport', 40)->nullable();
            $table->string('color', 7)->nullable();
            $table->string('logo')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        // A club asks to join; the federation accepts or refuses. One row per
        // (federation, club) — a club may belong to several federations.
        Schema::create('federation_clubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['federation_id', 'tenant_id']);
            $table->index(['federation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_clubs');
        Schema::dropIfExists('federations');
    }
};
