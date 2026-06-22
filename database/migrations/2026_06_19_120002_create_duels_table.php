<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1v1 duels — a head-to-head challenge between two people. The opponent
     * may be a real platform user (opponent_id) or an external invite
     * (opponent_handle: @username, email or phone) who hasn't joined yet.
     */
    public function up(): void
    {
        Schema::create('duels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('opponent_handle')->nullable();   // external invite target
            $table->string('opponent_name')->nullable();     // display name for external

            $table->string('type')->default('athletic');     // athletic | fight
            $table->string('discipline');
            $table->string('metric')->default('Best score'); // win condition
            $table->unsignedInteger('stake_points')->default(150);
            $table->date('deadline')->nullable();
            $table->string('location')->nullable();
            $table->text('message')->nullable();

            // pending → active → completed (or declined / cancelled)
            $table->string('status')->default('pending');
            $table->string('challenger_score')->nullable();
            $table->string('opponent_score')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['challenger_id', 'status']);
            $table->index(['opponent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duels');
    }
};
