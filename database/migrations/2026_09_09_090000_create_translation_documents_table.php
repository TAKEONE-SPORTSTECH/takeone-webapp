<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ONE record per event holding every language it can be read in.
 *
 * Asked for on 2026-09-09: "the tournament information stored as one record as
 * json, based on what the user selects it displays that language."
 *
 * ── What it replaces, and the trade that was made ────────────────────────────
 *
 * The first cut stored one ROW per field per language — 40 fields × 60
 * languages is 2,400 rows for a single event. Correct, and unpleasant to look
 * at or reason about. One document per event is what a person picturing this
 * feature actually pictures, and it makes the common operation — "render this
 * event in Portuguese" — a single row read instead of a forty-row gather.
 *
 * The cost, stated plainly: the row carries every language, so a write must
 * re-read and merge rather than update in place, and two organisers editing two
 * languages at the same instant would otherwise clobber each other. That is
 * handled — every write goes through TranslationDocument::mutate(), which locks
 * and merges inside a transaction — but it is the reason this shape needs care
 * that rows did not.
 *
 * ── The shape ────────────────────────────────────────────────────────────────
 *
 *   {
 *     "pt": {
 *       "status":  "ready",              queued | running | ready | failed
 *       "error":   null,                 never a provider's raw body
 *       "provider":"anthropic",          who wrote it, for a later re-run
 *       "model":   "claude-opus-5",
 *       "updated_at": "2026-09-09T…",
 *       "fields": {
 *         "title": { "v": "…",           the words
 *                    "h": "<sha256>",    hash of the SOURCE it was made from
 *                    "o": "machine" }    or "human" — never overwritten
 *       }
 *     }
 *   }
 *
 * The three short keys are deliberate: they repeat once per field per language,
 * so `value`/`source_hash`/`origin` would add roughly 25 bytes × 2,400 to every
 * document for no gain to anyone reading it in a database client.
 *
 * `content_translations` and `content_translation_runs` are NOT dropped here.
 * The data is copied forward, the old tables are left in place and unread, and
 * retiring them is a separate deliberate step (CLAUDE.md → House Cleaning).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_documents', function (Blueprint $table) {
            $table->id();

            // Morph ALIAS ('club_event'), never a class name — see MorphMap.
            $table->string('translatable_type', 64);
            $table->unsignedBigInteger('translatable_id');

            // Every language, as one JSON object keyed by locale.
            $table->json('document')->nullable();

            $table->timestamps();

            // One document per record. The whole point.
            $table->unique(['translatable_type', 'translatable_id'], 'translation_documents_unique');
        });

        /*
         * Carry the existing translations forward.
         *
         * Written in the migration rather than a command because the read path
         * switches over in the same deploy: an event that is readable in four
         * languages this morning must still be readable in four languages this
         * afternoon (RULE #1). Guarded so a fresh install with no old tables —
         * or a re-run — is a no-op rather than a failure.
         */
        if (! Schema::hasTable('content_translations')) {
            return;
        }

        $documents = [];

        foreach (\Illuminate\Support\Facades\DB::table('content_translations')->orderBy('id')->cursor() as $row) {
            $key = $row->translatable_type.'|'.$row->translatable_id;

            $documents[$key][$row->locale]['fields'][$row->field] = [
                'v' => (string) $row->value,
                'h' => (string) $row->source_hash,
                'o' => $row->origin === 'human' ? 'human' : 'machine',
            ];

            // Last writer of any field stands in for the language's own
            // provenance, which is what the old per-row columns amounted to.
            $documents[$key][$row->locale]['provider'] = $row->provider;
            $documents[$key][$row->locale]['model'] = $row->model;
            $documents[$key][$row->locale]['updated_at'] = $row->updated_at;
        }

        if (Schema::hasTable('content_translation_runs')) {
            foreach (\Illuminate\Support\Facades\DB::table('content_translation_runs')->cursor() as $run) {
                $key = $run->translatable_type.'|'.$run->translatable_id;

                // A run for a language that produced no rows is still worth
                // carrying: it is how a failure stays remembered.
                $documents[$key][$run->locale]['status'] = $run->status;
                $documents[$key][$run->locale]['error'] = $run->error;
                $documents[$key][$run->locale]['fields'] ??= [];
            }
        }

        $now = now();

        foreach ($documents as $key => $document) {
            [$type, $id] = explode('|', $key, 2);

            // Anything that had rows but no run row was, by definition, done.
            foreach ($document as $locale => $entry) {
                $document[$locale]['status'] ??= ($entry['fields'] ?? []) !== [] ? 'ready' : 'failed';
            }

            \Illuminate\Support\Facades\DB::table('translation_documents')->insert([
                'translatable_type' => $type,
                'translatable_id' => (int) $id,
                'document' => json_encode($document, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_documents');
    }
};
