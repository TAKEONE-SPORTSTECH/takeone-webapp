<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A make-up session a member is owed because a class they were enrolled in was
 * cancelled. `credit_days` records how far the member's subscription end_date was
 * pushed, so a restore can reverse it exactly. status: open | used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_makeup_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_activity_id')->constrained('club_package_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('club_member_subscriptions')->nullOnDelete();
            $table->date('source_date');                 // the cancelled occurrence
            $table->unsignedSmallInteger('credit_days')->default(0);
            $table->string('status', 12)->default('open'); // open | used
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['package_activity_id', 'user_id', 'source_date'], 'class_makeup_unique');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('class_makeup_credits'); }
};
