<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notes and cards are two different things (owner, 27 Oct 2026): a NOTE is in
 * the notebook, a CARD is on the action-plan board, and a note can be turned
 * into a card. Until now TakeNote kept every note as a card underneath; this
 * says which each row is. Plus a card's cover and its attachments (files and
 * links). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('devboard_cards', 'kind')) {
            Schema::table('devboard_cards', function (Blueprint $t) {
                $t->string('kind', 8)->default('card')->after('uuid');
                $t->uuid('cover_uuid')->nullable()->after('source_url');
                $t->index(['kind', 'lane_id', 'position']);
            });

            // What TakeNote wrote and nobody has planned yet is a note: a card
            // whose whole history is being written. Anything moved, planned or
            // commented on was already being worked as a card, and stays one.
            $planned = DB::table('devboard_activity')
                ->whereNotIn('action', ['created', 'note_added', 'note_edited'])
                ->distinct()->pluck('card_id');
            DB::table('devboard_cards')
                ->whereNotIn('id', $planned)
                ->whereNotIn('id', DB::table('devboard_comments')->distinct()->pluck('card_id'))
                ->whereExists(fn ($q) => $q->from('devboard_items')->whereColumn('devboard_items.card_id', 'devboard_cards.id')->where('type', 'note'))
                ->update(['kind' => 'note']);
        }

        if (! Schema::hasTable('devboard_attachments')) {
            Schema::create('devboard_attachments', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('card_id')->constrained('devboard_cards')->cascadeOnDelete();
                $t->string('kind', 8); // file | link
                $t->string('name', 200);
                $t->string('url', 1000)->nullable(); // a link's address (http/https only)
                $t->string('path', 300)->nullable(); // a file, private disk, server-made name
                $t->string('ext', 10)->nullable();
                $t->string('mime', 120)->nullable();
                $t->unsignedBigInteger('size')->nullable();
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->index(['card_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('devboard_attachments');
        if (Schema::hasColumn('devboard_cards', 'kind')) {
            Schema::table('devboard_cards', function (Blueprint $t) {
                $t->dropIndex(['kind', 'lane_id', 'position']);
                $t->dropColumn(['kind', 'cover_uuid']);
            });
        }
    }
};
