<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who accepted an event's rules, and which rules (owner, 2026-10-04 — step 5
 * of "one front door for every event"). Until now the two acceptance ticks on
 * the rules screen were remembered only in the reader's browser.
 *
 * One row per acceptance: the statements exactly as the reader saw them, a
 * fingerprint of the rules they were shown (so a changed rule book asks
 * again), and — once they finish — the club they registered. Additive: a new
 * table, nothing else touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_entry_consents')) {
            return;
        }

        Schema::create('event_entry_consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->string('channel', 20);                    // club | individual
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('statements');                       // the texts accepted, as shown
            $table->char('rules_hash', 64);                   // sha256 of about + requirements + documents
            $table->string('locale', 12)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('accepted_at');
            $table->string('subject_key', 64)->nullable();    // the club, once known (host's own key)
            $table->string('subject_name', 190)->nullable();
            $table->timestamp('subject_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_entry_consents');
    }
};
