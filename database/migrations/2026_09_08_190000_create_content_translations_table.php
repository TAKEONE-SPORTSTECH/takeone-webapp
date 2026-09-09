<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an organiser's own words live in every other language.
 *
 * ⚠️ Deliberately NOT the `translations` JSON column that Tenant, ClubPackage
 * and friends already carry. That column is right for two languages — the whole
 * row is read anyway, and en/ar sit beside each other in one blob. It is wrong
 * for sixty: every read of an event would drag every language of every field
 * off disk to use one of them, and two organisers editing two languages of the
 * same event would overwrite each other's blob. Rows, not a blob.
 *
 * The existing column is untouched and keeps working exactly as it does. This
 * is a second, additive store (RULE #1) that the models opting into
 * App\Translation use.
 *
 * ONE ROW = one field, of one record, in one language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();

            /*
             * Morph columns, so the same store serves an event today and a club
             * package tomorrow without a second table. `translatable_type` holds
             * the MORPH ALIAS ('club_event'), never the class name — see
             * App\Support\MorphMap, and never write X::class into this column.
             */
            $table->string('translatable_type', 64);
            $table->unsignedBigInteger('translatable_id');

            // A code from config/content_locales.php. Long enough for a
            // region-tagged one ('zh-TW'), never free text — validated on write.
            $table->string('locale', 12);

            /*
             * The field this translates, in the shape the model published it:
             * 'title', 'description', or a dotted path into a list —
             * 'requirements.2', 'divisions.0'. Dotted keys are how a list of
             * strings survives translation without a second table, and how a
             * single changed bullet can go stale on its own.
             */
            $table->string('field', 120);

            // The translated words themselves.
            $table->text('value');

            /*
             * sha256 of the SOURCE text this was translated from.
             *
             * This is what makes an edit safe: change the title and only the
             * title's hash stops matching, so only the title is re-translated —
             * the other forty fields stay exactly as they were, in sixty
             * languages, and cost nothing to keep. A stale row is never shown
             * and never deleted either; it is the previous answer, kept in case
             * the organiser undoes the edit.
             */
            $table->string('source_hash', 64);

            /*
             * WHO wrote it — the one flag the whole feature turns on.
             *
             * 'machine' may be replaced by a later run. 'human' never is: an
             * organiser who corrects a sentence has said the machine was wrong,
             * and a re-run that quietly reinstates the machine's version would
             * make the correction pointless. The agent skips human rows, and
             * they do not go stale when the source changes — they are marked
             * needing review instead, which is a person's decision, not a job's.
             */
            $table->string('origin', 16)->default('machine');

            // Which model produced a machine row — so a bad batch is findable
            // later, and so an upgrade can re-run only what an older model wrote.
            $table->string('provider', 64)->nullable();
            $table->string('model', 120)->nullable();

            $table->timestamps();

            // One answer per field per language. `updateOrCreate` leans on this.
            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field'], 'content_translations_unique');

            // The read path: every field of one record in one language, at once.
            $table->index(['translatable_type', 'translatable_id', 'locale'], 'content_translations_lookup');
        });

        /*
         * One row per (record, language) saying how the translation of it is
         * going. Kept as a table rather than a cache key because a visitor is
         * WATCHING it — the picker polls this while it says "preparing
         * Português" — and a cache that evaporates on restart would leave that
         * spinner turning forever with nothing to answer it.
         */
        Schema::create('content_translation_runs', function (Blueprint $table) {
            $table->id();

            $table->string('translatable_type', 64);
            $table->unsignedBigInteger('translatable_id');
            $table->string('locale', 12);

            // queued → running → ready | failed
            $table->string('status', 16)->default('queued');

            // How many fields the last successful run wrote, for the admin view.
            $table->unsignedSmallInteger('fields')->default(0);

            // Truncated failure reason. NEVER the API key, the prompt, or the
            // provider's raw body — see TranslateContent::fail().
            $table->string('error', 500)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale'], 'content_translation_runs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translation_runs');
        Schema::dropIfExists('content_translations');
    }
};
