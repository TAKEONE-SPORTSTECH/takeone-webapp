<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The link that lets an athlete complete an entry somebody else committed.
 *
 * It is a CREDENTIAL, so it is stored like one: the URL carries a public uuid
 * plus a secret half, and only a hash of the secret is kept here. A row is
 * scoped to exactly one entry and one person — it can never be used to reach
 * another athlete, another event, or anything else on the platform — and it
 * expires, is single-use, and can be revoked by the coach who issued it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_entry_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('token_hash', 64);

            $table->foreignId('registration_id')->constrained('club_event_registrations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            // The person this claim will become. Cascades: an unclaimed person
            // deleted is a claim that means nothing.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');

            // Optional, and only so the platform can deliver the link for the
            // coach. Never used to sign anyone in.
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->string('claimed_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'claimed_at']);
            $table->index('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_entry_claims');
    }
};
