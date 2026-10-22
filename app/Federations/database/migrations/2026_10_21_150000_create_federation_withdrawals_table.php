<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who took athletes off a federation event, when, and why.
 *
 * One row per withdrawal ACTION (a "Withdraw selected" of 98 is one row), and
 * every athlete row it touched points back at it through `withdrawal_id`. Added
 * 2026-10-21 after a single unconfirmed click withdrew 98 entrants with no
 * record of who did it. Additive only: nothing existing is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('federation_event_id')->nullable()->constrained('federation_events')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 20);            // selected · club
            $table->text('reason')->nullable();     // required for "selected"; a club removal carries none yet
            $table->unsignedInteger('count')->default(0);
            $table->json('athletes')->nullable();   // [{id, name, takeone_id}] — names as they were at the time
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['federation_event_id', 'created_at']);
        });

        Schema::table('federation_entry_athletes', function (Blueprint $table) {
            $table->foreignId('withdrawal_id')->nullable()->after('withdrawn_at')->constrained('federation_withdrawals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('federation_entry_athletes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('withdrawal_id');
        });
        Schema::dropIfExists('federation_withdrawals');
    }
};
