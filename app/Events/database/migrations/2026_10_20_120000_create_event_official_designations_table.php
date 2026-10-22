<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Designations a host adds to its officials' list (owner, 2026-10-20): a
 * federation's "Announcer" or "Results officer", made once and offered on every
 * one of its events beside the standard jobs. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_official_designations')) {
            return;
        }
        Schema::create('event_official_designations', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            // The host whose list it is — EventHost's kind ('club', 'federation') and id.
            $t->string('host_type', 40);
            $t->unsignedBigInteger('host_id');
            $t->string('label', 80);
            // Which part of the officials list it reads under (OfficialRoles::SECTIONS).
            $t->string('section', 20)->default('organising');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['host_type', 'host_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_official_designations');
    }
};
