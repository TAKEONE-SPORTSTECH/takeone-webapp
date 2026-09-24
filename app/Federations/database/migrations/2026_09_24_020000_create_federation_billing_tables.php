<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Federations, step 2: the TakeOne subscription a federation pays — per sport,
 * per billing account — and its invoices.
 *
 * There is no payment gateway (deferred indefinitely): an invoice is created
 * when the federation says it has sent a bank transfer, and becomes Paid only
 * when a platform administrator confirms the money arrived.
 *
 * Purely additive — two new tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->unique()->constrained('federations')->cascadeOnDelete();
            $table->string('plan', 20)->default('sport');
            $table->string('cycle', 10)->default('monthly');   // monthly | annual
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('paid_until')->nullable();
            $table->timestamps();
        });

        Schema::create('federation_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->string('number', 40)->unique();              // also the bank-transfer reference
            $table->string('description', 150);
            $table->string('cycle', 10);
            $table->decimal('amount', 12, 3);
            $table->string('currency', 3)->default('BHD');
            $table->json('lines')->nullable();                   // the per-sport lines it was priced from
            $table->string('method', 30)->default('Bank transfer');
            $table->string('status', 20)->default('Pending');    // Pending | Paid | Void
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['federation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_invoices');
        Schema::dropIfExists('federation_subscriptions');
    }
};
