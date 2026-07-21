<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peer/coach attestations for a member's self-claimed tournament achievement.
 * `weight` is a server-derived credibility score (never client-set); the
 * verification service recomputes a claim's status from the weighted vouches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievement_vouches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_event_id')->constrained('tournament_events')->onDelete('cascade');
            $table->foreignId('voucher_user_id')->constrained('users')->onDelete('cascade');
            $table->string('stance')->default('vouch');          // vouch | dispute
            $table->string('relationship')->default('teammate'); // coach | official | teammate | other
            $table->decimal('weight', 5, 2)->default(0);         // server-derived credibility
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['tournament_event_id', 'voucher_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_vouches');
    }
};
