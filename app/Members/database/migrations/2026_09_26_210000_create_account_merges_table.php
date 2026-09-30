<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger of account merges: which duplicate was folded into which account,
 * by whom, and EVERY row it touched — the rows re-pointed and the full copy of
 * any row removed as the same fact twice. That record is what makes a merge
 * undoable (App\Members\Support\AccountMerger::undo()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_merges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('kept_user_id')->index();
            $table->unsignedBigInteger('merged_user_id')->index();
            $table->unsignedBigInteger('merged_by')->nullable();
            $table->json('moved');
            $table->json('removed');
            $table->json('user_before');
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_merges');
    }
};
