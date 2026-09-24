<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail of business (chain) ownership transfers.
     */
    public function up(): void
    {
        Schema::create('business_ownership_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('clubs_reassigned')->default(false);
            $table->unsignedInteger('clubs_reassigned_count')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('business_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_ownership_logs');
    }
};
