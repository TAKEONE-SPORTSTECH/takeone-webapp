<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * "Not the same person" — a pair of accounts the duplicate scan flagged and a
 * super-admin examined and found to be two different people (two Mohammed
 * Alis; a parent and child sharing one phone). The scan leaves the pair out
 * from then on. Stored lowest id first, so a pair is one row either way round.
 *
 * Purely additive — one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_duplicate_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_low_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_high_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_low_id', 'user_high_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_duplicate_dismissals');
    }
};
