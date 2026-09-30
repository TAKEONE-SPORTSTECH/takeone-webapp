<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The entry desk's way back in for an athlete who forgot their password.
 *
 * Most athletes enter with a phone number and no email, and the platform
 * sends no SMS — so a forgotten password used to mean a brand-new account.
 * The organiser (or an official at the desk) issues a one-time code on the
 * athlete's card; the athlete types it with their number on the event's
 * sign-in page and chooses a new password. Six digits, fifteen minutes, one
 * use, five tries. Only the HMAC of the code is stored.
 *
 * Purely additive — one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sign_in_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash', 64);
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sign_in_codes');
    }
};
