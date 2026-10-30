<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pictures placed inside a note. Additive: one new table, nothing existing is
 * touched.
 *
 * An asset belongs to the CARD, not to one note, so a picture can move between
 * notes on the same card (cut and paste) without being re-uploaded. Files live
 * on the private disk under platform/devboard/cards/{card-uuid}/ and are served
 * only through the board's authorising controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('devboard_assets')) {
            return;
        }

        Schema::create('devboard_assets', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('card_id')->constrained('devboard_cards')->cascadeOnDelete();
            $t->string('path');
            $t->string('preview_path')->nullable();
            $t->unsignedInteger('width');
            $t->unsignedInteger('height');
            $t->unsignedInteger('bytes');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devboard_assets');
    }
};
