<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('club_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Member associated with transaction
            $table->enum('type', ['income', 'expense', 'refund'])->default('income');
            $table->string('category')->nullable(); // e.g., "Membership Fee", "Equipment", "Utilities"
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer', 'online', 'other'])->default('cash');
            $table->text('description')->nullable();
            $table->date('transaction_date');
            $table->string('reference_number')->nullable(); // Invoice/Receipt number
            $table->foreignId('subscription_id')->nullable()->constrained('club_member_subscriptions')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('type');
            $table->index('transaction_date');
            $table->index('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_transactions');
    }
};
