<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A club's payment for its athletes at an event, with the proof it sent
 * (owner, 2026-10-04 — "Register your club" on club-hosted events). A club pays
 * ONCE for N athletes, so the proof cannot live on each registration the way an
 * individual's does (club_event_registrations.payment_proof). The organiser
 * approves or rejects it once; approving marks the athletes it covers paid.
 * Additive: a new table, nothing else touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_entry_invoices')) {
            return;
        }

        Schema::create('event_entry_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->string('payer_type', 20);                 // club (a tenant) | event_club
            $table->unsignedBigInteger('payer_id');
            $table->string('payer_name', 190)->nullable();
            $table->decimal('amount', 12, 3)->default(0);
            $table->string('currency', 8)->nullable();
            $table->string('proof_path')->nullable();         // private (local) disk
            $table->string('state', 12)->default('submitted'); // submitted | approved | rejected
            $table->string('reason', 500)->nullable();
            $table->json('registration_ids');                 // the entries this payment covers
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'state']);
            $table->index(['event_id', 'payer_type', 'payer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_entry_invoices');
    }
};
