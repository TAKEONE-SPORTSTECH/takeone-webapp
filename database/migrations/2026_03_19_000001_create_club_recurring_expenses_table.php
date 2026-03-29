<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->string('category')->nullable();
            $table->string('payment_method')->default('bank_transfer');
            $table->tinyInteger('day_of_month'); // 1-31
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'day_of_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_recurring_expenses');
    }
};
