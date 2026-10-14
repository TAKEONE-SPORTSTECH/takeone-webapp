<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PEOPLE on the budget — a referee line is "8 × 30", not one lump of 240.
 *
 *   role       referee | judge | organiser | volunteer | medical | other
 *   quantity   how many people
 *   unit_cost  what each one costs (0 for a volunteer, or their meal/T-shirt)
 *
 * When quantity is set, `amount` is quantity × unit_cost (kept in step on
 * save), so every existing sum that reads `amount` stays right. Additive and
 * nullable: a line without people is exactly what it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->string('role', 20)->nullable()->after('category');
            $table->unsignedInteger('quantity')->nullable()->after('amount');
            $table->decimal('unit_cost', 10, 3)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->dropColumn(['role', 'quantity', 'unit_cost']);
        });
    }
};
