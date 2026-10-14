<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EVENT BUDGET — what an organiser PLANS to spend and charge, beside what the
 * event actually took and spent (event_expenses + the entries' fee lines).
 *
 *   event_budgets            one plan per event: the fee and turnout it assumes,
 *                            the profit it aims for, and how medals are costed
 *   event_budget_items       planned costs — a fixed sum, or a unit cost that
 *                            grows with every entrant
 *   event_budget_scenarios   saved what-ifs (fee × turnout × medal rule ×
 *                            sponsor); one may be approved, which is the only
 *                            step that touches the event's real fee
 *
 * Additive only: three new tables, nothing existing is altered. Behind
 * EVENT_BUDGET (off by default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_budgets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->unique()->constrained('club_events')->cascadeOnDelete();
            $table->decimal('planned_fee', 10, 3)->nullable();      // null = the event's own list price
            $table->unsignedInteger('expected_entrants')->nullable(); // null = the paid entries so far
            $table->decimal('target_profit', 10, 3)->default(0);
            $table->decimal('reserve_percent', 5, 2)->default(10);
            $table->decimal('round_to', 6, 3)->default(0.5);
            $table->decimal('medal_unit_cost', 10, 3)->default(0);
            $table->string('medals_mode', 12)->default('entries');   // entries | full
            $table->decimal('sponsor_amount', 10, 3)->default(0);
            $table->unsignedBigInteger('approved_scenario_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('copied_from_event_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('event_budget_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('budget_id')->constrained('event_budgets')->cascadeOnDelete();
            $table->string('category', 20)->default('other');   // people | awards | venue | running | other
            $table->string('label', 120);
            $table->decimal('amount', 10, 3)->default(0);       // the total, or the cost per entrant
            $table->string('basis', 16)->default('fixed');      // fixed | per_entrant
            $table->string('status', 12)->default('planned');   // planned | quoted | paid
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['budget_id', 'sort']);
        });

        Schema::create('event_budget_scenarios', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('budget_id')->constrained('event_budgets')->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('fee', 10, 3)->default(0);
            $table->unsignedInteger('entrants')->default(0);
            $table->string('medals_mode', 12)->default('entries');
            $table->decimal('sponsor_amount', 10, 3)->default(0);
            $table->decimal('net', 12, 3)->default(0);   // the result when it was saved, for the list
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_budget_scenarios');
        Schema::dropIfExists('event_budget_items');
        Schema::dropIfExists('event_budgets');
    }
};
