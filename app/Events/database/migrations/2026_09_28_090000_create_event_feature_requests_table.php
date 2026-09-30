<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Paid featuring on /me/events (2026-09-28).
 *
 * The spotlight card at the top of the events list is no longer "whatever is
 * soonest": an organiser asks for it, pays the platform's flat fee (manual —
 * proof of payment, no gateway), and the super-admin approves. An event is
 * featured while it has an APPROVED row here and has not ended.
 *
 * One table for both kinds of event: a club event (club_events) or a
 * federation's (federation_events). Exactly one of the two ids is set. The
 * federation id carries no foreign key on purpose — the Federations module can
 * be removed without this table failing to migrate.
 *
 * Purely additive — one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_feature_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('club_event_id')->nullable()->constrained('club_events')->cascadeOnDelete();
            $table->unsignedBigInteger('federation_event_id')->nullable()->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            // The fee as it stood when the request was made — a later change to
            // the platform price never rewrites what somebody already paid.
            $table->decimal('amount', 10, 3)->default(0);
            $table->string('currency', 8)->default('BHD');
            $table->string('proof_path')->nullable();
            $table->string('proof_mime', 100)->nullable();
            // pending → approved | rejected; approved → revoked.
            $table->string('status', 16)->default('pending')->index();
            $table->string('reason', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['club_event_id', 'status']);
            $table->index(['federation_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_feature_requests');
    }
};
