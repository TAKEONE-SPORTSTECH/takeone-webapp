<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A named connection between two TakeNote notes (Trilium's relations): "Plan
 * B → depends on → Budget". Drawn on the note map and listed under each note.
 * Kept apart from the note's text, so connecting two notes never rewrites
 * either of them. One connection per pair; removing a note removes its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devboard_relations', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('notebook_id')->nullable()->constrained('devboard_notebooks')->cascadeOnDelete();
            $t->foreignId('from_card_id')->constrained('devboard_cards')->cascadeOnDelete();
            $t->foreignId('to_card_id')->constrained('devboard_cards')->cascadeOnDelete();
            $t->string('label', 40)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['from_card_id', 'to_card_id']);
            $t->index('to_card_id');
            $t->index('notebook_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devboard_relations');
    }
};
