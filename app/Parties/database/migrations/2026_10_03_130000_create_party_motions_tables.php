<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ownership decisions put to the owners (PLAN.md §9a, owner 2026-10-03):
 * "the majority decides" — an owner holding MORE than 50% acts alone; anyone
 * else proposes, and the owners vote weighted by their shares. MORE than 50%
 * of the shares saying yes passes it; a 50/50 split does not. Every motion and
 * every vote is kept, dated. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('party_motions')) {
            Schema::create('party_motions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('to_type', 32);
                $table->unsignedBigInteger('to_id');
                $table->string('kind', 40);                    // set_owners
                $table->json('proposal');                      // [{type, id, share_bp}, …] — the ownership asked for
                $table->string('reason')->nullable();
                $table->unsignedBigInteger('proposed_by');
                $table->string('status', 16)->default('open'); // open|passed|rejected|withdrawn|superseded
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
                $table->index(['to_type', 'to_id', 'status'], 'party_motion_party');
            });
        }

        if (! Schema::hasTable('party_motion_votes')) {
            Schema::create('party_motion_votes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('motion_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedSmallInteger('share_bp');      // the voter's share WHEN they voted
                $table->boolean('approve');
                $table->timestamps();
                $table->unique(['motion_id', 'user_id'], 'party_vote_once');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('party_motion_votes');
        Schema::dropIfExists('party_motions');
    }
};
