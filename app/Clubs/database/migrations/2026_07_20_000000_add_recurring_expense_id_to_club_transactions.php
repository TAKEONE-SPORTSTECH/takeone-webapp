<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a posted expense back to the recurring rule (staff wage, rent, …) that
 * generated it, so editing the rule mid-month can correct exactly that row and
 * nothing else in the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('recurring_expense_id')->nullable()->after('instructor_id');
            $table->index(['recurring_expense_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::table('club_transactions', function (Blueprint $table) {
            $table->dropIndex(['recurring_expense_id', 'transaction_date']);
            $table->dropColumn('recurring_expense_id');
        });
    }
};
